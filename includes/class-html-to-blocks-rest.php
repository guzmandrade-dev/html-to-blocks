<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML_To_Blocks_REST {

	public function register() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'html2blocks/v1',
					'/fetch',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'handle_fetch' ),
						'permission_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
						'args'                => array(
							'url'      => array(
								'required' => true,
								'type'     => 'string',
							),
							'language' => array(
								'required' => false,
								'type'     => 'string',
							),
							'selector' => array(
								'required' => false,
								'type'     => 'string',
							),
							'use_ai'   => array(
								'required' => false,
								'type'     => 'boolean',
							),
						),
					)
				);

				register_rest_route(
					'html2blocks/v1',
					'/ai-batch/start',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle_ai_batch_start' ),
						'permission_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
						'args'                => array(
							'url'      => array(
								'required' => true,
								'type'     => 'string',
							),
							'language' => array(
								'required' => false,
								'type'     => 'string',
							),
							'selector' => array(
								'required' => false,
								'type'     => 'string',
							),
						),
					)
				);

				register_rest_route(
					'html2blocks/v1',
					'/ai-batch/status',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'handle_ai_batch_status' ),
						'permission_callback' => function () {
							return current_user_can( 'edit_posts' );
						},
						'args'                => array(
							'batchId' => array(
								'required' => true,
								'type'     => 'string',
							),
						),
					)
				);
			}
		);
	}

	public function handle_fetch( WP_REST_Request $req ) {
		$url      = $req->get_param( 'url' );
		$language = $req->get_param( 'language' );
		$selector = $req->get_param( 'selector' ) ?? 'body';
		$use_ai   = in_array( strtolower( (string) $req->get_param( 'use_ai' ) ), array( '1', 'true', 'yes', 'on' ), true );

		$runner = new HTML_To_Blocks_Runner();
		$result = $runner->fetch( $url, $language, $selector );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$html = (string) ( $result['html'] ?? '' );

		$conversion_result = $use_ai
			? $this->convert_with_ai_client( $html, $result, $url, $selector, $language )
			: $this->convert_with_block_converter( $html );

		$blocks_markup = '';
		$blocks_error  = '';

		if ( is_wp_error( $conversion_result ) ) {
			$blocks_error = $conversion_result->get_error_message();
		} else {
			$blocks_markup = (string) $conversion_result;
		}

		return new WP_REST_Response(
			array(
				'html'             => $html,
				'blocks'           => $blocks_markup,
				'blocksError'      => $blocks_error,
				'conversionMethod' => $use_ai ? 'ai' : 'converter',
				'sourceUrl'        => $result['sourceUrl'] ?? $url,
				'selector'         => $result['selector'] ?? $selector,
				'language'         => $result['language'] ?? $language,
			),
			200
		);
	}

	private function convert_with_block_converter( string $html ) {
		if ( ! class_exists( HTML_To_Blocks_Converter::class ) ) {
			return new WP_Error(
				'html2blocks_converter_missing',
				'The local block converter class is not available.',
				array( 'status' => 500 )
			);
		}

		try {
			$converter = new HTML_To_Blocks_Converter( $html );
			return $converter->convert();
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			if ( '' === $message ) {
				$message = 'The local block converter failed.';
			}

			return new WP_Error(
				'html2blocks_converter_failed',
				$message,
				array( 'status' => 500 )
			);
		}
	}

	private function convert_with_ai_client( string $html, array $result, string $url, string $selector, $language ) {
		$converter = new HTML_To_Blocks_AI_Converter();

		return $converter->convert(
			$html,
			array(
				'sourceUrl' => $result['sourceUrl'] ?? $url,
				'selector'  => $result['selector'] ?? $selector,
				'language'  => $result['language'] ?? $language,
			)
		);
	}

	public function handle_ai_batch_start( WP_REST_Request $req ) {
		$url      = (string) $req->get_param( 'url' );
		$language = $req->get_param( 'language' );
		$selector = $req->get_param( 'selector' ) ?? 'body';

		$runner = new HTML_To_Blocks_Runner();
		$result = $runner->fetch( $url, $language, $selector );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$html    = (string) ( $result['html'] ?? '' );
		$context = array(
			'sourceUrl' => $result['sourceUrl'] ?? $url,
			'selector'  => $result['selector'] ?? $selector,
			'language'  => $result['language'] ?? $language,
		);

		$batch_service = new HTML_To_Blocks_AI_Batch_Service();
		$batch         = $batch_service->create_batch( $html, $context );

		if ( is_wp_error( $batch ) ) {
			return $batch;
		}

		return new WP_REST_Response(
			array(
				'batchId'         => $batch['batchId'],
				'status'          => $batch['status'],
				'totalChunks'     => $batch['totalChunks'],
				'completedChunks' => $batch['completedChunks'],
				'html'            => $html,
				'sourceUrl'       => $context['sourceUrl'],
				'selector'        => $context['selector'],
				'language'        => $context['language'],
			),
			200
		);
	}

	public function handle_ai_batch_status( WP_REST_Request $req ) {
		$batch_id = trim( (string) $req->get_param( 'batchId' ) );

		if ( '' === $batch_id ) {
			return new WP_Error(
				'html2blocks_ai_batch_missing_id',
				'The batchId parameter is required.',
				array( 'status' => 400 )
			);
		}

		$batch_service = new HTML_To_Blocks_AI_Batch_Service();
		$status        = $batch_service->process_next( $batch_id );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		return new WP_REST_Response( $status, 200 );
	}
}
