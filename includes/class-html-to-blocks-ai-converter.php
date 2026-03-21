<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML_To_Blocks_AI_Converter {
	public function convert( string $html, array $context = array() ) {
		$timeout = isset( $context['chunkTimeout'] ) ? (int) $context['chunkTimeout'] : 0;

		if ( $timeout <= 0 ) {
			$timeout = (int) apply_filters( 'html2blocks_ai_request_timeout', 30, $html, $context );
		}

		return $this->convert_with_timeout( $html, $context, $timeout );
	}

	public function convert_with_timeout( string $html, array $context = array(), int $timeout = 30 ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'html2blocks_ai_client_missing',
				'WP AI Client is not available. Activate the AI Client plugin before using AI conversion.',
				array( 'status' => 500 )
			);
		}

		$provider = (string) apply_filters( 'html2blocks_ai_provider', 'ollama', $html, $context );
		$prompt   = wp_ai_client_prompt( $this->build_user_prompt( $html, $context ) );

		if ( ! is_object( $prompt ) ) {
			return new WP_Error(
				'html2blocks_ai_prompt_unavailable',
				'Unable to initialize the WP AI Client prompt builder.',
				array( 'status' => 500 )
			);
		}

		$prompt = $prompt
			->using_provider( $provider )
			->using_system_instruction( $this->build_system_instruction() )
			->using_temperature( 0.2 );

		$supported = $prompt->is_supported_for_text_generation();
		if ( $supported instanceof WP_Error ) {
			return new WP_Error(
				'html2blocks_ai_support_error',
				$supported->get_error_message(),
				array( 'status' => 500 )
			);
		}

		if ( ! $supported ) {
			return new WP_Error(
				'html2blocks_ai_unsupported',
				'No supported AI text-generation model is configured for the selected provider.',
				array( 'status' => 500 )
			);
		}

		$timeout_filter = null;
		if ( $timeout > 0 ) {
			$timeout_filter = static function () use ( $timeout ) {
				return $timeout;
			};
			add_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 10, 0 );
		}

		try {
			$result = $prompt->generate_text();
		} finally {
			if ( null !== $timeout_filter ) {
				remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter, 10 );
			}
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'html2blocks_ai_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$blocks_markup = $this->normalize_response( (string) $result );

		if ( '' === $blocks_markup ) {
			return new WP_Error(
				'html2blocks_ai_invalid_response',
				'The AI response did not contain serialized Gutenberg block markup.',
				array( 'status' => 500 )
			);
		}

		return $blocks_markup;
	}

	private function build_system_instruction(): string {
		return implode(
			"\n",
			array(
				'You convert HTML fragments into serialized WordPress Gutenberg block markup.',
				'Return only valid block markup ready to paste into the block editor.',
				'Do not include markdown fences, commentary, explanations, or surrounding prose.',
				'Prefer core blocks that best match the source structure and visual intent.',
				'Preserve text, links, headings, lists, images, tables, embeds, and inline styles when block attributes or supported styles can represent them.',
				'Use className, anchor, and block style attributes when needed to preserve intent.',
				'If a fragment cannot be represented faithfully with core blocks, use the smallest possible core/html fallback for just that fragment.',
				'Keep the original content and order intact.',
			)
		);
	}

	private function build_user_prompt( string $html, array $context ): string {
		$lines    = array(
			'Convert the following HTML fragment into serialized Gutenberg blocks.',
			'Requirements:',
			'- Return only block markup using WordPress block comments.',
			'- Prefer core blocks over raw HTML.',
			'- Preserve inline styles and layout intent when possible.',
			'- Use minimal core/html fallbacks only when necessary.',
			'- Do not omit content.',
		);
		$url      = isset( $context['sourceUrl'] ) ? trim( (string) $context['sourceUrl'] ) : '';
		$selector = isset( $context['selector'] ) ? trim( (string) $context['selector'] ) : '';
		$language = isset( $context['language'] ) ? trim( (string) $context['language'] ) : '';

		if ( '' !== $url || '' !== $selector || '' !== $language ) {
			$lines[] = '';
			$lines[] = 'Context:';
			if ( '' !== $url ) {
				$lines[] = 'Source URL: ' . $url;
			}
			if ( '' !== $selector ) {
				$lines[] = 'Selector: ' . $selector;
			}
			if ( '' !== $language ) {
				$lines[] = 'Language hint: ' . $language;
			}
		}

		$chunk_index = isset( $context['chunkIndex'] ) ? (int) $context['chunkIndex'] : 0;
		$chunk_total = isset( $context['chunkTotal'] ) ? (int) $context['chunkTotal'] : 0;

		if ( $chunk_index > 0 && $chunk_total > 0 ) {
			$lines[] = '';
			$lines[] = 'Chunk: ' . $chunk_index . ' of ' . $chunk_total;
		}

		$lines[] = '';
		$lines[] = 'HTML:';
		$lines[] = '```html';
		$lines[] = $html;
		$lines[] = '```';

		return (string) apply_filters(
			'html2blocks_ai_prompt',
			implode( "\n", $lines ),
			$html,
			$context
		);
	}

	private function normalize_response( string $response ): string {
		$markup = trim( $response );

		if ( preg_match( '/```(?:html|text|txt|markdown)?\s*(.*?)```/is', $markup, $matches ) ) {
			$markup = trim( $matches[1] );
		}

		$first_block = strpos( $markup, '<!-- wp:' );
		if ( false === $first_block ) {
			return '';
		}

		$markup     = trim( substr( $markup, $first_block ) );
		$last_close = strrpos( $markup, '-->' );

		if ( false !== $last_close ) {
			$markup = trim( substr( $markup, 0, $last_close + 3 ) );
		}

		return $markup;
	}
}
