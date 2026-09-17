<?php
/**
 * Forms list table and Quick Edit regression test.
 */

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__ );
$list = file_get_contents( $root . '/modules/Forms/Forms_List_Table.php' );
$boot = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$main = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		"manage_' . Forms_Post_Type::FORM_POST_TYPE . '_posts_columns'",
		"'form_key'",
		"'form_slug'",
		"'form_enabled'",
		"'form_fields'",
		"'Key semántico'",
		"'Slug'",
		"'Estado'",
		"'Campos'",
		"add_action( 'quick_edit_custom_box'",
		"name=\"headless_form_key_quick\"",
		"name=\"headless_form_enabled_quick\"",
		"name=\"headless_form_quick_edit\"",
		"wp_verify_nonce",
		"'inlineeditnonce'",
		"Forms_Identity::is_active_key_available",
		"inlineEditPost.edit",
		"data-form-key",
		"data-form-enabled",
	) as $needle
) {
	if ( false === strpos( $list, $needle ) ) {
		fwrite( STDERR, "Forms list/Quick Edit contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $boot, 'new Forms_List_Table()' ) || false === strpos( $boot, '$list_table->register();' ) ) {
	fwrite( STDERR, "Forms list-table service is not registered by Forms_Module.\n" );
	exit( 1 );
}

if ( false === strpos( $main, "modules/Forms/Forms_List_Table.php" ) ) {
	fwrite( STDERR, "Forms_List_Table.php is not loaded by the plugin bootstrap.\n" );
	exit( 1 );
}

echo "Forms list table and Quick Edit regression test passed.\n";
