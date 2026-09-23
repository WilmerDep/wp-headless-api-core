<?php
/**
 * Licensing admin page registration and lifecycle-message regression test.
 *
 * @package HeadlessApiCore
 */

$registered_actions = array();

function add_action( $hook, $callback ) {
	global $registered_actions;
	$registered_actions[] = array( $hook, $callback );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/Licensing/License_Admin_Page.php';

use HeadlessApiCore\Licensing\License_Admin_Page;

$passed = 0;
$failed = 0;

function assert_admin_page_test( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		$passed++;
		return;
	}

	echo "FAIL: {$label}\n";
	$failed++;
}

License_Admin_Page::register();

$hooks = array_map(
	static function ( $entry ) {
		return $entry[0];
	},
	$registered_actions
);

assert_admin_page_test( in_array( 'admin_menu', $hooks, true ), 'settings page registration hook is present' );
assert_admin_page_test( in_array( 'admin_post_headless_api_core_license_activate', $hooks, true ), 'activation action hook is present' );
assert_admin_page_test( in_array( 'admin_post_headless_api_core_license_refresh', $hooks, true ), 'refresh action hook is present' );
assert_admin_page_test( in_array( 'admin_post_headless_api_core_license_deactivate', $hooks, true ), 'deactivation action hook is present' );
assert_admin_page_test( 'headless-api-core-license' === License_Admin_Page::PAGE_SLUG, 'settings page slug remains stable' );

$activated = License_Admin_Page::build_operation_notice( 'success', 'activate' );
assert_admin_page_test( 'success' === $activated['level'], 'activation success uses success notice level' );
assert_admin_page_test( false !== strpos( $activated['message'], 'activó correctamente' ), 'activation success copy is specific and clean' );

$refreshed = License_Admin_Page::build_operation_notice( 'success', 'refresh' );
assert_admin_page_test( false !== strpos( $refreshed['message'], 'revalidó correctamente' ), 'refresh success copy describes revalidation' );

$deactivated = License_Admin_Page::build_operation_notice( 'success', 'deactivate' );
assert_admin_page_test( false !== strpos( $deactivated['message'], 'desactivó correctamente' ), 'deactivation success copy is explicit' );

$suspended = License_Admin_Page::build_operation_notice( 'error', 'refresh', 'LICENSE_SUSPENDED' );
assert_admin_page_test( 'warning' === $suspended['level'], 'suspended lifecycle response is a warning instead of generic HTTP error' );
assert_admin_page_test( false !== strpos( $suspended['message'], 'licencia está suspendida' ), 'suspended copy explains the real lifecycle state' );

$expired = License_Admin_Page::build_operation_notice( 'error', 'refresh', 'license_expired' );
assert_admin_page_test( 'warning' === $expired['level'], 'expired lifecycle response is normalized case-insensitively' );
assert_admin_page_test( false !== strpos( $expired['message'], 'licencia está vencida' ), 'expired copy explains renewal action' );

$revoked = License_Admin_Page::build_operation_notice( 'error', 'refresh', 'LICENSE_REVOKED' );
assert_admin_page_test( 'error' === $revoked['level'], 'revoked lifecycle response stays an error' );
assert_admin_page_test( false !== strpos( $revoked['message'], 'fue revocada' ), 'revoked copy explains irreversibility' );

$generic = License_Admin_Page::build_operation_notice( 'error', 'refresh', 'UNKNOWN_SERVER_FAILURE' );
assert_admin_page_test( 'error' === $generic['level'], 'unknown licensing error falls back safely' );
assert_admin_page_test( false === strpos( $generic['message'], 'UNKNOWN_SERVER_FAILURE' ), 'unknown technical code is not exposed in user-facing copy' );

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
