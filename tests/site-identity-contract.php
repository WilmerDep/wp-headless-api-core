<?php
/**
 * Site Identity / Branding public contract regression test.
 */

define( 'ABSPATH', __DIR__ );

$root         = dirname( __DIR__ );
$serializer   = file_get_contents( $root . '/modules/SiteIdentity/Site_Identity_Serializer.php' );
$controller   = file_get_contents( $root . '/modules/SiteIdentity/Site_Identity_Controller.php' );
$revalidation = file_get_contents( $root . '/modules/SiteIdentity/Site_Identity_Revalidation.php' );
$module       = file_get_contents( $root . '/modules/SiteIdentity/Site_Identity_Module.php' );
$plugin       = file_get_contents( $root . '/wp-headless-api-core.php' );
$core         = file_get_contents( $root . '/includes/Core/Plugin.php' );

foreach (
	array(
		"'schemaVersion'",
		"'site'",
		"'branding'",
		"'logo'",
		"'favicon'",
		"get_option( 'site_logo'",
		"get_theme_mod( 'custom_logo'",
		"get_option( 'site_icon'",
		'get_site_icon_url(',
		"'kind' => 'text'",
		"'kind'    => 'initial'",
		"'modifiedAt'",
	) as $needle
) {
	if ( false === strpos( $serializer, $needle ) ) {
		fwrite( STDERR, "Site Identity serializer contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach ( array( "'/site'", 'permission_callback', 'no-store' ) as $needle ) {
	if ( false === strpos( $controller, $needle ) ) {
		fwrite( STDERR, "Site Identity controller contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		"'updated_option'",
		"'added_option'",
		"'deleted_option'",
		"'site_icon'",
		"'site_logo'",
		"'theme_mods_'",
		"'resource' => 'site_identity'",
		"'event'    => 'identity_updated'",
	) as $needle
) {
	if ( false === strpos( $revalidation, $needle ) ) {
		fwrite( STDERR, "Site Identity revalidation contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $module, 'new Site_Identity_Serializer()' ) || false === strpos( $module, 'new Site_Identity_Revalidation(' ) ) {
	fwrite( STDERR, "Site Identity module is not fully bootstrapped.\n" );
	exit( 1 );
}

foreach (
	array(
		'modules/SiteIdentity/Site_Identity_Serializer.php',
		'modules/SiteIdentity/Site_Identity_Controller.php',
		'modules/SiteIdentity/Site_Identity_Revalidation.php',
		'modules/SiteIdentity/Site_Identity_Module.php',
	) as $path
) {
	if ( false === strpos( $plugin, $path ) ) {
		fwrite( STDERR, "Plugin bootstrap missing Site Identity file: {$path}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $core, 'Site_Identity_Module::boot();' ) ) {
	fwrite( STDERR, "Core bootstrap does not boot Site Identity.\n" );
	exit( 1 );
}

if ( false !== stripos( $serializer . $controller . $revalidation . $module, 'hosgedopol' ) ) {
	fwrite( STDERR, "Site Identity runtime must remain institution-agnostic.\n" );
	exit( 1 );
}

echo "Site Identity native branding contract test passed.\n";
