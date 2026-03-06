<?php
/**
 * Theme compatibility layer.
 *
 * Reads the active theme's design tokens from the WordPress Global Styles API
 * (theme.json) and exposes them as an associative array that the rest of the
 * plugin can use as mid-priority defaults between hardcoded fallbacks and
 * explicit user settings from the Appearance tab.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_Theme_Compat {

	/** @var array|null Cached token array for the current request. */
	private static $cache = null;

	/**
	 * Return design tokens detected from the active theme.
	 *
	 * Keys mirror the Appearance option keys so they can be merged directly.
	 * Values are only set when the theme provides a corresponding token;
	 * missing keys mean the theme has no opinion and the plugin default wins.
	 *
	 * @return array
	 */
	public static function get_tokens() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		self::$cache = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return self::$cache;
		}

		self::detect_colors();
		self::detect_typography();
		self::detect_spacing();
		self::detect_border_radius();

		return self::$cache;
	}

	/**
	 * Reset the cached tokens (useful after theme switch).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Detect accent, text, and background colors from the theme palette.
	 *
	 * @return void
	 */
	private static function detect_colors() {
		$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		if ( empty( $palette ) || ! is_array( $palette ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette', 'default' ) );
		}
		if ( empty( $palette ) || ! is_array( $palette ) ) {
			return;
		}

		$by_slug = array();
		foreach ( $palette as $entry ) {
			if ( isset( $entry['slug'], $entry['color'] ) ) {
				$by_slug[ $entry['slug'] ] = $entry['color'];
			}
		}

		$accent_slugs = array( 'primary', 'accent', 'vivid-cyan-blue', 'link', 'theme', 'brand' );
		foreach ( $accent_slugs as $slug ) {
			if ( ! empty( $by_slug[ $slug ] ) ) {
				self::$cache['accent_color'] = $by_slug[ $slug ];
				break;
			}
		}

		$text_slugs = array( 'contrast', 'foreground', 'heading', 'text', 'dark' );
		foreach ( $text_slugs as $slug ) {
			if ( ! empty( $by_slug[ $slug ] ) ) {
				self::$cache['text_color'] = $by_slug[ $slug ];
				break;
			}
		}

		$bg_slugs = array( 'base', 'background', 'light', 'white' );
		foreach ( $bg_slugs as $slug ) {
			if ( ! empty( $by_slug[ $slug ] ) ) {
				self::$cache['bg_color'] = $by_slug[ $slug ];
				break;
			}
		}

		$styles = wp_get_global_settings( array() );
		if ( ! empty( $styles['color']['text'] ) ) {
			self::$cache['text_color'] = $styles['color']['text'];
		}
		if ( ! empty( $styles['color']['background'] ) ) {
			self::$cache['bg_color'] = $styles['color']['background'];
		}
	}

	/**
	 * Detect font family and font size from the theme.
	 *
	 * @return void
	 */
	private static function detect_typography() {
		$families = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) );
		if ( empty( $families ) || ! is_array( $families ) ) {
			$families = wp_get_global_settings( array( 'typography', 'fontFamilies', 'default' ) );
		}
		if ( ! empty( $families ) && is_array( $families ) ) {
			$body_slugs = array( 'body', 'system-font', 'sans-serif', 'primary' );
			foreach ( $body_slugs as $slug ) {
				foreach ( $families as $fam ) {
					if ( isset( $fam['slug'] ) && $fam['slug'] === $slug && ! empty( $fam['fontFamily'] ) ) {
						self::$cache['font_family_value'] = $fam['fontFamily'];
						break 2;
					}
				}
			}

			if ( empty( self::$cache['font_family_value'] ) && ! empty( $families[0]['fontFamily'] ) ) {
				self::$cache['font_family_value'] = $families[0]['fontFamily'];
			}
		}

		$font_sizes = wp_get_global_settings( array( 'typography', 'fontSizes', 'theme' ) );
		if ( empty( $font_sizes ) || ! is_array( $font_sizes ) ) {
			$font_sizes = wp_get_global_settings( array( 'typography', 'fontSizes', 'default' ) );
		}
		if ( ! empty( $font_sizes ) && is_array( $font_sizes ) ) {
			foreach ( $font_sizes as $size ) {
				if ( isset( $size['slug'] ) && $size['slug'] === 'medium' && ! empty( $size['size'] ) ) {
					$px = self::size_to_px( $size['size'] );
					if ( $px ) {
						self::$cache['font_size'] = $px;
					}
					break;
				}
			}
		}
	}

	/**
	 * Detect spacing scale from the theme.
	 *
	 * @return void
	 */
	private static function detect_spacing() {
		$spacing_sizes = wp_get_global_settings( array( 'spacing', 'spacingSizes', 'theme' ) );
		if ( empty( $spacing_sizes ) || ! is_array( $spacing_sizes ) ) {
			return;
		}

		$by_slug = array();
		foreach ( $spacing_sizes as $entry ) {
			if ( isset( $entry['slug'], $entry['size'] ) ) {
				$by_slug[ $entry['slug'] ] = $entry['size'];
			}
		}

		if ( ! empty( $by_slug['30'] ) ) {
			self::$cache['spacing_base'] = $by_slug['30'];
		}
		if ( ! empty( $by_slug['40'] ) ) {
			self::$cache['spacing_lg'] = $by_slug['40'];
		}
	}

	/**
	 * Detect border radius from the theme's custom settings.
	 *
	 * @return void
	 */
	private static function detect_border_radius() {
		$custom = wp_get_global_settings( array( 'custom' ) );
		if ( ! empty( $custom['border']['radius'] ) ) {
			$px = self::size_to_px( $custom['border']['radius'] );
			if ( $px ) {
				self::$cache['border_radius'] = $px;
			}
		}
	}

	/**
	 * Convert a CSS size value (px, rem, em) to an integer pixel value.
	 *
	 * @param string|int $value CSS size value.
	 * @return int|null Pixel value or null.
	 */
	private static function size_to_px( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*px$/i', $value, $m ) ) {
			return (int) round( (float) $m[1] );
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*r?em$/i', $value, $m ) ) {
			return (int) round( (float) $m[1] * 16 );
		}
		return null;
	}

	/**
	 * Return a human-readable label for a detected token.
	 *
	 * Used in the admin Appearance tab to show what the theme provides.
	 *
	 * @param string $key Token key (e.g. 'accent_color').
	 * @return string Label or empty string.
	 */
	public static function get_label( $key ) {
		$tokens = self::get_tokens();
		if ( empty( $tokens[ $key ] ) ) {
			return '';
		}

		$theme = wp_get_theme();
		$name  = $theme->get( 'Name' );

		return sprintf(
			/* translators: %s: theme name */
			__( 'Auto-detected from %s', 'wh4u-domains' ),
			$name
		);
	}
}
