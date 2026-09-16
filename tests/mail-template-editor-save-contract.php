<?php
/**
 * Mail-template editor save/redirect regression contract.
 */

define( 'ABSPATH', __DIR__ );

$root       = dirname( __DIR__ );
$box        = file_get_contents( $root . '/modules/Forms/Mail_Template_Test_Box.php' );
$module     = file_get_contents( $root . '/modules/Forms/Forms_Module.php' );
$bootstrap  = file_get_contents( $root . '/wp-headless-api-core.php' );
$serializer = file_get_contents( $root . '/modules/Forms/Forms_Serializer.php' );

foreach (
	array(
		"remove_meta_box( 'headless-mail-template-test'",
		'data-headless-mail-template-test',
		'wp_add_inline_script',
		"document.createElement('form')",
		"add('action', box.getAttribute('data-action'))",
		"add('template_id', box.getAttribute('data-template-id'))",
		'keep_editor_after_save',
		"'editpost' !== $action",
		"get_edit_post_link( $post_id, 'raw' )",
	) as $needle
) {
	if ( false === strpos( $box, $needle ) ) {
		fwrite( STDERR, "Safe mail-template editor contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $box, '<form method=' ) ) {
	fwrite( STDERR, "Mail-template test box must not render a nested HTML form inside the WordPress post editor.\n" );
	exit( 1 );
}

foreach (
	array(
		'new Mail_Template_Test_Box( $mail_settings )',
		'$template_test_box->register();',
	) as $needle
) {
	if ( false === strpos( $module, $needle ) ) {
		fwrite( STDERR, "Forms module must register the safe mail-template test box: {$needle}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $bootstrap, "modules/Forms/Mail_Template_Test_Box.php" ) ) {
	fwrite( STDERR, "Plugin bootstrap must load Mail_Template_Test_Box.php.\n" );
	exit( 1 );
}

if ( false === strpos( $serializer, "(object) array()" ) ) {
	fwrite( STDERR, "v0.7.10 validation-object compatibility must remain present in v0.7.11.\n" );
	exit( 1 );
}

echo "Mail template editor save integrity contract test passed.\n";
