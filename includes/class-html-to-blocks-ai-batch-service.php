<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML_To_Blocks_AI_Batch_Service {
	private const TRANSIENT_PREFIX = 'html2blocks_ai_batch_';

	public function create_batch( string $html, array $context = array(), array $options = array() ) {
		$html = trim( $html );
		if ( '' === $html ) {
			return new WP_Error(
				'html2blocks_ai_empty_html',
				'No HTML was available to convert.',
				array( 'status' => 400 )
			);
		}

		$chunker = new HTML_To_Blocks_Chunker();
		$chunks  = $chunker->chunk_html( $html, $options );

		if ( empty( $chunks ) ) {
			return new WP_Error(
				'html2blocks_ai_chunking_failed',
				'Unable to prepare HTML chunks for AI conversion.',
				array( 'status' => 500 )
			);
		}

		$batch_id      = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'html2blocks_', true );
		$chunk_timeout = isset( $options['chunkTimeout'] ) ? (int) $options['chunkTimeout'] : 0;
		if ( $chunk_timeout <= 0 ) {
			$chunk_timeout = (int) apply_filters( 'html2blocks_ai_chunk_timeout', 60, $html, $context );
		}

		$state = array(
			'batchId'         => $batch_id,
			'status'          => 'queued',
			'totalChunks'     => count( $chunks ),
			'completedChunks' => 0,
			'nextChunk'       => 0,
			'chunks'          => array_values( $chunks ),
			'chunkResults'    => array(),
			'blocks'          => '',
			'error'           => '',
			'context'         => $context,
			'chunkTimeout'    => $chunk_timeout,
			'createdAt'       => time(),
			'updatedAt'       => time(),
		);

		$this->save_state( $state );

		do_action( 'html2blocks_ai_batch_created', $state );

		return $this->summarize_state( $state );
	}

	public function process_next( string $batch_id ) {
		$state = $this->get_state( $batch_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		if ( in_array( $state['status'], array( 'completed', 'failed' ), true ) ) {
			return $this->summarize_state( $state );
		}

		$state['status']    = 'running';
		$state['updatedAt'] = time();

		$next_chunk = (int) $state['nextChunk'];
		$total      = (int) $state['totalChunks'];

		if ( $next_chunk >= $total ) {
			$state = $this->complete_state( $state );
			$this->save_state( $state );

			return $this->summarize_state( $state );
		}

		$converter               = new HTML_To_Blocks_AI_Converter();
		$context                 = (array) $state['context'];
		$context['chunkIndex']   = $next_chunk + 1;
		$context['chunkTotal']   = $total;
		$context['chunkTimeout'] = (int) $state['chunkTimeout'];

		$chunk_html = (string) ( $state['chunks'][ $next_chunk ] ?? '' );
		$result     = $converter->convert_with_timeout( $chunk_html, $context, (int) $state['chunkTimeout'] );

		if ( is_wp_error( $result ) ) {
			$state['status']    = 'failed';
			$state['error']     = sprintf( 'Chunk %1$d failed: %2$s', $next_chunk + 1, $result->get_error_message() );
			$state['updatedAt'] = time();
			$this->save_state( $state );

			do_action( 'html2blocks_ai_batch_failed', $state, $result );

			return $this->summarize_state( $state );
		}

		$state['chunkResults'][ $next_chunk ] = (string) $result;
		$state['nextChunk']                   = $next_chunk + 1;
		$state['completedChunks']             = (int) $state['nextChunk'];
		$state['updatedAt']                   = time();

		if ( (int) $state['nextChunk'] >= $total ) {
			$state = $this->complete_state( $state );
			do_action( 'html2blocks_ai_batch_completed', $state );
		} else {
			do_action( 'html2blocks_ai_batch_progress', $state );
		}

		$this->save_state( $state );

		return $this->summarize_state( $state );
	}

	public function get_batch( string $batch_id ) {
		$state = $this->get_state( $batch_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return $this->summarize_state( $state );
	}

	private function complete_state( array $state ): array {
		$results = (array) $state['chunkResults'];
		ksort( $results );

		$merged                   = trim( implode( "\n\n", $results ) );
		$state['blocks']          = $merged;
		$state['status']          = '' === $merged ? 'failed' : 'completed';
		$state['error']           = '' === $merged ? 'Batch processing completed, but no block markup was produced.' : '';
		$state['completedChunks'] = (int) $state['totalChunks'];
		$state['updatedAt']       = time();

		return $state;
	}

	private function get_state( string $batch_id ) {
		$key   = self::TRANSIENT_PREFIX . $batch_id;
		$state = get_transient( $key );

		if ( ! is_array( $state ) || empty( $state['batchId'] ) ) {
			return new WP_Error(
				'html2blocks_ai_batch_not_found',
				'The requested AI conversion batch was not found or has expired.',
				array( 'status' => 404 )
			);
		}

		return $state;
	}

	private function save_state( array $state ): void {
		$batch_id = (string) ( $state['batchId'] ?? '' );
		if ( '' === $batch_id ) {
			return;
		}

		$ttl = (int) apply_filters( 'html2blocks_ai_batch_ttl', HOUR_IN_SECONDS, $state );
		if ( $ttl <= 0 ) {
			$ttl = HOUR_IN_SECONDS;
		}

		set_transient( self::TRANSIENT_PREFIX . $batch_id, $state, $ttl );
	}

	private function summarize_state( array $state ): array {
		$summary = array(
			'batchId'         => (string) ( $state['batchId'] ?? '' ),
			'status'          => (string) ( $state['status'] ?? 'queued' ),
			'totalChunks'     => (int) ( $state['totalChunks'] ?? 0 ),
			'completedChunks' => (int) ( $state['completedChunks'] ?? 0 ),
			'error'           => (string) ( $state['error'] ?? '' ),
			'blocks'          => '',
			'createdAt'       => (int) ( $state['createdAt'] ?? time() ),
			'updatedAt'       => (int) ( $state['updatedAt'] ?? time() ),
		);

		if ( 'completed' === $summary['status'] ) {
			$summary['blocks'] = (string) ( $state['blocks'] ?? '' );
		}

		return $summary;
	}
}
