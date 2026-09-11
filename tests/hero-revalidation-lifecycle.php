<?php
/**
 * Isolated regression test for Hero editorial lifecycle aggregation.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HEADLESS_API_CORE_VERSION', '0.3.4' );
	define( 'HEADLESS_REVALIDATION_URL', 'https://consumer.example.org/api/headless/revalidate' );
	define( 'HEADLESS_REVALIDATION_SECRET', 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789' );

	#[\AllowDynamicProperties]
	class WP_Post {
		public $ID;
		public $post_status;
		public $post_type = 'headless_hero';
		public $menu_order = 0;

		public function __construct( $id, $status, $post_type = 'headless_hero', $menu_order = 0 ) {
			$this->ID          = $id;
			$this->post_status = $status;
			$this->post_type   = $post_type;
			$this->menu_order  = $menu_order;
		}
	}

	class WP_Error {
		private $message;

		public function __construct( $message = 'test error' ) {
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	$GLOBALS['hero_revalidation_requests'] = array();
	$GLOBALS['hero_revalidation_actions']  = array();
	$GLOBALS['hero_revalidation_logs']     = array();
	$GLOBALS['hero_revalidation_fail']     = false;
	$GLOBALS['hero_test_posts']            = array();
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

	function wp_safe_remote_post( $url, $args ) {
		$GLOBALS['hero_revalidation_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( ! empty( $GLOBALS['hero_revalidation_fail'] ) ) {
			return new \WP_Error( 'consumer unavailable' );
		}

		return array( 'response' => array( 'code' => 200 ) );
	}

	function is_wp_error( $value ) {
		return $value instanceof \WP_Error;
	}

	function wp_remote_retrieve_response_code( $response ) {
		return (int) $response['response']['code'];
	}

	function do_action( $hook, ...$args ) {
		$GLOBALS['hero_revalidation_actions'][] = array(
			'hook' => $hook,
			'args' => $args,
		);
	}

	function error_log( $message ) {
		$GLOBALS['hero_revalidation_logs'][] = $message;
		return true;
	}
}

namespace HeadlessApiCore\Modules\Hero {
	function get_post( $post_id ) {
		return $GLOBALS['hero_test_posts'][ $post_id ] ?? null;
	}

	require_once dirname( __DIR__ ) . '/includes/Revalidation/Revalidation_Client.php';
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Revalidation.php';

	use HeadlessApiCore\Revalidation\Revalidation_Client;

	function payload_after_flush( Hero_Revalidation $lifecycle, $expected_delta = 1 ) {
		$before = count( $GLOBALS['hero_revalidation_requests'] );
		$lifecycle->flush();
		$after = count( $GLOBALS['hero_revalidation_requests'] );

		if ( $expected_delta !== ( $after - $before ) ) {
			fwrite( STDERR, "Unexpected number of Hero lifecycle deliveries.\n" );
			exit( 1 );
		}

		if ( 0 === $expected_delta ) {
			return null;
		}

		$request = $GLOBALS['hero_revalidation_requests'][ $after - 1 ];
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
	$lifecycle = new Hero_Revalidation( $client );

	// draft -> publish: transition + post_updated + save collapse to one event.
	$before = new \WP_Post( 201, 'draft' );
	$after  = new \WP_Post( 201, 'publish' );
	$lifecycle->on_transition( 'publish', 'draft', $after );
	$lifecycle->on_post_updated( 201, $after, $before );
	$lifecycle->on_save( 201, $after, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload(
		$payload,
		array(
			'resource'       => 'hero',
			'postId'         => 201,
			'status'         => 'publish',
			'previousStatus' => 'draft',
			'event'          => 'status_changed',
		),
		'draft -> publish'
	);

	// future -> publish fires only when WordPress performs the real transition.
	$after = new \WP_Post( 202, 'publish' );
	$lifecycle->on_transition( 'publish', 'future', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'publish', 'previousStatus' => 'future', 'event' => 'status_changed' ), 'future -> publish' );

	// Public -> non-public transitions must invalidate immediately.
	foreach ( array( 'draft', 'private', 'trash', 'future' ) as $index => $status ) {
		$post_id = 203 + $index;
		$after   = new \WP_Post( $post_id, $status );
		$lifecycle->on_transition( $status, 'publish', $after );
		$payload = payload_after_flush( $lifecycle );
		assert_payload( $payload, array( 'status' => $status, 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> ' . $status );
	}

	// Republishing from a non-public state must invalidate too.
	$after = new \WP_Post( 207, 'publish' );
	$lifecycle->on_transition( 'publish', 'private', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'publish', 'previousStatus' => 'private', 'event' => 'status_changed' ), 'republish' );

	// Published in-place save.
	$published = new \WP_Post( 208, 'publish' );
	$lifecycle->on_save( 208, $published, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'content_updated', 'status' => 'publish', 'previousStatus' => 'publish' ), 'published save' );

	// Order changes are represented by post_updated while remaining published.
	$before = new \WP_Post( 209, 'publish', 'headless_hero', 1 );
	$after  = new \WP_Post( 209, 'publish', 'headless_hero', 2 );
	$lifecycle->on_post_updated( 209, $after, $before );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'content_updated', 'status' => 'publish' ), 'order change' );

	// Every public Hero meta field must invalidate an already published Hero.
	$watched_meta = array(
		'_thumbnail_id',
		Hero_Post_Type::META_MOBILE_IMAGE_ID,
		Hero_Post_Type::META_HREF,
		Hero_Post_Type::META_ALT,
		Hero_Post_Type::META_OBJECT_POSITION,
	);

	foreach ( $watched_meta as $index => $meta_key ) {
		$post_id = 210 + $index;
		$GLOBALS['hero_test_posts'][ $post_id ] = new \WP_Post( $post_id, 'publish' );
		$lifecycle->on_meta_changed( 1, $post_id, $meta_key, 'changed' );
		$payload = payload_after_flush( $lifecycle );
		assert_payload( $payload, array( 'postId' => $post_id, 'event' => 'content_updated', 'status' => 'publish' ), 'meta change ' . $meta_key );
	}

	// Unrelated metadata must not emit a Hero webhook by itself.
	$GLOBALS['hero_test_posts'][220] = new \WP_Post( 220, 'publish' );
	$lifecycle->on_meta_changed( 1, 220, '_unrelated_meta', 'changed' );
	payload_after_flush( $lifecycle, 0 );

	// Permanent deletion emits a terminal deleted event.
	$deleted = new \WP_Post( 221, 'trash' );
	$lifecycle->on_before_delete( 221, $deleted );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'deleted', 'status' => 'deleted', 'previousStatus' => 'trash' ), 'permanent delete' );

	// Draft-only saves do not affect the public Hero collection.
	$draft = new \WP_Post( 222, 'draft' );
	$lifecycle->on_save( 222, $draft, true );
	payload_after_flush( $lifecycle, 0 );

	// Consumer failure must not throw or block the already-completed WP save.
	$GLOBALS['hero_revalidation_fail'] = true;
	$published = new \WP_Post( 223, 'publish' );
	$lifecycle->on_save( 223, $published, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'resource' => 'hero', 'postId' => 223, 'event' => 'content_updated' ), 'consumer failure payload' );

	if ( empty( $GLOBALS['hero_revalidation_logs'] ) ) {
		fwrite( STDERR, "Expected safe failure logging when the Consumer is unavailable.\n" );
		exit( 1 );
	}

	$GLOBALS['hero_revalidation_fail'] = false;

	echo "Hero editorial revalidation lifecycle test passed.\n";
}
