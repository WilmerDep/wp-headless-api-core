<?php
/**
 * Licensing integration contract for modules introduced after the original
 * News/Hero/Directory licensing work.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$root = dirname( __DIR__ );
require_once $root . '/includes/Licensing/License_Policy.php';

use HeadlessApiCore\Licensing\License_Policy;

$failed = 0;

function advanced_license_assert( $condition, $label ) {
	global $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		return;
	}
	fwrite( STDERR, "FAIL: {$label}\n" );
	$failed++;
}

function advanced_source_contains( $path, array $needles, $label ) {
	$source = file_get_contents( $path );
	if ( false === $source ) {
		advanced_license_assert( false, $label . ' source readable' );
		return;
	}
	foreach ( $needles as $needle ) {
		advanced_license_assert( false !== strpos( $source, $needle ), $label . ' contains ' . $needle );
	}
}

advanced_source_contains(
	$root . '/modules/Services/Services_Controller.php',
	array(
		"License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'services' )",
		"'LICENSE_REVALIDATION_REQUIRED'",
	),
	'Services licensing'
);

advanced_source_contains(
	$root . '/modules/SiteIdentity/Site_Identity_Controller.php',
	array(
		"License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'site-identity' )",
		"'LICENSE_REVALIDATION_REQUIRED'",
	),
	'Site Identity licensing'
);

advanced_source_contains(
	$root . '/modules/Mail/Mail_Controller.php',
	array(
		"License_Gate::evaluate( License_Policy::CAPABILITY_PUBLIC_CONTENT, 'mail' )",
		"'LICENSE_REVALIDATION_REQUIRED'",
	),
	'Mail status licensing'
);

advanced_source_contains(
	$root . '/modules/Forms/Forms_Controller.php',
	array(
		"License_Policy::CAPABILITY_PUBLIC_CONTENT, 'forms'",
		"License_Policy::CAPABILITY_CRITICAL_TRANSACTION, 'forms'",
		"License_Policy::CAPABILITY_CRITICAL_TRANSACTION, 'mail'",
	),
	'Forms licensing'
);

foreach ( array(
	License_Policy::STATE_EXPIRED,
	License_Policy::STATE_SUSPENDED,
	License_Policy::STATE_OFFLINE_EXCEEDED,
) as $state ) {
	$decision = License_Policy::evaluate( $state, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true );
	advanced_license_assert( ! empty( $decision['allowed'] ), "critical Forms/Mail continuity allowed in {$state}" );
}

$revoked = License_Policy::evaluate( License_Policy::STATE_REVOKED, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true );
advanced_license_assert( empty( $revoked['allowed'] ) && 'LICENSE_REVOKED' === ( $revoked['code'] ?? null ), 'revoked critical transaction is blocked' );

$untrusted = License_Policy::evaluate( License_Policy::STATE_UNTRUSTED, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, true );
advanced_license_assert( empty( $untrusted['allowed'] ) && 'LICENSE_VERIFICATION_REQUIRED' === ( $untrusted['code'] ?? null ), 'untrusted critical transaction is blocked' );

$missing_entitlement = License_Policy::evaluate( License_Policy::STATE_ACTIVE, License_Policy::CAPABILITY_CRITICAL_TRANSACTION, false );
advanced_license_assert( empty( $missing_entitlement['allowed'] ) && 'ENTITLEMENT_REQUIRED' === ( $missing_entitlement['code'] ?? null ), 'critical transaction still requires entitlement' );

$public_expired = License_Policy::evaluate( License_Policy::STATE_EXPIRED, License_Policy::CAPABILITY_PUBLIC_CONTENT, true );
advanced_license_assert( empty( $public_expired['allowed'] ) && 'LICENSE_RENEWAL_REQUIRED' === ( $public_expired['code'] ?? null ), 'expired public content is restricted' );

$bootstrap = file_get_contents( $root . '/wp-headless-api-core.php' );
advanced_license_assert( false !== strpos( $bootstrap, 'Version: 0.8.2' ), 'integration package version is 0.8.2' );
advanced_license_assert( false !== strpos( $bootstrap, 'Author: PholioDev' ), 'plugin author branding is PholioDev' );
foreach ( array( 'modules/Mail/', 'modules/Forms/', 'modules/SiteIdentity/', 'modules/News/', 'modules/Hero/', 'modules/Directory/', 'modules/Services/', 'includes/Licensing/' ) as $module_path ) {
	advanced_license_assert( false !== strpos( $bootstrap, $module_path ), "bootstrap preserves {$module_path}" );
}

echo $failed ? "Advanced licensing integration failed: {$failed} assertion(s).\n" : "Advanced licensing integration contract passed.\n";
exit( $failed > 0 ? 1 : 0 );
