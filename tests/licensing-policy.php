<?php
/**
 * Licensing enforcement policy regression tests.
 *
 * @package HeadlessApiCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Policy.php';

use HeadlessApiCore\Licensing\License_Policy;

$passed = 0;
$failed = 0;

function assert_policy( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

$admin_expired = License_Policy::evaluate(
	License_Policy::STATE_EXPIRED,
	License_Policy::CAPABILITY_ADMINISTRATIVE,
	false
);
assert_policy( true === $admin_expired['allowed'], 'expired license keeps administrative access' );

$public_active = License_Policy::evaluate(
	License_Policy::STATE_ACTIVE,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	true
);
assert_policy( true === $public_active['allowed'] && 'allow' === $public_active['mode'], 'active entitled public content is allowed' );

$public_grace = License_Policy::evaluate(
	License_Policy::STATE_GRACE,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	true
);
assert_policy( true === $public_grace['allowed'] && 'warning' === $public_grace['noticeLevel'], 'grace period remains public with warning' );

$public_expired = License_Policy::evaluate(
	License_Policy::STATE_EXPIRED,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	true
);
assert_policy(
	false === $public_expired['allowed'] && 'LICENSE_RENEWAL_REQUIRED' === $public_expired['code'],
	'expired public content is restricted with renewal code'
);

$public_offline = License_Policy::evaluate(
	License_Policy::STATE_OFFLINE_EXCEEDED,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	true
);
assert_policy(
	false === $public_offline['allowed'] && 'LICENSE_REVALIDATION_REQUIRED' === $public_offline['code'],
	'offline tolerance exceeded restricts public content until revalidation'
);

$critical_offline = License_Policy::evaluate(
	License_Policy::STATE_OFFLINE_EXCEEDED,
	License_Policy::CAPABILITY_CRITICAL_TRANSACTION,
	true
);
assert_policy( true === $critical_offline['allowed'], 'critical transaction survives offline revalidation state' );

$critical_expired = License_Policy::evaluate(
	License_Policy::STATE_EXPIRED,
	License_Policy::CAPABILITY_CRITICAL_TRANSACTION,
	true
);
assert_policy( true === $critical_expired['allowed'], 'critical transaction survives ordinary expiration' );

$critical_suspended = License_Policy::evaluate(
	License_Policy::STATE_SUSPENDED,
	License_Policy::CAPABILITY_CRITICAL_TRANSACTION,
	true
);
assert_policy( true === $critical_suspended['allowed'], 'critical transaction survives ordinary suspension' );

$critical_revoked = License_Policy::evaluate(
	License_Policy::STATE_REVOKED,
	License_Policy::CAPABILITY_CRITICAL_TRANSACTION,
	true
);
assert_policy( false === $critical_revoked['allowed'] && 'LICENSE_REVOKED' === $critical_revoked['code'], 'revocation blocks critical transaction' );

$missing_entitlement = License_Policy::evaluate(
	License_Policy::STATE_ACTIVE,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	false
);
assert_policy( false === $missing_entitlement['allowed'] && 'ENTITLEMENT_REQUIRED' === $missing_entitlement['code'], 'missing entitlement restricts module' );

$untrusted_public = License_Policy::evaluate(
	License_Policy::STATE_UNTRUSTED,
	License_Policy::CAPABILITY_PUBLIC_CONTENT,
	true
);
assert_policy( false === $untrusted_public['allowed'] && 'LICENSE_VERIFICATION_REQUIRED' === $untrusted_public['code'], 'untrusted public state fails closed' );

$untrusted_admin = License_Policy::evaluate(
	License_Policy::STATE_UNTRUSTED,
	License_Policy::CAPABILITY_ADMINISTRATIVE,
	false
);
assert_policy( true === $untrusted_admin['allowed'] && 'error' === $untrusted_admin['noticeLevel'], 'untrusted license preserves admin recovery access with error notice' );

$premium_grace = License_Policy::evaluate(
	License_Policy::STATE_GRACE,
	License_Policy::CAPABILITY_PREMIUM,
	true
);
assert_policy( true === $premium_grace['allowed'], 'premium capability remains available during grace when entitled' );

$premium_expired = License_Policy::evaluate(
	License_Policy::STATE_EXPIRED,
	License_Policy::CAPABILITY_PREMIUM,
	true
);
assert_policy( false === $premium_expired['allowed'], 'premium capability is restricted after grace expiration' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
