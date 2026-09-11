<?php
/**
 * Isolated Hero sanitization regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	function esc_url_raw( $value, $protocols = null ) {
		unset( $protocols );
		return trim( (string) $value );
	}

	function wp_parse_url( $value, $component = -1 ) {
		return parse_url( $value, $component );
	}
}

namespace HeadlessApiCore\Modules\Hero {
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Post_Type.php';

	$href_cases = array(
		'/servicios'                    => '/servicios',
		'/servicios?x=1#top'            => '/servicios?x=1#top',
		'https://example.org/servicios' => 'https://example.org/servicios',
		'http://example.org/path'       => 'http://example.org/path',
		'javascript:alert(1)'           => '',
		'data:text/html,test'           => '',
		'//evil.example/path'           => '',
		"/safe\nheader"                => '',
	);

	foreach ( $href_cases as $input => $expected ) {
		$actual = Hero_Post_Type::sanitize_href( $input );
		if ( $actual !== $expected ) {
			fwrite( STDERR, 'Hero href sanitizer mismatch for ' . var_export( $input, true ) . ".\n" );
			exit( 1 );
		}
	}

	$position_cases = array(
		'center center' => 'center center',
		'CENTER   TOP'  => 'center top',
		'50% 25%'       => '50% 25%',
		'100% 0%'       => '100% 0%',
		'left'           => 'left',
		'101% 50%'       => '',
		'-1% 50%'        => '',
		'calc(50%) 20%'  => '',
		'left top extra' => '',
	);

	foreach ( $position_cases as $input => $expected ) {
		$actual = Hero_Post_Type::sanitize_object_position( $input );
		if ( $actual !== $expected ) {
			fwrite( STDERR, 'Hero object-position sanitizer mismatch for ' . var_export( $input, true ) . ".\n" );
			exit( 1 );
		}
	}

	echo "Hero sanitization test passed.\n";
}
