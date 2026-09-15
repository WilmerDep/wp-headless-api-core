<?php
/**
 * Forms post-type registration contract.
 */

$path = dirname( __DIR__ ) . '/modules/Forms/Forms_Post_Type.php';
$code = file_get_contents( $path );

if ( false === $code ) {
	fwrite( STDERR, "Could not read Forms_Post_Type.php\n" );
	exit( 1 );
}

if ( ! preg_match( "/const FORM_POST_TYPE\\s*=\\s*'([^']+)'/", $code, $form_match ) ) {
	fwrite( STDERR, "FORM_POST_TYPE constant not found.\n" );
	exit( 1 );
}

if ( ! preg_match( "/const TEMPLATE_POST_TYPE\\s*=\\s*'([^']+)'/", $code, $template_match ) ) {
	fwrite( STDERR, "TEMPLATE_POST_TYPE constant not found.\n" );
	exit( 1 );
}

$form_key     = $form_match[1];
$template_key = $template_match[1];

foreach ( array( 'form' => $form_key, 'template' => $template_key ) as $label => $key ) {
	if ( strlen( $key ) > 20 ) {
		fwrite( STDERR, strtoupper( $label ) . " post type exceeds WordPress 20-character limit: {$key}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $code, "'show_in_menu'        => true" ) ) {
	fwrite( STDERR, "Forms admin menu is not configured as a top-level menu.\n" );
	exit( 1 );
}

if ( false === strpos( $code, "'show_in_menu'        => 'edit.php?post_type=' . self::FORM_POST_TYPE" ) ) {
	fwrite( STDERR, "Mail Templates are not configured under the Forms menu.\n" );
	exit( 1 );
}

echo "Forms post type/admin menu contract test passed.\n";
