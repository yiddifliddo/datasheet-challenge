<?php
/**
 * Minimal Twilio REST client built on the WordPress HTTP API.
 *
 * No Composer, no SDK, no external dependencies. Talks to the Twilio REST API
 * directly with HTTP Basic auth so the plugin stays 100% self-contained.
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CTC_Twilio {

	const API_BASE = 'https://api.twilio.com/2010-04-01/Accounts/';

	/**
	 * Are the minimum Twilio credentials present?
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$s = CTC_Settings::get();
		return ! empty( $s['account_sid'] ) && ! empty( $s['auth_token'] ) && ! empty( $s['twilio_number'] );
	}

	/**
	 * Send an SMS.
	 *
	 * @param string $to   Destination E.164 number.
	 * @param string $body Message body.
	 * @return true|WP_Error
	 */
	public static function send_sms( $to, $body ) {
		$s = CTC_Settings::get();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'ctc_not_configured', __( 'The calling service is not configured yet.', 'click-to-call-twilio' ) );
		}

		$endpoint = self::API_BASE . rawurlencode( $s['account_sid'] ) . '/Messages.json';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $s['account_sid'] . ':' . $s['auth_token'] ),
				),
				'body'    => array(
					'To'   => $to,
					'From' => $s['twilio_number'],
					'Body' => $body,
				),
			)
		);

		return self::handle_response( $response );
	}

	/**
	 * Create an outbound call to the customer that, on answer, fetches TwiML from
	 * our voice webhook (which bridges them to the sales number).
	 *
	 * @param string $to        Customer E.164 number to call.
	 * @param string $voice_url  Absolute URL Twilio will request for TwiML.
	 * @return true|WP_Error
	 */
	public static function create_call( $to, $voice_url ) {
		$s = CTC_Settings::get();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'ctc_not_configured', __( 'The calling service is not configured yet.', 'click-to-call-twilio' ) );
		}

		$endpoint = self::API_BASE . rawurlencode( $s['account_sid'] ) . '/Calls.json';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $s['account_sid'] . ':' . $s['auth_token'] ),
				),
				'body'    => array(
					'To'     => $to,
					'From'   => $s['twilio_number'],
					'Url'    => $voice_url,
					'Method' => 'POST',
				),
			)
		);

		return self::handle_response( $response );
	}

	/**
	 * Normalise a Twilio API response into true or a WP_Error carrying Twilio's message.
	 *
	 * @param array|WP_Error $response Result of wp_remote_post().
	 * @return true|WP_Error
	 */
	private static function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$message = is_array( $body ) && ! empty( $body['message'] )
			? $body['message']
			: __( 'The calling service returned an unexpected error.', 'click-to-call-twilio' );

		return new WP_Error( 'ctc_twilio_error', $message, array( 'status' => $code ) );
	}

	/**
	 * Validate an inbound Twilio webhook request signature (X-Twilio-Signature).
	 *
	 * Twilio signs the request URL plus the alphabetically sorted POST params using
	 * HMAC-SHA1 keyed with your auth token. We reconstruct that value and compare.
	 *
	 * @param string $url       The exact URL Twilio requested.
	 * @param array  $params    POST body params.
	 * @param string $signature Value of the X-Twilio-Signature header.
	 * @return bool
	 */
	public static function verify_signature( $url, $params, $signature ) {
		$s = CTC_Settings::get();
		if ( empty( $s['auth_token'] ) || empty( $signature ) ) {
			return false;
		}

		ksort( $params );
		$data = $url;
		foreach ( $params as $key => $value ) {
			$data .= $key . $value;
		}

		$expected = base64_encode( hash_hmac( 'sha1', $data, $s['auth_token'], true ) );

		return hash_equals( $expected, $signature );
	}
}
