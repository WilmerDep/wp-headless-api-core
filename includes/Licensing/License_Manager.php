<?php
/**
 * Licensing lifecycle orchestrator.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Manager {
	/**
	 * Current official public keys indexed by kid.
	 * Additional keys can be introduced during documented rotation.
	 */
	private const PUBLIC_KEYS = array(
		'pholio-2026-01' => 'f6b2cf982820407f2048a814f328bd0d93c4ec3dc03f7caff690b514aac568b7',
	);

	/**
	 * Product identity expected in every signed token accepted by this plugin.
	 */
	private const PRODUCT_KEY = 'wp-headless-api-core';

	/**
	 * Initialize licensing infrastructure without gating feature modules yet.
	 *
	 * @return void
	 */
	public static function boot() {
		License_Instance::get();
	}

	/**
	 * Activate and locally verify a license token.
	 *
	 * @param string $license_key License key.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function activate( $license_key ) {
		$domain   = self::current_domain();
		$response = License_Client::activate( $license_key, $domain );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::store_verified_response( $response, $domain );
	}

	/**
	 * Refresh the persisted token when one is available.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function refresh() {
		$state = License_Storage::get();
		$token = isset( $state['token'] ) && is_string( $state['token'] ) ? $state['token'] : '';
		if ( '' === $token ) {
			return new \WP_Error( 'licensing_missing_token', 'No license token is stored for refresh.' );
		}

		$domain   = self::current_domain();
		$response = License_Client::refresh( $token, $domain );
		if ( is_wp_error( $response ) ) {
			self::persist_authoritative_status_from_error( $response );
			return $response;
		}

		return self::store_verified_response( $response, $domain );
	}

	/**
	 * Deactivate this installation and clear the local token after server confirmation.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function deactivate() {
		$state = License_Storage::get();
		$token = isset( $state['token'] ) && is_string( $state['token'] ) ? $state['token'] : '';
		if ( '' === $token ) {
			License_Storage::clear();
			return array( 'ok' => true, 'deactivated' => true );
		}

		$response = License_Client::deactivate( $token );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		License_Storage::clear();
		return $response;
	}

	/**
	 * Verify currently stored token against this installation.
	 *
	 * A restrictive status learned directly from the licensing server during a
	 * refresh attempt is applied after cryptographic token verification. This
	 * preserves signed-token trust while ensuring suspension/revocation learned
	 * online is not lost merely because the previously issued token itself does
	 * not carry a status claim.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_stored() {
		$state = License_Storage::get();
		$token = isset( $state['token'] ) && is_string( $state['token'] ) ? $state['token'] : '';
		if ( '' === $token ) {
			return array(
				'trusted'     => false,
				'valid'       => false,
				'operational' => false,
				'code'        => 'TOKEN_MISSING',
			);
		}

		$verification = License_Verifier::verify(
			$token,
			self::PUBLIC_KEYS,
			array(
				'product'    => self::PRODUCT_KEY,
				'domain'     => self::current_domain(),
				'instanceId' => License_Instance::get(),
			)
		);

		if ( empty( $verification['trusted'] ) ) {
			return $verification;
		}

		$server_status = isset( $state['serverStatus'] ) && is_string( $state['serverStatus'] )
			? $state['serverStatus']
			: '';

		if ( in_array( $server_status, array( 'suspended', 'revoked', 'expired' ), true ) ) {
			$verification['valid']       = false;
			$verification['operational'] = false;
			$verification['status']      = $server_status;
			$verification['code']        = 'suspended' === $server_status
				? 'LICENSE_SUSPENDED'
				: ( 'revoked' === $server_status ? 'LICENSE_REVOKED' : 'LICENSE_EXPIRED' );
			$verification['message']     = 'suspended' === $server_status
				? 'License is suspended according to the licensing server.'
				: ( 'revoked' === $server_status
					? 'License is revoked according to the licensing server.'
					: 'License is expired according to the licensing server.' );
			$verification['serverStatusObservedAt'] = isset( $state['serverStatusObservedAt'] )
				? (string) $state['serverStatusObservedAt']
				: null;
		}

		return $verification;
	}

	/**
	 * Whether the current token is cryptographically trusted and bound to this site.
	 *
	 * @return bool
	 */
	public static function is_trusted() {
		$result = self::verify_stored();
		return ! empty( $result['trusted'] );
	}

	/**
	 * Whether the current stored token is both trusted and operational.
	 *
	 * @return bool
	 */
	public static function is_operational() {
		$result = self::verify_stored();
		return ! empty( $result['trusted'] ) && ! empty( $result['operational'] );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function store_verified_response( array $response, $domain ) {
		$token = isset( $response['token'] ) && is_string( $response['token'] ) ? $response['token'] : '';
		if ( '' === $token ) {
			return new \WP_Error( 'licensing_missing_token', 'Licensing response did not include a token.' );
		}

		$verification = License_Verifier::verify(
			$token,
			self::PUBLIC_KEYS,
			array(
				'product'    => self::PRODUCT_KEY,
				'domain'     => $domain,
				'instanceId' => License_Instance::get(),
			)
		);

		if ( empty( $verification['trusted'] ) ) {
			License_Storage::set(
				array(
					'token'        => $token,
					'trusted'      => false,
					'operational'  => false,
					'verified'     => false,
					'verification' => $verification,
					'updatedAt'    => gmdate( 'c' ),
				)
			);
			return new \WP_Error(
				'licensing_verification_failed',
				isset( $verification['message'] ) ? (string) $verification['message'] : 'License token verification failed.',
				$verification
			);
		}

		$license       = isset( $response['license'] ) && is_array( $response['license'] ) ? $response['license'] : array();
		$server_status = isset( $license['status'] ) && is_string( $license['status'] ) ? $license['status'] : '';
		$now           = gmdate( 'c' );

		License_Storage::set(
			array(
				'token'                  => $token,
				'trusted'                => true,
				'operational'            => ! empty( $verification['operational'] ),
				'verified'               => true,
				'status'                 => isset( $verification['status'] ) ? $verification['status'] : 'untrusted',
				'payload'                => $verification['payload'],
				'header'                 => $verification['header'],
				'verification'           => $verification,
				'license'                => $license,
				'serverStatus'           => $server_status,
				'serverStatusObservedAt' => '' !== $server_status ? $now : null,
				'updatedAt'              => $now,
			)
		);

		return $verification;
	}

	/**
	 * Persist only authoritative lifecycle failures returned by the server.
	 * Transport errors, authentication errors and malformed responses do not
	 * overwrite the last known licensing status.
	 *
	 * @param \WP_Error $error Refresh error.
	 * @return void
	 */
	private static function persist_authoritative_status_from_error( $error ) {
		$data     = $error->get_error_data();
		$response = is_array( $data ) && isset( $data['response'] ) && is_array( $data['response'] )
			? $data['response']
			: array();
		$code     = isset( $response['code'] ) && is_string( $response['code'] ) ? $response['code'] : '';
		$map      = array(
			'LICENSE_SUSPENDED' => 'suspended',
			'LICENSE_REVOKED'   => 'revoked',
			'LICENSE_EXPIRED'   => 'expired',
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		$state                           = License_Storage::get();
		$state['serverStatus']           = $map[ $code ];
		$state['serverStatusObservedAt'] = gmdate( 'c' );
		$state['updatedAt']              = $state['serverStatusObservedAt'];
		if ( isset( $state['license'] ) && is_array( $state['license'] ) ) {
			$state['license']['status'] = $map[ $code ];
		}
		License_Storage::set( $state );
	}

	/** @return string */
	private static function current_domain() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
