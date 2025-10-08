<?php
/**
 * Plugin Name: HTML To Blocks Fetcher
 * Description: Fetch remote HTML fragments (with inline computed styles) and integrate into WordPress.
 * Version: 0.1.0
 * Author: You
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HTML2BLOCKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'HTML2BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'HTML2BLOCKS_NODE_PATH', HTML2BLOCKS_PATH . 'node/getRemoteHTML.js' );

require_once __DIR__ . '/includes/class-html-to-blocks-runner.php';
require_once __DIR__ . '/includes/class-html-to-blocks-rest.php';
require_once __DIR__ . '/includes/class-html-to-blocks-admin-page.php';

add_action(
	'plugins_loaded',
	function () {
		( new HTML2Blocks_REST() )->register();
		( new HTML2Blocks_Admin() )->hooks();
	}
);
