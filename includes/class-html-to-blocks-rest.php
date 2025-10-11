<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Alley\WP\Block_Converter\Block_Converter; // from composer

class HTML2Blocks_REST {

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
						),
					)
				);
			}
		);
	}

	public function handle_fetch( WP_REST_Request $req ) {
		$url      = $req->get_param( 'url' );
		$language = $req->get_param( 'language' );
		$selector = $req->get_param( 'selector' ) ?: 'body';

		$runner = new HTML2Blocks_Runner();
		$result = $runner->fetch( $url, $language, $selector );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$html = (string) ( $result['html'] ?? '' );

		// Convert HTML to blocks markup using Block_Converter
		$blocks_markup = '';
		if ( class_exists( Block_Converter::class ) ) {
			try {
				$converter = new Block_Converter( $html );
				// Some versions provide convert() returning serialized blocks as string.
				// If it returns an array, adjust to implode/serialize accordingly.
				$blocks_markup = $converter->convert();
			} catch ( \Throwable $e ) {
				$blocks_markup = '';
			}
		}

		return new WP_REST_Response(
			array(
				'html'      => $html,
				'blocks'    => $blocks_markup,
				'sourceUrl' => $result['sourceUrl'] ?? $url,
				'selector'  => $result['selector'] ?? $selector,
				'language'  => $result['language'] ?? $language,
			),
			200
		);
	}
}
