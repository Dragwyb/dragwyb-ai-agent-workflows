<?php
/**
 * Builds Gutenberg block markup from structured design sections.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts simple section arrays into valid <!-- wp:... --> markup.
 */
class GutenbergBlockBuilder {

	/**
	 * Build post_content from design_sections (array or JSON string).
	 *
	 * @param mixed $sections Sections list or JSON string.
	 *
	 * @return string Block markup (empty when input is unusable).
	 */
	public static function fromSections( $sections ): string {
		$parsed = self::normalizeSections( $sections );
		if ( array() === $parsed ) {
			return '';
		}

		$blocks = array();
		foreach ( $parsed as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$type = strtolower( (string) ( $section['type'] ?? 'paragraph' ) );
			switch ( $type ) {
				case 'hero':
					$blocks[] = self::heroSection( $section );
					break;
				case 'heading':
					$blocks[] = self::headingBlock(
						(string) ( $section['text'] ?? $section['heading'] ?? '' ),
						(int) ( $section['level'] ?? 2 ),
						(string) ( $section['text_color'] ?? '' ),
						(string) ( $section['align'] ?? 'left' )
					);
					break;
				case 'paragraph':
					$blocks[] = self::paragraphBlock(
						(string) ( $section['text'] ?? '' ),
						(string) ( $section['text_color'] ?? '' ),
						(string) ( $section['align'] ?? 'left' )
					);
					break;
				case 'columns':
					$blocks[] = self::columnsSection( $section );
					break;
				case 'cta':
				case 'buttons':
					$blocks[] = self::ctaSection( $section );
					break;
				case 'spacer':
					$blocks[] = self::spacerBlock( (int) ( $section['height'] ?? 40 ) );
					break;
				case 'separator':
					$blocks[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
					break;
				case 'group':
					$blocks[] = self::groupSection( $section );
					break;
				default:
					$text = (string) ( $section['text'] ?? $section['heading'] ?? '' );
					if ( '' !== $text ) {
						$blocks[] = self::paragraphBlock( $text );
					}
					break;
			}
		}

		return implode( "\n\n", array_filter( $blocks ) );
	}

	/**
	 * @param mixed $sections Raw input.
	 *
	 * @return array<int, mixed>
	 */
	private static function normalizeSections( $sections ): array {
		if ( is_string( $sections ) ) {
			$sections = trim( $sections );
			if ( '' === $sections ) {
				return array();
			}
			$decoded = json_decode( $sections, true );
			if ( ! is_array( $decoded ) ) {
				return array();
			}
			$sections = $decoded;
		}

		if ( ! is_array( $sections ) ) {
			return array();
		}

		// Allow { "sections": [ ... ] } wrapper.
		if ( isset( $sections['sections'] ) && is_array( $sections['sections'] ) ) {
			$sections = $sections['sections'];
		}

		return array_values( $sections );
	}

	/**
	 * @param array<string, mixed> $section Section data.
	 */
	private static function heroSection( array $section ): string {
		$heading     = (string) ( $section['heading'] ?? $section['title'] ?? '' );
		$text        = (string) ( $section['text'] ?? $section['subtitle'] ?? '' );
		$background  = self::sanitizeColor( (string) ( $section['background'] ?? $section['background_color'] ?? '#0f172a' ) );
		$text_color  = self::sanitizeColor( (string) ( $section['text_color'] ?? '#ffffff' ) );
		$gradient    = (string) ( $section['gradient'] ?? '' );
		$button_text = (string) ( $section['button_text'] ?? '' );
		$button_url  = (string) ( $section['button_url'] ?? '' );

		$style = array(
			'spacing' => array(
				'padding' => array(
					'top'    => '80px',
					'bottom' => '80px',
					'left'   => '40px',
					'right'  => '40px',
				),
			),
		);

		if ( '' !== $gradient ) {
			$style['color'] = array( 'gradient' => $gradient );
		} else {
			$style['color'] = array( 'background' => $background );
		}

		$attrs = wp_json_encode(
			array(
				'align'           => 'full',
				'style'           => $style,
				'textColor'       => null,
				'backgroundColor' => null,
			)
		);

		$inner = array();
		if ( '' !== $heading ) {
			$inner[] = self::headingBlock( $heading, 1, $text_color, 'center' );
		}
		if ( '' !== $text ) {
			$inner[] = self::paragraphBlock( $text, $text_color, 'center' );
		}
		if ( '' !== $button_text ) {
			$inner[] = self::buttonsBlock( $button_text, $button_url !== '' ? $button_url : '#', 'center' );
		}

		$class = 'wp-block-group alignfull has-background';
		$css   = '' !== $gradient
			? sprintf( 'background:%s;padding:80px 40px;', esc_attr( $gradient ) )
			: sprintf( 'background-color:%s;padding:80px 40px;', esc_attr( $background ) );

		return sprintf(
			"<!-- wp:group %s -->\n<div class=\"%s\" style=\"%s\">\n%s\n</div>\n<!-- /wp:group -->",
			$attrs ? $attrs : '{}',
			esc_attr( $class ),
			$css,
			implode( "\n", $inner )
		);
	}

	/**
	 * @param array<string, mixed> $section Section data.
	 */
	private static function columnsSection( array $section ): string {
		$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
		if ( array() === $items ) {
			return '';
		}

		$count      = min( 3, max( 2, count( $items ) ) );
		$items      = array_slice( array_values( $items ), 0, $count );
		$text_color = self::sanitizeColor( (string) ( $section['text_color'] ?? '' ) );
		$bg         = self::sanitizeColor( (string) ( $section['background'] ?? $section['background_color'] ?? '' ) );

		$column_blocks = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = (string) ( $item['title'] ?? $item['heading'] ?? '' );
			$text  = (string) ( $item['text'] ?? '' );
			$parts = array();
			if ( '' !== $title ) {
				$parts[] = self::headingBlock( $title, 3, $text_color, 'left' );
			}
			if ( '' !== $text ) {
				$parts[] = self::paragraphBlock( $text, $text_color, 'left' );
			}
			$column_blocks[] = "<!-- wp:column -->\n<div class=\"wp-block-column\">\n"
				. implode( "\n", $parts )
				. "\n</div>\n<!-- /wp:column -->";
		}

		$wrapper_style = '';
		$wrapper_class = 'wp-block-group';
		if ( '' !== $bg ) {
			$wrapper_class .= ' has-background';
			$wrapper_style  = sprintf( ' style="background-color:%s;padding:48px 24px;"', esc_attr( $bg ) );
		}

		$inner = "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n"
			. implode( "\n", $column_blocks )
			. "\n</div>\n<!-- /wp:columns -->";

		return "<!-- wp:group -->\n<div class=\"" . esc_attr( $wrapper_class ) . '"' . $wrapper_style . ">\n"
			. $inner
			. "\n</div>\n<!-- /wp:group -->";
	}

