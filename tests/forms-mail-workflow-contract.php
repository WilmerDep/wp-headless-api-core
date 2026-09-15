<?php
/**
 * Forms mail-test workflow + project compatibility fixture contract.
 */

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__ );

$template_test = file_get_contents( $root . '/modules/Forms/Mail_Template_Test.php' );
$submission    = file_get_contents( $root . '/modules/Forms/Forms_Submission.php' );

foreach (
	array(
		"admin_post_' . self::ACTION",
		'check_admin_referer',
		"current_user_can( 'edit_post'",
		'$this->mail_settings->is_ready()',
		'$this->renderer->render(',
		'wp_mail(',
	) as $needle
) {
	if ( false === strpos( $template_test, $needle ) ) {
		fwrite( STDERR, "Mail template test workflow lost required behavior: {$needle}\n" );
		exit( 1 );
	}
}

foreach ( array( 'headless_api_core_form_mail_headers', 'headless_api_core_form_mail_attachments' ) as $hook ) {
	if ( false === strpos( $submission, $hook ) ) {
		fwrite( STDERR, "Forms notification extension contract is missing: {$hook}\n" );
		exit( 1 );
	}
}

$fixture_files = array(
	'contact'     => $root . '/project-docs/forms/hosgedopol-contact-form.json',
	'appointment' => $root . '/project-docs/forms/hosgedopol-appointment-form.json',
	'templates'   => $root . '/project-docs/forms/hosgedopol-mail-templates.json',
);

$fixtures = array();
foreach ( $fixture_files as $key => $path ) {
	$decoded = json_decode( file_get_contents( $path ), true );
	if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
		fwrite( STDERR, "Invalid JSON fixture: {$path}\n" );
		exit( 1 );
	}
	$fixtures[ $key ] = $decoded;
}

$contact = $fixtures['contact']['form'] ?? array();
if (
	'contacto' !== ( $contact['slug'] ?? '' ) ||
	'info@hosgedopol.gob.do' !== ( $contact['notifications'][0]['to'][0] ?? '' ) ||
	'email' !== ( $contact['notifications'][0]['replyToField'] ?? '' ) ||
	'hosgedopol-contacto' !== ( $contact['notifications'][0]['template'] ?? '' )
) {
	fwrite( STDERR, "Contact compatibility fixture changed unexpectedly.\n" );
	exit( 1 );
}

$appointment = $fixtures['appointment']['form'] ?? array();
$field_names = array_column( $appointment['fields'] ?? array(), 'name' );
foreach ( array( 'firstName', 'lastName', 'phoneCountry', 'appointmentDate', 'experienceRating' ) as $required_name ) {
	if ( ! in_array( $required_name, $field_names, true ) ) {
		fwrite( STDERR, "Appointment fixture lost exact Consumer field casing: {$required_name}\n" );
		exit( 1 );
	}
}
if (
	'citas@hosgedopol.gob.do' !== ( $appointment['notifications'][0]['to'][0] ?? '' ) ||
	'hosgedopol-citas-en-linea' !== ( $appointment['notifications'][0]['template'] ?? '' )
) {
	fwrite( STDERR, "Appointment compatibility notification changed unexpectedly.\n" );
	exit( 1 );
}

$template_slugs = array_column( $fixtures['templates']['templates'] ?? array(), 'slug' );
foreach ( array( 'hosgedopol-contacto', 'hosgedopol-citas-en-linea' ) as $slug ) {
	if ( ! in_array( $slug, $template_slugs, true ) ) {
		fwrite( STDERR, "Mail template fixture missing: {$slug}\n" );
		exit( 1 );
	}
}

echo "Forms mail workflow and compatibility fixture contract test passed.\n";
