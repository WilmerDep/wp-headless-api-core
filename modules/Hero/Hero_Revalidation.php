<?php
/**
 * Hero editorial lifecycle -> generic revalidation delivery.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

use HeadlessApiCore\Revalidation\Revalidation_Client;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Hero_Revalidation {
	/**
	 * Event priority when multiple WordPress hooks describe one editorial save.
	 * Higher priority wins while previous status context is preserved.
	 *
	 * @var array<string,int>
	 */
	private static $event_priority = array(
		'content_updated' => 10,
		'status_changed'  => 30,
		'deleted'         => 40,
	);

	/**
	 * Generic signed client.
	 *
	 * @var Revalidation_Client
	 */
	private $client;

	/**
	 * One aggregated outbound event per Hero post for the current request.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $pending = array();

	/**
	 * Prevent recursive/double flushes.
	 *
	 * @var bool
	 */
	private $flushing = false;

	/**
	 * @param Revalidation_Client $client Signed revalidation client.
	 */
	public function __construct( Revalidation_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Register Hero lifecycle hooks.
	 *
	 * transition_post_status covers real public visibility transitions, including
	 * WP-Cron future -> publish. post/meta/save hooks cover published content,
	 * image and ordering changes. Delivery is deferred to shutdown so one editor
	 * save emits at most one Hero event per post.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'save_post_' . Hero_Post_Type::POST_TYPE, array( $this, 'on_save' ), 30, 3 );
		add_action( 'added_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
	}

	/**
	 * Record a status transition that crosses the public publish boundary.
	 *
	 * WordPress may fire transition_post_status with identical old/new states;
	 * those pseudo-transitions are intentionally ignored.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $this->is_hero_post( $post ) || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}

		$this->queue_event( $post, 'status_changed', $old_status, $new_status );
	}

	/**
	 * Capture previous status and published in-place updates, including order.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  New post state.
	 * @param WP_Post $post_before Previous post state.
	 * @return void
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		unset( $post_id );

		if ( ! $this->is_hero_post( $post_after ) || ! $this->is_hero_post( $post_before ) ) {
			return;
		}

		$status_changed = $post_after->post_status !== $post_before->post_status;

		if ( $status_changed && ( 'publish' === $post_after->post_status || 'publish' === $post_before->post_status ) ) {
			$this->queue_event( $post_after, 'status_changed', $post_before->post_status, $post_after->post_status );
			return;
		}

		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		$this->queue_event( $post_after, 'content_updated', 'publish', 'publish' );
	}

	/**
	 * Queue a published Hero save after the guided Hero admin has persisted its
	 * custom fields. A status event, when present, wins during request dedupe.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public function on_save( $post_id, $post, $update ) {
		unset( $post_id, $update );

		if ( ! $this->is_hero_post( $post ) || 'publish' !== $post->post_status ) {
			return;
		}

		$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
	}

	/**
	 * Public Hero metadata changes must invalidate the Consumer immediately.
	 *
	 * Watched keys cover the primary image, mobile image, optional link, Hero
	 * alt override and object-position. menu_order is captured by post_updated.
	 *
	 * @param int    $meta_id    Metadata row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 * @return void
	 */
	public function on_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		$watched_keys = array(
			'_thumbnail_id',
			Hero_Post_Type::META_MOBILE_IMAGE_ID,
			Hero_Post_Type::META_HREF,
			Hero_Post_Type::META_ALT,
			Hero_Post_Type::META_OBJECT_POSITION,
		);

		if ( ! in_array( (string) $meta_key, $watched_keys, true ) ) {
			return;
		}

		$post = get_post( $object_id );

		if ( $this->is_hero_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	/**
	 * Invalidate Hero data on permanent deletion, even when already trashed.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when supplied by WordPress.
	 * @return void
	 */
	public function on_before_delete( $post_id, $post = null ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			$post = get_post( $post_id );
		}

		if ( ! $this->is_hero_post( $post ) ) {
			return;
		}

		$this->queue_event( $post, 'deleted', $post->post_status, 'deleted' );
	}

	/**
	 * Deliver aggregated request-local events.
	 *
	 * Revalidation_Client converts transport/consumer errors to false and never
	 * throws them into the WordPress editorial flow.
	 *
	 * @return void
	 */
	public function flush() {
		if ( $this->flushing || empty( $this->pending ) ) {
			return;
		}

		$this->flushing = true;
		$events         = $this->pending;
		$this->pending  = array();

		foreach ( $events as $payload ) {
			$this->client->send( $payload );
		}

		$this->flushing = false;
	}

	/**
	 * Aggregate duplicate hooks for one Hero post/request.
	 *
	 * @param WP_Post $post            Current post object.
	 * @param string  $event           Event name.
	 * @param string  $previous_status Previous status when known.
	 * @param string  $status          Current logical status.
	 * @return void
	 */
	private function queue_event( WP_Post $post, $event, $previous_status, $status ) {
		$post_id = (int) $post->ID;

		$candidate = array(
			'resource'       => 'hero',
			'postId'         => $post_id,
			'status'         => (string) $status,
			'previousStatus' => (string) $previous_status,
			'event'          => (string) $event,
		);

		if ( ! isset( $this->pending[ $post_id ] ) ) {
			$this->pending[ $post_id ] = $candidate;
			return;
		}

		$current = $this->pending[ $post_id ];

		// Latest current status is authoritative at the end of the request.
		$current['status'] = $candidate['status'];

		// Preserve a real old status when later hooks only know current status.
		if ( $candidate['previousStatus'] !== $candidate['status'] ) {
			$current['previousStatus'] = $candidate['previousStatus'];
		}

		$current_priority   = isset( self::$event_priority[ $current['event'] ] ) ? self::$event_priority[ $current['event'] ] : 0;
		$candidate_priority = isset( self::$event_priority[ $candidate['event'] ] ) ? self::$event_priority[ $candidate['event'] ] : 0;

		if ( $candidate_priority > $current_priority ) {
			$current['event'] = $candidate['event'];
		}

		$this->pending[ $post_id ] = $current;
	}

	/**
	 * Limit this observer to the Hero CPT only.
	 *
	 * @param mixed $post Candidate post.
	 * @return bool
	 */
	private function is_hero_post( $post ) {
		return $post instanceof WP_Post && Hero_Post_Type::POST_TYPE === $post->post_type;
	}
}
