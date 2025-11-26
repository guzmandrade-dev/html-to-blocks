<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML_To_Blocks_Admin {

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting(
			'html2blocks',
			'html2blocks_service_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => 'http://host.docker.internal:3001/fetch',
			)
		);

		add_settings_section( 'html2blocks_main', 'Service Settings', function () {}, 'html2blocks' );

		add_settings_field(
			'html2blocks_service_url',
			'Service URL',
			function () {
				$val = get_option( 'html2blocks_service_url', 'http://host.docker.internal:3001/fetch' );
				echo '<input type="url" class="regular-text" name="html2blocks_service_url" value="' . esc_url( $val ) . '" placeholder="http://host.docker.internal:3001/fetch" />';
			},
			'html2blocks',
			'html2blocks_main'
		);
	}

	public function add_page() {
		add_management_page(
			'HTML To Blocks',
			'HTML To Blocks',
			'edit_posts',
			'html2blocks',
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( 'tools_page_html2blocks' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'html2blocks-admin', HTML2BLOCKS_URL . 'assets/admin.js', array( 'wp-api-fetch' ), '0.2.0', true );
		wp_localize_script(
			'html2blocks-admin',
			'HTML2BLOCKS_DATA',
			array(
				'rest'  => esc_url_raw( rest_url( 'html2blocks/v1/fetch' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_enqueue_style( 'html2blocks-admin', HTML2BLOCKS_URL . 'assets/admin.css', array(), '0.2.0' );
	}

	public function render() {
		?>
		<div class="wrap">
			<h1>HTML To Blocks Fetcher</h1>

			<form method="post" action="options.php" style="margin-bottom:20px;">
				<?php
					settings_fields( 'html2blocks' );
					do_settings_sections( 'html2blocks' );
					submit_button( 'Save Settings' );
				?>
			</form>

			<form id="html2blocks-form">
				<table class="form-table">
					<tr>
						<th><label for="h2b-url">URL</label></th>
						<td><input type="url" id="h2b-url" required class="regular-text" placeholder="https://example.com/page" /></td>
					</tr>
					<tr>
						<th><label for="h2b-language">Language (optional)</label></th>
						<td><input type="text" id="h2b-language" placeholder="es" /></td>
					</tr>
					<tr>
						<th><label for="h2b-selector">Selector</label></th>
						<td><input type="text" id="h2b-selector" value="body" /></td>
					</tr>
				</table>
				<p><button class="button button-primary" type="submit">Fetch</button></p>
			</form>

			<div id="html2blocks-result" style="margin-top:20px;">
				<h2>Result</h2>

				<h3>HTML</h3>
				<div id="html2blocks-fragment" style="border:1px solid #ccd0d4;padding:12px;background:#fff; max-height:300px; overflow:auto;"></div>
				<p><button id="html2blocks-copy-html" class="button">Copy HTML</button></p>

				<h3>Blocks</h3>
				<textarea id="html2blocks-blocks" readonly style="width:100%;height:200px;font-family:monospace;"></textarea>
				<p><button id="html2blocks-copy-blocks" class="button">Copy Blocks</button></p>
			</div>
		</div>
		<?php
	}
}
