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
		"name=\"headless_mail_test_recipient\"",
		'RECIPIENT_META_PREFIX',
		"add_action( 'save_post_' . Forms_Post_Type::TEMPLATE_POST_TYPE, array( \$this, 'save_recipient_preference' ), 20, 2 )",
		'get_user_meta( get_current_user_id(), self::recipient_meta_key( $post->ID ), true )',
		'update_user_meta( get_current_user_id(), self::recipient_meta_key( $template_id ), $recipient )',
		'delete_user_meta( get_current_user_id(), self::recipient_meta_key( $post_id ) )',
		'Forms_Admin::TEMPLATE_NONCE_NAME',
		'Forms_Admin::TEMPLATE_NONCE_ACTION',
		'$this->persist_recipient_preference( $template_id, $recipient );',
	) as $needle
) {
	if ( false === strpos( $test, $needle ) ) {
		fwrite( STDERR, "Safe Mail Template persistence contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		"closest('[data-mail-test-send]')",
		"document.createElement('form')",
		"form.appendChild(hidden('action', action))",
		"form.appendChild(hidden('recipient', recipient))",
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
