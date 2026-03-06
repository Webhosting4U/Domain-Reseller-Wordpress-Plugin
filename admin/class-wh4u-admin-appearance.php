<?php
/**
 * Appearance settings tab for frontend customization.
 *
 * Provides a visual settings panel with live preview so WordPress admins
 * can customize the domain search form without writing code.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_Admin_Appearance {

	/** @var string Option key in wp_options. */
	const OPTION_KEY = 'wh4u_appearance';

	/** @var string Settings group for the WP Settings API. */
	const SETTINGS_GROUP = 'wh4u_appearance_group';

	/**
	 * Default appearance values.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'css_mode'            => 'full',
			'style_variant'       => 'elevated',
			'accent_color'        => '',
			'text_color'          => '',
			'bg_color'            => '',
			'border_color'        => '',
			'available_color'     => '#059669',
			'unavailable_color'   => '#dc2626',
			'font_family'         => 'inherit',
			'custom_font'         => '',
			'font_size'           => 16,
			'button_font_weight'  => '600',
			'max_width'           => 720,
			'border_radius'       => 12,
			'spacing'             => 'default',
			'placeholder'         => '',
			'button_text'         => '',
			'show_search_icon'    => true,
			'show_pricing'        => false,
			'show_suggestions'    => true,
			'show_transfer'       => true,
			'suggestion_count'    => 5,
			'form_title'          => '',
			'form_description'    => '',
			'visible_fields'      => array( 'firstName', 'lastName', 'email', 'phone', 'company', 'address', 'city', 'state', 'country', 'zip' ),
			'period_options'      => array( 1, 2, 3, 5, 10 ),
			'success_title'       => '',
			'success_message'     => '',
			'search_another_text' => '',
			'popular_tlds'        => '.com, .net, .io, .org, .gr',
		);
	}

	/**
	 * Fields that cannot be hidden (required by the registration API).
	 *
	 * @return array
	 */
	private static function get_required_fields() {
		return array( 'firstName', 'lastName', 'email', 'phone', 'address', 'city', 'state', 'country', 'zip' );
	}

	/**
	 * Get the current appearance settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Register the WP Settings API entry.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting( self::SETTINGS_GROUP, self::OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_appearance' ),
			'default'           => self::get_defaults(),
		) );
	}

	/**
	 * Sanitize all appearance settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_appearance( $input ) {
		if ( ! is_array( $input ) ) {
			return self::get_defaults();
		}

		$defaults  = self::get_defaults();
		$sanitized = array();

		$valid_css_modes = array( 'full', 'minimal', 'none' );
		$sanitized['css_mode'] = isset( $input['css_mode'] ) && in_array( $input['css_mode'], $valid_css_modes, true )
			? $input['css_mode']
			: $defaults['css_mode'];

		$valid_variants = array( 'elevated', 'flat', 'bordered', 'minimal' );
		$sanitized['style_variant'] = isset( $input['style_variant'] ) && in_array( $input['style_variant'], $valid_variants, true )
			? $input['style_variant']
			: $defaults['style_variant'];

		$color_fields = array( 'accent_color', 'text_color', 'bg_color', 'border_color', 'available_color', 'unavailable_color' );
		foreach ( $color_fields as $field ) {
			$sanitized[ $field ] = '';
			if ( ! empty( $input[ $field ] ) ) {
				$hex = sanitize_hex_color( $input[ $field ] );
				if ( $hex ) {
					$sanitized[ $field ] = $hex;
				}
			}
		}

		$valid_font_families = array( 'inherit', 'system', 'custom' );
		$sanitized['font_family'] = isset( $input['font_family'] ) && in_array( $input['font_family'], $valid_font_families, true )
			? $input['font_family']
			: $defaults['font_family'];

		$sanitized['custom_font'] = isset( $input['custom_font'] )
			? sanitize_text_field( wp_unslash( $input['custom_font'] ) )
			: '';

		$sanitized['font_size'] = isset( $input['font_size'] )
			? max( 12, min( 20, absint( $input['font_size'] ) ) )
			: $defaults['font_size'];

		$valid_weights = array( '400', '600', '700' );
		$sanitized['button_font_weight'] = isset( $input['button_font_weight'] ) && in_array( (string) $input['button_font_weight'], $valid_weights, true )
			? (string) $input['button_font_weight']
			: $defaults['button_font_weight'];

		$sanitized['max_width'] = isset( $input['max_width'] )
			? max( 400, min( 1200, absint( $input['max_width'] ) ) )
			: $defaults['max_width'];

		$sanitized['border_radius'] = isset( $input['border_radius'] )
			? max( 0, min( 32, absint( $input['border_radius'] ) ) )
			: $defaults['border_radius'];

		$valid_spacing = array( 'compact', 'default', 'relaxed' );
		$sanitized['spacing'] = isset( $input['spacing'] ) && in_array( $input['spacing'], $valid_spacing, true )
			? $input['spacing']
			: $defaults['spacing'];

		$text_fields = array( 'placeholder', 'button_text', 'form_title', 'form_description', 'success_title', 'success_message', 'search_another_text' );
		foreach ( $text_fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] )
				? sanitize_text_field( wp_unslash( $input[ $field ] ) )
				: '';
		}

		$sanitized['show_search_icon'] = ! empty( $input['show_search_icon'] );
		$sanitized['show_pricing']     = ! empty( $input['show_pricing'] );
		$sanitized['show_suggestions'] = ! empty( $input['show_suggestions'] );
		$sanitized['show_transfer']    = ! empty( $input['show_transfer'] );

		$sanitized['popular_tlds'] = isset( $input['popular_tlds'] )
			? sanitize_text_field( wp_unslash( $input['popular_tlds'] ) )
			: $defaults['popular_tlds'];

		$sanitized['suggestion_count'] = isset( $input['suggestion_count'] )
			? max( 3, min( 10, absint( $input['suggestion_count'] ) ) )
			: $defaults['suggestion_count'];

		$all_fields      = array( 'firstName', 'lastName', 'email', 'phone', 'company', 'address', 'city', 'state', 'country', 'zip' );
		$required_fields = self::get_required_fields();
		$visible         = array();
		if ( isset( $input['visible_fields'] ) && is_array( $input['visible_fields'] ) ) {
			foreach ( $input['visible_fields'] as $f ) {
				$f = sanitize_key( $f );
				if ( in_array( $f, $all_fields, true ) ) {
					$visible[] = $f;
				}
			}
		}
		foreach ( $required_fields as $rf ) {
			if ( ! in_array( $rf, $visible, true ) ) {
				$visible[] = $rf;
			}
		}
		$sanitized['visible_fields'] = $visible;

		$all_periods     = array( 1, 2, 3, 5, 10 );
		$period_options  = array();
		if ( isset( $input['period_options'] ) && is_array( $input['period_options'] ) ) {
			foreach ( $input['period_options'] as $p ) {
				$p = absint( $p );
				if ( in_array( $p, $all_periods, true ) ) {
					$period_options[] = $p;
				}
			}
		}
		if ( empty( $period_options ) ) {
			$period_options = array( 1 );
		}
		sort( $period_options );
		$sanitized['period_options'] = $period_options;

		return $sanitized;
	}

	/**
	 * Render the Appearance tab content.
	 *
	 * @return void
	 */
	public static function render_tab() {
		$settings = self::get_settings();
		$preview_url = add_query_arg(
			array(
				'action'   => 'wh4u_appearance_preview',
				'_wpnonce' => wp_create_nonce( 'wh4u_appearance_preview' ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wh4u-appearance-wrap">
			<div class="wh4u-appearance-controls">
				<form method="post" action="options.php" id="wh4u-appearance-form">
					<?php settings_fields( self::SETTINGS_GROUP ); ?>

					<div class="wh4u-appearance-controls__header">
						<h2><?php esc_html_e( 'Frontend Appearance', 'wh4u-domains' ); ?></h2>
						<?php submit_button( __( 'Save Appearance', 'wh4u-domains' ), 'primary', 'submit-top', false ); ?>
					</div>

					<?php self::render_theme_banner(); ?>
					<?php self::render_section_style_mode( $settings ); ?>
					<?php self::render_section_colors( $settings ); ?>
					<?php self::render_section_typography( $settings ); ?>
					<?php self::render_section_layout( $settings ); ?>
					<?php self::render_section_search_bar( $settings ); ?>
					<?php self::render_section_results( $settings ); ?>
					<?php self::render_section_form( $settings ); ?>
					<?php self::render_section_messages( $settings ); ?>

					<?php submit_button( __( 'Save Appearance', 'wh4u-domains' ) ); ?>
				</form>
			</div>
			<div class="wh4u-appearance-preview">
				<div class="wh4u-appearance-preview__header">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Live Preview', 'wh4u-domains' ); ?>
				</div>
				<iframe
					id="wh4u-appearance-preview-frame"
					src="<?php echo esc_url( $preview_url ); ?>"
					sandbox="allow-same-origin allow-scripts"
					title="<?php esc_attr_e( 'Frontend Preview', 'wh4u-domains' ); ?>"
				></iframe>
			</div>
		</div>
		<?php
	}

	/* ─── Section Renderers ─────────────────────────────────────── */

	/**
	 * Render a banner showing theme detection status.
	 *
	 * @return void
	 */
	private static function render_theme_banner() {
		if ( ! class_exists( 'WH4U_Theme_Compat' ) ) {
			return;
		}

		$tokens = WH4U_Theme_Compat::get_tokens();
		$theme  = wp_get_theme();
		$name   = $theme->get( 'Name' );

		if ( empty( $tokens ) ) {
			?>
			<div class="wh4u-theme-banner">
				<span class="dashicons dashicons-info"></span>
				<span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: active theme name */
							__( 'Theme "%s" does not provide design tokens (theme.json). The plugin will use its own defaults. You can customize everything below.', 'wh4u-domains' ),
							$name
						)
					);
					?>
				</span>
			</div>
			<?php
			return;
		}

		$detected = array();
		if ( ! empty( $tokens['accent_color'] ) ) {
			$detected[] = __( 'accent color', 'wh4u-domains' );
		}
		if ( ! empty( $tokens['text_color'] ) ) {
			$detected[] = __( 'text color', 'wh4u-domains' );
		}
		if ( ! empty( $tokens['bg_color'] ) ) {
			$detected[] = __( 'background', 'wh4u-domains' );
		}
		if ( ! empty( $tokens['font_family_value'] ) ) {
			$detected[] = __( 'font family', 'wh4u-domains' );
		}
		if ( ! empty( $tokens['border_radius'] ) ) {
			$detected[] = __( 'border radius', 'wh4u-domains' );
		}
		?>
		<div class="wh4u-theme-banner">
			<span class="dashicons dashicons-admin-appearance"></span>
			<span>
				<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: active theme name */
						__( 'Auto-adapting to "%s"', 'wh4u-domains' ),
						$name
					)
				);
				?>
				</strong>
				&mdash;
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: comma-separated list of detected tokens */
						__( 'Detected: %s. Set a value below to override any of these.', 'wh4u-domains' ),
						implode( ', ', $detected )
					)
				);
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_style_mode( $s ) {
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Style Mode', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'CSS Mode', 'wh4u-domains' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[css_mode]" data-preview="css_mode">
							<option value="full" <?php selected( $s['css_mode'], 'full' ); ?>><?php esc_html_e( 'Full (plugin styles)', 'wh4u-domains' ); ?></option>
							<option value="minimal" <?php selected( $s['css_mode'], 'minimal' ); ?>><?php esc_html_e( 'Minimal (layout only)', 'wh4u-domains' ); ?></option>
							<option value="none" <?php selected( $s['css_mode'], 'none' ); ?>><?php esc_html_e( 'None (theme controls all)', 'wh4u-domains' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Controls how much CSS the plugin outputs. "Minimal" keeps only layout/spacing. "None" outputs only the HTML structure.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Style Variant', 'wh4u-domains' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_variant]" data-preview="style_variant">
							<option value="elevated" <?php selected( $s['style_variant'], 'elevated' ); ?>><?php esc_html_e( 'Elevated (Shadow)', 'wh4u-domains' ); ?></option>
							<option value="flat" <?php selected( $s['style_variant'], 'flat' ); ?>><?php esc_html_e( 'Flat', 'wh4u-domains' ); ?></option>
							<option value="bordered" <?php selected( $s['style_variant'], 'bordered' ); ?>><?php esc_html_e( 'Bordered', 'wh4u-domains' ); ?></option>
							<option value="minimal" <?php selected( $s['style_variant'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'wh4u-domains' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_colors( $s ) {
		$colors = array(
			'accent_color'      => __( 'Accent / Primary', 'wh4u-domains' ),
			'text_color'        => __( 'Text', 'wh4u-domains' ),
			'bg_color'          => __( 'Background', 'wh4u-domains' ),
			'border_color'      => __( 'Border', 'wh4u-domains' ),
			'available_color'   => __( 'Available Status', 'wh4u-domains' ),
			'unavailable_color' => __( 'Unavailable Status', 'wh4u-domains' ),
		);

		$theme_tokens = class_exists( 'WH4U_Theme_Compat' ) ? WH4U_Theme_Compat::get_tokens() : array();
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Colors', 'wh4u-domains' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Leave empty to auto-detect from your theme. Detected theme colors are shown as swatches.', 'wh4u-domains' ); ?></p>
			<table class="form-table">
				<?php foreach ( $colors as $key => $label ) :
					$theme_val = ! empty( $theme_tokens[ $key ] ) ? $theme_tokens[ $key ] : '';
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<input type="text"
							   class="wh4u-color-picker"
							   name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>"
							   value="<?php echo esc_attr( $s[ $key ] ); ?>"
							   data-preview="<?php echo esc_attr( $key ); ?>"
							   data-default-color="" />
						<?php if ( $theme_val && empty( $s[ $key ] ) ) : ?>
							<p class="description wh4u-theme-hint">
								<span class="wh4u-theme-swatch" style="background:<?php echo esc_attr( $theme_val ); ?>"></span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: hex color value */
										__( 'Using theme color: %s', 'wh4u-domains' ),
										$theme_val
									)
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_typography( $s ) {
		$theme_tokens = class_exists( 'WH4U_Theme_Compat' ) ? WH4U_Theme_Compat::get_tokens() : array();
		$detected_font = ! empty( $theme_tokens['font_family_value'] ) ? $theme_tokens['font_family_value'] : '';
		$inherit_label = __( 'Inherit from theme', 'wh4u-domains' );
		if ( $detected_font ) {
			$short_font = strtok( $detected_font, ',' );
			$short_font = trim( $short_font, " \t\n\r\0\x0B\"'" );
			$inherit_label = sprintf(
				/* translators: %s: detected font family name */
				__( 'Inherit from theme (%s)', 'wh4u-domains' ),
				$short_font
			);
		}
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Typography', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Font Family', 'wh4u-domains' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]" data-preview="font_family" id="wh4u-font-family-select">
							<option value="inherit" <?php selected( $s['font_family'], 'inherit' ); ?>><?php echo esc_html( $inherit_label ); ?></option>
							<option value="system" <?php selected( $s['font_family'], 'system' ); ?>><?php esc_html_e( 'System UI', 'wh4u-domains' ); ?></option>
							<option value="custom" <?php selected( $s['font_family'], 'custom' ); ?>><?php esc_html_e( 'Custom (Google Fonts)', 'wh4u-domains' ); ?></option>
						</select>
					</td>
				</tr>
				<tr id="wh4u-custom-font-row" style="<?php echo $s['font_family'] !== 'custom' ? 'display:none;' : ''; ?>">
					<th scope="row"><?php esc_html_e( 'Google Font Name', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_font]"
							   value="<?php echo esc_attr( $s['custom_font'] ); ?>"
							   data-preview="custom_font"
							   class="regular-text"
							   placeholder="<?php esc_attr_e( 'e.g. Inter, Roboto, Open Sans', 'wh4u-domains' ); ?>" />
						<p class="description"><?php esc_html_e( 'Enter the exact Google Font family name. You must enqueue the font via your theme or a fonts plugin.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Base Font Size', 'wh4u-domains' ); ?></th>
					<td>
						<input type="range" min="12" max="20" step="1"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_size]"
							   value="<?php echo esc_attr( $s['font_size'] ); ?>"
							   data-preview="font_size"
							   id="wh4u-font-size-range" />
						<span id="wh4u-font-size-value"><?php echo esc_html( $s['font_size'] ); ?>px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Button Font Weight', 'wh4u-domains' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_font_weight]" data-preview="button_font_weight">
							<option value="400" <?php selected( $s['button_font_weight'], '400' ); ?>><?php esc_html_e( 'Normal (400)', 'wh4u-domains' ); ?></option>
							<option value="600" <?php selected( $s['button_font_weight'], '600' ); ?>><?php esc_html_e( 'Semi-Bold (600)', 'wh4u-domains' ); ?></option>
							<option value="700" <?php selected( $s['button_font_weight'], '700' ); ?>><?php esc_html_e( 'Bold (700)', 'wh4u-domains' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_layout( $s ) {
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Layout', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Width', 'wh4u-domains' ); ?></th>
					<td>
						<input type="range" min="400" max="1200" step="20"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_width]"
							   value="<?php echo esc_attr( $s['max_width'] ); ?>"
							   data-preview="max_width"
							   id="wh4u-max-width-range" />
						<span id="wh4u-max-width-value"><?php echo esc_html( $s['max_width'] ); ?>px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Border Radius', 'wh4u-domains' ); ?></th>
					<td>
						<input type="range" min="0" max="32" step="2"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_radius]"
							   value="<?php echo esc_attr( $s['border_radius'] ); ?>"
							   data-preview="border_radius"
							   id="wh4u-border-radius-range" />
						<span id="wh4u-border-radius-value"><?php echo esc_html( $s['border_radius'] ); ?>px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Spacing', 'wh4u-domains' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[spacing]" data-preview="spacing">
							<option value="compact" <?php selected( $s['spacing'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'wh4u-domains' ); ?></option>
							<option value="default" <?php selected( $s['spacing'], 'default' ); ?>><?php esc_html_e( 'Default', 'wh4u-domains' ); ?></option>
							<option value="relaxed" <?php selected( $s['spacing'], 'relaxed' ); ?>><?php esc_html_e( 'Relaxed', 'wh4u-domains' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_search_bar( $s ) {
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Search Bar', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Placeholder Text', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[placeholder]"
							   value="<?php echo esc_attr( $s['placeholder'] ); ?>"
							   data-preview="placeholder"
							   placeholder="<?php esc_attr_e( 'Search for your perfect domain...', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Button Text', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_text]"
							   value="<?php echo esc_attr( $s['button_text'] ); ?>"
							   data-preview="button_text"
							   placeholder="<?php esc_attr_e( 'Search', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Search Icon', 'wh4u-domains' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_search_icon]"
								   value="1"
								   data-preview="show_search_icon"
								   <?php checked( $s['show_search_icon'] ); ?> />
							<?php esc_html_e( 'Display the magnifying glass icon inside the search input', 'wh4u-domains' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Popular TLDs', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[popular_tlds]"
							   value="<?php echo esc_attr( $s['popular_tlds'] ); ?>"
							   placeholder=".com, .net, .io, .org, .gr" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of TLD chips shown below the search bar. Leave empty to hide.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_results( $s ) {
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Domain Results', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Pricing', 'wh4u-domains' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_pricing]"
								   value="1"
								   data-preview="show_pricing"
								   <?php checked( $s['show_pricing'] ); ?> />
							<?php esc_html_e( 'Display domain pricing in search results', 'wh4u-domains' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Suggestions', 'wh4u-domains' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_suggestions]"
								   value="1"
								   data-preview="show_suggestions"
								   <?php checked( $s['show_suggestions'] ); ?> />
							<?php esc_html_e( 'Show alternative domain suggestions', 'wh4u-domains' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Transfer', 'wh4u-domains' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_transfer]"
								   value="1"
								   data-preview="show_transfer"
								   <?php checked( $s['show_transfer'] ); ?> />
							<?php esc_html_e( 'Show "Transfer" button on taken domains', 'wh4u-domains' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Suggestion Count', 'wh4u-domains' ); ?></th>
					<td>
						<input type="range" min="3" max="10" step="1"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[suggestion_count]"
							   value="<?php echo esc_attr( $s['suggestion_count'] ); ?>"
							   data-preview="suggestion_count"
							   id="wh4u-suggestion-count-range" />
						<span id="wh4u-suggestion-count-value"><?php echo esc_html( $s['suggestion_count'] ); ?></span>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_form( $s ) {
		$all_fields = array(
			'firstName' => __( 'First Name', 'wh4u-domains' ),
			'lastName'  => __( 'Last Name', 'wh4u-domains' ),
			'email'     => __( 'Email', 'wh4u-domains' ),
			'phone'     => __( 'Phone', 'wh4u-domains' ),
			'company'   => __( 'Company', 'wh4u-domains' ),
			'address'   => __( 'Address', 'wh4u-domains' ),
			'city'      => __( 'City', 'wh4u-domains' ),
			'state'     => __( 'State / Province', 'wh4u-domains' ),
			'country'   => __( 'Country Code', 'wh4u-domains' ),
			'zip'       => __( 'Zip / Postal Code', 'wh4u-domains' ),
		);
		$required_fields = self::get_required_fields();
		$all_periods     = array( 1, 2, 3, 5, 10 );
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Registration Form', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Form Title', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[form_title]"
							   value="<?php echo esc_attr( $s['form_title'] ); ?>"
							   data-preview="form_title"
							   placeholder="<?php esc_attr_e( 'Register this domain', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Form Description', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="large-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[form_description]"
							   value="<?php echo esc_attr( $s['form_description'] ); ?>"
							   data-preview="form_description"
							   placeholder="<?php esc_attr_e( 'Fill in your details below to secure this domain.', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Visible Fields', 'wh4u-domains' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $all_fields as $field_key => $field_label ) :
								$is_required = in_array( $field_key, $required_fields, true );
								$is_checked  = in_array( $field_key, $s['visible_fields'], true );
							?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox"
									   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[visible_fields][]"
									   value="<?php echo esc_attr( $field_key ); ?>"
									   data-preview="visible_field"
									   <?php checked( $is_checked ); ?>
									   <?php disabled( $is_required ); ?> />
								<?php echo esc_html( $field_label ); ?>
								<?php if ( $is_required ) : ?>
									<em class="description">(<?php esc_html_e( 'required', 'wh4u-domains' ); ?>)</em>
								<?php endif; ?>
							</label>
							<?php if ( $is_required ) : ?>
								<input type="hidden"
									   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[visible_fields][]"
									   value="<?php echo esc_attr( $field_key ); ?>" />
							<?php endif; ?>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Required fields cannot be hidden. Only "Company" can be toggled.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Registration Periods', 'wh4u-domains' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $all_periods as $period ) :
								$is_checked = in_array( $period, $s['period_options'], true );
							$label = $period === 1
								/* translators: %d: number of years */
								? sprintf( __( '%d Year', 'wh4u-domains' ), $period )
								/* translators: %d: number of years */
								: sprintf( __( '%d Years', 'wh4u-domains' ), $period );
							?>
							<label style="display:inline-block;margin-right:16px;">
								<input type="checkbox"
									   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[period_options][]"
									   value="<?php echo esc_attr( $period ); ?>"
									   data-preview="period_option"
									   <?php checked( $is_checked ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * @param array $s Current settings.
	 * @return void
	 */
	private static function render_section_messages( $s ) {
		?>
		<div class="wh4u-appearance-section">
			<h3><?php esc_html_e( 'Messages', 'wh4u-domains' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Success Title', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_title]"
							   value="<?php echo esc_attr( $s['success_title'] ); ?>"
							   data-preview="success_title"
							   placeholder="<?php esc_attr_e( 'Request Submitted!', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Success Message', 'wh4u-domains' ); ?></th>
					<td>
						<textarea class="large-text" rows="3"
								  name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_message]"
								  data-preview="success_message"
								  placeholder="<?php esc_attr_e( 'Your domain registration request has been submitted. We will contact you shortly.', 'wh4u-domains' ); ?>"><?php echo esc_textarea( $s['success_message'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '"Search Another" Button', 'wh4u-domains' ); ?></th>
					<td>
						<input type="text" class="regular-text"
							   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[search_another_text]"
							   value="<?php echo esc_attr( $s['search_another_text'] ); ?>"
							   data-preview="search_another_text"
							   placeholder="<?php esc_attr_e( 'Search another domain', 'wh4u-domains' ); ?>" />
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/* ─── Live Preview AJAX Endpoint ────────────────────────────── */

	/**
	 * Register the admin-ajax handler for the preview iframe.
	 *
	 * @return void
	 */
	public static function register_preview_ajax() {
		add_action( 'wp_ajax_wh4u_appearance_preview', array( __CLASS__, 'render_preview' ) );
	}

	/**
	 * Output an isolated HTML page with the shortcode output and a postMessage listener.
	 *
	 * @return void
	 */
	public static function render_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wh4u-domains' ) );
		}

		check_ajax_referer( 'wh4u_appearance_preview' );

		$settings = self::get_settings();

		$css_url = WH4U_DOMAINS_PLUGIN_URL . 'public/css/wh4u-public.css?ver=' . WH4U_DOMAINS_VERSION;
		$minimal_css_url = WH4U_DOMAINS_PLUGIN_URL . 'public/css/wh4u-public-minimal.css?ver=' . WH4U_DOMAINS_VERSION;

		$atts = array(
			'placeholder'      => ! empty( $settings['placeholder'] ) ? $settings['placeholder'] : __( 'Search for your perfect domain...', 'wh4u-domains' ),
			'button_text'      => ! empty( $settings['button_text'] ) ? $settings['button_text'] : __( 'Search', 'wh4u-domains' ),
			'accent_color'     => $settings['accent_color'],
			'style_variant'    => $settings['style_variant'],
			'show_pricing'     => $settings['show_pricing'] ? 'true' : 'false',
			'show_suggestions' => $settings['show_suggestions'] ? 'true' : 'false',
			'form_title'       => ! empty( $settings['form_title'] ) ? $settings['form_title'] : __( 'Register this domain', 'wh4u-domains' ),
			'form_description' => ! empty( $settings['form_description'] ) ? $settings['form_description'] : __( 'Fill in your details below to secure this domain.', 'wh4u-domains' ),
			'border_radius'    => (string) $settings['border_radius'],
		);

		$html = WH4U_Public::get_lookup_html( $atts );

		$parsed_url     = wp_parse_url( home_url() );
		$allowed_origin = $parsed_url['scheme'] . '://' . $parsed_url['host'];
		if ( ! empty( $parsed_url['port'] ) ) {
			$allowed_origin .= ':' . $parsed_url['port'];
		}
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- isolated preview iframe, no WP head/footer ?>
<link rel="stylesheet" id="wh4u-preview-full-css" href="<?php echo esc_url( $css_url ); ?>"<?php echo $settings['css_mode'] !== 'full' ? ' disabled' : ''; ?> />
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- isolated preview iframe, no WP head/footer ?>
<link rel="stylesheet" id="wh4u-preview-minimal-css" href="<?php echo esc_url( $minimal_css_url ); ?>"<?php echo $settings['css_mode'] !== 'minimal' ? ' disabled' : ''; ?> />
<style>
body { margin: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; }
.wh4u-preview-badge { position: fixed; top: 8px; right: 8px; background: #2271b1; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; opacity: 0.7; z-index: 999; }
</style>
</head>
<body>
<div class="wh4u-preview-badge"><?php esc_html_e( 'Preview', 'wh4u-domains' ); ?></div>
<?php
echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output generated by get_lookup_html which escapes internally
?>
<script>
(function(){
	var allowedOrigin = <?php echo wp_json_encode( $allowed_origin ); ?>;

	window.addEventListener('message', function(e) {
		if (e.origin !== allowedOrigin) return;
		if (!e.data || e.data.type !== 'wh4u-preview') return;

		var d = e.data.data;
		var root = document.querySelector('.wh4u-domains');
		if (!root) return;

		// CSS custom properties
		if (d.accent_color !== undefined) root.style.setProperty('--wh4u-accent', d.accent_color || '');
		if (d.text_color !== undefined) root.style.setProperty('--wh4u-text', d.text_color || '');
		if (d.bg_color !== undefined) root.style.setProperty('--wh4u-bg', d.bg_color || '');
		if (d.border_color !== undefined) root.style.setProperty('--wh4u-border', d.border_color || '');
		if (d.available_color !== undefined) root.style.setProperty('--wh4u-available', d.available_color || '');
		if (d.unavailable_color !== undefined) root.style.setProperty('--wh4u-unavailable', d.unavailable_color || '');
		if (d.border_radius !== undefined) root.style.setProperty('--wh4u-radius', d.border_radius + 'px');
		if (d.max_width !== undefined) root.style.maxWidth = d.max_width + 'px';
		if (d.font_size !== undefined) root.style.fontSize = d.font_size + 'px';

		if (d.font_family !== undefined) {
			var ff = 'inherit';
			if (d.font_family === 'system') ff = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
			else if (d.font_family === 'custom' && d.custom_font) {
				var safeFontName = String(d.custom_font).replace(/[";{}\\<>]/g, '');
				if (safeFontName) ff = '"' + safeFontName + '", sans-serif';
			}
			root.style.fontFamily = ff;
		}

		if (d.button_font_weight !== undefined) {
			var btns = root.querySelectorAll('.wh4u-domains__btn, .wh4u-domains__btn-primary, .wh4u-domains__register-btn');
			for (var i = 0; i < btns.length; i++) btns[i].style.fontWeight = d.button_font_weight;
		}

		if (d.spacing !== undefined) {
			var gap = d.spacing === 'compact' ? '0.75rem' : (d.spacing === 'relaxed' ? '1.5rem' : '1rem');
			root.style.setProperty('--wh4u-gap', gap);
		}

		// Style variant (allowlist validated)
		if (d.style_variant !== undefined && ['elevated','flat','bordered','minimal'].indexOf(d.style_variant) !== -1) {
			root.className = root.className.replace(/wh4u-domains--\S+/g, '');
			root.classList.add('wh4u-domains--' + d.style_variant);
		}

		// Text content
		var input = root.querySelector('.wh4u-domains__input');
		if (d.placeholder !== undefined && input) input.placeholder = d.placeholder || '';

		var btnText = root.querySelector('.wh4u-domains__btn .wh4u-domains__btn-text');
		if (d.button_text !== undefined && btnText) btnText.textContent = d.button_text || '';

		var formTitle = root.querySelector('.wh4u-domains__form-title');
		if (d.form_title !== undefined && formTitle) formTitle.textContent = d.form_title || '';

		var formDesc = root.querySelector('.wh4u-domains__form-desc');
		if (d.form_description !== undefined && formDesc) formDesc.textContent = d.form_description || '';

		// Search icon
		var icon = root.querySelector('.wh4u-domains__search-icon');
		if (d.show_search_icon !== undefined && icon) icon.style.display = d.show_search_icon ? '' : 'none';

		// Field visibility
		if (d.visible_fields !== undefined) {
			var fieldMap = {
				'firstName': 'wh4u-reg-firstname', 'lastName': 'wh4u-reg-lastname',
				'email': 'wh4u-reg-email', 'phone': 'wh4u-reg-phone',
				'company': 'wh4u-reg-company', 'address': 'wh4u-reg-address',
				'city': 'wh4u-reg-city', 'state': 'wh4u-reg-state',
				'country': 'wh4u-reg-country', 'zip': 'wh4u-reg-zip'
			};
			for (var fname in fieldMap) {
				var el = document.getElementById(fieldMap[fname]);
				if (el) {
					var wrap = el.closest('.wh4u-domains__field');
					if (wrap) wrap.style.display = d.visible_fields.indexOf(fname) !== -1 ? '' : 'none';
				}
			}
		}

		// CSS mode (both sheets are always present, toggled via disabled)
		if (d.css_mode !== undefined) {
			var fullLink = document.getElementById('wh4u-preview-full-css');
			var minLink = document.getElementById('wh4u-preview-minimal-css');
			if (fullLink) fullLink.disabled = (d.css_mode !== 'full');
			if (minLink) minLink.disabled = (d.css_mode !== 'minimal');
		}

		// Show the form section in preview so fields are visible
		var formSection = root.querySelector('.wh4u-domains__form-section');
		if (formSection && (d.visible_fields !== undefined || d.form_title !== undefined || d.form_description !== undefined)) {
			formSection.style.display = '';
		}
	});

	// Show the form section by default in preview
	var fs = document.querySelector('.wh4u-domains__form-section');
	if (fs) fs.style.display = '';
})();
</script>
</body>
</html>
		<?php
		wp_die();
	}
}
