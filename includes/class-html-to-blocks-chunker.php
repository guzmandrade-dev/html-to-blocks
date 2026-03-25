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
			$max_chars = (int) apply_filters( 'html2blocks_ai_chunk_max_chars', 5000, $trimmed, $options );
		}

		$max_chunks = isset( $options['maxChunks'] ) ? (int) $options['maxChunks'] : 0;
		if ( $max_chunks <= 0 ) {
			$max_chunks = (int) apply_filters( 'html2blocks_ai_chunk_max_chunks', 30, $trimmed, $options );
		}

		$max_depth = isset( $options['maxSubdivisionDepth'] ) ? (int) $options['maxSubdivisionDepth'] : 0;
		if ( $max_depth <= 0 ) {
			$max_depth = (int) apply_filters( 'html2blocks_ai_chunk_max_subdivision_depth', 6, $trimmed, $options );
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

			if ( strlen( $fragment ) > $max_chars ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}

				$sub_chunks = $this->subdivide_fragment( $fragment, $max_chars, $max_depth );
				foreach ( $sub_chunks as $sub_chunk ) {
					$sub_chunk = trim( (string) $sub_chunk );
					if ( '' !== $sub_chunk ) {
						$chunks[] = $sub_chunk;
					}
				}

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

	private function subdivide_fragment( string $fragment, int $max_chars, int $max_depth, int $depth = 0 ): array {
		$fragment = trim( $fragment );

		if ( '' === $fragment || strlen( $fragment ) <= $max_chars || $depth >= $max_depth ) {
			return array( $fragment );
		}

		$dom = $this->load_html( $fragment );
		if ( ! ( $dom instanceof DOMDocument ) ) {
			return array( $fragment );
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! ( $body instanceof DOMNode ) ) {
			return array( $fragment );
		}

		$nodes = $this->get_child_nodes( $body );
		if ( empty( $nodes ) ) {
			return array( $fragment );
		}

		if ( 1 === count( $nodes ) && $nodes[0] instanceof DOMElement ) {
			$wrapped = $this->subdivide_wrapped_element( $nodes[0], $dom, $max_chars, $max_depth, $depth + 1 );
			if ( ! empty( $wrapped ) ) {
				return $wrapped;
			}
		}

		$chunks  = array();
		$current = '';

		foreach ( $nodes as $node ) {
			$node_html = trim( (string) $dom->saveHTML( $node ) );
			if ( '' === $node_html ) {
				continue;
			}

			if ( strlen( $node_html ) > $max_chars ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}

				$sub_nodes = $this->subdivide_fragment( $node_html, $max_chars, $max_depth, $depth + 1 );
				$chunks    = array_merge( $chunks, $sub_nodes );
				continue;
			}

			if ( '' === $current ) {
				$current = $node_html;
				continue;
			}

			$candidate = $current . "\n" . $node_html;
			if ( strlen( $candidate ) > $max_chars ) {
				$chunks[] = $current;
				$current  = $node_html;
			} else {
				$current = $candidate;
			}
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		$chunks = array_values( array_filter( array_map( 'trim', $chunks ) ) );

		return empty( $chunks ) ? array( $fragment ) : $chunks;
	}

	private function subdivide_wrapped_element( DOMElement $element, DOMDocument $dom, int $max_chars, int $max_depth, int $depth ): array {
		$children = $this->get_child_nodes( $element );
		if ( empty( $children ) ) {
			return array();
		}

		$opening_tag = $this->build_opening_tag( $element );
		if ( '' === $opening_tag ) {
			return array();
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$node_name   = strtolower( $element->nodeName );
		$closing_tag = '</' . $node_name . '>';
		$fixed_len   = strlen( $opening_tag ) + strlen( $closing_tag );

		if ( $fixed_len >= $max_chars ) {
			return array();
		}

		$inner_limit = $max_chars - $fixed_len;
		$inner_html  = '';
		$results     = array();

		foreach ( $children as $child ) {
			$child_html = trim( (string) $dom->saveHTML( $child ) );
			if ( '' === $child_html ) {
				continue;
			}

			if ( strlen( $child_html ) > $inner_limit ) {
				if ( '' !== $inner_html ) {
					$results[] = $opening_tag . $inner_html . $closing_tag;

					$inner_html = '';
				}

				$subdivided = $this->subdivide_fragment( $child_html, $inner_limit, $max_depth, $depth + 1 );
				foreach ( $subdivided as $sub_chunk ) {
					$sub_chunk = trim( (string) $sub_chunk );
					if ( '' !== $sub_chunk ) {
						$results[] = $opening_tag . $sub_chunk . $closing_tag;
					}
				}

				continue;
			}

			if ( '' === $inner_html ) {
				$inner_html = $child_html;
				continue;
			}

			$candidate = $inner_html . "\n" . $child_html;
			if ( strlen( $candidate ) > $inner_limit ) {
				$results[] = $opening_tag . $inner_html . $closing_tag;

				$inner_html = $child_html;
			} else {
				$inner_html = $candidate;
			}
		}

		if ( '' !== $inner_html ) {
			$results[] = $opening_tag . $inner_html . $closing_tag;
		}

		$results = array_values( array_filter( array_map( 'trim', $results ) ) );

		return $results;
	}

	private function build_opening_tag( DOMElement $element ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$node_name = strtolower( trim( (string) $element->nodeName ) );
		if ( '' === $node_name ) {
			return '';
		}

		$attrs = '';
		foreach ( $element->attributes as $attribute ) {
			$name = trim( (string) $attribute->name );
			if ( '' === $name ) {
				continue;
			}

			$value  = (string) $attribute->value;
			$attrs .= sprintf( ' %1$s="%2$s"', $name, esc_attr( $value ) );
		}

		return '<' . $node_name . $attrs . '>';
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
