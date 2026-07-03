<?php
/**
 * Front-end rendering: the [click_to_call] shortcode, optional footer button,
 * asset loading and full styling control (size, font, colour, background, Google Fonts).
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CTC_Shortcode {

	private static $instance = null;

	/** Google Font families already printed on this page (dedupe). */
	private $printed_fonts = array();

	/** Whether the shared modal + config have been enqueued yet. */
	private $assets_done = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'click_to_call', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_footer_button' ), 20 );
	}

	/* --------------------------------------------------------------------- */
	/* Assets                                                                 */
	/* --------------------------------------------------------------------- */

	public function register_assets() {
		wp_register_style( 'ctc', CTC_PLUGIN_URL . 'assets/ctc.css', array(), CTC_VERSION );
		wp_register_script( 'ctc', CTC_PLUGIN_URL . 'assets/ctc.js', array(), CTC_VERSION, true );

		// If the footer button is on it renders during wp_footer, which is too late to
		// enqueue reliably — so enqueue now for that case. Shortcodes enqueue on render.
		$s = CTC_Settings::get();
		if ( ! empty( $s['footer_enable'] ) ) {
			$this->ensure_assets();
		}
	}

	/** Enqueue the stylesheet, script and localized config once per page. */
	private function ensure_assets() {
		if ( $this->assets_done ) {
			return;
		}
		$this->assets_done = true;

		wp_enqueue_style( 'ctc' );
		wp_enqueue_script( 'ctc' );

		$s = CTC_Settings::get();

		wp_localize_script(
			'ctc',
			'CTC_DATA',
			array(
				'endpoint' => esc_url_raw( rest_url( CTC_REST_NAMESPACE . '/request' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'text'     => array(
					'title'        => $s['modal_title'],
					'intro'        => $s['modal_intro'],
					'phoneLabel'   => __( 'US phone number', 'click-to-call-twilio' ),
					'placeholder'  => __( '(555) 123-4567', 'click-to-call-twilio' ),
					'consent'      => $s['consent_text'],
					'submit'       => $s['submit_label'],
					'sending'      => __( 'Sending…', 'click-to-call-twilio' ),
					'close'        => __( 'Close', 'click-to-call-twilio' ),
					'invalidPhone' => __( 'Please enter a valid 10-digit US phone number.', 'click-to-call-twilio' ),
					'consentReq'   => __( 'Please tick the box to agree to receive a text message.', 'click-to-call-twilio' ),
					'genericErr'   => __( 'Something went wrong. Please try again.', 'click-to-call-twilio' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Shortcode                                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * [click_to_call text="Call Us" bg="#0b5cff" color="#fff" font="Poppins"
	 *   size="18px" weight="600" radius="10px" pad_y="14px" pad_x="26px"
	 *   hover_bg="#0949cc" hover_color="#fff" width="auto" align="left"
	 *   icon="1" gfont="1" class="my-extra-class"]
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'text'        => '',
				'bg'          => '',
				'color'       => '',
				'font'        => '',
				'size'        => '',
				'weight'      => '',
				'radius'      => '',
				'pad_y'       => '',
				'pad_x'       => '',
				'hover_bg'    => '',
				'hover_color' => '',
				'width'       => 'auto',
				'align'       => 'left',
				'icon'        => '',
				'gfont'       => '',
				'class'       => '',
			),
			$atts,
			'click_to_call'
		);

		return $this->render_button( $atts, 'ctc-wrap--inline' );
	}

	/* --------------------------------------------------------------------- */
	/* Footer button (all pages)                                              */
	/* --------------------------------------------------------------------- */

	public function render_footer_button() {
		if ( is_admin() ) {
			return;
		}
		$s = CTC_Settings::get();
		if ( empty( $s['footer_enable'] ) ) {
			return;
		}

		$is_float   = ( 'float' === $s['footer_style'] );
		$wrap_class = $is_float ? 'ctc-wrap--float' : 'ctc-wrap--bar';
		if ( $is_float ) {
			$wrap_class .= ( 'left' === $s['footer_position'] ) ? ' ctc-wrap--float-left' : ' ctc-wrap--float-right';
		}

		$atts = array(
			'align' => $is_float ? 'left' : 'center',
			'width' => 'auto',
		);

		// Buttons use the saved defaults; nothing to override here.
		echo $this->render_button( $atts, $wrap_class ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* --------------------------------------------------------------------- */
	/* Rendering + styling                                                    */
	/* --------------------------------------------------------------------- */

	/**
	 * Build the button markup from attributes, falling back to saved defaults.
	 *
	 * @param array  $atts       Raw attributes (unsanitised).
	 * @param string $wrap_class Wrapper modifier class.
	 * @return string HTML.
	 */
	private function render_button( $atts, $wrap_class ) {
		$this->ensure_assets();
		$s = CTC_Settings::get();

		$text     = ( isset( $atts['text'] ) && '' !== $atts['text'] ) ? $atts['text'] : $s['btn_text'];
		$bg       = $this->css_color( $this->pick( $atts, 'bg' ), $s['btn_bg'] );
		$color    = $this->css_color( $this->pick( $atts, 'color' ), $s['btn_color'] );
		$hover_bg = $this->css_color( $this->pick( $atts, 'hover_bg' ), $s['btn_hover_bg'] );
		$hover_co = $this->css_color( $this->pick( $atts, 'hover_color' ), $s['btn_hover_color'] );
		$size     = $this->css_length( $this->pick( $atts, 'size' ), $s['btn_size'] );
		$weight   = $this->css_weight( $this->pick( $atts, 'weight' ), $s['btn_weight'] );
		$radius   = $this->css_length( $this->pick( $atts, 'radius' ), $s['btn_radius'] );
		$pad_y    = $this->css_length( $this->pick( $atts, 'pad_y' ), $s['btn_padding_y'] );
		$pad_x    = $this->css_length( $this->pick( $atts, 'pad_x' ), $s['btn_padding_x'] );

		$font_family = $this->pick( $atts, 'font' );
		$font_family = ( '' !== $font_family ) ? $font_family : $s['btn_font'];
		$font_stack  = $this->font_stack( $font_family );

		// Decide whether to load this font from Google Fonts.
		$want_gfont = ( '' !== $this->pick( $atts, 'gfont' ) )
			? $this->truthy( $atts['gfont'] )
			: ! empty( $s['load_google_font'] );

		$show_icon = ( '' !== $this->pick( $atts, 'icon' ) )
			? $this->truthy( $atts['icon'] )
			: ! empty( $s['btn_icon'] );

		$align = in_array( $this->pick( $atts, 'align' ), array( 'left', 'center', 'right' ), true )
			? $atts['align']
			: 'left';

		$full = ( 'full' === $this->pick( $atts, 'width' ) || '100%' === $this->pick( $atts, 'width' ) );

		$extra_class = isset( $atts['class'] ) ? $this->sanitize_class_list( $atts['class'] ) : '';

		$style = sprintf(
			'--ctc-bg:%s;--ctc-color:%s;--ctc-hover-bg:%s;--ctc-hover-color:%s;--ctc-size:%s;--ctc-weight:%s;--ctc-radius:%s;--ctc-pad-y:%s;--ctc-pad-x:%s;--ctc-font:%s;',
			$bg,
			$color,
			$hover_bg,
			$hover_co,
			$size,
			$weight,
			$radius,
			$pad_y,
			$pad_x,
			$font_stack
		);

		$btn_classes = 'ctc-button';
		if ( $full ) {
			$btn_classes .= ' ctc-button--full';
		}
		if ( $extra_class ) {
			$btn_classes .= ' ' . $extra_class;
		}

		$icon_html = $show_icon ? $this->icon_svg() : '';

		$font_link = $want_gfont ? $this->google_font_link( $font_family, $s['google_font_weights'] ) : '';

		$html  = $font_link;
		$html .= '<div class="ctc-wrap ' . esc_attr( $wrap_class ) . '" style="text-align:' . esc_attr( $align ) . ';">';
		$html .= '<button type="button" class="' . esc_attr( $btn_classes ) . '" style="' . esc_attr( $style ) . '" data-ctc-open="1">';
		$html .= $icon_html;
		$html .= '<span class="ctc-button__label">' . esc_html( $text ) . '</span>';
		$html .= '</button>';
		$html .= '</div>';

		return $html;
	}

	/** Return the raw attribute value or '' if unset/blank. */
	private function pick( $atts, $key ) {
		return ( isset( $atts[ $key ] ) && '' !== $atts[ $key ] ) ? trim( (string) $atts[ $key ] ) : '';
	}

	private function truthy( $val ) {
		return in_array( strtolower( (string) $val ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Whitelist a CSS colour: hex, rgb/rgba, hsl/hsla, or a plain named colour.
	 * Anything else falls back to the provided default.
	 */
	private function css_color( $value, $default ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $default;
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[a-zA-Z]{3,20}$/', $value ) ) {
			return strtolower( $value );
		}
		return $default;
	}

	/**
	 * Whitelist a CSS length: number + optional unit (px, em, rem, %, vw, vh, pt).
	 */
	private function css_length( $value, $default ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $default;
		}
		if ( preg_match( '/^[0-9]*\.?[0-9]+(px|em|rem|%|vw|vh|pt)?$/', $value ) ) {
			// Bare numbers get px.
			return preg_match( '/[a-z%]$/i', $value ) ? $value : $value . 'px';
		}
		return $default;
	}

	private function css_weight( $value, $default ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return $default;
		}
		if ( preg_match( '/^(100|200|300|400|500|600|700|800|900|normal|bold|bolder|lighter)$/', $value ) ) {
			return $value;
		}
		return $default;
	}

	/**
	 * Build a safe font-family stack from a family name, appending sensible fallbacks.
	 */
	private function font_stack( $family ) {
		$family = trim( (string) $family );
		// Strip anything that isn't a letter, number, space or hyphen.
		$family = preg_replace( '/[^A-Za-z0-9 \-]/', '', $family );
		if ( '' === $family ) {
			return "system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
		}
		return "'" . $family . "', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
	}

	/**
	 * Print (once) the Google Fonts <link> for a family. Returns '' if already printed.
	 */
	private function google_font_link( $family, $weights ) {
		$family = trim( preg_replace( '/[^A-Za-z0-9 \-]/', '', (string) $family ) );
		if ( '' === $family ) {
			return '';
		}
		$key = strtolower( $family );
		if ( isset( $this->printed_fonts[ $key ] ) ) {
			return '';
		}
		$this->printed_fonts[ $key ] = true;

		$weights = preg_replace( '/[^0-9,;]/', '', (string) $weights );
		$weights = '' !== $weights ? str_replace( ',', ';', $weights ) : '400;500;600;700';

		$fam_url = str_replace( ' ', '+', $family );
		$href    = 'https://fonts.googleapis.com/css2?family=' . $fam_url . ':wght@' . $weights . '&display=swap';

		return '<link rel="preconnect" href="https://fonts.googleapis.com">'
			. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
			. '<link rel="stylesheet" href="' . esc_attr( $href ) . '">';
	}

	private function icon_svg() {
		return '<svg class="ctc-button__icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false">'
			. '<path fill="currentColor" d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.4 0 .8-.2 1l-2.3 2.2z"/>'
			. '</svg>';
	}

	/** Sanitise a space-separated list of CSS classes (kept internal — no global footprint). */
	private function sanitize_class_list( $classes ) {
		$out = array();
		foreach ( preg_split( '/\s+/', (string) $classes ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( $class ) {
				$out[] = $class;
			}
		}
		return implode( ' ', $out );
	}
}
