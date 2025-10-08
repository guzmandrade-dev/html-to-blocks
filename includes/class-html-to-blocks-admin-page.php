<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML2Blocks_Admin {

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
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
		if ( $hook !== 'tools_page_html2blocks' ) {
			return;
		}
		wp_enqueue_script( 'html2blocks-admin', HTML2BLOCKS_URL . 'assets/admin.js', array( 'wp-api-fetch' ), '0.1.0', true );
		wp_localize_script(
			'html2blocks-admin',
			'HTML2BLOCKS_DATA',
			array(
				'rest'  => esc_url_raw( rest_url( 'html2blocks/v1/fetch' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_enqueue_style( 'html2blocks-admin', HTML2BLOCKS_URL . 'assets/admin.css', array(), '0.1.0' );
	}

	public function render() {
		?>
		<div class="wrap">
			<h1>HTML To Blocks Fetcher</h1>
			<p>Fetch remote HTML fragment with computed inline styles.</p>
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
				<p>
					<button class="button button-primary" type="submit">Fetch</button>
				</p>
			</form>
			<div id="html2blocks-result" style="margin-top:20px;">
				<h2>Result</h2>
				<div id="html2blocks-fragment" style="border:1px solid #ccd0d4;padding:12px;background:#fff; max-height:400px; overflow:auto;"></div>
				<p>
					<button id="html2blocks-copy" class="button">Copy HTML</button>
				</p>
			</div>
		</div>
		<?php
	}
}
