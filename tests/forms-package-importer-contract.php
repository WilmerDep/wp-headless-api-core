<?php
/**
 * Forms JSON package importer contract test.
 */

define( 'ABSPATH', __DIR__ );

$root     = dirname( __DIR__ );
$importer = file_get_contents( $root . '/modules/Forms/Forms_Package_Importer.php' );
$module   = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$plugin   = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		"add_submenu_page(",
		"admin_post_headless_forms_package_preview",
		"admin_post_headless_forms_package_import",
		"admin_post_headless_forms_package_example",
		"schemaVersion",
		"create_only",
		"upsert",
		"get_page_by_path( $item['slug']",
		"wp_insert_post(",
		"wp_update_post(",
	) as $needle
) {
	if ( false === strpos( $importer, $needle ) ) {
		fwrite( STDERR, "Forms package importer lost required behavior: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $module, 'new Forms_Package_Importer()' ) || false === strpos( $module, '$importer->register()' ) ) {
	fwrite( STDERR, "Forms package importer is not registered by Forms_Module.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, "modules/Forms/Forms_Package_Importer.php" ) ) {
	fwrite( STDERR, "Forms package importer is not loaded by the plugin bootstrap.\n" );
	exit( 1 );
}

$template_position = strpos( $importer, "foreach ( $data['templates']" );
$form_position     = strpos( $importer, "foreach ( $data['forms']" );
if ( false === $template_position || false === $form_position || $template_position >= $form_position ) {
	fwrite( STDERR, "Templates must import before forms.\n" );
	exit( 1 );
}

if ( false !== stripos( $importer, 'hosgedopol' ) ) {
	fwrite( STDERR, "Runtime package importer must stay institution-agnostic.\n" );
	exit( 1 );
}

if ( false === strpos( $importer, "$id = $post['id'];" ) ) {
	fwrite( STDERR, "Importer must resolve the numeric post ID before writing metadata.\n" );
	exit( 1 );
}

echo "Forms package importer contract test passed.\n";
