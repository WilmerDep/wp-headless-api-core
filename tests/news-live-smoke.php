<?php
/**
 * Reusable live smoke test for the public News REST contract.
 *
 * Usage:
 * HEADLESS_CORE_API_URL="https://cms.example.org/wp-json/headless-core/v1" \
 * HEADLESS_EXPECTED_VERSION="0.2.2" \
 * HEADLESS_EXPECTED_TIMEZONE_OFFSET="-04:00" \
 * HEADLESS_EXPECT_PRESENT_SLUG="published-test" \
 * HEADLESS_EXPECT_ABSENT_SLUG="draft-or-trashed-test" \
 * php tests/news-live-smoke.php
 *
 * The present/absent slug checks are optional and are intended for editorial
 * lifecycle QA after changing a real post status in the target CMS.
 *
 * This test is intentionally not executed by default in CI because CI must not
 * depend on a particular external WordPress installation. Run it after a
 * candidate ZIP is deployed to a target CMS.
 */

$api_url          = getenv( 'HEADLESS_CORE_API_URL' );
$offset           = getenv( 'HEADLESS_EXPECTED_TIMEZONE_OFFSET' );
$expected_version = getenv( 'HEADLESS_EXPECTED_VERSION' );
$present_slug     = getenv( 'HEADLESS_EXPECT_PRESENT_SLUG' );
$absent_slug      = getenv( 'HEADLESS_EXPECT_ABSENT_SLUG' );

if ( ! is_string( $api_url ) || '' === trim( $api_url ) ) {
	fwrite( STDERR, "HEADLESS_CORE_API_URL is required.\n" );
	exit( 1 );
}

$api_url          = rtrim( trim( $api_url ), '/' );
$offset           = is_string( $offset ) ? trim( $offset ) : '';
$expected_version = is_string( $expected_version ) ? trim( $expected_version ) : '';
$present_slug     = is_string( $present_slug ) ? trim( $present_slug ) : '';
$absent_slug      = is_string( $absent_slug ) ? trim( $absent_slug ) : '';

function smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function smoke_get_json( $url ) {
	$context = stream_context_create(
		array(
			'http' => array(
				'method'        => 'GET',
				'ignore_errors' => true,
				'header'        => "Accept: application/json\r\nUser-Agent: Headless-API-Core-Live-Smoke/1.1\r\n",
				'timeout'       => 20,
			),
		)
	);

	$body = file_get_contents( $url, false, $context );

	if ( false === $body ) {
		throw new RuntimeException( 'Request failed: ' . $url );
	}

	$status = 0;
	foreach ( $http_response_header ?? array() as $header ) {
		if ( preg_match( '/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches ) ) {
			$status = (int) $matches[1];
			break;
		}
	}

	$data = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		throw new RuntimeException( 'Expected JSON from ' . $url );
	}

	return array( $status, $data );
}

function validate_iso_datetime( $value, $field, $expected_offset ) {
	smoke_assert( is_string( $value ) && '' !== $value, $field . ' must be a non-empty string.' );
	smoke_assert(
		1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$/', $value ),
		$field . ' must be ISO 8601 with an explicit timezone offset.'
	);

	if ( '' !== $expected_offset ) {
		smoke_assert(
			substr( $value, -6 ) === $expected_offset,
			$field . ' must use expected site offset ' . $expected_offset . '; got ' . $value
		);
	}
}

function validate_author( $author ) {
	smoke_assert( is_array( $author ), 'author must be an object.' );
	smoke_assert( array( 'name' ) === array_keys( $author ), 'author must expose only name.' );
	smoke_assert( is_string( $author['name'] ) && '' !== trim( $author['name'] ), 'author.name is required.' );
}

function smoke_catalog_contains_slug( $api_url, $slug ) {
	$page = 1;
	$encoded_slug = rawurldecode( $slug );

	do {
		list( $status, $collection ) = smoke_get_json(
			$api_url . '/news?page=' . $page . '&per_page=50&orderby=date&order=desc'
		);
		smoke_assert( 200 === $status, 'News collection scan must return HTTP 200.' );

		foreach ( $collection['items'] ?? array() as $item ) {
			if ( rawurldecode( (string) ( $item['slug'] ?? '' ) ) === $encoded_slug ) {
				return true;
			}
		}

		$total_pages = max( 1, (int) ( $collection['pagination']['totalPages'] ?? 1 ) );
		++$page;
	} while ( $page <= $total_pages );

	return false;
}

