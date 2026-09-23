<?php
/**
 * Focused Contract v1 hardening coverage for the WordPress verifier.
 */

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Verifier.php';

use HeadlessApiCore\Licensing\License_Verifier;

if ( ! function_exists( 'sodium_crypto_sign_seed_keypair' ) ) {
	echo "SKIP: Sodium unavailable in this PHP runtime.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function hardening_assert( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

function hardening_b64url( $value ) {
	return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}

function hardening_token( array $payload, $secret_key, $kid = 'hardening-test' ) {
	$header = array( 'alg' => 'EdDSA', 'typ' => 'PHOLIO-LICENSE', 'kid' => $kid );
	$header_b64  = hardening_b64url( json_encode( $header ) );
	$payload_b64 = hardening_b64url( json_encode( $payload ) );
	$input       = $header_b64 . '.' . $payload_b64;
	$signature   = sodium_crypto_sign_detached( $input, $secret_key );
	return $input . '.' . hardening_b64url( $signature );
}

$keypair    = sodium_crypto_sign_seed_keypair( str_repeat( "\x04", SODIUM_CRYPTO_SIGN_SEEDBYTES ) );
$secret_key = sodium_crypto_sign_secretkey( $keypair );
$public_key = sodium_crypto_sign_publickey( $keypair );
$keys       = array( 'hardening-test' => bin2hex( $public_key ) );
$now        = strtotime( '2026-09-22T20:00:00+00:00' );

$payload = array(
	'version'      => 1,
	'licenseId'    => 'lic_hardening',
	'product'      => 'wp-headless-api-core',
	'customerId'   => 'cus_hardening',
	'plan'         => 'institutional',
	'instanceId'   => 'instance-hardening',
	'domain'       => 'cms.example.com',
	'entitlements' => array( 'news', 'forms' ),
	'issuedAt'     => '2026-09-22T18:00:00+00:00',
	'refreshAfter' => '2026-09-23T06:00:00+00:00',
	'offlineUntil' => '2026-09-29T18:00:00+00:00',
	'expiresAt'    => '2027-09-22T18:00:00+00:00',
	'graceUntil'   => '2027-10-22T18:00:00+00:00',
);

$context = array(
	'now'        => $now,
	'product'    => 'wp-headless-api-core',
	'domain'     => 'cms.example.com',
	'instanceId' => 'instance-hardening',
);

$valid = License_Verifier::verify( hardening_token( $payload, $secret_key ), $keys, $context );
hardening_assert( ! empty( $valid['valid'] ), 'baseline hardened token verifies' );

$unsupported = $payload;
$unsupported['version'] = 2;
$result = License_Verifier::verify( hardening_token( $unsupported, $secret_key ), $keys, $context );
hardening_assert( 'UNSUPPORTED_TOKEN_VERSION' === ( $result['code'] ?? null ), 'unsupported token version has explicit code' );

$wrong_product = $payload;
$wrong_product['product'] = 'another-product';
$result = License_Verifier::verify( hardening_token( $wrong_product, $secret_key ), $keys, $context );
hardening_assert( 'PRODUCT_MISMATCH' === ( $result['code'] ?? null ), 'token is bound to expected product' );

$bad_entitlements = $payload;
$bad_entitlements['entitlements'] = array( 'news', 123 );
$result = License_Verifier::verify( hardening_token( $bad_entitlements, $secret_key ), $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'non-string entitlement is rejected' );

$empty_claim = $payload;
$empty_claim['customerId'] = '   ';
$result = License_Verifier::verify( hardening_token( $empty_claim, $secret_key ), $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'empty required string claim is rejected' );

$bad_refresh_order = $payload;
$bad_refresh_order['refreshAfter'] = '2026-09-22T17:00:00+00:00';
$result = License_Verifier::verify( hardening_token( $bad_refresh_order, $secret_key ), $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'refreshAfter before issuedAt is rejected' );

$bad_offline_order = $payload;
$bad_offline_order['offlineUntil'] = '2026-09-23T05:00:00+00:00';
$result = License_Verifier::verify( hardening_token( $bad_offline_order, $secret_key ), $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'offlineUntil before refreshAfter is rejected' );

$bad_grace_order = $payload;
$bad_grace_order['graceUntil'] = '2027-09-21T18:00:00+00:00';
$result = License_Verifier::verify( hardening_token( $bad_grace_order, $secret_key ), $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'graceUntil before expiresAt is rejected' );

$valid_token = hardening_token( $payload, $secret_key );
$parts       = explode( '.', $valid_token );
$short_sig   = $parts[0] . '.' . $parts[1] . '.' . hardening_b64url( str_repeat( "\x01", 12 ) );
$result      = License_Verifier::verify( $short_sig, $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'malformed signature length is rejected before sodium verification' );

$result = License_Verifier::verify( $valid_token, array( 'hardening-test' => 'zz-not-hex' ), $context );
hardening_assert( 'UNKNOWN_KEY_ID' === ( $result['code'] ?? null ), 'invalid configured public key encoding is rejected safely' );

$parts = explode( '.', $valid_token );
$invalid_b64 = $parts[0] . '.' . $parts[1] . '.***';
$result = License_Verifier::verify( $invalid_b64, $keys, $context );
hardening_assert( 'TOKEN_MALFORMED' === ( $result['code'] ?? null ), 'invalid base64url alphabet is rejected' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
