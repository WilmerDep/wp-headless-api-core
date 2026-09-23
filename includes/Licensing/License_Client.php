<?php
/**
 * HTTP client for the PholioDev Licensing service.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Licensing;

defined( 'ABSPATH' ) || exit;

final class License_Client {
	const DEFAULT_BASE_URL = 'https://pholiodev-licensing.ai.studio';

	/**
	 * Activate a license for this installation.
	 *
	 * @param string $license_key License key.
	 * @param string $domain      Normalized site domain.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function activate( $license_key, $domain ) {
		return self::post(
			'/api/v1/licenses/activate',
			array(
				'licenseKey' => (string) $license_key,
				'product'    => 'wp-headless-api-core',
				'instanceId' => License_Instance::get(),
				'domain'     => (string) $domain,
			)
		);
	}

	/**
	 * Refresh an existing signed license token.
	 *
	 * @param string $token  Signed license token.
	 * @param string $domain Current domain.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function refresh( $token, $domain ) {
		return self::post(
			'/api/v1/licenses/refresh',
			array(
				'token'      => (string) $token,
				'instanceId' => License_Instance::get(),
				'domain'     => (string) $domain,
			)
		);
	}

	/**
	 * Deactivate the current installation.
	 *
	 * @param string $token Signed license token.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function deactivate( $token ) {
		return self::post(
			'/api/v1/licenses/deactivate',
			array(
				'token'      => (string) $token,
				'instanceId' => License_Instance::get(),
			)
		);
	}

	/**
	 * Fetch public signing keys.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function public_keys() {
		$response = wp_remote_get(
			self::url( '/api/v1/public-keys' ),
			array( 'timeout' => 15 )
		);

		return self::decode_response( $response );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function post( $path, array $body ) {
		$response = wp_remote_post(
			self::url( $path ),
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		return self::decode_response( $response );
	}

	/** @return string */
	private static function url( $path ) {
		$base = defined( 'HEADLESS_API_CORE_LICENSE_SERVER' )
			? HEADLESS_API_CORE_LICENSE_SERVER
			: self::DEFAULT_BASE_URL;

		return rtrim( (string) $base, '/' ) . '/' . ltrim( (string) $path, '/' );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function decode_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'licensing_invalid_response', 'Licensing server returned invalid JSON.' );
		}

		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $body['error'] ) && is_string( $body['error'] ) ? $body['error'] : 'Licensing request failed.';
			return new \WP_Error( 'licensing_http_error', $message, array( 'status' => $status, 'response' => $body ) );
		}

		return $body;
	}
}
