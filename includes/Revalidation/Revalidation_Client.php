<?php
/**
 * Generic signed outbound revalidation client.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Revalidation;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Revalidation_Client {
	/**
	 * Timestamp header used by consumers to validate freshness/replay windows.
	 */
	const TIMESTAMP_HEADER = 'X-Headless-Timestamp';

	/**
	 * HMAC signature header.
	 */
	const SIGNATURE_HEADER = 'X-Headless-Signature';

	/**
	 * Default outbound timeout. Saving content is already committed before the
	 * shutdown delivery runs, and failures are never thrown back into editing.
	 */
	const DEFAULT_TIMEOUT = 3.0;

	/**
	 * Deliver one revalidation event.
	 *
	 * Signature input is the exact string: "<unix timestamp>.<raw JSON body>".
	 * The header value is formatted as "sha256=<lowercase hex digest>".
	 *
	 * @param array $payload Public lifecycle payload.
	 * @return bool True when the consumer returned a 2xx response.
	 */
	public function send( array $payload ) {
		$url    = $this->get_url();
		$secret = $this->get_secret();

		// A completely unconfigured installation simply has revalidation disabled.
		if ( empty( $url ) && empty( $secret ) ) {
			return false;
		}

		if ( empty( $url ) || empty( $secret ) ) {
			$this->log_failure( 'Revalidation is partially configured; both URL and secret are required.' );
			return false;
		}

		if ( strlen( $secret ) < 32 ) {
			$this->log_failure( 'Revalidation secret must contain at least 32 characters.' );
			return false;
		}

		if ( ! $this->is_allowed_target( $url ) ) {
			$this->log_failure( 'Revalidation target must use HTTPS unless insecure HTTP is explicitly allowed by filter.' );
			return false;
		}

		$body = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $body ) {
			$this->log_failure( 'Unable to encode revalidation payload as JSON.' );
			return false;
		}

		$timestamp = (string) time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		$timeout   = $this->get_timeout();

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'         => 'application/json; charset=utf-8',
					'Accept'               => 'application/json',
					self::TIMESTAMP_HEADER => $timestamp,
					self::SIGNATURE_HEADER => 'sha256=' . $signature,
					'User-Agent'           => 'Headless-API-Core/' . ( defined( 'HEADLESS_API_CORE_VERSION' ) ? HEADLESS_API_CORE_VERSION : 'unknown' ),
				),
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_failure( 'Revalidation request failed: ' . $response->get_error_message() );
			do_action( 'headless_api_core_revalidation_delivery', false, $payload, $response );
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$ok     = $status >= 200 && $status < 300;

		if ( ! $ok ) {
			$this->log_failure( 'Revalidation consumer returned HTTP ' . $status . '.' );
		}

		do_action( 'headless_api_core_revalidation_delivery', $ok, $payload, $response );

		return $ok;
	}

	/**
	 * Resolve target URL from wp-config/environment and filters.
	 *
	 * @return string
	 */
	private function get_url() {
		$value = '';

		if ( defined( 'HEADLESS_REVALIDATION_URL' ) ) {
			$value = (string) HEADLESS_REVALIDATION_URL;
		} elseif ( false !== getenv( 'HEADLESS_REVALIDATION_URL' ) ) {
			$value = (string) getenv( 'HEADLESS_REVALIDATION_URL' );
		}

		$value = (string) apply_filters( 'headless_api_core_revalidation_url', $value );

		return esc_url_raw( trim( $value ) );
	}

	/**
	 * Resolve shared secret. The secret is never exposed by a public endpoint.
	 *
	 * @return string
	 */
	private function get_secret() {
		$value = '';

		if ( defined( 'HEADLESS_REVALIDATION_SECRET' ) ) {
			$value = (string) HEADLESS_REVALIDATION_SECRET;
		} elseif ( false !== getenv( 'HEADLESS_REVALIDATION_SECRET' ) ) {
			$value = (string) getenv( 'HEADLESS_REVALIDATION_SECRET' );
		}

		return (string) apply_filters( 'headless_api_core_revalidation_secret', $value );
	}

	/**
	 * Resolve request timeout while preventing unexpectedly long editor waits.
	 *
	 * @return float
	 */
	private function get_timeout() {
		$value = self::DEFAULT_TIMEOUT;

		if ( defined( 'HEADLESS_REVALIDATION_TIMEOUT' ) ) {
			$value = (float) HEADLESS_REVALIDATION_TIMEOUT;
		} elseif ( false !== getenv( 'HEADLESS_REVALIDATION_TIMEOUT' ) ) {
			$value = (float) getenv( 'HEADLESS_REVALIDATION_TIMEOUT' );
		}

		$value = (float) apply_filters( 'headless_api_core_revalidation_timeout', $value );

		return max( 0.5, min( 10.0, $value ) );
	}

	/**
	 * Require HTTPS by default. Local development may opt in to HTTP through a
	 * filter without weakening production defaults.
	 *
	 * @param string $url Revalidation endpoint.
	 * @return bool
	 */
	private function is_allowed_target( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

		if ( 'https' === $scheme ) {
			return true;
		}

		return 'http' === $scheme && (bool) apply_filters( 'headless_api_core_revalidation_allow_insecure_http', false, $url );
	}

	/**
	 * Log delivery/configuration failures without secrets or signatures.
	 *
	 * @param string $message Safe diagnostic message.
	 * @return void
	 */
	private function log_failure( $message ) {
		if ( (bool) apply_filters( 'headless_api_core_revalidation_log_errors', true, $message ) ) {
			error_log( '[Headless API Core] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
