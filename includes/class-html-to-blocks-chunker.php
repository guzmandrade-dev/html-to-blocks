<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTML_To_Blocks_Chunker {
	public function chunk_html( string $html, array $options = array() ): array {
		$trimmed = trim( $html );
		if ( '' === $trimmed ) {
			return array();
		}

		$max_chars = isset( $options['maxChars'] ) ? (int) $options['maxChars'] : 0;
		if ( $max_chars <= 0 ) {
			$max_chars = (int) apply_filters( 'html2blocks_ai_chunk_max_chars', 12000, $trimmed, $options );
		}

		$max_chunks = isset( $options['maxChunks'] ) ? (int) $options['maxChunks'] : 0;
		if ( $max_chunks <= 0 ) {
			$max_chunks = (int) apply_filters( 'html2blocks_ai_chunk_max_chunks', 30, $trimmed, $options );
		}

		if ( strlen( $trimmed ) <= $max_chars ) {
			return array( $trimmed );
		}

		$dom = $this->load_html( $trimmed );
		if ( ! ( $dom instanceof DOMDocument ) ) {
			return array( $trimmed );
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! ( $body instanceof DOMNode ) ) {
			return array( $trimmed );
		}

		$chunks  = array();
		$current = '';

		$nodes = $this->get_child_nodes( $body );

		foreach ( $nodes as $node ) {
			$fragment = trim( (string) $dom->saveHTML( $node ) );
			if ( '' === $fragment ) {
				continue;
			}

			if ( '' === $current ) {
				$current = $fragment;
				continue;
			}

			$candidate = $current . "\n" . $fragment;
			if ( strlen( $candidate ) > $max_chars ) {
				$chunks[] = $current;
				$current  = $fragment;
			} else {
				$current = $candidate;
			}
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		$chunks = array_values( array_filter( array_map( 'trim', $chunks ) ) );
		if ( empty( $chunks ) ) {
			return array( $trimmed );
		}

		$chunks = $this->enforce_max_chunks( $chunks, $max_chunks );

		return (array) apply_filters( 'html2blocks_ai_chunks', $chunks, $trimmed, $options );
	}

	private function enforce_max_chunks( array $chunks, int $max_chunks ): array {
		if ( $max_chunks <= 0 || count( $chunks ) <= $max_chunks ) {
			return $chunks;
		}

		$head   = array_slice( $chunks, 0, $max_chunks - 1 );
		$tail   = implode( "\n", array_slice( $chunks, $max_chunks - 1 ) );
		$head[] = $tail;

		return $head;
	}

	private function load_html( string $html ): ?DOMDocument {
		if ( '' === trim( $html ) ) {
			return null;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<!DOCTYPE html><html><body>' . $html . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $loaded ) {
			return null;
		}

		return $dom;
	}

	private function get_child_nodes( DOMNode $node ): array {
		$children = array();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$current = $node->firstChild;

		while ( null !== $current ) {
			$children[] = $current;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$current = $current->nextSibling;
		}

		return $children;
	}
}
