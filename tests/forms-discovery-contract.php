<?php
/**
 * Dynamic Forms discovery/API contract regression test.
 */

define( 'ABSPATH', __DIR__ );

$root       = dirname( __DIR__ );
$serializer = file_get_contents( $root . '/modules/Forms/Forms_Serializer.php' );
$controller = file_get_contents( $root . '/modules/Forms/Forms_Controller.php' );

foreach (
	array(
		"'slug'",
		"'fields'",
		"'api'",
		"'schema' => rest_url",
		"'submit' => rest_url",
		"'method'      => 'POST'",
		"'contentType' => 'application/json'",
		'Plugin::REST_NAMESPACE',
	) as $needle
) {
	if ( false === strpos( $serializer, $needle ) ) {
		fwrite( STDERR, "Dynamic form serializer contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		"'/forms'",
		"'/forms/(?P<slug>[a-z0-9-]+)'",
		"'/forms/(?P<slug>[a-z0-9-]+)/submit'",
		"array_map( array( \$this->serializer, 'serialize' ), \$posts )",
	) as $needle
) {
	if ( false === strpos( $controller, $needle ) ) {
		fwrite( STDERR, "Dynamic form controller discovery contract missing: {$needle}\n" );
		exit( 1 );
	}
}

$contact_path     = $root . '/project-docs/forms/hosgedopol-contact-form.json';
$appointment_path = $root . '/project-docs/forms/hosgedopol-appointment-form.json';

foreach ( array( $contact_path, $appointment_path ) as $path ) {
	$fixture = json_decode( file_get_contents( $path ), true );
	if ( ! is_array( $fixture ) || empty( $fixture['form']['slug'] ) || empty( $fixture['form']['fields'] ) ) {
		fwrite( STDERR, "Project form fixture must expose a slug plus dynamic fields: {$path}\n" );
		exit( 1 );
	}
	foreach ( $fixture['form']['fields'] as $field ) {
		if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
			fwrite( STDERR, "Every dynamic form field must expose name + type: {$path}\n" );
			exit( 1 );
		}
	}
}

echo "Forms dynamic discovery/API contract test passed.\n";
