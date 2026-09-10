<?php
/**
 * Isolated regression test for News editorial lifecycle aggregation.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HEADLESS_API_CORE_VERSION', '0.2.2' );
	define( 'HEADLESS_REVALIDATION_URL', 'https://consumer.example.org/api/headless/revalidate' );
	define( 'HEADLESS_REVALIDATION_SECRET', 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789' );

	#[\AllowDynamicProperties]
	class WP_Post {
		public $ID;
		public $post_name;
		public $post_status;
		public $post_type = 'post';

		public function __construct( $id, $slug, $status, $post_type = 'post' ) {
			$this->ID          = $id;
			$this->post_name   = $slug;
			$this->post_status = $status;
			$this->post_type   = $post_type;
		}
	}

	class WP_Error {
		public function get_error_message() {
			return 'test error';
		}
	}

	$GLOBALS['news_revalidation_requests'] = array();
	$GLOBALS['news_test_posts']            = array();
}

namespace HeadlessApiCore\Revalidation {
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
		$GLOBALS['news_revalidation_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		return array( 'response' => array( 'code' => 200 ) );
	}

	function is_wp_error( $value ) {
		return $value instanceof \WP_Error;
	}

	function wp_remote_retrieve_response_code( $response ) {
		return (int) $response['response']['code'];
	}

	function do_action( $hook, ...$args ) {
		unset( $hook, $args );
	}
}

namespace HeadlessApiCore\Modules\News {
	function get_post( $post_id ) {
		return $GLOBALS['news_test_posts'][ $post_id ] ?? null;
	}

	require_once dirname( __DIR__ ) . '/includes/Revalidation/Revalidation_Client.php';
	require_once dirname( __DIR__ ) . '/modules/News/News_Revalidation.php';

	use HeadlessApiCore\Revalidation\Revalidation_Client;

	function payload_after_flush( News_Revalidation $lifecycle, $expected_delta = 1 ) {
		$before = count( $GLOBALS['news_revalidation_requests'] );
		$lifecycle->flush();
		$after = count( $GLOBALS['news_revalidation_requests'] );

		if ( $expected_delta !== ( $after - $before ) ) {
			fwrite( STDERR, "Unexpected number of lifecycle deliveries.\n" );
			exit( 1 );
		}

		if ( 0 === $expected_delta ) {
			return null;
		}

		$request = $GLOBALS['news_revalidation_requests'][ $after - 1 ];
		return json_decode( $request['args']['body'], true );
	}

	function assert_payload( array $payload, array $expected, $label ) {
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $payload ) || $value !== $payload[ $key ] ) {
				fwrite( STDERR, $label . ' mismatch for ' . $key . ".\n" );
				fwrite( STDERR, 'Expected: ' . var_export( $value, true ) . "\n" );
				fwrite( STDERR, 'Actual: ' . var_export( $payload[ $key ] ?? null, true ) . "\n" );
				exit( 1 );
			}
		}
	}

	$client    = new Revalidation_Client();
	$lifecycle = new News_Revalidation( $client );

	// draft -> publish: transition + post_updated + save must collapse to one event.
	$before = new \WP_Post( 101, 'noticia-uno', 'draft' );
	$after  = new \WP_Post( 101, 'noticia-uno', 'publish' );
	$lifecycle->on_transition( 'publish', 'draft', $after );
	$lifecycle->on_post_updated( 101, $after, $before );
	$lifecycle->on_save( 101, $after, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload(
		$payload,
		array(
			'resource'       => 'news',
			'postId'         => 101,
			'slug'           => 'noticia-uno',
			'previousSlug'   => 'noticia-uno',
			'status'         => 'publish',
			'previousStatus' => 'draft',
			'event'          => 'status_changed',
		),
		'draft -> publish'
	);

	// future -> publish must be represented as the real WordPress transition.
	$before = new \WP_Post( 102, 'programada', 'future' );
	$after  = new \WP_Post( 102, 'programada', 'publish' );
	$lifecycle->on_transition( 'publish', 'future', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'publish', 'previousStatus' => 'future', 'event' => 'status_changed' ), 'future -> publish' );

	// publish -> draft must invalidate public collection/detail immediately.
	$before = new \WP_Post( 103, 'ocultar', 'publish' );
	$after  = new \WP_Post( 103, 'ocultar', 'draft' );
	$lifecycle->on_transition( 'draft', 'publish', $after );
	$lifecycle->on_post_updated( 103, $after, $before );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'draft', 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> draft' );

	// publish -> private.
	$before = new \WP_Post( 104, 'privada', 'publish' );
	$after  = new \WP_Post( 104, 'privada', 'private' );
	$lifecycle->on_transition( 'private', 'publish', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'private', 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> private' );

	// publish -> trash.
	$before = new \WP_Post( 105, 'papelera', 'publish' );
	$after  = new \WP_Post( 105, 'papelera', 'trash' );
	$lifecycle->on_transition( 'trash', 'publish', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'trash', 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> trash' );

	// publish -> future.
	$before = new \WP_Post( 106, 'reprogramada', 'publish' );
	$after  = new \WP_Post( 106, 'reprogramada', 'future' );
	$lifecycle->on_transition( 'future', 'publish', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'future', 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> future' );

	// Published slug change must preserve old/new slug in a single event.
	$before = new \WP_Post( 107, 'slug-anterior', 'publish' );
	$after  = new \WP_Post( 107, 'slug-nuevo', 'publish' );
	$lifecycle->on_post_updated( 107, $after, $before );
	$lifecycle->on_save( 107, $after, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload(
		$payload,
		array(
			'slug'           => 'slug-nuevo',
			'previousSlug'   => 'slug-anterior',
			'status'         => 'publish',
			'previousStatus' => 'publish',
			'event'          => 'slug_changed',
		),
		'slug change'
	);

	// Published in-place edit.
	$published = new \WP_Post( 108, 'edicion', 'publish' );
	$lifecycle->on_save( 108, $published, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'content_updated', 'status' => 'publish' ), 'publish edit' );

	// Category/meta changes can independently invalidate an already published post.
	$GLOBALS['news_test_posts'][109] = new \WP_Post( 109, 'categoria', 'publish' );
	$lifecycle->on_terms_changed( 109, array(), array(), 'category', false, array() );
	$lifecycle->on_meta_changed( 1, 109, '_thumbnail_id', 55 );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'content_updated', 'slug' => 'categoria' ), 'category/featured image edit' );

	// Permanent deletion invalidates even if the post was already in trash.
	$deleted = new \WP_Post( 110, 'eliminada', 'trash' );
	$lifecycle->on_before_delete( 110, $deleted );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'deleted', 'status' => 'deleted', 'previousStatus' => 'trash' ), 'permanent delete' );

	// Draft-only saves are not public lifecycle events.
	$draft = new \WP_Post( 111, 'sigue-borrador', 'draft' );
	$lifecycle->on_save( 111, $draft, true );
	payload_after_flush( $lifecycle, 0 );

	echo "News editorial revalidation lifecycle test passed.\n";
}