	/**
	 * @param array<string, mixed> $section Section data.
	 */
	private static function ctaSection( array $section ): string {
		$heading     = (string) ( $section['heading'] ?? $section['title'] ?? '' );
		$text        = (string) ( $section['text'] ?? '' );
		$button_text = (string) ( $section['button_text'] ?? __( 'Learn more', 'workflow-automate' ) );
		$button_url  = (string) ( $section['button_url'] ?? '#' );
		$background  = self::sanitizeColor( (string) ( $section['background'] ?? '#f97316' ) );
		$text_color  = self::sanitizeColor( (string) ( $section['text_color'] ?? '#ffffff' ) );

		$parts = array();
		if ( '' !== $heading ) {
			$parts[] = self::headingBlock( $heading, 2, $text_color, 'center' );
		}
		if ( '' !== $text ) {
			$parts[] = self::paragraphBlock( $text, $text_color, 'center' );
		}
		$parts[] = self::buttonsBlock( $button_text, $button_url, 'center' );

		return sprintf(
			"<!-- wp:group {\"style\":{\"color\":{\"background\":\"%1\$s\"},\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"60px\",\"left\":\"32px\",\"right\":\"32px\"}}}} -->\n<div class=\"wp-block-group has-background\" style=\"background-color:%1\$s;padding:60px 32px\">\n%2\$s\n</div>\n<!-- /wp:group -->",
			esc_attr( $background ),
			implode( "\n", $parts )
		);
	}

