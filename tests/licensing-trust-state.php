<?php
/**
 * Regression coverage for cryptographic trust versus operational state.
 *
 * @package HeadlessApiCore
 */

$test_options = array();

function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}

function add_option( $key, $value ) {
	global $test_options;
	if ( array_key_exists( $key, $test_options ) ) {
		return false;
	}
	$test_options[ $key ] = $value;
	return true;
}

function update_option( $key, $value ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	global $test_options;
	unset( $test_options[ $key ] );
	return true;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Storage.php';
require_once __DIR__ . '/../includes/Licensing/License_Verifier.php';
require_once __DIR__ . '/../includes/Licensing/License_Entitlements.php';

use HeadlessApiCore\Licensing\License_Entitlements;
use HeadlessApiCore\Licensing\License_Storage;
use HeadlessApiCore\Licensing\License_Verifier;

if ( ! function_exists( 'sodium_crypto_sign_seed_keypair' ) ) {
	echo "SKIP: Sodium unavailable in this PHP runtime.\n";
	exit( 0 );
}

function assert_trust_state( $condition, $label ) {
	if ( ! $condition ) {
		echo "FAIL: {$label}\n";
		exit( 1 );
	}
	echo "PASS: {$label}\n";
}

function trust_b64url( $value ) {
	return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}

function trust_token( array $payload, $secret_key ) {
	$header = array( 'alg' => 'EdDSA', 'typ' => 'PHOLIO-LICENSE', 'kid' => 'trust-test' );
	$header_b64 = trust_b64url( json_encode( $header ) );
	$payload_b64 = trust_b64url( json_encode( $payload ) );
	$input = $header_b64 . '.' . $payload_b64;
	$signature = sodium_crypto_sign_detached( $input, $secret_key );
	return $input . '.' . trust_b64url( $signature );
}

$keypair = sodium_crypto_sign_seed_keypair( str_repeat( "\x02", SODIUM_CRYPTO_SIGN_SEEDBYTES ) );
$secret_key = sodium_crypto_sign_secretkey( $keypair );
$public_key = sodium_crypto_sign_publickey( $keypair );
$keys = array( 'trust-test' => bin2hex( $public_key ) );

$payload = array(
	'version' => 1,
	'licenseId' => 'lic_trust',
	'product' => 'wp-headless-api-core',
	'customerId' => 'cus_trust',
	'plan' => 'institutional',
	'instanceId' => 'instance-trust',
	'domain' => 'cms.example.com',
	'entitlements' => array( 'news', 'forms', 'mail' ),
	'issuedAt' => '2026-09-01T00:00:00+00:00',
	'refreshAfter' => '2026-09-01T12:00:00+00:00',
	'offlineUntil' => '2026-09-08T00:00:00+00:00',
	'expiresAt' => '2026-09-10T00:00:00+00:00',
	'graceUntil' => '2026-09-20T00:00:00+00:00',
);

$token = trust_token( $payload, $secret_key );
$context = array( 'domain' => 'cms.example.com', 'instanceId' => 'instance-trust' );

$expired_context = $context;
$expired_context['now'] = strtotime( '2026-09-21T00:00:00+00:00' );
$expired = License_Verifier::verify( $token, $keys, $expired_context );
assert_trust_state( ! empty( $expired['trusted'] ), 'expired signed token remains cryptographically trusted' );
assert_trust_state( empty( $expired['operational'] ) && 'expired' === $expired['status'], 'expired signed token is not operational' );
assert_trust_state( 'LICENSE_EXPIRED' === $expired['code'], 'expired operational result keeps explicit code' );

$offline_context = $context;
$offline_context['now'] = strtotime( '2026-09-09T00:00:00+00:00' );
$offline = License_Verifier::verify( $token, $keys, $offline_context );
assert_trust_state( ! empty( $offline['trusted'] ) && empty( $offline['operational'] ), 'offline tolerance stops operation without destroying trust' );
assert_trust_state( 'OFFLINE_TOLERANCE_EXCEEDED' === $offline['code'], 'offline tolerance has explicit operational code' );

License_Storage::set( array( 'trusted' => true, 'operational' => false, 'payload' => $payload ) );
assert_trust_state( License_Entitlements::has( 'forms' ) && License_Entitlements::has( 'mail' ), 'trusted non-operational state can still expose signed critical entitlements to policy' );
assert_trust_state( ! License_Entitlements::has( 'directory' ), 'missing entitlement stays denied' );

License_Storage::set( array( 'trusted' => false, 'operational' => false, 'payload' => $payload ) );
assert_trust_state( ! License_Entitlements::has( 'forms' ), 'untrusted state cannot grant entitlements' );

echo "Results: trust/operational separation passed.\n";
