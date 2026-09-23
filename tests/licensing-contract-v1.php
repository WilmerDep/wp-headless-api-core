<?php
/**
 * Licensing contract v1 regression tests.
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

function wp_generate_uuid4() {
	return '11111111-2222-4333-8444-555555555555';
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Instance.php';
require_once __DIR__ . '/../includes/Licensing/License_Storage.php';
require_once __DIR__ . '/../includes/Licensing/License_Verifier.php';

use HeadlessApiCore\Licensing\License_Instance;
use HeadlessApiCore\Licensing\License_Storage;
use HeadlessApiCore\Licensing\License_Verifier;

$passed = 0;
$failed = 0;

function assert_test( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

function b64url( $value ) {
	return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}

function make_token( array $payload, $secret_key, $kid = 'pholio-test' ) {
	$header = array(
		'alg' => 'EdDSA',
		'typ' => 'PHOLIO-LICENSE',
		'kid' => $kid,
	);
	$header_b64 = b64url( json_encode( $header ) );
	$payload_b64 = b64url( json_encode( $payload ) );
	$input = $header_b64 . '.' . $payload_b64;
	$signature = sodium_crypto_sign_detached( $input, $secret_key );
	return $input . '.' . b64url( $signature );
}

if ( ! function_exists( 'sodium_crypto_sign_seed_keypair' ) ) {
	echo "SKIP: Sodium unavailable in this PHP runtime.\n";
	exit( 0 );
}

$seed = str_repeat( "\x01", SODIUM_CRYPTO_SIGN_SEEDBYTES );
$keypair = sodium_crypto_sign_seed_keypair( $seed );
$secret_key = sodium_crypto_sign_secretkey( $keypair );
$public_key = sodium_crypto_sign_publickey( $keypair );
$public_keys = array( 'pholio-test' => bin2hex( $public_key ) );

$now = strtotime( '2026-09-21T21:00:00+00:00' );
$payload = array(
	'version' => 1,
	'licenseId' => 'lic_test',
	'product' => 'wp-headless-api-core',
	'customerId' => 'cus_test',
	'plan' => 'institutional',
	'instanceId' => '11111111-2222-4333-8444-555555555555',
	'domain' => 'cms.example.com',
	'entitlements' => array( 'news', 'hero' ),
	'issuedAt' => '2026-09-21T20:00:00+00:00',
	'refreshAfter' => '2026-09-22T08:00:00+00:00',
	'offlineUntil' => '2026-09-28T20:00:00+00:00',
	'expiresAt' => '2027-09-21T20:00:00+00:00',
	'graceUntil' => '2027-10-21T20:00:00+00:00',
);

$token = make_token( $payload, $secret_key );
$result = License_Verifier::verify(
	$token,
	$public_keys,
	array(
		'now' => $now,
		'domain' => 'https://cms.example.com/wp-admin/',
		'instanceId' => $payload['instanceId'],
	)
);
assert_test( ! empty( $result['valid'] ) && 'active' === $result['status'], 'valid Ed25519 token verifies' );

$parts = explode( '.', $token );
$tampered_payload = $payload;
$tampered_payload['plan'] = 'enterprise';
$tampered_token = $parts[0] . '.' . b64url( json_encode( $tampered_payload ) ) . '.' . $parts[2];
$tampered = License_Verifier::verify( $tampered_token, $public_keys, array( 'now' => $now ) );
assert_test( empty( $tampered['valid'] ) && 'INVALID_SIGNATURE' === $tampered['code'], 'tampered payload is rejected' );

$unknown = License_Verifier::verify( make_token( $payload, $secret_key, 'unknown-kid' ), $public_keys, array( 'now' => $now ) );
assert_test( empty( $unknown['valid'] ) && 'UNKNOWN_KEY_ID' === $unknown['code'], 'unknown kid is rejected' );

$domain_mismatch = License_Verifier::verify( $token, $public_keys, array( 'now' => $now, 'domain' => 'other.example.com' ) );
assert_test( empty( $domain_mismatch['valid'] ) && 'DOMAIN_MISMATCH' === $domain_mismatch['code'], 'domain mismatch is rejected' );

$instance_mismatch = License_Verifier::verify( $token, $public_keys, array( 'now' => $now, 'instanceId' => 'different-instance' ) );
assert_test( empty( $instance_mismatch['valid'] ) && 'INSTANCE_MISMATCH' === $instance_mismatch['code'], 'instance mismatch is rejected' );

$offline = License_Verifier::verify( $token, $public_keys, array( 'now' => strtotime( '2026-09-29T20:00:00+00:00' ) ) );
assert_test( empty( $offline['valid'] ) && 'OFFLINE_TOLERANCE_EXCEEDED' === $offline['code'], 'offlineUntil is enforced' );

$expired = License_Verifier::verify( $token, $public_keys, array( 'now' => strtotime( '2027-10-22T20:00:00+00:00' ) ) );
assert_test( empty( $expired['valid'] ) && 'LICENSE_EXPIRED' === $expired['code'], 'graceUntil is enforced' );

$grace_payload = $payload;
$grace_payload['expiresAt'] = '2026-09-20T20:00:00+00:00';
$grace_payload['graceUntil'] = '2026-10-20T20:00:00+00:00';
$grace_payload['offlineUntil'] = '2026-09-28T20:00:00+00:00';
$grace = License_Verifier::verify( make_token( $grace_payload, $secret_key ), $public_keys, array( 'now' => $now ) );
assert_test( ! empty( $grace['valid'] ) && 'grace_period' === $grace['status'], 'grace period status is reported' );

$first_instance = License_Instance::get();
$second_instance = License_Instance::get();
assert_test( $first_instance === $second_instance && $first_instance === $payload['instanceId'], 'instance ID is persistent' );

License_Storage::set( array( 'token' => 'abc', 'verified' => true ) );
$stored = License_Storage::get();
assert_test( isset( $stored['token'] ) && 'abc' === $stored['token'], 'license state persists' );
License_Storage::clear();
assert_test( array() === License_Storage::get() && $first_instance === License_Instance::get(), 'clearing license state preserves instance ID' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
