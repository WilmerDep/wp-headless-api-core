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

	function wp_kses( $value, $allowed_html ) {
		$allowed = '';
		foreach ( array_keys( $allowed_html ) as $tag ) {
			$allowed .= '<' . $tag . '>';
		}
		$value = strip_tags( (string) $value, $allowed );
		return preg_replace_callback(
			'/<\/?([a-z0-9]+)(?:\s[^>]*)?>/i',
			static function ( $matches ) use ( $allowed_html ) {
				$tag = strtolower( $matches[1] );
				if ( ! isset( $allowed_html[ $tag ] ) ) {
					return '';
				}
				$is_close = 0 === strpos( $matches[0], '</' );
				return $is_close ? '</' . $tag . '>' : '<' . $tag . '>';
			},
			$value
		);
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

	$rich = '<p class="bad">Hola <strong data-x="1">Director</strong><script>alert(1)</script><br><em>Institucional</em><a href="https://bad.example">link</a></p>';
	$clean = Directory_Post_Type::sanitize_summary_html( $rich );
	$expected = '<p>Hola <strong>Director</strong>alert(1)<br><em>Institucional</em>link</p>';
	if ( $clean !== $expected ) {
		fwrite( STDERR, "Directory rich summary sanitizer mismatch: {$clean}\n" );
		exit( 1 );
	}

	$encoded = '&lt;p&gt;Hola &lt;strong&gt;Director&lt;/strong&gt;&lt;/p&gt;';
	$decoded_clean = Directory_Post_Type::sanitize_summary_html( $encoded );
	if ( '<p>Hola <strong>Director</strong></p>' !== $decoded_clean ) {
		fwrite( STDERR, "Directory rich summary sanitizer did not normalize encoded markup: {$decoded_clean}\n" );
		exit( 1 );
	}

	if ( false !== strpos( $decoded_clean, '&lt;p&gt;' ) || false !== strpos( $decoded_clean, '&lt;strong&gt;' ) ) {
		fwrite( STDERR, "Directory rich summary sanitizer returned encoded tags.\n" );
		exit( 1 );
	}

	if ( false !== strpos( $clean, 'class=' ) || false !== strpos( $clean, '<script' ) || false !== strpos( $clean, '<a' ) ) {
		fwrite( STDERR, "Directory rich summary sanitizer retained forbidden markup.\n" );
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
