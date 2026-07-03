<?php
/**
 * US phone number validation and normalisation (North American Numbering Plan).
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CTC_Phone {

	/**
	 * Validate and normalise a raw, user-entered US phone number to E.164 (+1XXXXXXXXXX).
	 *
	 * @param string $raw Raw input, e.g. "(415) 555-2671", "415-555-2671", "+1 415 555 2671".
	 * @return string|WP_Error E.164 string on success, WP_Error on failure.
	 */
	public static function normalize_us( $raw ) {
		$raw    = (string) $raw;
		$digits = preg_replace( '/\D+/', '', $raw );

		// Drop a leading country code "1" if present.
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}

		if ( 10 !== strlen( $digits ) ) {
			return new WP_Error(
				'ctc_invalid_phone',
				__( 'Please enter a valid 10-digit US phone number.', 'click-to-call-twilio' )
			);
		}

		$area     = substr( $digits, 0, 3 );
		$exchange = substr( $digits, 3, 3 );

		// NANP rules: the first digit of both the area code and the exchange must be 2-9.
		if ( ! preg_match( '/^[2-9]/', $area ) || ! preg_match( '/^[2-9]/', $exchange ) ) {
			return new WP_Error(
				'ctc_invalid_phone',
				__( 'That does not look like a valid US phone number. Please check and try again.', 'click-to-call-twilio' )
			);
		}

		// Reject N11 service codes used as an area code (211, 311, 411, 511, 611, 711, 811, 911).
		if ( '1' === $area[1] && '1' === $area[2] ) {
			return new WP_Error(
				'ctc_invalid_phone',
				__( 'That does not look like a valid US phone number. Please check and try again.', 'click-to-call-twilio' )
			);
		}

		return '+1' . $digits;
	}

	/**
	 * Format an E.164 US number for display, e.g. "+14155552671" => "(415) 555-2671".
	 *
	 * @param string $e164 E.164 number.
	 * @return string Human-friendly string (falls back to the input if it cannot be parsed).
	 */
	public static function format_display( $e164 ) {
		$digits = preg_replace( '/\D+/', '', (string) $e164 );
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( 10 !== strlen( $digits ) ) {
			return (string) $e164;
		}
		return sprintf(
			'(%s) %s-%s',
			substr( $digits, 0, 3 ),
			substr( $digits, 3, 3 ),
			substr( $digits, 6, 4 )
		);
	}

	/**
	 * A stable transient-safe key for a phone number.
	 *
	 * @param string $e164 E.164 number.
	 * @return string
	 */
	public static function key( $e164 ) {
		return 'ctc_pending_' . md5( (string) $e164 );
	}
}
