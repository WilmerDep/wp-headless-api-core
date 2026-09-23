<?php
/**
 * PholioDev License Token Contract v1 verifier.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Verifier {
	/**
	 * Verify compact JWS trust and then apply temporal operational rules.
	 *
	 * A token can remain cryptographically trusted after it stops being
	 * operational. This distinction lets policy code safely read signed claims
	 * (for example critical Forms/Mail entitlements) without treating stale or
	 * expired public capabilities as active.
	 *
	 * @param string               $token       Compact JWS token.
	 * @param array<string,string> $public_keys Map of kid => raw public key hex.
	 * @param array<string,mixed>  $context     Expected product/domain/instance and optional now timestamp.
	 * @return array<string,mixed>
	 */
	public static function verify( $token, array $public_keys, array $context = array() ) {
		$result = self::verify_trust( $token, $public_keys, $context );
		if ( empty( $result['trusted'] ) ) {
			return $result;
		}

		$payload = $result['payload'];
		$now     = isset( $context['now'] ) ? (int) $context['now'] : time();

		if ( $now > strtotime( $payload['graceUntil'] ) ) {
			$result['valid']       = false;
			$result['operational'] = false;
			$result['status']      = 'expired';
			$result['code']        = 'LICENSE_EXPIRED';
			$result['message']     = 'License grace period has expired.';
			return $result;
		}

		if ( $now > strtotime( $payload['offlineUntil'] ) ) {
			$result['valid']       = false;
			$result['operational'] = false;
			$result['code']        = 'OFFLINE_TOLERANCE_EXCEEDED';
			$result['message']     = 'Offline tolerance window has expired.';
			return $result;
		}

		$result['valid']       = true;
		$result['operational'] = true;
		$result['code']        = null;
		$result['message']     = null;
		return $result;
	}

	/**
	 * Verify cryptographic trust, contract shape and installation binding only.
	 *
	 * Temporal claims are parsed and a lifecycle status is reported, but
	 * `offlineUntil` and `graceUntil` do not destroy trust in an otherwise
	 * authentic signed payload.
	 *
	 * @param string               $token       Compact JWS token.
	 * @param array<string,string> $public_keys Map of kid => raw public key hex.
	 * @param array<string,mixed>  $context     Expected product/domain/instance and optional now timestamp.
	 * @return array<string,mixed>
	 */
	public static function verify_trust( $token, array $public_keys, array $context = array() ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return self::failure( 'VERIFICATION_UNAVAILABLE', 'Ed25519 verification requires Sodium.' );
		}

		if ( ! is_string( $token ) || '' === $token ) {
			return self::failure( 'TOKEN_MALFORMED', 'Token is empty or invalid.' );
		}

		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) ) {
			return self::failure( 'TOKEN_MALFORMED', 'Token must contain three dot-separated parts.' );
		}

		list( $header_b64, $payload_b64, $signature_b64 ) = $parts;
		$header    = self::decode_json_part( $header_b64 );
		$payload   = self::decode_json_part( $payload_b64 );
		$signature = self::base64url_decode( $signature_b64 );

		if ( ! is_array( $header ) || ! is_array( $payload ) || false === $signature ) {
			return self::failure( 'TOKEN_MALFORMED', 'Token contains invalid base64url or JSON data.' );
		}

		if ( SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) ) {
			return self::failure( 'TOKEN_MALFORMED', 'Ed25519 signature must be exactly 64 bytes.' );
		}

		if ( 'EdDSA' !== ( $header['alg'] ?? null ) || 'PHOLIO-LICENSE' !== ( $header['typ'] ?? null ) ) {
			return self::failure( 'TOKEN_MALFORMED', 'Unsupported token header.' );
		}

		$kid = isset( $header['kid'] ) && is_string( $header['kid'] ) ? trim( $header['kid'] ) : '';
		if ( '' === $kid || ! isset( $public_keys[ $kid ] ) ) {
			return self::failure( 'UNKNOWN_KEY_ID', 'Unknown signing key identifier.' );
		}

		$key_hex = $public_keys[ $kid ];
		if ( ! is_string( $key_hex ) || 1 !== preg_match( '/\A[0-9a-fA-F]{64}\z/', $key_hex ) ) {
			return self::failure( 'UNKNOWN_KEY_ID', 'Configured public key is invalid.' );
		}

		$raw_public_key = hex2bin( $key_hex );
		if ( false === $raw_public_key || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $raw_public_key ) ) {
			return self::failure( 'UNKNOWN_KEY_ID', 'Configured public key is invalid.' );
		}

		$signing_input = $header_b64 . '.' . $payload_b64;
		if ( ! sodium_crypto_sign_verify_detached( $signature, $signing_input, $raw_public_key ) ) {
			return self::failure( 'INVALID_SIGNATURE', 'Token signature verification failed.' );
		}

		$required = array(
			'version', 'licenseId', 'product', 'customerId', 'plan', 'instanceId', 'domain',
			'entitlements', 'issuedAt', 'refreshAfter', 'offlineUntil', 'expiresAt', 'graceUntil',
		);
		foreach ( $required as $claim ) {
			if ( ! array_key_exists( $claim, $payload ) ) {
				return self::failure( 'TOKEN_MALFORMED', 'Missing required claim: ' . $claim );
			}
		}

		if ( 1 !== $payload['version'] ) {
			return self::failure( 'UNSUPPORTED_TOKEN_VERSION', 'Unsupported token version.' );
		}

		$string_claims = array( 'licenseId', 'product', 'customerId', 'plan', 'instanceId', 'domain' );
		foreach ( $string_claims as $claim ) {
			if ( ! is_string( $payload[ $claim ] ) || '' === trim( $payload[ $claim ] ) ) {
				return self::failure( 'TOKEN_MALFORMED', 'Invalid string claim: ' . $claim );
			}
		}

		if ( ! is_array( $payload['entitlements'] ) ) {
			return self::failure( 'TOKEN_MALFORMED', 'Entitlements must be an array.' );
		}
		foreach ( $payload['entitlements'] as $entitlement ) {
			if ( ! is_string( $entitlement ) || '' === trim( $entitlement ) ) {
				return self::failure( 'TOKEN_MALFORMED', 'Entitlements must contain non-empty strings only.' );
			}
		}

		$dates      = array( 'issuedAt', 'refreshAfter', 'offlineUntil', 'expiresAt', 'graceUntil' );
		$timestamps = array();
		foreach ( $dates as $claim ) {
			if ( ! is_string( $payload[ $claim ] ) || '' === trim( $payload[ $claim ] ) ) {
				return self::failure( 'TOKEN_MALFORMED', 'Invalid timestamp claim: ' . $claim );
			}
			$timestamp = strtotime( $payload[ $claim ] );
			if ( false === $timestamp ) {
				return self::failure( 'TOKEN_MALFORMED', 'Invalid timestamp claim: ' . $claim );
			}
			$timestamps[ $claim ] = $timestamp;
		}

		if (
			$timestamps['issuedAt'] > $timestamps['refreshAfter'] ||
			$timestamps['refreshAfter'] > $timestamps['offlineUntil'] ||
			$timestamps['expiresAt'] > $timestamps['graceUntil']
		) {
			return self::failure( 'TOKEN_MALFORMED', 'License token temporal claims are out of order.' );
		}

		if ( isset( $context['product'] ) && (string) $context['product'] !== $payload['product'] ) {
			return self::failure( 'PRODUCT_MISMATCH', 'Token product does not match this plugin.' );
		}

		if ( isset( $context['domain'] ) && self::normalize_domain( $context['domain'] ) !== self::normalize_domain( $payload['domain'] ) ) {
			return self::failure( 'DOMAIN_MISMATCH', 'Token domain does not match this site.' );
		}

		if ( isset( $context['instanceId'] ) && $context['instanceId'] !== $payload['instanceId'] ) {
			return self::failure( 'INSTANCE_MISMATCH', 'Token instanceId does not match this installation.' );
		}

		$now = isset( $context['now'] ) ? (int) $context['now'] : time();
		if ( $now > $timestamps['graceUntil'] ) {
			$status = 'expired';
		} elseif ( $now > $timestamps['expiresAt'] ) {
			$status = 'grace_period';
		} else {
			$status = 'active';
		}

		return array(
			'trusted'     => true,
			'valid'       => false,
			'operational' => false,
			'code'        => null,
			'message'     => null,
			'status'      => $status,
			'header'      => $header,
			'payload'     => $payload,
		);
	}

	/** @return array<string,mixed> */
	private static function failure( $code, $message ) {
		return array(
			'trusted'     => false,
			'valid'       => false,
			'operational' => false,
			'code'        => $code,
			'message'     => $message,
		);
	}

	/** @return array<string,mixed>|null */
	private static function decode_json_part( $value ) {
		$decoded = self::base64url_decode( $value );
		if ( false === $decoded ) {
			return null;
		}

		$data = json_decode( $decoded, true );
		return is_array( $data ) ? $data : null;
	}

	/** @return string|false */
	private static function base64url_decode( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		if ( 1 !== preg_match( '/\A[A-Za-z0-9_-]+\z/', $value ) ) {
			return false;
		}

		$remainder = strlen( $value ) % 4;
		if ( 0 !== $remainder ) {
			$value .= str_repeat( '=', 4 - $remainder );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}

	/** @return string */
	private static function normalize_domain( $domain ) {
		$domain = strtolower( trim( (string) $domain ) );
		if ( false !== strpos( $domain, '://' ) ) {
			$host = wp_parse_url( $domain, PHP_URL_HOST );
			return is_string( $host ) ? strtolower( $host ) : $domain;
		}

		return preg_replace( '/[:\/].*$/', '', $domain );
	}
}
