<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML2Blocks_Runner {

	public function fetch( $url, $language = null, $selector = 'body' ) {
		$url      = esc_url_raw( $url );
		$language = $language ? preg_replace( '/[^a-zA-Z0-9_-]/', '', $language ) : '';
		$selector = $selector ? sanitize_text_field( $selector ) : 'body';

		if ( empty( $url ) ) {
			return new WP_Error( 'html2blocks_invalid_url', 'Invalid URL', array( 'status' => 400 ) );
		}

		if ( ! file_exists( HTML2BLOCKS_NODE_PATH ) ) {
			return new WP_Error( 'html2blocks_missing_node', 'Node script not found', array( 'status' => 500 ) );
		}

		$node = $this->detect_node_binary();
		if ( ! $node ) {
			return new WP_Error( 'html2blocks_node_not_found', 'Node.js not available on server', array( 'status' => 500 ) );
		}

		$cmd = escapeshellcmd( $node ) . ' ' . escapeshellarg( HTML2BLOCKS_NODE_PATH )
			. ' --url=' . escapeshellarg( $url )
			. ( $language ? ' --language=' . escapeshellarg( $language ) : '' )
			. ' --selector=' . escapeshellarg( $selector );

		$descriptor_spec = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process        = proc_open( $cmd, $descriptor_spec, $pipes, HTML2BLOCKS_PATH );
		if ( ! is_resource( $process ) ) {
			return new WP_Error( 'html2blocks_exec_fail', 'Failed to start headless browser', array( 'status' => 500 ) );
		}

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );

		if ( $exit !== 0 ) {
			return new WP_Error( 'html2blocks_process_error', 'Scrape failed: ' . trim( $stderr ), array( 'status' => 500 ) );
		}

		return array(
			'html'      => $stdout,
			'sourceUrl' => $url,
			'selector'  => $selector,
			'language'  => $language,
		);
	}

	private function detect_node_binary() {
		// Basic detection; optionally allow filter override
		$candidates = array(
			'/usr/bin/node',
			'/usr/local/bin/node',
			'C:\\Program Files\\nodejs\\node.exe',
			'C:\\nodejs\\node.exe',
			'node',
		);
		foreach ( $candidates as $cand ) {
			$which = $cand === 'node' ? trim( shell_exec( 'which node 2>/dev/null' ) ) : $cand;
			if ( $which && is_executable( $which ) ) {
				return $which;
			}
		}
		return apply_filters( 'html2blocks_node_binary', null );
	}
}
