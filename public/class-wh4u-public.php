<?php
/**
 * Public-facing functionality: shortcode registration, block rendering, and
 * frontend asset loading.
 *
 * Generates a modern, theme-agnostic domain search with an inline public
 * registration form. Uses CSS custom properties that inherit from theme.json
 * presets so the block adapts to any installed theme.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_Public {

	/** @var bool Whether the shortcode/block has been detected on the current page. */
	private static $shortcode_used = false;

	/**
	 * Initialize public hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'wh4u_domain_lookup', array( $this, 'render_lookup_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Render the [wh4u_domain_lookup] shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string HTML output.
	 */
	public function render_lookup_shortcode( $atts = array(), $content = '' ) {
		self::$shortcode_used = true;

		$this->enqueue_frontend_assets();

		$known_keys = array(
			'placeholder', 'button_text', 'accent_color', 'style_variant',
			'show_pricing', 'show_suggestions', 'form_title', 'form_description',
			'border_radius',
		);

		$filtered = array();
		if ( is_array( $atts ) ) {
			foreach ( $atts as $key => $value ) {
				if ( in_array( $key, $known_keys, true ) ) {
					$filtered[ $key ] = $value;
				}
			}
		}

		return self::get_lookup_html( $filtered );
	}

	/**
	 * Generate the lookup form HTML with modern structure.
	 *
	 * Merges global appearance settings with per-instance shortcode/block
	 * attributes. Shortcode attributes win when explicitly provided.
	 *
	 * @param array $atts Block/shortcode attributes.
	 * @return string HTML markup.
	 */
	public static function get_lookup_html( $atts = array() ) {
		$appearance = class_exists( 'WH4U_Admin_Appearance' )
			? WH4U_Admin_Appearance::get_settings()
			: array();

		$theme_tokens = class_exists( 'WH4U_Theme_Compat' )
			? WH4U_Theme_Compat::get_tokens()
			: array();

		$defaults = array(
			'placeholder'      => ! empty( $appearance['placeholder'] ) ? $appearance['placeholder'] : __( 'Search for your perfect domain...', 'wh4u-domains' ),
			'button_text'      => ! empty( $appearance['button_text'] ) ? $appearance['button_text'] : __( 'Search', 'wh4u-domains' ),
			'accent_color'     => ! empty( $appearance['accent_color'] ) ? $appearance['accent_color'] : ( ! empty( $theme_tokens['accent_color'] ) ? $theme_tokens['accent_color'] : '' ),
			'style_variant'    => ! empty( $appearance['style_variant'] ) ? $appearance['style_variant'] : 'elevated',
			'show_pricing'     => ! empty( $appearance['show_pricing'] ) ? 'true' : 'false',
			'show_suggestions' => isset( $appearance['show_suggestions'] ) ? ( $appearance['show_suggestions'] ? 'true' : 'false' ) : 'true',
			'form_title'       => ! empty( $appearance['form_title'] ) ? $appearance['form_title'] : __( 'Register this domain', 'wh4u-domains' ),
			'form_description' => ! empty( $appearance['form_description'] ) ? $appearance['form_description'] : __( 'Fill in your details below to secure this domain.', 'wh4u-domains' ),
			'border_radius'    => isset( $appearance['border_radius'] ) ? (string) $appearance['border_radius'] : ( ! empty( $theme_tokens['border_radius'] ) ? (string) $theme_tokens['border_radius'] : '12' ),
		);

		$atts = wp_parse_args( $atts, $defaults );

		$visible_fields = isset( $appearance['visible_fields'] ) && is_array( $appearance['visible_fields'] )
			? $appearance['visible_fields']
			: array( 'firstName', 'lastName', 'email', 'phone', 'company', 'address', 'city', 'state', 'country', 'zip' );

		$period_options = isset( $appearance['period_options'] ) && is_array( $appearance['period_options'] )
			? $appearance['period_options']
			: array( 1, 2, 3, 5, 10 );

		$show_search_icon = isset( $appearance['show_search_icon'] ) ? $appearance['show_search_icon'] : true;
		$show_transfer    = isset( $appearance['show_transfer'] ) ? $appearance['show_transfer'] : true;

		$popular_tlds_raw = isset( $appearance['popular_tlds'] ) ? $appearance['popular_tlds'] : '.com, .net, .io, .org, .gr';
		$popular_tlds     = array_filter( array_map( 'trim', explode( ',', $popular_tlds_raw ) ) );

		$style_parts = array();

		if ( ! empty( $atts['accent_color'] ) ) {
			$hex = sanitize_hex_color( $atts['accent_color'] );
			if ( $hex ) {
				$style_parts[] = '--wh4u-accent: ' . $hex;
			}
		}
		if ( ! empty( $appearance['text_color'] ) ) {
			$style_parts[] = '--wh4u-text: ' . sanitize_hex_color( $appearance['text_color'] );
		} elseif ( ! empty( $theme_tokens['text_color'] ) ) {
			$style_parts[] = '--wh4u-text: ' . sanitize_hex_color( $theme_tokens['text_color'] );
		}
		if ( ! empty( $appearance['bg_color'] ) ) {
			$style_parts[] = '--wh4u-bg: ' . sanitize_hex_color( $appearance['bg_color'] );
		} elseif ( ! empty( $theme_tokens['bg_color'] ) ) {
			$style_parts[] = '--wh4u-bg: ' . sanitize_hex_color( $theme_tokens['bg_color'] );
		}
		if ( ! empty( $appearance['border_color'] ) ) {
			$style_parts[] = '--wh4u-border: ' . sanitize_hex_color( $appearance['border_color'] );
		}
		if ( ! empty( $appearance['available_color'] ) ) {
			$style_parts[] = '--wh4u-available: ' . sanitize_hex_color( $appearance['available_color'] );
		}
		if ( ! empty( $appearance['unavailable_color'] ) ) {
			$style_parts[] = '--wh4u-unavailable: ' . sanitize_hex_color( $appearance['unavailable_color'] );
		}
		if ( ! empty( $atts['border_radius'] ) ) {
			$style_parts[] = '--wh4u-radius: ' . absint( $atts['border_radius'] ) . 'px';
		}
		if ( ! empty( $appearance['max_width'] ) && (int) $appearance['max_width'] !== 720 ) {
			$style_parts[] = 'max-width: ' . absint( $appearance['max_width'] ) . 'px';
		}
		if ( ! empty( $appearance['font_size'] ) && (int) $appearance['font_size'] !== 16 ) {
			$style_parts[] = 'font-size: ' . absint( $appearance['font_size'] ) . 'px';
		} elseif ( ! empty( $theme_tokens['font_size'] ) ) {
			$style_parts[] = 'font-size: ' . absint( $theme_tokens['font_size'] ) . 'px';
		}

		$font_family_mode = isset( $appearance['font_family'] ) ? $appearance['font_family'] : 'inherit';
		if ( $font_family_mode === 'system' ) {
			$style_parts[] = 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
		} elseif ( $font_family_mode === 'custom' && ! empty( $appearance['custom_font'] ) ) {
			$style_parts[] = 'font-family: "' . esc_attr( sanitize_text_field( $appearance['custom_font'] ) ) . '", sans-serif';
		} elseif ( $font_family_mode === 'inherit' && ! empty( $theme_tokens['font_family_value'] ) ) {
			$style_parts[] = 'font-family: ' . esc_attr( $theme_tokens['font_family_value'] );
		}

		$spacing_mode = isset( $appearance['spacing'] ) ? $appearance['spacing'] : 'default';
		if ( $spacing_mode === 'compact' ) {
			$style_parts[] = '--wh4u-gap: 0.75rem';
			$style_parts[] = '--wh4u-gap-lg: 1rem';
		} elseif ( $spacing_mode === 'relaxed' ) {
			$style_parts[] = '--wh4u-gap: 1.25rem';
			$style_parts[] = '--wh4u-gap-lg: 2rem';
		} elseif ( $spacing_mode === 'default' ) {
			if ( ! empty( $theme_tokens['spacing_base'] ) ) {
				$style_parts[] = '--wh4u-gap: ' . esc_attr( $theme_tokens['spacing_base'] );
			}
			if ( ! empty( $theme_tokens['spacing_lg'] ) ) {
				$style_parts[] = '--wh4u-gap-lg: ' . esc_attr( $theme_tokens['spacing_lg'] );
			}
		}

		$style_attr    = ! empty( $style_parts ) ? implode( '; ', $style_parts ) : '';
		$variant_class = sanitize_html_class( $atts['style_variant'] );

		$btn_weight_style = '';
		if ( ! empty( $appearance['button_font_weight'] ) && $appearance['button_font_weight'] !== '600' ) {
			$btn_weight_style = ' style="font-weight:' . esc_attr( $appearance['button_font_weight'] ) . '"';
		}

		ob_start();
		?>
		<div class="wh4u-domains wh4u-domains--<?php echo esc_attr( $variant_class ); ?>"<?php echo $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : ''; ?> data-show-pricing="<?php echo esc_attr( $atts['show_pricing'] ); ?>" data-show-suggestions="<?php echo esc_attr( $atts['show_suggestions'] ); ?>" data-show-transfer="<?php echo $show_transfer ? 'true' : 'false'; ?>" data-form-title="<?php echo esc_attr( $atts['form_title'] ); ?>" data-form-description="<?php echo esc_attr( $atts['form_description'] ); ?>">
			<div class="wh4u-domains__search-section">
				<form class="wh4u-domains__form" id="wh4u-public-lookup-form" role="search" aria-label="<?php esc_attr_e( 'Domain search', 'wh4u-domains' ); ?>">
					<div class="wh4u-domains__input-wrap">
						<?php if ( $show_search_icon ) : ?>
						<span class="wh4u-domains__search-icon" aria-hidden="true">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
						</span>
						<?php endif; ?>
						<input type="text"
							   class="wh4u-domains__input"
							   id="wh4u-public-search"
							   name="searchTerm"
							   placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
							   required
							   minlength="2"
							   maxlength="253"
							   autocomplete="off"
							   aria-label="<?php esc_attr_e( 'Domain name', 'wh4u-domains' ); ?>" />
						<button type="submit" class="wh4u-domains__btn" id="wh4u-public-search-btn"<?php echo $btn_weight_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value built with esc_attr() at assignment ?>>
							<span class="wh4u-domains__btn-text"><?php echo esc_html( $atts['button_text'] ); ?></span>
							<span class="wh4u-domains__btn-spinner" aria-hidden="true"></span>
						</button>
					</div>
				</form>

				<?php if ( ! empty( $popular_tlds ) ) : ?>
				<div class="wh4u-domains__tld-chips" aria-label="<?php esc_attr_e( 'Popular extensions', 'wh4u-domains' ); ?>">
					<?php foreach ( $popular_tlds as $tld ) :
						$tld = sanitize_text_field( $tld );
						if ( empty( $tld ) ) {
							continue;
						}
						if ( '.' !== $tld[0] ) {
							$tld = '.' . $tld;
						}
					?>
					<button type="button" class="wh4u-domains__tld-chip" data-tld="<?php echo esc_attr( $tld ); ?>"><?php echo esc_html( $tld ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="wh4u-domains__loading" id="wh4u-public-loading" aria-live="polite" style="display:none;">
				<div class="wh4u-domains__loading-bar"></div>
			</div>

			<div class="wh4u-domains__error" id="wh4u-public-error" role="alert" style="display:none;">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
				<p></p>
			</div>

			<div class="wh4u-domains__results" id="wh4u-public-results" aria-live="polite" style="display:none;">
			</div>

			<div class="wh4u-domains__form-section" id="wh4u-public-form-section" style="display:none;">
				<div class="wh4u-domains__form-header">
					<div class="wh4u-domains__form-domain-badge" id="wh4u-form-domain-badge"></div>
					<h3 class="wh4u-domains__form-title" id="wh4u-form-title"><?php echo esc_html( $atts['form_title'] ); ?></h3>
					<p class="wh4u-domains__form-desc" id="wh4u-form-desc"><?php echo esc_html( $atts['form_description'] ); ?></p>
				</div>
				<form class="wh4u-domains__register-form" id="wh4u-public-register-form" novalidate>
					<input type="text" name="wh4u_hp_check" class="wh4u-domains__hp" tabindex="-1" autocomplete="new-password" aria-hidden="true" />

					<div class="wh4u-domains__form-grid">
						<?php if ( in_array( 'firstName', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-firstname"><?php esc_html_e( 'First Name', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-firstname" name="firstName" required maxlength="100" autocomplete="given-name" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'lastName', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-lastname"><?php esc_html_e( 'Last Name', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-lastname" name="lastName" required maxlength="100" autocomplete="family-name" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'email', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-email"><?php esc_html_e( 'Email', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="email" id="wh4u-reg-email" name="email" required maxlength="255" autocomplete="email" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'phone', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-phone"><?php esc_html_e( 'Phone', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="tel" id="wh4u-reg-phone" name="phone" required maxlength="50" autocomplete="tel" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'company', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field wh4u-domains__field--full">
							<label for="wh4u-reg-company"><?php esc_html_e( 'Company', 'wh4u-domains' ); ?></label>
							<input type="text" id="wh4u-reg-company" name="company" maxlength="255" autocomplete="organization" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'address', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field wh4u-domains__field--full">
							<label for="wh4u-reg-address"><?php esc_html_e( 'Address', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-address" name="address" required maxlength="255" autocomplete="street-address" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'city', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-city"><?php esc_html_e( 'City', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-city" name="city" required maxlength="100" autocomplete="address-level2" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'state', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-state"><?php esc_html_e( 'State / Province', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-state" name="state" required maxlength="100" autocomplete="address-level1" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'country', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-country"><?php esc_html_e( 'Country Code', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-country" name="country" required maxlength="2" minlength="2" placeholder="<?php esc_attr_e( 'e.g. US', 'wh4u-domains' ); ?>" autocomplete="country" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'zip', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-reg-zip"><?php esc_html_e( 'Zip / Postal Code', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-reg-zip" name="zip" required maxlength="20" autocomplete="postal-code" />
						</div>
						<?php endif; ?>
					</div>

					<div class="wh4u-domains__field wh4u-domains__field--period">
						<label for="wh4u-reg-period"><?php esc_html_e( 'Registration Period', 'wh4u-domains' ); ?></label>
						<select id="wh4u-reg-period" name="regperiod">
						<?php foreach ( $period_options as $period ) :
							$label = $period === 1
								/* translators: %d: number of years */
								? sprintf( __( '%d Year', 'wh4u-domains' ), $period )
								/* translators: %d: number of years */
								: sprintf( __( '%d Years', 'wh4u-domains' ), $period );
						?>
						<option value="<?php echo esc_attr( $period ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wh4u-domains__form-actions">
					<button type="button" class="wh4u-domains__btn-secondary" id="wh4u-form-cancel">
							<?php esc_html_e( 'Back to results', 'wh4u-domains' ); ?>
						</button>
						<button type="submit" class="wh4u-domains__btn-primary" id="wh4u-form-submit"<?php echo $btn_weight_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value built with esc_attr() at assignment ?>>
							<span class="wh4u-domains__btn-text"><?php esc_html_e( 'Submit Registration Request', 'wh4u-domains' ); ?></span>
							<span class="wh4u-domains__btn-spinner" aria-hidden="true"></span>
						</button>
					</div>
				</form>
			</div>

			<?php if ( $show_transfer ) : ?>
			<div class="wh4u-domains__form-section" id="wh4u-public-transfer-section" style="display:none;">
				<div class="wh4u-domains__form-header">
					<div class="wh4u-domains__form-domain-badge" id="wh4u-transfer-domain-badge"></div>
					<h3 class="wh4u-domains__form-title" id="wh4u-transfer-title"><?php esc_html_e( 'Transfer this domain', 'wh4u-domains' ); ?></h3>
					<p class="wh4u-domains__form-desc" id="wh4u-transfer-desc"><?php esc_html_e( 'Fill in your details and the EPP/Auth code to transfer this domain.', 'wh4u-domains' ); ?></p>
				</div>
				<form class="wh4u-domains__register-form" id="wh4u-public-transfer-form" novalidate>
					<input type="text" name="wh4u_hp_check" class="wh4u-domains__hp" tabindex="-1" autocomplete="new-password" aria-hidden="true" />

					<div class="wh4u-domains__field wh4u-domains__field--full wh4u-domains__field--eppcode">
						<label for="wh4u-transfer-eppcode"><?php esc_html_e( 'EPP / Auth Code', 'wh4u-domains' ); ?></label>
						<input type="text" id="wh4u-transfer-eppcode" name="eppcode" maxlength="255" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional -- can be provided later', 'wh4u-domains' ); ?>" />
					</div>

					<div class="wh4u-domains__form-grid">
						<?php if ( in_array( 'firstName', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-firstname"><?php esc_html_e( 'First Name', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-firstname" name="firstName" required maxlength="100" autocomplete="given-name" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'lastName', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-lastname"><?php esc_html_e( 'Last Name', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-lastname" name="lastName" required maxlength="100" autocomplete="family-name" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'email', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-email"><?php esc_html_e( 'Email', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="email" id="wh4u-xfer-email" name="email" required maxlength="255" autocomplete="email" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'phone', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-phone"><?php esc_html_e( 'Phone', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="tel" id="wh4u-xfer-phone" name="phone" required maxlength="50" autocomplete="tel" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'company', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field wh4u-domains__field--full">
							<label for="wh4u-xfer-company"><?php esc_html_e( 'Company', 'wh4u-domains' ); ?></label>
							<input type="text" id="wh4u-xfer-company" name="company" maxlength="255" autocomplete="organization" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'address', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field wh4u-domains__field--full">
							<label for="wh4u-xfer-address"><?php esc_html_e( 'Address', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-address" name="address" required maxlength="255" autocomplete="street-address" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'city', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-city"><?php esc_html_e( 'City', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-city" name="city" required maxlength="100" autocomplete="address-level2" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'state', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-state"><?php esc_html_e( 'State / Province', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-state" name="state" required maxlength="100" autocomplete="address-level1" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'country', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-country"><?php esc_html_e( 'Country Code', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-country" name="country" required maxlength="2" minlength="2" placeholder="<?php esc_attr_e( 'e.g. US', 'wh4u-domains' ); ?>" autocomplete="country" />
						</div>
						<?php endif; ?>
						<?php if ( in_array( 'zip', $visible_fields, true ) ) : ?>
						<div class="wh4u-domains__field">
							<label for="wh4u-xfer-zip"><?php esc_html_e( 'Zip / Postal Code', 'wh4u-domains' ); ?> <abbr title="<?php esc_attr_e( 'required', 'wh4u-domains' ); ?>">*</abbr></label>
							<input type="text" id="wh4u-xfer-zip" name="zip" required maxlength="20" autocomplete="postal-code" />
						</div>
						<?php endif; ?>
					</div>

					<div class="wh4u-domains__field wh4u-domains__field--period">
						<label for="wh4u-xfer-period"><?php esc_html_e( 'Transfer Period', 'wh4u-domains' ); ?></label>
						<select id="wh4u-xfer-period" name="regperiod">
						<?php foreach ( $period_options as $period ) :
							$label = $period === 1
								/* translators: %d: number of years */
								? sprintf( __( '%d Year', 'wh4u-domains' ), $period )
								/* translators: %d: number of years */
								: sprintf( __( '%d Years', 'wh4u-domains' ), $period );
						?>
						<option value="<?php echo esc_attr( $period ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wh4u-domains__form-actions">
					<button type="button" class="wh4u-domains__btn-secondary" id="wh4u-transfer-cancel">
							<?php esc_html_e( 'Back to results', 'wh4u-domains' ); ?>
						</button>
						<button type="submit" class="wh4u-domains__btn-primary" id="wh4u-transfer-submit"<?php echo $btn_weight_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value built with esc_attr() at assignment ?>>
							<span class="wh4u-domains__btn-text"><?php esc_html_e( 'Submit Transfer Request', 'wh4u-domains' ); ?></span>
							<span class="wh4u-domains__btn-spinner" aria-hidden="true"></span>
						</button>
					</div>
				</form>
			</div>
			<?php endif; ?>

			<div class="wh4u-domains__success" id="wh4u-public-success" role="alert" style="display:none;">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<h3></h3>
				<p></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Conditionally enqueue frontend assets if shortcode/block is detected.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'wh4u_domain_lookup' ) || has_block( 'wh4u/domain-lookup', $post ) ) {
			$this->enqueue_frontend_assets();
		}
	}

	/**
	 * Enqueue the frontend CSS and JS.
	 *
	 * Respects the CSS mode setting from Appearance: full, minimal, or none.
	 *
	 * @return void
	 */
	private function enqueue_frontend_assets() {
		if ( wp_script_is( 'wh4u-public-js', 'enqueued' ) ) {
			return;
		}

		$appearance = class_exists( 'WH4U_Admin_Appearance' )
			? WH4U_Admin_Appearance::get_settings()
			: array();
		$css_mode = isset( $appearance['css_mode'] ) ? $appearance['css_mode'] : 'full';

		if ( $css_mode === 'full' ) {
			wp_enqueue_style(
				'wh4u-public-css',
				WH4U_DOMAINS_PLUGIN_URL . 'public/css/wh4u-public.css',
				array(),
				WH4U_DOMAINS_VERSION
			);
		} elseif ( $css_mode === 'minimal' ) {
			wp_enqueue_style(
				'wh4u-public-css',
				WH4U_DOMAINS_PLUGIN_URL . 'public/css/wh4u-public-minimal.css',
				array(),
				WH4U_DOMAINS_VERSION
			);
		}

		wp_enqueue_script(
			'wh4u-public-js',
			WH4U_DOMAINS_PLUGIN_URL . 'public/js/wh4u-public.js',
			array(),
			WH4U_DOMAINS_VERSION,
			true
		);

		$success_title   = ! empty( $appearance['success_title'] ) ? $appearance['success_title'] : __( 'Request Submitted!', 'wh4u-domains' );
		$success_message = ! empty( $appearance['success_message'] ) ? $appearance['success_message'] : __( 'Your domain registration request has been submitted. We will contact you shortly.', 'wh4u-domains' );
		$search_another  = ! empty( $appearance['search_another_text'] ) ? $appearance['search_another_text'] : __( 'Search another domain', 'wh4u-domains' );
		$button_text     = ! empty( $appearance['button_text'] ) ? $appearance['button_text'] : __( 'Search', 'wh4u-domains' );

		$cart_redirect_enabled = class_exists( 'WH4U_Cart_Redirect' ) && WH4U_Cart_Redirect::is_configured();

		$placeholder_text = ! empty( $appearance['placeholder'] ) ? $appearance['placeholder'] : '';
		$search_placeholder = $placeholder_text ? $placeholder_text : __( 'Search for your perfect domain...', 'wh4u-domains' );

		wp_localize_script( 'wh4u-public-js', 'wh4uPublic', array(
			'restUrl'               => esc_url_raw( rest_url( 'wh4u/v1/' ) ),
			'nonce'                 => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'            => is_user_logged_in(),
			'adminUrl'              => is_user_logged_in() ? esc_url( admin_url( 'admin.php' ) ) : '',
			'cartRedirectEnabled'   => $cart_redirect_enabled,
			'customPlaceholder'     => ! empty( $placeholder_text ),
			'i18n'                  => array(
				'searching'         => __( 'Searching...', 'wh4u-domains' ),
				'available'         => __( 'Available', 'wh4u-domains' ),
				'unavailable'       => __( 'Taken', 'wh4u-domains' ),
				'register'          => __( 'Register', 'wh4u-domains' ),
				'error'             => __( 'An error occurred. Please try again.', 'wh4u-domains' ),
				'noResults'         => __( 'No results found for this search.', 'wh4u-domains' ),
				'buttonText'        => $button_text,
				'submitting'        => __( 'Submitting...', 'wh4u-domains' ),
				'successTitle'      => $success_title,
				'successMessage'    => $success_message,
				'searchAnother'     => $search_another,
				'invalidEmail'      => __( 'Please enter a valid email address.', 'wh4u-domains' ),
				'invalidPhone'      => __( 'Please enter a valid phone number.', 'wh4u-domains' ),
				'invalidCountry'    => __( 'Please enter a 2-letter country code (e.g. US, GR, DE).', 'wh4u-domains' ),
				'requiredFields'    => __( 'Please fill in all required fields.', 'wh4u-domains' ),
				'transfer'          => __( 'Transfer', 'wh4u-domains' ),
				'transferTitle'     => __( 'Transfer this domain', 'wh4u-domains' ),
				'transferDesc'      => __( 'Fill in your details and the EPP/Auth code to transfer this domain.', 'wh4u-domains' ),
				'transferSuccess'   => __( 'Your domain transfer request has been submitted. We will contact you shortly.', 'wh4u-domains' ),
				'bestMatch'         => __( 'Best match', 'wh4u-domains' ),
				'searchPlaceholder' => $search_placeholder,
			),
		) );
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @return void
	 */
	public function register_block() {
		$block_dir = WH4U_DOMAINS_PLUGIN_DIR . 'blocks/domain-lookup';

		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		$block_type = register_block_type( $block_dir, array(
			'render_callback' => array( $this, 'render_block' ),
		) );

		if ( $block_type && $block_type->editor_script_handles ) {
			$handle = $block_type->editor_script_handles[0];
			add_action( 'enqueue_block_editor_assets', function () use ( $handle ) {
				if ( ! wp_script_is( $handle, 'registered' ) ) {
					return;
				}
				$appearance   = class_exists( 'WH4U_Admin_Appearance' ) ? WH4U_Admin_Appearance::get_settings() : array();
				$theme_tokens = class_exists( 'WH4U_Theme_Compat' ) ? WH4U_Theme_Compat::get_tokens() : array();

				$accent = ! empty( $appearance['accent_color'] )
					? $appearance['accent_color']
					: ( ! empty( $theme_tokens['accent_color'] ) ? $theme_tokens['accent_color'] : '' );

				$radius = isset( $appearance['border_radius'] )
					? (string) $appearance['border_radius']
					: ( ! empty( $theme_tokens['border_radius'] ) ? (string) $theme_tokens['border_radius'] : '12' );

				wp_localize_script( $handle, 'wh4uBlockDefaults', array(
					'placeholder'     => ! empty( $appearance['placeholder'] ) ? $appearance['placeholder'] : __( 'Search for your perfect domain...', 'wh4u-domains' ),
					'buttonText'      => ! empty( $appearance['button_text'] ) ? $appearance['button_text'] : __( 'Search', 'wh4u-domains' ),
					'accentColor'     => $accent,
					'styleVariant'    => ! empty( $appearance['style_variant'] ) ? $appearance['style_variant'] : 'elevated',
					'showPricing'     => ! empty( $appearance['show_pricing'] ),
					'showSuggestions' => isset( $appearance['show_suggestions'] ) ? (bool) $appearance['show_suggestions'] : true,
					'formTitle'       => ! empty( $appearance['form_title'] ) ? $appearance['form_title'] : __( 'Register this domain', 'wh4u-domains' ),
					'formDescription' => ! empty( $appearance['form_description'] ) ? $appearance['form_description'] : __( 'Fill in your details below to secure this domain.', 'wh4u-domains' ),
					'borderRadius'    => $radius,
					'themeName'       => wp_get_theme()->get( 'Name' ),
				) );
			} );
		}
	}

	/**
	 * Server-side render callback for the Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_block( $attributes = array() ) {
		self::$shortcode_used = true;
		$this->enqueue_frontend_assets();

		$attr_map = array(
			'placeholder'      => 'placeholder',
			'buttonText'       => 'button_text',
			'accentColor'      => 'accent_color',
			'styleVariant'     => 'style_variant',
			'showPricing'      => 'show_pricing',
			'showSuggestions'  => 'show_suggestions',
			'formTitle'        => 'form_title',
			'formDescription'  => 'form_description',
			'borderRadius'     => 'border_radius',
		);

		$atts = array();
		foreach ( $attr_map as $block_key => $html_key ) {
			if ( ! isset( $attributes[ $block_key ] ) ) {
				continue;
			}
			$val = $attributes[ $block_key ];
			if ( is_bool( $val ) ) {
				$atts[ $html_key ] = $val ? 'true' : 'false';
			} else {
				$atts[ $html_key ] = sanitize_text_field( (string) $val );
			}
		}

		return self::get_lookup_html( $atts );
	}
}
