<?php
/**
 * Central licensing gate regression tests.
 *
 * @package HeadlessApiCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Policy.php';
require_once __DIR__ . '/../includes/Licensing/License_Gate.php';

use HeadlessApiCore\Licensing\License_Gate;
use HeadlessApiCore\Licensing\License_Policy;

$passed = 0;
$failed = 0;

function gate_assert( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

$active = array(
	'trusted' => true,
	'operational' => true,
	'status' => 'active',
);
$grace = array(
	'trusted' => true,
	'operational' => true,
	'status' => 'grace_period',
);
$expired = array(
	'trusted' => true,
	'operational' => false,
	'status' => 'expired',
);
$offline_exceeded = array(
	'trusted' => true,
	'operational' => false,
	'status' => 'active',
	'code' => 'OFFLINE_TOLERANCE_EXCEEDED',
);
$untrusted = array(
	'trusted' => false,
	'operational' => false,
	'status' => 'active',
);

$decision = License_Gate::decide( $active, License_Policy::CAPABILITY_PUBLIC_CONTENT, true, 'news' );
gate_assert( ! empty( $decision['allowed'] ) && 'allow' === $decision['mode'], 'active public content with entitlement is allowed' );
gate_assert( true === $decision['trusted'] && true === $decision['operational'] && 'active' === $decision['status'], 'gate exposes active trust and operational state' );

$decision = License_Gate::decide( $active, License_Policy::CAPABILITY_PUBLIC_CONTENT, false, 'news' );
gate_assert( empty( $decision['allowed'] ) && 'ENTITLEMENT_REQUIRED' === $decision['code'], 'missing entitlement restricts active public content' );

$decision = License_Gate::decide( $grace, License_Policy::CAPABILITY_PUBLIC_CONTENT, true, 'news' );
gate_assert( ! empty( $decision['allowed'] ) && 'warning' === $decision['noticeLevel'], 'grace period preserves public content with warning' );

$decision = License_Gate::decide( $expired, License_Policy::CAPABILITY_PUBLIC_CONTENT, true, 'news' );
gate_assert( empty( $decision['allowed'] ) && 'LICENSE_RENEWAL_REQUIRED' === $decision['code'], 'expired public content is restricted after grace' );
gate_assert( true === $decision['trusted'] && false === $decision['operational'], 'expired token can remain trusted while non-operational' );

$decision = License_Gate::decide( $offline_exceeded, License_Policy::CAPABILITY_PUBLIC_CONTENT, true, 'news' );
gate_assert( empty( $decision['allowed'] ) && 'LICENSE_REVALIDATION_REQUIRED' === $decision['code'], 'offline tolerance exceeded restricts public content until revalidation' );
gate_assert( 'offline_tolerance_exceeded' === $decision['status'], 'offline tolerance receives a distinct trusted policy state' );

$decision = License_Gate::decide( $offline_exceeded, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true, 'forms' );
gate_assert( ! empty( $decision['allowed'] ), 'critical transaction survives offline revalidation state' );

$decision = License_Gate::decide( $expired, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true, 'forms' );
gate_assert( ! empty( $decision['allowed'] ) && 'allow' === $decision['mode'], 'critical transaction survives ordinary expiration' );

$decision = License_Gate::decide( $expired, License_Policy::CAPABILITY_ADMINISTRATIVE, false, null );
gate_assert( ! empty( $decision['allowed'] ), 'administrative capability remains available after expiration' );

$decision = License_Gate::decide( $untrusted, License_Policy::CAPABILITY_PUBLIC_CONTENT, true, 'news' );
gate_assert( empty( $decision['allowed'] ) && 'LICENSE_VERIFICATION_REQUIRED' === $decision['code'], 'untrusted token blocks public content regardless of claimed entitlement' );
gate_assert( 'untrusted' === $decision['status'], 'untrusted verification cannot inject an active policy state' );

$revoked = array(
	'trusted' => true,
	'operational' => false,
	'status' => 'revoked',
);
$decision = License_Gate::decide( $revoked, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true, 'forms' );
gate_assert( empty( $decision['allowed'] ) && 'LICENSE_REVOKED' === $decision['code'], 'revocation blocks critical transactions' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
