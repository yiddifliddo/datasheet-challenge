<?php
/**
 * Twilio "Text me this datasheet" feature.
 *
 * Lets a visitor on a single product page enter their mobile number and
 * receive an SMS containing a link (or links) to that product's datasheet
 * documents — the same files listed in the "View / Print Product
 * Information" dropdown. This mirrors the email-the-datasheet flow, but
 * over text.
 *
 * Security model: the browser only ever submits the product post ID and a
 * phone number. The datasheet URLs are looked up server-side from the
 * product's ACF fields, so the endpoint can never be abused to text
 * arbitrary links to arbitrary numbers.
 *
 * To enable, require this file from the active theme's functions.php:
 *
 *     require_once get_stylesheet_directory() . '/inc/twilio-datasheet-sms.php';
 *
 * Then set your Account SID, Auth Token and "From" number under
 * Settings → Datasheet SMS in wp-admin.
 *
 * @package abs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ABS_Datasheet_SMS' ) ) {

	class ABS_Datasheet_SMS {

		/** Option key holding the Twilio settings. */
		const OPTION = 'abs_datasheet_sms_settings';

		/** Nonce action used to protect the AJAX endpoint. */
		const NONCE = 'abs_datasheet_sms';

		/** AJAX action name. */
		const ACTION = 'abs_send_datasheet_sms';

		/** Max sends allowed per IP per hour. */
		const RATE_LIMIT = 5;

		/** @var ABS_Datasheet_SMS|null */
		private static $instance = null;

		/** Boot the module once and return the shared instance. */
		public static function init() {
			if ( null !== self::$instance ) {
				return self::$instance;
			}

			$self           = new self();
			self::$instance = $self;

			// Admin settings page.
			add_action( 'admin_menu', array( $self, 'register_settings_page' ) );
			add_action( 'admin_init', array( $self, 'register_settings' ) );

			// AJAX endpoint (logged-in and logged-out visitors).
			add_action( 'wp_ajax_' . self::ACTION, array( $self, 'handle_request' ) );
			add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $self, 'handle_request' ) );

			return $self;
		}

		/* --------------------------------------------------------------------
		 * Settings
		 * ------------------------------------------------------------------ */

		/** @return array{sid:string,token:string,from:string,country_code:string} */
		public function get_settings() {
			$defaults = array(
				'sid'          => '',
				'token'        => '',
				'from'         => '',
				'country_code' => '44', // Default dialling code for local numbers (UK).
			);

			return wp_parse_args( get_option( self::OPTION, array() ), $defaults );
		}

		/** True only when all credentials needed to send are present. */
		public function is_configured() {
			$s = $this->get_settings();

			return ( '' !== $s['sid'] && '' !== $s['token'] && '' !== $s['from'] );
		}

		public function register_settings_page() {
			add_options_page(
				__( 'Datasheet SMS', 'abs' ),
				__( 'Datasheet SMS', 'abs' ),
				'manage_options',
				'abs-datasheet-sms',
				array( $this, 'render_settings_page' )
			);
		}

		public function register_settings() {
			register_setting(
				'abs_datasheet_sms',
				self::OPTION,
				array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
			);
		}

		/** @param mixed $input */
		public function sanitize_settings( $input ) {
			$input = is_array( $input ) ? $input : array();

			return array(
				'sid'          => sanitize_text_field( $input['sid'] ?? '' ),
				'token'        => sanitize_text_field( $input['token'] ?? '' ),
				'from'         => sanitize_text_field( $input['from'] ?? '' ),
				'country_code' => preg_replace( '/\D/', '', (string) ( $input['country_code'] ?? '' ) ),
			);
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$s = $this->get_settings();
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Datasheet SMS (Twilio)', 'abs' ); ?></h1>
				<p><?php esc_html_e( 'Credentials are available from your Twilio Console. The "From" number must be an SMS-enabled Twilio number in E.164 format (e.g. +447700900123).', 'abs' ); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields( 'abs_datasheet_sms' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="abs-sms-sid"><?php esc_html_e( 'Account SID', 'abs' ); ?></label></th>
							<td><input name="<?php echo esc_attr( self::OPTION ); ?>[sid]" id="abs-sms-sid" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $s['sid'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="abs-sms-token"><?php esc_html_e( 'Auth Token', 'abs' ); ?></label></th>
							<td>
								<input name="<?php echo esc_attr( self::OPTION ); ?>[token]" id="abs-sms-token" type="password" class="regular-text" autocomplete="new-password" value="<?php echo esc_attr( $s['token'] ); ?>">
								<p class="description"><?php esc_html_e( 'Stored in the database. For higher security, consider a Twilio API Key instead of your primary Auth Token.', 'abs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="abs-sms-from"><?php esc_html_e( 'From number', 'abs' ); ?></label></th>
							<td><input name="<?php echo esc_attr( self::OPTION ); ?>[from]" id="abs-sms-from" type="text" class="regular-text" placeholder="+447700900123" value="<?php echo esc_attr( $s['from'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="abs-sms-cc"><?php esc_html_e( 'Default country code', 'abs' ); ?></label></th>
							<td>
								<input name="<?php echo esc_attr( self::OPTION ); ?>[country_code]" id="abs-sms-cc" type="text" class="small-text" value="<?php echo esc_attr( $s['country_code'] ); ?>">
								<p class="description"><?php esc_html_e( 'Digits only (e.g. 44 for the UK, 1 for the US). Applied when a visitor enters a local number without a country code.', 'abs' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}

		/* --------------------------------------------------------------------
		 * Front-end form
		 * ------------------------------------------------------------------ */

		/**
		 * Echo the "Text me this datasheet" form for the given product.
		 *
		 * Renders nothing unless Twilio is configured and the product has at
		 * least one datasheet document attached.
		 *
		 * @param int|null $post_id Product post ID. Defaults to current post.
		 */
		public function render_form( $post_id = null ) {
			$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

			if ( ! $post_id || ! $this->is_configured() ) {
				return;
			}

			if ( ! $this->get_documents( $post_id ) ) {
				return; // No datasheet to send.
			}

			$this->enqueue_assets();
			?>
			<div class="datasheet-sms" data-product-id="<?php echo esc_attr( $post_id ); ?>">
				<button type="button" class="button datasheet-sms__toggle js-datasheet-sms-toggle" aria-expanded="false">
					<?php esc_html_e( 'Text me this datasheet', 'abs' ); ?>
				</button>

				<form class="datasheet-sms__form js-datasheet-sms-form" style="display:none;" novalidate>
					<label class="datasheet-sms__label" for="datasheet-sms-phone-<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Enter your mobile number and we will text you a link to this datasheet.', 'abs' ); ?>
					</label>
					<div class="datasheet-sms__row">
						<input
							type="tel"
							id="datasheet-sms-phone-<?php echo esc_attr( $post_id ); ?>"
							class="datasheet-sms__input js-datasheet-sms-phone"
							name="phone"
							inputmode="tel"
							autocomplete="tel"
							placeholder="<?php esc_attr_e( '07700 900123', 'abs' ); ?>"
							required>
						<button type="submit" class="button primary datasheet-sms__submit js-datasheet-sms-submit">
							<?php esc_html_e( 'Send', 'abs' ); ?>
						</button>
					</div>

					<?php // Honeypot field — bots fill it in, humans never see it. ?>
					<div class="datasheet-sms__hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
						<label><?php esc_html_e( 'Leave this field empty', 'abs' ); ?>
							<input type="text" name="company" tabindex="-1" autocomplete="off">
						</label>
					</div>

					<p class="datasheet-sms__message js-datasheet-sms-message" role="status" aria-live="polite" style="display:none;"></p>
				</form>
			</div>
			<?php
		}

		/** Register and localise the front-end script. Safe to call repeatedly. */
		private function enqueue_assets() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			$rel  = '/assets/js/datasheet-sms.js';
			$path = get_stylesheet_directory() . $rel;
			$ver  = file_exists( $path ) ? filemtime( $path ) : false;

			wp_enqueue_script(
				'abs-datasheet-sms',
				get_stylesheet_directory_uri() . $rel,
				array(),
				$ver,
				true
			);

			wp_localize_script(
				'abs-datasheet-sms',
				'absDatasheetSMS',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::ACTION,
					'nonce'   => wp_create_nonce( self::NONCE ),
					'i18n'    => array(
						'sending'    => __( 'Sending…', 'abs' ),
						'send'       => __( 'Send', 'abs' ),
						'invalid'    => __( 'Please enter a valid mobile number.', 'abs' ),
						'genericErr' => __( 'Sorry, something went wrong. Please try again.', 'abs' ),
					),
				)
			);
		}

		/* --------------------------------------------------------------------
		 * AJAX handler
		 * ------------------------------------------------------------------ */

		public function handle_request() {
			check_ajax_referer( self::NONCE, 'nonce' );

			if ( ! $this->is_configured() ) {
				wp_send_json_error( array( 'message' => __( 'SMS sending is not configured.', 'abs' ) ), 503 );
			}

			// Honeypot: silently accept but do nothing for bots.
			if ( ! empty( $_POST['company'] ) ) {
				wp_send_json_success( array( 'message' => __( 'Sent! Check your phone for the datasheet link.', 'abs' ) ) );
			}

			$post_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$raw_phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';

			if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Product not found.', 'abs' ) ), 404 );
			}

			$to = $this->normalize_phone( $raw_phone );
			if ( ! $to ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid mobile number.', 'abs' ) ), 400 );
			}

			if ( $this->is_rate_limited() ) {
				wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'abs' ) ), 429 );
			}

			$documents = $this->get_documents( $post_id );
			if ( ! $documents ) {
				wp_send_json_error( array( 'message' => __( 'No datasheet is available for this product.', 'abs' ) ), 404 );
			}

			$body   = $this->build_message( $post_id, $documents );
			$result = $this->send_sms( $to, $body );

			if ( is_wp_error( $result ) ) {
				// Log the detail for admins; show a friendly message to visitors.
				error_log( '[abs-datasheet-sms] ' . $result->get_error_message() );
				wp_send_json_error( array( 'message' => __( 'Sorry, we could not send the text right now. Please try again later.', 'abs' ) ), 502 );
			}

			$this->bump_rate_limit();

			wp_send_json_success( array( 'message' => __( 'Sent! Check your phone for the datasheet link.', 'abs' ) ) );
		}

		/* --------------------------------------------------------------------
		 * Helpers
		 * ------------------------------------------------------------------ */

		/**
		 * Fetch the product's datasheet documents.
		 *
		 * @param int $post_id
		 * @return array<int,array{label:string,url:string}>
		 */
		private function get_documents( $post_id ) {
			$out = array();

			if ( ! function_exists( 'get_field' ) ) {
				return $out;
			}

			$rows = get_field( 'related_documents_or_other_files', $post_id );
			if ( ! is_array( $rows ) ) {
				return $out;
			}

			foreach ( $rows as $row ) {
				$file = $row['file'] ?? null;
				$url  = '';

				if ( is_array( $file ) && ! empty( $file['url'] ) ) {
					$url = $file['url'];
				} elseif ( is_numeric( $file ) ) {
					$url = (string) wp_get_attachment_url( (int) $file );
				} elseif ( is_string( $file ) ) {
					$url = $file;
				}

				if ( $url ) {
					$out[] = array(
						'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
						'url'   => esc_url_raw( $url ),
					);
				}
			}

			return $out;
		}

		/**
		 * Build the SMS body for the product's datasheet(s).
		 *
		 * @param int                                            $post_id
		 * @param array<int,array{label:string,url:string}>      $documents
		 */
		private function build_message( $post_id, $documents ) {
			$title = '';
			if ( function_exists( 'get_field' ) ) {
				$title = (string) get_field( 'product_title', $post_id );
			}
			if ( '' === $title ) {
				$title = get_the_title( $post_id );
			}

			$site = get_bloginfo( 'name' );

			if ( 1 === count( $documents ) ) {
				$lines   = array();
				$lines[] = sprintf(
					/* translators: 1: product title, 2: site name */
					__( 'Datasheet for %1$s from %2$s:', 'abs' ),
					$title,
					$site
				);
				$lines[] = $documents[0]['url'];
			} else {
				$lines   = array();
				$lines[] = sprintf(
					/* translators: 1: product title, 2: site name */
					__( 'Product information for %1$s from %2$s:', 'abs' ),
					$title,
					$site
				);
				foreach ( $documents as $doc ) {
					$lines[] = '' !== $doc['label']
						? $doc['label'] . ': ' . $doc['url']
						: $doc['url'];
				}
			}

			return implode( "\n", $lines );
		}

		/**
		 * Normalise a user-entered number to E.164, or return '' if invalid.
		 *
		 * @param string $raw
		 * @return string
		 */
		private function normalize_phone( $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				return '';
			}

			$has_plus = ( '+' === substr( $raw, 0, 1 ) );
			$digits   = preg_replace( '/\D/', '', $raw );

			if ( '' === $digits ) {
				return '';
			}

			$cc = $this->get_settings()['country_code'];

			if ( $has_plus ) {
				$e164 = '+' . $digits;
			} elseif ( '00' === substr( $digits, 0, 2 ) ) {
				// International prefix, e.g. 0044...
				$e164 = '+' . ltrim( substr( $digits, 2 ), '0' );
			} elseif ( '0' === substr( $digits, 0, 1 ) && '' !== $cc ) {
				// National number with trunk 0, e.g. UK 07700... -> +447700...
				$e164 = '+' . $cc . ltrim( $digits, '0' );
			} elseif ( '' !== $cc && substr( $digits, 0, strlen( $cc ) ) !== $cc ) {
				$e164 = '+' . $cc . $digits;
			} else {
				$e164 = '+' . $digits;
			}

			// E.164 allows up to 15 digits after the plus; require a sane minimum.
			if ( ! preg_match( '/^\+[1-9]\d{7,14}$/', $e164 ) ) {
				return '';
			}

			return $e164;
		}

		/**
		 * Send an SMS via the Twilio REST API.
		 *
		 * @param string $to   E.164 destination number.
		 * @param string $body Message text.
		 * @return true|WP_Error
		 */
		private function send_sms( $to, $body ) {
			$s        = $this->get_settings();
			$endpoint = sprintf(
				'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
				rawurlencode( $s['sid'] )
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $s['sid'] . ':' . $s['token'] ),
						'Content-Type'  => 'application/x-www-form-urlencoded',
					),
					'body'    => array(
						'To'   => $to,
						'From' => $s['from'],
						'Body' => $body,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = is_array( $data ) && ! empty( $data['message'] )
				? $data['message']
				: 'Twilio HTTP ' . $code;

			return new WP_Error( 'twilio_error', $msg );
		}

		/* ----- Simple per-IP rate limiting via transients ----- */

		private function rate_key() {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

			return 'abs_sms_rl_' . md5( $ip );
		}

		private function is_rate_limited() {
			return (int) get_transient( $this->rate_key() ) >= self::RATE_LIMIT;
		}

		private function bump_rate_limit() {
			$key   = $this->rate_key();
			$count = (int) get_transient( $key );
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}
	}

	ABS_Datasheet_SMS::init();
}

if ( ! function_exists( 'abs_datasheet_sms_form' ) ) {
	/**
	 * Template helper: output the "Text me this datasheet" form.
	 *
	 * @param int|null $post_id Optional product ID; defaults to current post.
	 */
	function abs_datasheet_sms_form( $post_id = null ) {
		ABS_Datasheet_SMS::init()->render_form( $post_id );
	}
}
