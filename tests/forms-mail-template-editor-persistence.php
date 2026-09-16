<?php
/**
 * Mail Template editor persistence regression test.
 */

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__ );
$test = file_get_contents( $root . '/modules/Forms/Mail_Template_Test.php' );
$js   = file_get_contents( $root . '/assets/admin/mail-template-test.js' );

if ( false !== strpos( $test, '<form method="post"' ) || false !== strpos( $test, '</form>' ) ) {
	fwrite( STDERR, "Mail Template test metabox must not render a nested form inside the WordPress post editor.\n" );
	exit( 1 );
}

foreach (
	array(
		"plugins_url( 'assets/admin/mail-template-test.js'",
		'data-mail-template-test',
		'data-mail-test-recipient',
		'data-mail-test-form',
		'data-mail-test-send',
		'wp_create_nonce( self::NONCE_ACTION )',
	) as $needle
) {
	if ( false === strpos( $test, $needle ) ) {
		fwrite( STDERR, "Safe Mail Template test UI contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		"closest('[data-mail-test-send]')",
		"document.createElement('form')",
		"form.appendChild(hidden('action', action))",
		"document.body.appendChild(form)",
		'form.submit()',
	) as $needle
) {
	if ( false === strpos( $js, $needle ) ) {
		fwrite( STDERR, "Detached Mail Template test submission contract missing: {$needle}\n" );
		exit( 1 );
	}
}

echo "Mail Template editor persistence regression test passed.\n";
