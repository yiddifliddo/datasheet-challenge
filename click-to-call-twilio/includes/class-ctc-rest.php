<?php
/**
 * REST endpoints that drive the Click-to-Call flow.
 *
 *   POST /wp-json/click-to-call/v1/request   Public. Called by the button; validates the
 *                                            number, rate-limits, and sends the confirm SMS.
 *   POST /wp-json/click-to-call/v1/sms        Twilio inbound-SMS webhook. On "YES" it starts
 *                                            the outbound call. Signature-verified.
 *   POST /wp-json/click-to-call/v1/voice      Twilio voice webhook. Returns TwiML that bridges
 *                                            the answered call to the sales number. Signature-verified.
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CTC_Rest {

	/**
	 * How long a "please reply YES" request stays valid, in seconds.
	 */
	const PENDING_TTL = 900; // 15 minutes.

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			CTC_REST_NAMESPACE,
			'/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			CTC_REST_NAMESPACE,
			'/sms',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_sms' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			CTC_REST_NAMESPACE,
			'/voice',
			array(
				'methods'             => array( 'POST', 'GET' ),
				'callback'            => array( $this, 'handle_voice' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Step 1: visitor asks to be called                                     */
	/* --------------------------------------------------------------------- */

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_request( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		// Honeypot: real users never fill this hidden field. Pretend success and do nothing.
		if ( ! empty( $params['company'] ) ) {
			return $this->success( $this->get_setting( 'success_message' ) );
		}

		if ( empty( $params['consent'] ) ) {
			return new WP_Error(
				'ctc_consent_required',
				__( 'Please tick the box to confirm you agree to receive a text message.', 'click-to-call-twilio' ),
				array( 'status' => 400 )
			);
		}

		$phone_raw = isset( $params['phone'] ) ? $params['phone'] : '';
		$e164      = CTC_Phone::normalize_us( $phone_raw );
		if ( is_wp_error( $e164 ) ) {
			return new WP_Error( 'ctc_invalid_phone', $e164->get_error_message(), array( 'status' => 400 ) );
		}

		if ( ! CTC_Twilio::is_configured() ) {
			return new WP_Error(
				'ctc_not_configured',
				__( 'The calling service is temporarily unavailable. Please try again later.', 'click-to-call-twilio' ),
				array( 'status' => 503 )
			);
		}

		// Per-IP rate limit.
		$ip       = $this->client_ip();
		$rl_key   = 'ctc_rl_' . md5( $ip );
		$rl_count = (int) get_transient( $rl_key );
		$rl_max   = max( 1, (int) $this->get_setting( 'rate_limit_ip' ) );
		if ( $rl_count >= $rl_max ) {
			return new WP_Error(
				'ctc_rate_limited',
				__( 'Too many requests. Please wait a little while and try again.', 'click-to-call-twilio' ),
				array( 'status' => 429 )
			);
		}

		// Per-number cooldown: if we already texted this number recently, don't text again.
		$pending_key = CTC_Phone::key( $e164 );
		if ( get_transient( $pending_key ) ) {
			return $this->success(
				__( "We've already sent you a text. Please reply YES to that message and we'll call you.", 'click-to-call-twilio' )
			);
		}

		$sent = CTC_Twilio::send_sms( $e164, $this->build_sms_body() );
		if ( is_wp_error( $sent ) ) {
			// Log the real reason for the site owner; show the visitor something generic.
			error_log( 'Click to Call: SMS send failed - ' . $sent->get_error_message() );
			return new WP_Error(
				'ctc_send_failed',
				__( "Sorry, we couldn't send the confirmation text. Please try again shortly.", 'click-to-call-twilio' ),
				array( 'status' => 502 )
			);
		}

		set_transient( $pending_key, array( 'ip' => $ip, 'ts' => time() ), self::PENDING_TTL );
		set_transient( $rl_key, $rl_count + 1, HOUR_IN_SECONDS );

		$display = CTC_Phone::format_display( $e164 );
		$message = str_replace( '{phone}', $display, $this->get_setting( 'success_message' ) );

		return $this->success( $message );
	}

	/* --------------------------------------------------------------------- */
	/* Step 2: Twilio delivers the visitor's SMS reply                        */
	/* --------------------------------------------------------------------- */

	/**
	 * @param WP_REST_Request $request
	 * @return void Outputs TwiML and exits.
	 */
	public function handle_sms( $request ) {
		if ( ! $this->verify_twilio( $request ) ) {
			$this->reject();
		}

		$params = $request->get_body_params();
		$from   = isset( $params['From'] ) ? trim( $params['From'] ) : '';
		$body   = isset( $params['Body'] ) ? trim( $params['Body'] ) : '';
		$word   = strtoupper( preg_replace( '/[^A-Za-z]/', '', $body ) );

		$opt_out = array( 'STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT' );
		$help    = array( 'HELP', 'INFO' );
		$yes     = array( 'YES', 'Y', 'YEAH', 'YEP', 'YE', 'CONFIRM', 'CALL', 'OK', 'OKAY' );

		if ( in_array( $word, $opt_out, true ) ) {
			if ( $from ) {
				delete_transient( CTC_Phone::key( $from ) );
			}
			$this->twiml_message( __( "You're unsubscribed and won't receive further texts. Reply START to opt back in.", 'click-to-call-twilio' ) );
		}

		if ( in_array( $word, $help, true ) ) {
			$this->twiml_message(
				sprintf(
					/* translators: %s: site name */
					__( '%s Click-to-Call: reply YES to confirm your call request, or STOP to cancel. Msg & data rates may apply.', 'click-to-call-twilio' ),
					$this->site_name()
				)
			);
		}

		if ( in_array( $word, $yes, true ) ) {
			$pending_key = CTC_Phone::key( $from );
			if ( ! $from || ! get_transient( $pending_key ) ) {
				$this->twiml_message(
					__( "We couldn't find a recent call request for this number. Please request a call again on our website.", 'click-to-call-twilio' )
				);
			}

			delete_transient( $pending_key );

			$voice_url = rest_url( CTC_REST_NAMESPACE . '/voice' );
			$call      = CTC_Twilio::create_call( $from, $voice_url );

			if ( is_wp_error( $call ) ) {
				error_log( 'Click to Call: call creation failed - ' . $call->get_error_message() );
				$this->twiml_message(
					__( "Sorry, we couldn't connect your call right now. Please try again shortly.", 'click-to-call-twilio' )
				);
			}

			$this->twiml_message(
				__( 'Thanks! Connecting your call now — your phone will ring in a moment.', 'click-to-call-twilio' )
			);
		}

		// Anything else.
		$this->twiml_message(
			__( 'Reply YES to confirm your call request, or STOP to cancel.', 'click-to-call-twilio' )
		);
	}

	/* --------------------------------------------------------------------- */
	/* Step 3: visitor answers; bridge them to sales                          */
	/* --------------------------------------------------------------------- */

	/**
	 * @param WP_REST_Request $request
	 * @return void Outputs TwiML and exits.
	 */
	public function handle_voice( $request ) {
		if ( ! $this->verify_twilio( $request ) ) {
			$this->reject();
		}

		$sales  = $this->get_setting( 'sales_number' );
		$twilio = $this->get_setting( 'twilio_number' );
		$intro  = $this->get_setting( 'voice_intro' );

		if ( empty( $sales ) ) {
			$this->send_xml(
				'<Response><Say>' . $this->xesc( __( 'We are sorry, no one is available to take your call right now. Please try again later.', 'click-to-call-twilio' ) ) . '</Say><Hangup/></Response>'
			);
		}

		$xml  = '<Response>';
		$xml .= '<Say>' . $this->xesc( $intro ) . '</Say>';
		$xml .= '<Dial answerOnBridge="true" callerId="' . $this->xesc( $twilio ) . '" timeout="30">' . $this->xesc( $sales ) . '</Dial>';
		$xml .= '<Say>' . $this->xesc( __( 'We are sorry, our team is unavailable right now. Please try again later. Goodbye.', 'click-to-call-twilio' ) ) . '</Say>';
		$xml .= '</Response>';

		$this->send_xml( $xml );
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                                */
	/* --------------------------------------------------------------------- */

	private function build_sms_body() {
		$template = $this->get_setting( 'sms_body' );
		return str_replace( '{site}', $this->site_name(), $template );
	}

	private function site_name() {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return $name ? $name : __( 'our team', 'click-to-call-twilio' );
	}

	private function get_setting( $key ) {
		$s = CTC_Settings::get();
		return isset( $s[ $key ] ) ? $s[ $key ] : '';
	}

	/**
	 * Verify the request genuinely came from Twilio (unless the owner disabled it).
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	private function verify_twilio( $request ) {
		$s = CTC_Settings::get();
		if ( empty( $s['verify_signature'] ) ) {
			return true;
		}

		$signature = $request->get_header( 'x_twilio_signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$params = $request->get_body_params();

		// Twilio may have used either the canonical REST URL or the raw request URL.
		$candidates = array(
			rest_url( ltrim( $request->get_route(), '/' ) ),
			$this->reconstruct_url(),
		);

		foreach ( array_unique( $candidates ) as $url ) {
			if ( CTC_Twilio::verify_signature( $url, $params, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	private function reconstruct_url() {
		$proto = 'http';
		if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) ) ) ) {
			$proto = 'https';
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$fwd   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) );
			$proto = strtolower( trim( explode( ',', $fwd )[0] ) );
		}

		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: wp_parse_url( home_url(), PHP_URL_HOST );

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		return $proto . '://' . $host . $uri;
	}

	private function client_ip() {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return $remote ? $remote : '0.0.0.0';
	}

	private function success( $message ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $message,
			),
			200
		);
	}

	/** XML-escape a string for safe inclusion in TwiML. */
	private function xesc( $string ) {
		return htmlspecialchars( (string) $string, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/** Emit a <Message> TwiML reply and stop. */
	private function twiml_message( $text ) {
		$this->send_xml( '<Response><Message>' . $this->xesc( $text ) . '</Message></Response>' );
	}

	/** Emit raw TwiML XML with the correct content type and stop. */
	private function send_xml( $xml ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/xml; charset=UTF-8' );
		}
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xml; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/** Reject an unverified webhook request. */
	private function reject() {
		if ( ! headers_sent() ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
		}
		echo 'Forbidden';
		exit;
	}
}
