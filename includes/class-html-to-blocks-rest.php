<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		return new WP_REST_Response( $result, 200 );
	}
}
