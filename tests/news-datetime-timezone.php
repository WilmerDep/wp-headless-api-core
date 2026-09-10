<?php
/**
 * Isolated regression test for News editorial timestamps.
 *
 * Verifies that the serializer requests local/site-timezone values from
 * WordPress instead of GMT so late-night publications do not move to the next
 * calendar day for consumers.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		/** @var int */
		public $ID = 999;

		/** @var string */
		public $post_name = 'prueba-de-consumo-api-headless';
	}
}

namespace HeadlessApiCore\Modules\News {
	function get_the_title( $post ) {
		unset( $post );
		return 'Prueba de consumo api headless';
	}

	function get_the_excerpt( $post ) {
		unset( $post );
		return 'Prueba de fecha editorial.';
	}

	function get_bloginfo( $key ) {
		return 'charset' === $key ? 'UTF-8' : 'CMS Test';
	}

	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}

	function get_post_time( $format, $gmt, $post ) {
		unset( $post );

		if ( DATE_ATOM !== $format ) {
			throw new \RuntimeException( 'publishedAt must request DATE_ATOM.' );
		}

		if ( false !== $gmt ) {
			throw new \RuntimeException( 'publishedAt must use the WordPress site timezone, not GMT.' );
		}

		return '2026-09-09T23:31:00-04:00';
	}

	function get_post_modified_time( $format, $gmt, $post ) {
		unset( $post );

		if ( DATE_ATOM !== $format ) {
			throw new \RuntimeException( 'modifiedAt must request DATE_ATOM.' );
		}

		if ( false !== $gmt ) {
			throw new \RuntimeException( 'modifiedAt must use the WordPress site timezone, not GMT.' );
		}

		return '2026-09-09T23:45:00-04:00';
	}

	function get_post_thumbnail_id( $post ) {
		unset( $post );
		return 0;
	}

	function get_the_category( $post_id ) {
		unset( $post_id );
		return array();
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Serializer.php';

	$post       = new \WP_Post();
	$serializer = new News_Serializer();
	$summary    = $serializer->summary( $post );

	$expected_published = '2026-09-09T23:31:00-04:00';
	$expected_modified  = '2026-09-09T23:45:00-04:00';

	if ( $expected_published !== $summary['publishedAt'] ) {
		fwrite( STDERR, "publishedAt timezone contract mismatch.\n" );
		fwrite( STDERR, 'Expected: ' . $expected_published . "\n" );
		fwrite( STDERR, 'Actual: ' . var_export( $summary['publishedAt'], true ) . "\n" );
		exit( 1 );
	}

	if ( $expected_modified !== $summary['modifiedAt'] ) {
		fwrite( STDERR, "modifiedAt timezone contract mismatch.\n" );
		fwrite( STDERR, 'Expected: ' . $expected_modified . "\n" );
		fwrite( STDERR, 'Actual: ' . var_export( $summary['modifiedAt'], true ) . "\n" );
		exit( 1 );
	}

	if ( '2026-09-09' !== substr( $summary['publishedAt'], 0, 10 ) ) {
		fwrite( STDERR, "publishedAt changed the editorial calendar day.\n" );
		exit( 1 );
	}

	echo "News site-timezone timestamp test passed.\n";
}
