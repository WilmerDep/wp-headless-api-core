<?php
/**
 * Forms unicode compatibility regression contract.
 */

define( 'ABSPATH', __DIR__ );

$root   = dirname( __DIR__ );
$compat = file_get_contents( $root . '/modules/Forms/Forms_Compatibility.php' );
$boot   = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$plugin = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		'added_post_meta',
		'updated_post_meta',
		'repair_existing_meta_once',
		'JSON_UNESCAPED_UNICODE',
		'wp_slash( $canonical )',
		"'Subtítulo'",
		'/u00([0-9a-fA-F]{2})/',
	) as $needle
) {
	if ( false === strpos( $compat, $needle ) ) {
		fwrite( STDERR, "Forms unicode compatibility contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $boot, 'new Forms_Compatibility()' ) || false === strpos( $boot, '$compatibility->register()' ) ) {
	fwrite( STDERR, "Forms compatibility migration is not registered.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'modules/Forms/Forms_Compatibility.php' ) ) {
	fwrite( STDERR, "Forms compatibility file is not loaded by the plugin bootstrap.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'Version: 0.7.4' ) ) {
	fwrite( STDERR, "Plugin version was not bumped to 0.7.4.\n" );
	exit( 1 );
}

echo "Forms unicode compatibility contract test passed.\n";
