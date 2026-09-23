<?php
/**
 * Licensing administrator notice regression tests.
 *
 * @package HeadlessApiCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Policy.php';
require_once __DIR__ . '/../includes/Licensing/License_Gate.php';
require_once __DIR__ . '/../includes/Licensing/License_Admin_Notice.php';

use HeadlessApiCore\Licensing\License_Admin_Notice;

$passed = 0;
$failed = 0;

function admin_notice_assert( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

$active = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => true,
		'status' => 'active',
	)
);
admin_notice_assert( null === $active, 'active operational license does not create an admin notice' );

$grace = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => true,
		'status' => 'grace_period',
	)
);
admin_notice_assert( 'warning' === $grace['level'] && 'LICENSE_GRACE_PERIOD' === $grace['code'], 'grace period creates renewal warning without blocking admin' );

$offline = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => false,
		'status' => 'active',
		'code' => 'OFFLINE_TOLERANCE_EXCEEDED',
	)
);
admin_notice_assert( 'warning' === $offline['level'] && 'LICENSE_REVALIDATION_REQUIRED' === $offline['code'], 'offline tolerance creates revalidation warning' );

$expired = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => false,
		'status' => 'expired',
		'code' => 'LICENSE_EXPIRED',
	)
);
admin_notice_assert( 'warning' === $expired['level'] && 'LICENSE_RENEWAL_REQUIRED' === $expired['code'], 'expired license creates renewal warning' );
admin_notice_assert( false !== strpos( $expired['message'], 'administrando' ), 'expired notice explicitly preserves WordPress administration' );

$suspended = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => false,
		'status' => 'suspended',
	)
);
admin_notice_assert( 'warning' === $suspended['level'] && 'LICENSE_SUSPENDED' === $suspended['code'], 'suspended license creates warning' );

$revoked = License_Admin_Notice::build_notice(
	array(
		'trusted' => true,
		'operational' => false,
		'status' => 'revoked',
	)
);
admin_notice_assert( 'error' === $revoked['level'] && 'LICENSE_REVOKED' === $revoked['code'], 'revoked license creates error notice' );

$missing = License_Admin_Notice::build_notice(
	array(
		'trusted' => false,
		'operational' => false,
		'code' => 'TOKEN_MISSING',
	)
);
admin_notice_assert( 'error' === $missing['level'] && 'TOKEN_MISSING' === $missing['code'], 'missing token creates activation-required error' );

$unavailable = License_Admin_Notice::build_notice(
	array(
		'trusted' => false,
		'operational' => false,
		'code' => 'VERIFICATION_UNAVAILABLE',
	)
);
admin_notice_assert( 'error' === $unavailable['level'] && false !== strpos( $unavailable['message'], 'Sodium' ), 'missing Sodium creates explicit verification error' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
