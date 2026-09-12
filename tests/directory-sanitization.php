<?php
/**
 * Isolated Directory sanitization regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}

	function sanitize_email( $value ) {
		return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL );
	}

	function is_email( $value ) {
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}
}

namespace HeadlessApiCore\Modules\Directory {
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Post_Type.php';

	$date_cases = array(
		'2026-09-11' => '2026-09-11',
		'2024-02-29' => '2024-02-29',
		'2025-02-29' => '',
		'11/09/2026' => '',
		''           => '',
	);

	foreach ( $date_cases as $input => $expected ) {
		if ( Directory_Post_Type::sanitize_date( $input ) !== $expected ) {
			fwrite( STDERR, 'Directory date sanitizer mismatch for ' . $input . "\n" );
			exit( 1 );
		}
	}

	if ( 'EMP-001:A.2' !== Directory_Post_Type::sanitize_external_id( ' EMP-001:A.2 ' ) ) {
		fwrite( STDERR, "Directory external ID sanitizer rejected safe punctuation.\n" );
		exit( 1 );
	}

	if ( 'EMP001' !== Directory_Post_Type::sanitize_external_id( 'EMP 001<script>' ) ) {
		fwrite( STDERR, "Directory external ID sanitizer did not strip unsafe characters.\n" );
		exit( 1 );
	}

	if ( 'test@example.org' !== Directory_Post_Type::sanitize_email_value( ' test@example.org ' ) ) {
		fwrite( STDERR, "Directory email sanitizer failed valid email.\n" );
		exit( 1 );
	}

	if ( '' !== Directory_Post_Type::sanitize_email_value( 'not-an-email' ) ) {
		fwrite( STDERR, "Directory email sanitizer accepted invalid email.\n" );
		exit( 1 );
	}

	$map = Directory_Post_Type::sanitize_group_order_map( array( '12' => '3', '-2' => 4, 'bad' => 8, 15 => -4 ) );
	$expected_map = array( '12' => 3, '2' => 4, '15' => 0 );
	if ( $map !== $expected_map ) {
		fwrite( STDERR, "Directory group-order sanitizer mismatch.\n" );
		exit( 1 );
	}

	echo "Directory sanitization test passed.\n";
}
