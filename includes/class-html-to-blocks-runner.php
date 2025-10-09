<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML2Blocks_Runner {
	public function fetch( $url, $language = null, $selector = 'body' ) {
		$endpoint = get_option( 'html2blocks_service_url', 'http://host.docker.internal:3001/fetch' );
		if ( empty( $endpoint ) ) {
			return new WP_Error( 'html2blocks_no_service', 'Service URL not configured', array( 'status' => 500 ) );
		}

		$body = array(
			'url'      => esc_url_raw( $url ),
			'selector' => $selector ?: 'body',
		);
		if ( $language ) {
			$body['language'] = preg_replace( '/[^a-zA-Z0-9_-]/', '', $language );
		}

		$resp = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'html2blocks_http', $resp->get_error_message(), array( 'status' => 500 ) );
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$html = wp_remote_retrieve_body( $resp );

		if ( $code !== 200 ) {
			return new WP_Error( 'html2blocks_bad_status', 'Service error: ' . $html, array( 'status' => 500 ) );
		}

		return array(
			'html'      => $html,
			'sourceUrl' => $url,
			'selector'  => $body['selector'],
			'language'  => $language,
		);
	}
}
