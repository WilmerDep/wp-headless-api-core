<?php
/**
 * Isolated regression test for signed News revalidation delivery.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HEADLESS_API_CORE_VERSION', '0.2.2' );
	define( 'HEADLESS_REVALIDATION_URL', 'https://consumer.example.org/api/headless/revalidate' );
	define( 'HEADLESS_REVALIDATION_SECRET', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef' );

	class WP_Error {
		public function get_error_message() {
			return 'test error';
		}
	}
}

namespace HeadlessApiCore\Revalidation {
	$GLOBALS['headless_revalidation_request'] = null;
	$GLOBALS['headless_revalidation_delivery'] = null;

	function time() {
		return 1789056000;
	}

	function getenv( $key ) {
		unset( $key );
		return false;
	}

	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}

	function esc_url_raw( $url ) {
		return $url;
	}

	function wp_parse_url( $url, $component ) {
		return parse_url( $url, $component );
	}

	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}

	function wp_remote_post( $url, $args ) {
		$GLOBALS['headless_revalidation_request'] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array( 'response' => array( 'code' => 204 ) );
	}

	function is_wp_error( $value ) {
		return $value instanceof \WP_Error;
	}

	function wp_remote_retrieve_response_code( $response ) {
		return (int) $response['response']['code'];
	}

	function do_action( $hook, ...$args ) {
		if ( 'headless_api_core_revalidation_delivery' === $hook ) {
			$GLOBALS['headless_revalidation_delivery'] = $args;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/Revalidation/Revalidation_Client.php';

	$payload = array(
		'resource'       => 'news',
		'postId'         => 123,
		'slug'           => 'noticia-actual',
		'previousSlug'   => 'noticia-anterior',
		'status'         => 'draft',
		'previousStatus' => 'publish',
		'event'          => 'status_changed',
	);

	$client = new Revalidation_Client();
	$result = $client->send( $payload );

	if ( true !== $result ) {
		fwrite( STDERR, "Expected successful revalidation delivery.\n" );
		exit( 1 );
	}

	$request = $GLOBALS['headless_revalidation_request'];

	if ( ! is_array( $request ) ) {
		fwrite( STDERR, "Revalidation request was not captured.\n" );
		exit( 1 );
	}

	$body      = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$timestamp = '1789056000';
	$expected  = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, HEADLESS_REVALIDATION_SECRET );
	$headers   = $request['args']['headers'];

	if ( HEADLESS_REVALIDATION_URL !== $request['url'] ) {
		fwrite( STDERR, "Revalidation target URL mismatch.\n" );
		exit( 1 );
	}

	if ( $body !== $request['args']['body'] ) {
		fwrite( STDERR, "Signed raw body differs from delivered body.\n" );
		exit( 1 );
	}

	if ( $timestamp !== $headers[ Revalidation_Client::TIMESTAMP_HEADER ] ) {
		fwrite( STDERR, "Timestamp header mismatch.\n" );
		exit( 1 );
	}

	if ( $expected !== $headers[ Revalidation_Client::SIGNATURE_HEADER ] ) {
		fwrite( STDERR, "HMAC signature mismatch.\n" );
		exit( 1 );
	}

	if ( false !== strpos( $request['args']['body'], HEADLESS_REVALIDATION_SECRET ) ) {
		fwrite( STDERR, "Shared secret leaked into request body.\n" );
		exit( 1 );
	}

	if ( empty( $GLOBALS['headless_revalidation_delivery'][0] ) ) {
		fwrite( STDERR, "Delivery result action did not report success.\n" );
		exit( 1 );
	}

	echo "News revalidation HMAC signature test passed.\n";
}
