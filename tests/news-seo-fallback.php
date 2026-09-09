<?php
/**
 * Isolated regression test for News SEO fallback behavior without Yoast.
 *
 * This file intentionally stubs only the WordPress functions required by the
 * serializer SEO path. It runs outside WordPress in CI and must not ship in the
 * installable plugin ZIP.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		/** @var int */
		public $ID = 321;
	}
}

namespace HeadlessApiCore\Modules\News {
	function get_bloginfo( $key ) {
		if ( 'charset' === $key ) {
			return 'UTF-8';
		}

		if ( 'name' === $key ) {
			return 'Internal CMS';
		}

		return '';
	}

	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}

	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Serializer.php';

	if ( \function_exists( 'YoastSEO' ) ) {
		fwrite( STDERR, "YoastSEO() unexpectedly exists in fallback test.\n" );
		exit( 1 );
	}

	$post       = new \WP_Post();
	$serializer = new News_Serializer();
	$method      = new \ReflectionMethod( $serializer, 'get_seo' );
	$method->setAccessible( true );

	$image = array(
		'url'    => 'https://cms.example.org/image.jpg',
		'alt'    => 'Native alt',
		'width'  => 1200,
		'height' => 800,
	);

	$payload = array(
		'title'         => 'Native public title',
		'excerpt'       => 'Native public excerpt',
		'featuredImage' => $image,
	);

	$seo = $method->invoke( $serializer, $post, $payload );

	$expected = array(
		'source'      => 'wordpress',
		'title'       => 'Native public title',
		'description' => 'Native public excerpt',
		'openGraph'   => array(
			'title'       => 'Native public title',
			'description' => 'Native public excerpt',
			'images'      => array( $image ),
		),
	);

	if ( $expected !== $seo ) {
		fwrite( STDERR, "News SEO fallback contract mismatch.\n" );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . "\n" );
		fwrite( STDERR, 'Actual: ' . var_export( $seo, true ) . "\n" );
		exit( 1 );
	}

	echo "News SEO fallback test passed.\n";
}
