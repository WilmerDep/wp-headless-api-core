<?php
/**
 * Licensing admin page registration regression test.
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

echo "Results: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