	/**
	 * @param array<string, mixed> $section Section data.
	 */
	private static function groupSection( array $section ): string {
		$background = self::sanitizeColor( (string) ( $section['background'] ?? '' ) );
		$text_color = self::sanitizeColor( (string) ( $section['text_color'] ?? '' ) );
		$children   = isset( $section['children'] ) && is_array( $section['children'] )
			? $section['children']
			: array();

		$inner_markup = self::fromSections( $children );
		if ( '' === $inner_markup && ! empty( $section['text'] ) ) {
			$inner_markup = self::paragraphBlock( (string) $section['text'], $text_color );
		}

		$style_attr = '';
		$class      = 'wp-block-group';
		if ( '' !== $background ) {
			$class     .= ' has-background';
			$style_attr = sprintf( ' style="background-color:%s;padding:40px 24px"', esc_attr( $background ) );
		}

		return sprintf(
			"<!-- wp:group -->\n<div class=\"%s\"%s>\n%s\n</div>\n<!-- /wp:group -->",
			esc_attr( $class ),
			$style_attr,
			$inner_markup
		);
	}

	private static function headingBlock( string $text, int $level = 2, string $color = '', string $align = 'left' ): string {
		$text  = trim( $text );
		$level = min( 6, max( 1, $level ) );
		if ( '' === $text ) {
			return '';
		}

		$tag   = 'h' . $level;
		$attrs = array( 'level' => $level );
		if ( 'left' !== $align ) {
			$attrs['textAlign'] = $align;
		}
		$style = array();
		if ( '' !== $color ) {
			$style['color'] = array( 'text' => $color );
			$attrs['style'] = $style;
		}

		$class = 'wp-block-heading';
		if ( 'left' !== $align ) {
			$class .= ' has-text-align-' . sanitize_html_class( $align );
		}
		$inline = '';
		if ( '' !== $color ) {
			$class .= ' has-text-color';
			$inline = sprintf( ' style="color:%s"', esc_attr( $color ) );
		}

		$json = wp_json_encode( $attrs );

		return sprintf(
			"<!-- wp:heading %s -->\n<%s class=\"%s\"%s>%s</%s>\n<!-- /wp:heading -->",
			$json ? $json : '{}',
			$tag,
			esc_attr( $class ),
			$inline,
			esc_html( $text ),
			$tag
		);
	}

	private static function paragraphBlock( string $text, string $color = '', string $align = 'left' ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		$attrs = array();
		if ( 'left' !== $align ) {
			$attrs['align'] = $align;
		}
		if ( '' !== $color ) {
			$attrs['style'] = array( 'color' => array( 'text' => $color ) );
		}

		$class = '';
		if ( 'left' !== $align ) {
			$class .= 'has-text-align-' . sanitize_html_class( $align );
		}
		$inline = '';
		if ( '' !== $color ) {
			$class .= ( '' === $class ? '' : ' ' ) . 'has-text-color';
			$inline = sprintf( ' style="color:%s"', esc_attr( $color ) );
		}

		$class_attr = '' !== $class ? sprintf( ' class="%s"', esc_attr( $class ) ) : '';
		$json       = array() === $attrs ? '' : ' ' . ( wp_json_encode( $attrs ) ?: '{}' );

		return sprintf(
			"<!-- wp:paragraph%s -->\n<p%s%s>%s</p>\n<!-- /wp:paragraph -->",
			$json,
			$class_attr,
			$inline,
			wp_kses_post( $text )
		);
	}

	private static function buttonsBlock( string $label, string $url, string $align = 'center' ): string {
		$label = trim( $label );
		$url   = trim( $url );
		if ( '' === $label ) {
			return '';
		}
		if ( '' === $url ) {
			$url = '#';
		}

		$layout = 'center' === $align
			? ' {"layout":{"type":"flex","justifyContent":"center"}}'
			: '';

		return sprintf(
			"<!-- wp:buttons%s -->\n<div class=\"wp-block-buttons\">\n<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"%s\">%s</a></div>\n<!-- /wp:button -->\n</div>\n<!-- /wp:buttons -->",
			$layout,
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private static function spacerBlock( int $height ): string {
		$height = max( 8, min( 200, $height ) );

		return sprintf(
			"<!-- wp:spacer {\"height\":\"%1\$dpx\"} -->\n<div style=\"height:%1\$dpx\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->",
			$height
		);
	}

	private static function sanitizeColor( string $color ): string {
		$color = trim( $color );
		if ( '' === $color ) {
			return '';
		}

		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([^)]+\)$/i', $color ) ) {
			return $color;
		}

		// Named CSS colors / simple tokens.
		if ( preg_match( '/^[a-zA-Z]{3,20}$/', $color ) ) {
			return strtolower( $color );
		}

		return '';
	}
}