try {
	list( $health_status, $health ) = smoke_get_json( $api_url . '/health' );
	smoke_assert( 200 === $health_status, 'Health must return HTTP 200.' );
	smoke_assert( true === ( $health['ok'] ?? null ), 'Health must return ok=true.' );
	if ( '' !== $expected_version ) {
		smoke_assert( $expected_version === ( $health['version'] ?? null ), 'Unexpected plugin version from Health.' );
	}
	echo "✓ health\n";

	list( $collection_status, $collection ) = smoke_get_json(
		$api_url . '/news?page=1&per_page=5&orderby=date&order=desc'
	);
	smoke_assert( 200 === $collection_status, 'News collection must return HTTP 200.' );
	smoke_assert( isset( $collection['items'] ) && is_array( $collection['items'] ), 'Collection items must be an array.' );
	smoke_assert( ! empty( $collection['items'] ), 'Collection must contain at least one News item.' );
	smoke_assert( count( $collection['items'] ) <= 5, 'Collection exceeded per_page=5.' );

	$previous_time = null;
	foreach ( $collection['items'] as $item ) {
		validate_iso_datetime( $item['publishedAt'] ?? null, 'publishedAt', $offset );
		validate_iso_datetime( $item['modifiedAt'] ?? null, 'modifiedAt', $offset );
		validate_author( $item['author'] ?? null );

		$current_time = strtotime( $item['publishedAt'] );
		smoke_assert( false !== $current_time, 'publishedAt must be parseable.' );
		if ( null !== $previous_time ) {
			smoke_assert( $current_time <= $previous_time, 'Collection is not ordered descending by publication time.' );
		}
		$previous_time = $current_time;
	}
	echo "✓ collection timestamps, author and chronological ordering\n";

	$first = $collection['items'][0];
	$slug  = rawurlencode( rawurldecode( (string) $first['slug'] ) );
	list( $detail_status, $detail ) = smoke_get_json( $api_url . '/news/' . $slug );
	smoke_assert( 200 === $detail_status, 'News detail must return HTTP 200.' );
	validate_iso_datetime( $detail['publishedAt'] ?? null, 'detail.publishedAt', $offset );
	validate_iso_datetime( $detail['modifiedAt'] ?? null, 'detail.modifiedAt', $offset );
	validate_author( $detail['author'] ?? null );
	smoke_assert( isset( $detail['content'] ) && is_string( $detail['content'] ), 'Detail content must be a string.' );
	echo "✓ detail\n";

	$missing_slug = 'headless-core-live-smoke-missing-' . time();
	list( $missing_status, $missing ) = smoke_get_json( $api_url . '/news/' . $missing_slug );
	smoke_assert( 404 === $missing_status, 'Unknown News slug must return HTTP 404.' );
	smoke_assert(
		'headless_core_news_not_found' === ( $missing['code'] ?? null ),
		'Unknown News slug must return headless_core_news_not_found.'
	);
	echo "✓ 404\n";

	if ( '' !== $present_slug ) {
		smoke_assert( smoke_catalog_contains_slug( $api_url, $present_slug ), 'Expected published slug is absent from collection: ' . $present_slug );
		list( $present_status ) = smoke_get_json( $api_url . '/news/' . rawurlencode( rawurldecode( $present_slug ) ) );
		smoke_assert( 200 === $present_status, 'Expected published slug detail must return HTTP 200.' );
		echo "✓ expected published slug is public\n";
	}

	if ( '' !== $absent_slug ) {
		smoke_assert( ! smoke_catalog_contains_slug( $api_url, $absent_slug ), 'Expected non-public slug still appears in collection: ' . $absent_slug );
		list( $absent_status, $absent_detail ) = smoke_get_json( $api_url . '/news/' . rawurlencode( rawurldecode( $absent_slug ) ) );
		smoke_assert( 404 === $absent_status, 'Expected non-public slug detail must return HTTP 404.' );
		smoke_assert( 'headless_core_news_not_found' === ( $absent_detail['code'] ?? null ), 'Expected non-public slug must use News 404 contract.' );
		echo "✓ expected non-public slug is absent\n";
	}

	echo "\n✓ Live News contract smoke passed.\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, "\n✗ Live News contract smoke failed.\n" );
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
