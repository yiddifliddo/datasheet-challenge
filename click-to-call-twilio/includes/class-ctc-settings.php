<?php
/**
 * Settings storage + admin screen.
 *
 * Everything lives in a single option array so the plugin stays self-contained
 * and easy to remove cleanly on uninstall.
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CTC_Settings {

	const GROUP = 'ctc_group';

	private static $instance = null;
	private static $cache    = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CTC_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Data                                                                   */
	/* --------------------------------------------------------------------- */

	public static function defaults() {
		return array(
			// Twilio connection.
			'account_sid'        => '',
			'auth_token'         => '',
			'twilio_number'      => '',
			'sales_number'       => '',
			'verify_signature'   => 1,

			// Messages.
			'sms_body'           => "You requested a call from {site}. Reply YES to confirm and we'll call you now. Reply STOP to cancel. Msg & data rates may apply.",
			'voice_intro'        => 'Please hold while we connect you to our sales team.',
			'rate_limit_ip'      => 5,

			// Button appearance (defaults, also used by the footer button).
			'btn_text'           => 'Click to Call',
			'btn_bg'             => '#0b5cff',
			'btn_color'          => '#ffffff',
			'btn_hover_bg'       => '#0949cc',
			'btn_hover_color'    => '#ffffff',
			'btn_font'           => 'Poppins',
			'btn_size'           => '16px',
			'btn_weight'         => '600',
			'btn_radius'         => '8px',
			'btn_padding_y'      => '12px',
			'btn_padding_x'      => '22px',
			'btn_icon'           => 1,
			'load_google_font'   => 1,
			'google_font_weights' => '400;500;600;700',

			// Footer button.
			'footer_enable'      => 0,
			'footer_style'       => 'float',   // float | bar
			'footer_position'    => 'right',   // left | right (float only)

			// Modal copy.
			'modal_title'        => 'Request a Call',
			'modal_intro'        => "Enter your US phone number and we'll text you to confirm. Reply YES and we'll call you straight away.",
			'consent_text'       => 'I agree to receive a one-time confirmation text. Msg & data rates may apply.',
			'submit_label'       => 'Text me to confirm',
			'success_message'    => "Great — we've texted {phone}. Reply YES to that message and we'll call you right away.",
		);
	}

	/** Get merged settings (defaults + saved), cached for the request. */
	public static function get() {
		if ( null === self::$cache ) {
			$saved = get_option( CTC_OPTION_KEY, array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$cache = array_merge( self::defaults(), $saved );
		}
		return self::$cache;
	}

	/* --------------------------------------------------------------------- */
	/* Registration                                                           */
	/* --------------------------------------------------------------------- */

	public function register() {
		register_setting(
			self::GROUP,
			CTC_OPTION_KEY,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function add_menu() {
		add_options_page(
			__( 'Click to Call', 'click-to-call-twilio' ),
			__( 'Click to Call', 'click-to-call-twilio' ),
			'manage_options',
			'click-to-call-twilio',
			array( $this, 'render_page' )
		);
	}

	public function action_links( $links ) {
		$url  = admin_url( 'options-general.php?page=click-to-call-twilio' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'click-to-call-twilio' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Sanitise the whole option array. Unknown keys are dropped; missing checkboxes
	 * become 0; existing values are preserved for any field not on the form.
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$existing = get_option( CTC_OPTION_KEY, array() );
		$existing = is_array( $existing ) ? $existing : array();
		$out      = array_merge( $defaults, $existing );

		$text_keys = array(
			'account_sid', 'auth_token', 'btn_text', 'btn_bg', 'btn_color',
			'btn_hover_bg', 'btn_hover_color', 'btn_font', 'btn_size', 'btn_weight',
			'btn_radius', 'btn_padding_y', 'btn_padding_x', 'google_font_weights',
			'modal_title', 'submit_label',
		);
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		$textarea_keys = array( 'sms_body', 'voice_intro', 'modal_intro', 'consent_text', 'success_message' );
		foreach ( $textarea_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_textarea_field( $input[ $key ] );
			}
		}

		// Phone numbers → E.164 where possible.
		foreach ( array( 'twilio_number', 'sales_number' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = $this->normalize_phone_setting( $input[ $key ] );
			}
		}

		// Selects.
		if ( isset( $input['footer_style'] ) ) {
			$out['footer_style'] = in_array( $input['footer_style'], array( 'float', 'bar' ), true ) ? $input['footer_style'] : 'float';
		}
		if ( isset( $input['footer_position'] ) ) {
			$out['footer_position'] = in_array( $input['footer_position'], array( 'left', 'right' ), true ) ? $input['footer_position'] : 'right';
		}

		// Integer.
		$out['rate_limit_ip'] = isset( $input['rate_limit_ip'] ) ? max( 1, min( 100, (int) $input['rate_limit_ip'] ) ) : $out['rate_limit_ip'];

		// Checkboxes (absent = off). These only reset when their form section is submitted;
		// all live on the single settings form, so treating absence as 0 is correct here.
		$checkboxes = array( 'verify_signature', 'btn_icon', 'load_google_font', 'footer_enable' );
		foreach ( $checkboxes as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		return $out;
	}

	private function normalize_phone_setting( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$us = CTC_Phone::normalize_us( $raw );
		if ( ! is_wp_error( $us ) ) {
			return $us;
		}
		// Allow non-US E.164 (e.g. an international sales line) — keep + and digits.
		$cleaned = preg_replace( '/[^\d+]/', '', $raw );
		if ( '' !== $cleaned && '+' !== $cleaned[0] ) {
			$cleaned = '+' . $cleaned;
		}
		return $cleaned;
	}

	/* --------------------------------------------------------------------- */
	/* Admin page                                                             */
	/* --------------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s           = self::get();
		$sms_hook    = esc_url( rest_url( CTC_REST_NAMESPACE . '/sms' ) );
		$configured  = CTC_Twilio::is_configured();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Click to Call (Twilio)', 'click-to-call-twilio' ); ?></h1>

			<div class="notice <?php echo $configured ? 'notice-success' : 'notice-warning'; ?> inline" style="margin:16px 0;padding:10px 14px;">
				<?php if ( $configured ) : ?>
					<strong><?php esc_html_e( 'Connected.', 'click-to-call-twilio' ); ?></strong>
					<?php esc_html_e( 'Your Twilio credentials are saved. Make sure the SMS webhook below is set in your Twilio number settings.', 'click-to-call-twilio' ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Not connected yet.', 'click-to-call-twilio' ); ?></strong>
					<?php esc_html_e( 'Add your Twilio Account SID, Auth Token and phone number below to switch the button on.', 'click-to-call-twilio' ); ?>
				<?php endif; ?>
			</div>

			<div class="notice notice-info inline" style="margin:16px 0;padding:10px 14px;">
				<p style="margin:.2em 0;">
					<strong><?php esc_html_e( 'Twilio SMS webhook — paste this into your Twilio phone number:', 'click-to-call-twilio' ); ?></strong>
				</p>
				<p style="margin:.2em 0;">
					<code style="user-select:all;"><?php echo $sms_hook; // phpcs:ignore WordPress.Security.EscapeOutput ?></code>
				</p>
				<p style="margin:.2em 0;color:#555;">
					<?php esc_html_e( 'Twilio Console → Phone Numbers → your number → Messaging → "A message comes in" → Webhook (HTTP POST). The voice call is created automatically, so no voice webhook setup is required.', 'click-to-call-twilio' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Twilio connection', 'click-to-call-twilio' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'account_sid', __( 'Account SID', 'click-to-call-twilio' ), $s['account_sid'], 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
					$this->password_row( 'auth_token', __( 'Auth Token', 'click-to-call-twilio' ), $s['auth_token'] );
					$this->text_row( 'twilio_number', __( 'Your Twilio phone number', 'click-to-call-twilio' ), $s['twilio_number'], '+1XXXXXXXXXX', __( 'The number that sends the SMS and places the call.', 'click-to-call-twilio' ) );
					$this->text_row( 'sales_number', __( 'Sales team phone number', 'click-to-call-twilio' ), $s['sales_number'], '+1XXXXXXXXXX', __( 'Confirmed calls are connected to this number.', 'click-to-call-twilio' ) );
					$this->checkbox_row( 'verify_signature', __( 'Verify Twilio signatures', 'click-to-call-twilio' ), $s['verify_signature'], __( 'Recommended. Rejects webhook requests that are not signed by Twilio. Turn off only if your host sits behind a proxy that rewrites the URL.', 'click-to-call-twilio' ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Messages', 'click-to-call-twilio' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->textarea_row( 'sms_body', __( 'Confirmation SMS', 'click-to-call-twilio' ), $s['sms_body'], __( 'Use {site} for your site name. Keep the word YES so people know how to confirm.', 'click-to-call-twilio' ) );
					$this->textarea_row( 'voice_intro', __( 'Spoken greeting before connecting', 'click-to-call-twilio' ), $s['voice_intro'], __( 'Read aloud to the caller right before they are bridged to sales.', 'click-to-call-twilio' ) );
					$this->number_row( 'rate_limit_ip', __( 'Max requests per visitor / hour', 'click-to-call-twilio' ), $s['rate_limit_ip'], __( 'Protects against SMS abuse. 5 is a sensible default.', 'click-to-call-twilio' ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Button appearance', 'click-to-call-twilio' ); ?></h2>
				<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'These are the defaults. Any shortcode attribute overrides them for that button.', 'click-to-call-twilio' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'btn_text', __( 'Button text', 'click-to-call-twilio' ), $s['btn_text'] );
					$this->color_row( 'btn_bg', __( 'Background colour', 'click-to-call-twilio' ), $s['btn_bg'] );
					$this->color_row( 'btn_color', __( 'Text colour', 'click-to-call-twilio' ), $s['btn_color'] );
					$this->color_row( 'btn_hover_bg', __( 'Background colour (hover)', 'click-to-call-twilio' ), $s['btn_hover_bg'] );
					$this->color_row( 'btn_hover_color', __( 'Text colour (hover)', 'click-to-call-twilio' ), $s['btn_hover_color'] );
					$this->text_row( 'btn_font', __( 'Font family', 'click-to-call-twilio' ), $s['btn_font'], 'Poppins', __( 'e.g. Poppins, Roboto, Montserrat.', 'click-to-call-twilio' ) );
					$this->checkbox_row( 'load_google_font', __( 'Load this font from Google Fonts', 'click-to-call-twilio' ), $s['load_google_font'], __( 'Automatically loads the font above (e.g. Poppins) so it renders even if the theme does not include it.', 'click-to-call-twilio' ) );
					$this->text_row( 'google_font_weights', __( 'Google Font weights', 'click-to-call-twilio' ), $s['google_font_weights'], '400;500;600;700' );
					$this->text_row( 'btn_size', __( 'Font size', 'click-to-call-twilio' ), $s['btn_size'], '16px' );
					$this->text_row( 'btn_weight', __( 'Font weight', 'click-to-call-twilio' ), $s['btn_weight'], '600' );
					$this->text_row( 'btn_radius', __( 'Corner radius', 'click-to-call-twilio' ), $s['btn_radius'], '8px' );
					$this->text_row( 'btn_padding_y', __( 'Vertical padding', 'click-to-call-twilio' ), $s['btn_padding_y'], '12px' );
					$this->text_row( 'btn_padding_x', __( 'Horizontal padding', 'click-to-call-twilio' ), $s['btn_padding_x'], '22px' );
					$this->checkbox_row( 'btn_icon', __( 'Show phone icon', 'click-to-call-twilio' ), $s['btn_icon'] );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Footer button (all pages)', 'click-to-call-twilio' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( 'footer_enable', __( 'Show a button in the footer of every page', 'click-to-call-twilio' ), $s['footer_enable'] );
					$this->select_row( 'footer_style', __( 'Footer style', 'click-to-call-twilio' ), $s['footer_style'], array(
						'float' => __( 'Floating button (bottom corner)', 'click-to-call-twilio' ),
						'bar'   => __( 'Centred button at the bottom of the page', 'click-to-call-twilio' ),
					) );
					$this->select_row( 'footer_position', __( 'Floating position', 'click-to-call-twilio' ), $s['footer_position'], array(
						'right' => __( 'Bottom right', 'click-to-call-twilio' ),
						'left'  => __( 'Bottom left', 'click-to-call-twilio' ),
					) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Pop-up text', 'click-to-call-twilio' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'modal_title', __( 'Pop-up title', 'click-to-call-twilio' ), $s['modal_title'] );
					$this->textarea_row( 'modal_intro', __( 'Pop-up intro', 'click-to-call-twilio' ), $s['modal_intro'] );
					$this->textarea_row( 'consent_text', __( 'Consent checkbox text', 'click-to-call-twilio' ), $s['consent_text'] );
					$this->text_row( 'submit_label', __( 'Submit button text', 'click-to-call-twilio' ), $s['submit_label'] );
					$this->textarea_row( 'success_message', __( 'Success message', 'click-to-call-twilio' ), $s['success_message'], __( 'Shown after the text is sent. Use {phone} for their number.', 'click-to-call-twilio' ) );
					?>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2 class="title"><?php esc_html_e( 'How to place the button', 'click-to-call-twilio' ); ?></h2>
			<p><?php esc_html_e( 'Use this shortcode anywhere — pages, posts, widgets, or theme templates:', 'click-to-call-twilio' ); ?></p>
			<p><code style="user-select:all;">[click_to_call]</code></p>
			<p><?php esc_html_e( 'Override the look per button, for example:', 'click-to-call-twilio' ); ?></p>
			<p><code style="user-select:all;">[click_to_call text="Speak to Sales" bg="#e63946" color="#ffffff" font="Poppins" size="18px" radius="30px" pad_x="30px"]</code></p>
			<p><?php esc_html_e( 'In a PHP template you can also use:', 'click-to-call-twilio' ); ?></p>
			<p><code style="user-select:all;">&lt;?php echo do_shortcode('[click_to_call]'); ?&gt;</code></p>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- */
	/* Field renderers                                                        */
	/* --------------------------------------------------------------------- */

	private function name( $key ) {
		return CTC_OPTION_KEY . '[' . $key . ']';
	}

	private function text_row( $key, $label, $value, $placeholder = '', $help = '' ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="ctc-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $this->name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function password_row( $key, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="password" class="regular-text" id="ctc-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $this->name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>" autocomplete="off">
				<p class="description"><?php esc_html_e( 'Stored in your WordPress database. Find it on your Twilio Console dashboard.', 'click-to-call-twilio' ); ?></p>
			</td>
		</tr>
		<?php
	}

	private function number_row( $key, $label, $value, $help = '' ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" min="1" max="100" id="ctc-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $this->name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>">
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function textarea_row( $key, $label, $value, $help = '' ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea class="large-text" rows="3" id="ctc-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $this->name( $key ) ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function color_row( $key, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="ctc-<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $this->name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>" placeholder="#0b5cff" style="max-width:160px;">
				<input type="color" value="<?php echo esc_attr( $this->hex_or( $value, '#0b5cff' ) ); ?>"
					oninput="var t=document.getElementById('ctc-<?php echo esc_js( $key ); ?>'); if(t) t.value=this.value;"
					style="vertical-align:middle;width:40px;height:32px;padding:0;border:none;background:none;cursor:pointer;">
			</td>
		</tr>
		<?php
	}

	private function checkbox_row( $key, $label, $value, $help = '' ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" value="1" id="ctc-<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $this->name( $key ) ); ?>" <?php checked( $value, 1 ); ?>>
					<?php esc_html_e( 'Enabled', 'click-to-call-twilio' ); ?>
				</label>
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function select_row( $key, $label, $value, $options ) {
		?>
		<tr>
			<th scope="row"><label for="ctc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="ctc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>">
					<?php foreach ( $options as $opt_val => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>>
							<?php echo esc_html( $opt_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	private function hex_or( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ? $value : $fallback;
	}
}
