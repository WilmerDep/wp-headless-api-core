<?php
/**
 * Isolated regression test for Directory editorial lifecycle aggregation.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HEADLESS_API_CORE_VERSION', '0.4.0' );
	define( 'HEADLESS_REVALIDATION_URL', 'https://consumer.example.org/api/headless/revalidate' );
	define( 'HEADLESS_REVALIDATION_SECRET', 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789' );

	#[\AllowDynamicProperties]
	class WP_Post {
		public $ID;
		public $post_status;
		public $post_type = 'headless_person';
		public $menu_order = 0;

		public function __construct( $id, $status, $post_type = 'headless_person', $menu_order = 0 ) {
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

	$GLOBALS['directory_revalidation_requests'] = array();
	$GLOBALS['directory_revalidation_actions']  = array();
	$GLOBALS['directory_revalidation_logs']     = array();
	$GLOBALS['directory_revalidation_fail']     = false;
	$GLOBALS['directory_test_posts']            = array();
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
		$GLOBALS['directory_revalidation_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( ! empty( $GLOBALS['directory_revalidation_fail'] ) ) {
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
		$GLOBALS['directory_revalidation_actions'][] = array(
			'hook' => $hook,
			'args' => $args,
		);
	}

	function error_log( $message ) {
		$GLOBALS['directory_revalidation_logs'][] = $message;
		return true;
	}
}

namespace HeadlessApiCore\Modules\Directory {
	function get_post( $post_id ) {
		return $GLOBALS['directory_test_posts'][ $post_id ] ?? null;
	}

	require_once dirname( __DIR__ ) . '/includes/Revalidation/Revalidation_Client.php';
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Revalidation.php';

	use HeadlessApiCore\Revalidation\Revalidation_Client;

	function payload_after_flush( Directory_Revalidation $lifecycle, $expected_delta = 1 ) {
		$before = count( $GLOBALS['directory_revalidation_requests'] );
		$lifecycle->flush();
		$after = count( $GLOBALS['directory_revalidation_requests'] );

		if ( $expected_delta !== ( $after - $before ) ) {
			fwrite( STDERR, "Unexpected number of Directory lifecycle deliveries.\n" );
			exit( 1 );
		}

		if ( 0 === $expected_delta ) {
			return null;
		}

		$request = $GLOBALS['directory_revalidation_requests'][ $after - 1 ];
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
	$lifecycle = new Directory_Revalidation( $client );

	// draft -> publish: transition + post_updated + save collapse to one event.
	$before = new \WP_Post( 301, 'draft' );
	$after  = new \WP_Post( 301, 'publish' );
	$lifecycle->on_transition( 'publish', 'draft', $after );
	$lifecycle->on_post_updated( 301, $after, $before );
	$lifecycle->on_save( 301, $after, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload(
		$payload,
		array(
			'resource'       => 'directory',
			'postId'         => 301,
			'status'         => 'publish',
			'previousStatus' => 'draft',
			'event'          => 'status_changed',
		),
		'draft -> publish'
	);

	// future -> publish fires only when WordPress performs the real transition.
	$after = new \WP_Post( 302, 'publish' );
	$lifecycle->on_transition( 'publish', 'future', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'publish', 'previousStatus' => 'future', 'event' => 'status_changed' ), 'future -> publish' );

	// Public -> non-public transitions must invalidate immediately.
	foreach ( array( 'draft', 'private', 'trash', 'future' ) as $index => $status ) {
		$post_id = 303 + $index;
		$after   = new \WP_Post( $post_id, $status );
		$lifecycle->on_transition( $status, 'publish', $after );
		$payload = payload_after_flush( $lifecycle );
		assert_payload( $payload, array( 'status' => $status, 'previousStatus' => 'publish', 'event' => 'status_changed' ), 'publish -> ' . $status );
	}

	// Republishing from a non-public state must invalidate too.
	$after = new \WP_Post( 307, 'publish' );
	$lifecycle->on_transition( 'publish', 'private', $after );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'status' => 'publish', 'previousStatus' => 'private', 'event' => 'status_changed' ), 'republish' );

	// Published in-place save.
	$published = new \WP_Post( 308, 'publish' );
	$lifecycle->on_save( 308, $published, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'event' => 'content_updated', 'status' => 'publish', 'previousStatus' => 'publish' ), 'published save' );

	// Global order changes are represented by post_updated while remaining published.
	$before = new \WP_Post( 309, 'publish', 'headless_person', 1 );
	$after  = new \WP_Post( 309, 'publish', 'headless_person', 2 );
	$lifecycle->on_post_updated( 309, $after, $before );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'postId' => 309, 'event' => 'content_updated', 'status' => 'publish' ), 'global order change' );

	// Every public Directory meta field must invalidate an already published person.
	$watched_meta = array(
		'_thumbnail_id',
		Directory_Post_Type::META_ROLE,
		Directory_Post_Type::META_JOINED_AT,
		Directory_Post_Type::META_PHONE,
		Directory_Post_Type::META_EMAIL,
		Directory_Post_Type::META_SUMMARY,
		Directory_Post_Type::META_ALT,
		Directory_Post_Type::META_GROUP_ORDER,
	);

	foreach ( $watched_meta as $index => $meta_key ) {
		$post_id = 310 + $index;
		$GLOBALS['directory_test_posts'][ $post_id ] = new \WP_Post( $post_id, 'publish' );
		$lifecycle->on_meta_changed( 1, $post_id, $meta_key, 'changed' );
		$payload = payload_after_flush( $lifecycle );
		assert_payload( $payload, array( 'postId' => $post_id, 'event' => 'content_updated', 'status' => 'publish' ), 'meta change ' . $meta_key );
	}

	// Group assignment changes invalidate the affected published person.
	$GLOBALS['directory_test_posts'][320] = new \WP_Post( 320, 'publish' );
	$lifecycle->on_terms_changed( 320, array( 10 ), array( 10 ), Directory_Post_Type::TAXONOMY, false, array() );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'postId' => 320, 'event' => 'content_updated', 'status' => 'publish' ), 'group assignment change' );

	// Unrelated metadata must not emit a Directory webhook by itself.
	$GLOBALS['directory_test_posts'][321] = new \WP_Post( 321, 'publish' );
	$lifecycle->on_meta_changed( 1, 321, '_unrelated_meta', 'changed' );
	payload_after_flush( $lifecycle, 0 );

	// Group create/edit/delete are collection-level changes.
	$lifecycle->on_group_changed( 10, 20, array() );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'resource' => 'directory', 'postId' => 0, 'event' => 'content_updated' ), 'group create/edit' );

	$lifecycle->on_group_deleted( 10, 20, null, array() );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'resource' => 'directory', 'postId' => 0, 'event' => 'content_updated' ), 'group delete' );

	// Bulk imports/reorders collapse many internal mutations to one collection event.
	$GLOBALS['directory_test_posts'][330] = new \WP_Post( 330, 'publish' );
	$GLOBALS['directory_test_posts'][331] = new \WP_Post( 331, 'publish' );
	$lifecycle->on_bulk_start( 'import' );
	$lifecycle->on_save( 330, $GLOBALS['directory_test_posts'][330], true );
	$lifecycle->on_meta_changed( 1, 331, Directory_Post_Type::META_ROLE, 'changed' );
	$lifecycle->on_group_changed( 12, 22, array() );
	$lifecycle->on_collection_changed( 'import' );
	$lifecycle->on_bulk_end( 'import' );
	$lifecycle->on_collection_changed( 'import' );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'resource' => 'directory', 'postId' => 0, 'event' => 'content_updated' ), 'bulk collapse' );

	// Permanent deletion emits a terminal deleted event.
	$deleted = new \WP_Post( 340, 'trash' );
	$lifecycle->on_before_delete( 340, $deleted );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'postId' => 340, 'event' => 'deleted', 'status' => 'deleted', 'previousStatus' => 'trash' ), 'permanent delete' );

	// Draft-only saves do not affect the public Directory collection.
	$draft = new \WP_Post( 341, 'draft' );
	$lifecycle->on_save( 341, $draft, true );
	payload_after_flush( $lifecycle, 0 );

	// Consumer failure must not throw or block the already-completed WordPress save.
	$GLOBALS['directory_revalidation_fail'] = true;
	$published = new \WP_Post( 342, 'publish' );
	$lifecycle->on_save( 342, $published, true );
	$payload = payload_after_flush( $lifecycle );
	assert_payload( $payload, array( 'resource' => 'directory', 'postId' => 342, 'event' => 'content_updated' ), 'consumer failure payload' );

	if ( empty( $GLOBALS['directory_revalidation_logs'] ) ) {
		fwrite( STDERR, "Expected safe failure logging when the Consumer is unavailable.\n" );
		exit( 1 );
	}

	$GLOBALS['directory_revalidation_fail'] = false;

	echo "Directory editorial revalidation lifecycle test passed.\n";
}
