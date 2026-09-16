<?php
/**
 * Contact form parity fixture contract.
 */

define( 'ABSPATH', __DIR__ );

$root    = dirname( __DIR__ );
$fixture = json_decode( file_get_contents( $root . '/project-docs/forms/hosgedopol-contact-form.json' ), true );
$form    = $fixture['form'] ?? array();

if ( 'contacto' !== ( $form['slug'] ?? '' ) ) {
	fwrite( STDERR, "Contact fixture slug changed unexpectedly.\n" );
	exit( 1 );
}

$sections = $form['sections'] ?? array();
if ( empty( $sections ) || '' !== ( $sections[0]['title'] ?? null ) || 'CONTACTO' !== ( $sections[0]['eyebrow'] ?? '' ) ) {
	fwrite( STDERR, "Contact section must preserve an empty title and CONTACTO subtitle.\n" );
	exit( 1 );
}

$expected = array( 'fullName', 'email', 'subject', 'message' );
$fields   = $form['fields'] ?? array();
$by_name  = array();
foreach ( $fields as $field ) {
	if ( isset( $field['name'] ) ) {
		$by_name[ $field['name'] ] = $field;
	}
}

foreach ( $expected as $name ) {
	if ( ! isset( $by_name[ $name ] ) ) {
		fwrite( STDERR, "Contact parity fixture missing field: {$name}\n" );
		exit( 1 );
	}
	if ( 12 !== (int) ( $by_name[ $name ]['width'] ?? 0 ) ) {
		fwrite( STDERR, "Contact field {$name} must remain full width (12/12).\n" );
		exit( 1 );
	}
}

if ( 'email' !== ( $form['notifications'][0]['replyToField'] ?? '' ) ) {
	fwrite( STDERR, "Contact Reply-To parity changed unexpectedly.\n" );
	exit( 1 );
}

echo "Contact frontend parity fixture test passed.\n";
