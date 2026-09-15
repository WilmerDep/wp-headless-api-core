<?php
/**
 * Forms package importer progressive UI contract.
 */

define( 'ABSPATH', __DIR__ );

$root   = dirname( __DIR__ );
$ui     = file_get_contents( $root . '/modules/Forms/Forms_Import_UI.php' );
$script = file_get_contents( $root . '/assets/admin/forms-import.js' );
$module = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$plugin = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		'Forms_Package_Importer::PAGE_SLUG',
		'Forms_Post_Type::FORM_POST_TYPE',
		"assets/admin/forms-import.js",
	) as $needle
) {
	if ( false === strpos( $ui, $needle ) ) {
		fwrite( STDERR, "Forms importer UI loader lost required behavior: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'headless-directory-dropzone',
		'headless-directory-import-file-name',
		'headless-directory-import-kpis',
		'headless-directory-import-table-wrap',
		'headless-directory-validation-pill',
		'headless-directory-import-options',
		'3. Importar',
	) as $needle
) {
	if ( false === strpos( $script, $needle ) ) {
		fwrite( STDERR, "Forms importer no longer reuses the approved SIP import pattern: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $module, 'new Forms_Import_UI()' ) || false === strpos( $module, '$import_ui->register()' ) ) {
	fwrite( STDERR, "Forms importer UI enhancer is not registered.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'modules/Forms/Forms_Import_UI.php' ) ) {
	fwrite( STDERR, "Forms importer UI enhancer is not loaded by the plugin bootstrap.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'Version: 0.7.4' ) || false === strpos( $plugin, "HEADLESS_API_CORE_VERSION', '0.7.4" ) ) {
	fwrite( STDERR, "Plugin version must be 0.7.4 for the current Forms visual gate.\n" );
	exit( 1 );
}

echo "Forms SIP importer UI contract test passed.\n";
