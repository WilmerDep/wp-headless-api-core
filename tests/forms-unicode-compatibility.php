<?php
/**
 * Forms unicode compatibility regression contract.
 */

define( 'ABSPATH', __DIR__ );

$root      = dirname( __DIR__ );
$compat    = file_get_contents( $root . '/modules/Forms/Forms_Compatibility.php' );
$editorial = file_get_contents( $root . '/assets/admin/forms-editorial-ux.js' );
$boot      = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$plugin    = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		'added_post_meta',
		'updated_post_meta',
		'repair_existing_meta_once',
		'JSON_UNESCAPED_UNICODE',
		'wp_slash( $canonical )',
		'/u([0-9a-fA-F]{4})/',
		'/u(d[89ab][0-9a-f]{2})u(d[cdef][0-9a-f]{2})/i',
		'headless_api_core_forms_json_unicode_v076',
	) as $needle
) {
	if ( false === strpos( $compat, $needle ) ) {
		fwrite( STDERR, "Forms unicode compatibility contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $editorial, 'Subtítulo' ) ) {
	fwrite( STDERR, "Forms editorial layer lost the Spanish subtitle label.\n" );
	exit( 1 );
}

if ( false === strpos( $boot, 'new Forms_Compatibility()' ) || false === strpos( $boot, '$compatibility->register()' ) ) {
	fwrite( STDERR, "Forms compatibility migration is not registered.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'modules/Forms/Forms_Compatibility.php' ) ) {
	fwrite( STDERR, "Forms compatibility file is not loaded by the plugin bootstrap.\n" );
	exit( 1 );
}

preg_match( '/\* Version:\s*([0-9.]+)/', $plugin, $version_match );
$version = isset( $version_match[1] ) ? $version_match[1] : '';
if ( '' === $version || version_compare( $version, '0.7.6', '<' ) ) {
	fwrite( STDERR, "Forms unicode compatibility requires plugin version 0.7.6 or newer.\n" );
	exit( 1 );
}

echo "Forms unicode compatibility contract test passed.\n";
