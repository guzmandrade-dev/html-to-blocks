<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

use Alley\WP\Block_Converter\Block_Converter as Base_Block_Converter;
use Alley\WP\Block_Converter\Block;
use Sabberworm\CSS\Parser as CSSParser;
use Sabberworm\CSS\OutputFormat;

// Basic div mapping example:
// - display: block => core/group
// Fallback => core/html
class HTML_To_Blocks_Converter extends Base_Block_Converter {
	public function __construct( public string $html, public bool $sideload_images = false ) {

		add_filter(
			'wp_block_converter_document_html',
			array( $this, 'wp_block_converter_document_html' ),
			10,
			2
		);
		parent::__construct( $html, $sideload_images );

		// Register a macro to handle <div>.
		Base_Block_Converter::macro( 'div', array( $this, 'div' ) );
	}
	public function wp_block_converter_document_html( string $html, $doc ): string {
		$processor = new WP_HTML_Tag_Processor( $html );

		// Iterate through all tags in the HTML
		while ( $processor->next_tag() ) {
			// Remove the style attribute if it exists
			if ( $processor->get_attribute( 'style' ) !== null ) {
				$processor->remove_attribute( 'style' );
			}
		}

		return $processor->get_updated_html();
	}

	// Convert a <div> using its inline CSS.
	protected function div( \DOMElement|\DOMNode $node ): ?Block {
		if ( ! ( $node instanceof \DOMElement ) ) {
			return new Block( 'core/html', array(), Base_Block_Converter::get_node_html( $node ) );
		}

		$style_map = $this->parse_inline_style( $node->getAttribute( 'style' ) );
		$attrs     = $this->get_common_block_attrs( $node );

		// Very simple initial mapping:
		// display: block -> group
		$display = isset( $style_map['display'] ) ? strtolower( trim( $style_map['display'] ) ) : '';

		if ( 'block' === $display ) {
			$inner = $this->get_sub_blocks( $node->childNodes );
			return new Block(
				block_name: 'group',
				attributes: $attrs,
				content: sprintf(
					'<div class="wp-block-group">%1$s</div>',
					$inner
				),
			);
		}

		// Example for future: display:flex -> group with flex layout.
		if ( 'flex' === $display ) {
			$direction       = strtolower( $style_map['flex-direction'] ?? 'row' );
			$attrs['layout'] = array(
				'type'        => 'flex',
				'orientation' => 'column' === $direction ? 'vertical' : 'horizontal',
			);
			$inner           = $this->get_sub_blocks( $node->childNodes );
			return new Block(
				block_name: 'group',
				attributes: $attrs,
				content: sprintf(
					'<div class="wp-block-group">%1$s</div>',
					$inner
				),
			);
		}

		// Fallback: emit raw HTML for now.
		return new Block( 'core/html', array(), Base_Block_Converter::get_node_html( $node ) );
	}

	// Convert children recursively to blocks.
	public function get_sub_blocks( \DOMNodeList $nodes ): string {
		$blocks = '';
		foreach ( $nodes as $child ) {
			$html      = $child->ownerDocument->saveHTML( $child );
			$converter = new self( $html, $this->sideload_images );
			$blocks   .= $converter->convert();
			unset( $converter );
		}
		return $blocks;
	}

	// Helper: build common block attributes from DOM (id/class -> anchor/className).
	protected function get_common_block_attrs( \DOMElement $node ): array {
		$attrs = array();

		if ( $node->hasAttribute( 'id' ) ) {
			$anchor = sanitize_title( $node->getAttribute( 'id' ) );
			if ( '' !== $anchor ) {
				$attrs['anchor'] = $anchor;
			}
		}
		if ( $node->hasAttribute( 'class' ) ) {
			$class = trim( $node->getAttribute( 'class' ) );
			if ( '' !== $class ) {
				$attrs['className'] = $class;
			}
		}

		return $attrs;
	}

	// Parse inline style attribute into an assoc map using sabberworm.
	protected function parse_inline_style( string $style ): array {
		$style = trim( $style ?? '' );
		if ( '' === $style ) {
			return array();
		}

		// Wrap as a fake rule to leverage the stylesheet parser.
		$css = 'div {' . $style . '}';
		try {
			$parser = new CSSParser( $css );
			$doc    = $parser->parse();
			$rules  = $doc->getAllDeclarationBlocks();

			$out = array();
			$fmt = OutputFormat::createCompact();

			foreach ( $rules as $block ) {
				foreach ( $block->getRules() as $decl ) {
					$prop = strtolower( trim( (string) $decl->getRule() ) );
					$val  = trim( $decl->getValue()->render( $fmt ) );
					if ( '' !== $prop && '' !== $val ) {
						$out[ $prop ] = $val;
					}
				}
			}
			return $out;
		} catch ( \Throwable $e ) {
			// On parse error fall back to naive parsing.
			return $this->fallback_parse_inline_style( $style );
		}
	}

	// Naive parser fallback for "a:b;c:d".
	protected function fallback_parse_inline_style( string $style ): array {
		$map = array();
		foreach ( explode( ';', $style ) as $decl ) {
			if ( strpos( $decl, ':' ) === false ) {
				continue;
			}
			list( $k, $v ) = array_map( 'trim', explode( ':', $decl, 2 ) );
			if ( '' !== $k && '' !== $v ) {
				$map[ strtolower( $k ) ] = $v;
			}
		}
		return $map;
	}

	public function get_elements_by_class( \DOMElement $element, string $class_name ): \DOMNodeList {
		$dom = new \DOMDocument();
		$dom->appendChild( $dom->importNode( $element, true ) );
		$xpath = new \DOMXPath( $dom );
		return $xpath->query( '/*[contains(concat(" ", normalize-space(@class), " "), " ' . $class_name . ' ")]', $dom );
	}

	protected function remove_attributes( \DOMElement $node, array $attributes_to_remove = array() ): void {
		if ( $node->hasAttributes() ) {
			foreach ( $attributes_to_remove as $attribute ) {
				if ( $node->hasAttribute( $attribute ) ) {
					$node->removeAttribute( $attribute );
				}
			}
		}
	}

	protected function remove_child( \DOMNode $node, string $tag_name ): void {
		$child_nodes = $node->childNodes;
		for ( $i = $child_nodes->length - 1; $i >= 0; $i-- ) {
			$childNode = $child_nodes->item( $i );
			if ( $childNode instanceof \DOMElement && $childNode->tagName === $tag_name ) {
				$node->removeChild( $childNode );
			}
		}
	}
}
