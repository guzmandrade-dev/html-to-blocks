<?php
/**
 * Plugin Name: HTML To Blocks Fetcher
 * Description: Fetch remote HTML fragments (with inline styles) and convert to blocks.
 * Version: 0.2.0
 * Author: You
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
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
		( new HTML_To_Blocks_REST() )->register();
		( new HTML2Blocks_Admin() )->hooks();
	}
);
