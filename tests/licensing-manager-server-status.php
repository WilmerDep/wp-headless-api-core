<?php
/**
 * Regression coverage for authoritative licensing server status persistence.
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		public function get_error_data() {
			return $this->data;
		}
	}

	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}

	function home_url( $path = '/' ) {
		return 'https://cms.example.com' . $path;
	}

	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

namespace HeadlessApiCore\Licensing {
	final class License_Storage {
		public static $state = array();
		public static function get() { return self::$state; }
		public static function set( array $state ) { self::$state = $state; }
		public static function clear() { self::$state = array(); }
	}

	final class License_Instance {
		public static function get() { return 'instance-test'; }
	}

	final class License_Client {
		public static $refresh_response;
		public static function activate( $license_key, $domain ) { return array(); }
		public static function refresh( $token, $domain ) { return self::$refresh_response; }
		public static function deactivate( $token ) { return array( 'ok' => true ); }
	}

	final class License_Verifier {
		public static function verify( $token, array $public_keys, array $context = array() ) {
			return array(
				'trusted'     => true,
				'valid'       => true,
				'operational' => true,
				'status'      => 'active',
				'code'        => null,
				'message'     => null,
				'payload'     => array( 'entitlements' => array( 'news' ) ),
				'header'      => array( 'kid' => 'pholio-2026-01' ),
			);
		}
	}

	require_once __DIR__ . '/../includes/Licensing/License_Manager.php';

	$passed = 0;
	$failed = 0;

	function manager_status_assert( $condition, $label ) {
		global $passed, $failed;
		if ( $condition ) {
			echo "PASS: {$label}\n";
			$passed++;
			return;
		}
		echo "FAIL: {$label}\n";
		$failed++;
	}

	License_Storage::$state = array(
		'token'   => 'signed-token',
		'license' => array( 'status' => 'active' ),
	);

	License_Client::$refresh_response = new \WP_Error(
		'licensing_http_error',
		'This license is suspended',
		array(
			'status'   => 403,
			'response' => array( 'code' => 'LICENSE_SUSPENDED' ),
		)
	);

	$result = License_Manager::refresh();
	manager_status_assert( \is_wp_error( $result ), 'refresh returns original server error' );
	manager_status_assert( 'suspended' === ( License_Storage::$state['serverStatus'] ?? null ), 'suspended server status is persisted' );
	manager_status_assert( 'suspended' === ( License_Storage::$state['license']['status'] ?? null ), 'stored license summary reflects suspension' );

	$verification = License_Manager::verify_stored();
	manager_status_assert( ! empty( $verification['trusted'] ), 'stored signed token remains cryptographically trusted' );
	manager_status_assert( empty( $verification['operational'] ), 'suspended license is not operational' );
	manager_status_assert( 'suspended' === ( $verification['status'] ?? null ), 'suspended server state overrides temporal active state' );
	manager_status_assert( 'LICENSE_SUSPENDED' === ( $verification['code'] ?? null ), 'suspended state exposes canonical code' );

	License_Client::$refresh_response = array(
		'ok'      => true,
		'token'   => 'fresh-signed-token',
		'license' => array(
			'status'       => 'active',
			'plan'         => 'institutional',
			'expiresAt'    => '2027-09-23T00:00:00+00:00',
			'graceUntil'   => '2027-10-23T00:00:00+00:00',
			'entitlements' => array( 'news' ),
		),
	);

	$result = License_Manager::refresh();
	manager_status_assert( ! \is_wp_error( $result ), 'successful refresh clears restrictive status' );
	manager_status_assert( 'active' === ( License_Storage::$state['serverStatus'] ?? null ), 'successful refresh persists current active server status' );
	$verification = License_Manager::verify_stored();
	manager_status_assert( ! empty( $verification['operational'] ), 'reactivated license becomes operational after successful refresh' );
	manager_status_assert( 'active' === ( $verification['status'] ?? null ), 'active temporal state is restored after successful refresh' );

	License_Client::$refresh_response = new \WP_Error(
		'licensing_http_error',
		'This license has been revoked',
		array(
			'status'   => 403,
			'response' => array( 'code' => 'LICENSE_REVOKED' ),
		)
	);
	License_Manager::refresh();
	$verification = License_Manager::verify_stored();
	manager_status_assert( 'revoked' === ( $verification['status'] ?? null ), 'revoked server state is persisted and enforced' );
	manager_status_assert( 'LICENSE_REVOKED' === ( $verification['code'] ?? null ), 'revoked state exposes canonical code' );

	echo "Results: {$passed} passed, {$failed} failed.\n";
	exit( $failed > 0 ? 1 : 0 );
}
