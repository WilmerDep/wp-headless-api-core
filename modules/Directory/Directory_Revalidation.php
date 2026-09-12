<?php
/**
 * Directory editorial lifecycle -> generic revalidation delivery.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use HeadlessApiCore\Revalidation\Revalidation_Client;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Directory_Revalidation {
	/**
	 * @var array<string,int>
	 */
	private static $event_priority = array(
		'content_updated' => 10,
		'status_changed'  => 30,
		'deleted'         => 40,
	);

	/** @var Revalidation_Client */
	private $client;

	/** @var array<int,array<string,mixed>> */
	private $pending = array();

	/** @var bool */
	private $flushing = false;

	/** @var bool */
	private $bulk_mode = false;

	/** @var bool */
	private $bulk_dirty = false;

	/**
	 * @param Revalidation_Client $client Signed transport.
	 */
	public function __construct( Revalidation_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Register Directory lifecycle hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'save_post_' . Directory_Post_Type::POST_TYPE, array( $this, 'on_save' ), 30, 3 );
		add_action( 'added_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'set_object_terms', array( $this, 'on_terms_changed' ), 10, 6 );
		add_action( 'created_' . Directory_Post_Type::TAXONOMY, array( $this, 'on_group_changed' ), 10, 3 );
		add_action( 'edited_' . Directory_Post_Type::TAXONOMY, array( $this, 'on_group_changed' ), 10, 3 );
		add_action( 'delete_' . Directory_Post_Type::TAXONOMY, array( $this, 'on_group_deleted' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ), 10, 2 );
		add_action( 'headless_api_core_directory_bulk_start', array( $this, 'on_bulk_start' ), 10, 1 );
		add_action( 'headless_api_core_directory_bulk_end', array( $this, 'on_bulk_end' ), 10, 1 );
		add_action( 'headless_api_core_directory_collection_changed', array( $this, 'on_collection_changed' ), 10, 1 );
		add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
	}

	/**
	 * Capture transitions crossing the public publish boundary.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $this->is_directory_post( $post ) || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}

		$this->queue_event( $post, 'status_changed', $old_status, $new_status );
	}

	/**
	 * Capture published content/order updates.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  New state.
	 * @param WP_Post $post_before Previous state.
	 * @return void
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		unset( $post_id );

		if ( ! $this->is_directory_post( $post_after ) || ! $this->is_directory_post( $post_before ) ) {
			return;
		}

		if ( $post_after->post_status !== $post_before->post_status ) {
			if ( 'publish' === $post_after->post_status || 'publish' === $post_before->post_status ) {
				$this->queue_event( $post_after, 'status_changed', $post_before->post_status, $post_after->post_status );
			}
			return;
		}

		if ( 'publish' === $post_after->post_status ) {
			$this->queue_event( $post_after, 'content_updated', 'publish', 'publish' );
		}
	}

	/**
	 * Capture a published save after custom admin persistence.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Update flag.
	 * @return void
	 */
	public function on_save( $post_id, $post, $update ) {
		unset( $post_id, $update );

		if ( $this->is_directory_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	/**
	 * Capture public person meta changes.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		$watched = array(
			'_thumbnail_id',
			Directory_Post_Type::META_ROLE,
			Directory_Post_Type::META_JOINED_AT,
			Directory_Post_Type::META_PHONE,
			Directory_Post_Type::META_EMAIL,
			Directory_Post_Type::META_SUMMARY,
			Directory_Post_Type::META_ALT,
			Directory_Post_Type::META_GROUP_ORDER,
		);

		if ( ! in_array( (string) $meta_key, $watched, true ) ) {
			return;
		}

		$post = get_post( $object_id );
		if ( $this->is_directory_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	/**
	 * Capture person group assignment changes.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @param bool   $append    Append flag.
	 * @param array  $old_tt_ids Previous term taxonomy IDs.
	 * @return void
	 */
	public function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $tt_ids, $append, $old_tt_ids );

		if ( Directory_Post_Type::TAXONOMY !== $taxonomy ) {
			return;
		}

		$post = get_post( $object_id );
		if ( $this->is_directory_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	/**
	 * Group create/edit affects the groups endpoint and nested group labels.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Hook args.
	 * @return void
	 */
	public function on_group_changed( $term_id, $tt_id, $args = array() ) {
		unset( $term_id, $tt_id, $args );
		$this->queue_collection_event();
	}

	/**
	 * Group deletion affects both person group arrays and groups endpoint.
	 *
	 * @param int   $term         Term ID.
	 * @param int   $tt_id        Term taxonomy ID.
	 * @param mixed $deleted_term Deleted term object.
	 * @param array $object_ids   Related object IDs.
	 * @return void
	 */
	public function on_group_deleted( $term, $tt_id, $deleted_term, $object_ids ) {
		unset( $term, $tt_id, $deleted_term, $object_ids );
		$this->queue_collection_event();
	}

	/**
	 * Permanent deletion must invalidate the public collection.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 * @return void
	 */
	public function on_before_delete( $post_id, $post = null ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			$post = get_post( $post_id );
		}

		if ( $this->is_directory_post( $post ) ) {
			$this->queue_event( $post, 'deleted', $post->post_status, 'deleted' );
		}
	}

	/**
	 * Enter request-local bulk mode so imports/reorders collapse deliveries.
	 *
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_bulk_start( $reason = '' ) {
		unset( $reason );
		$this->bulk_mode = true;
	}

	/**
	 * Leave bulk mode and queue one collection invalidation when needed.
	 *
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_bulk_end( $reason = '' ) {
		unset( $reason );
		$this->bulk_mode = false;
		if ( $this->bulk_dirty ) {
			$this->bulk_dirty = false;
			$this->queue_collection_event();
		}
	}

	/**
	 * Explicit collection-level invalidation from order/import services.
	 *
	 * @param string $reason Reason.
	 * @return void
	 */
	public function on_collection_changed( $reason = '' ) {
		unset( $reason );
		$this->queue_collection_event();
	}

	/**
	 * Deliver aggregated events.
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
	 * Queue/dedupe one person event.
	 *
	 * @param WP_Post $post            Post.
	 * @param string  $event           Event.
	 * @param string  $previous_status Previous status.
	 * @param string  $status          Current status.
	 * @return void
	 */
	private function queue_event( WP_Post $post, $event, $previous_status, $status ) {
		if ( $this->bulk_mode ) {
			$this->bulk_dirty = true;
			return;
		}

		$key       = (int) $post->ID;
		$candidate = array(
			'resource'       => 'directory',
			'postId'         => $key,
			'status'         => (string) $status,
			'previousStatus' => (string) $previous_status,
			'event'          => (string) $event,
		);

		$this->merge_candidate( $key, $candidate );
	}

	/**
	 * Queue one collection-level event.
	 *
	 * @return void
	 */
	private function queue_collection_event() {
		if ( $this->bulk_mode ) {
			$this->bulk_dirty = true;
			return;
		}

		$this->merge_candidate(
			0,
			array(
				'resource'       => 'directory',
				'postId'         => 0,
				'status'         => 'publish',
				'previousStatus' => 'publish',
				'event'          => 'content_updated',
			)
		);
	}

	/**
	 * Merge one candidate into the request-local queue.
	 *
	 * @param int   $key       Queue key.
	 * @param array $candidate Candidate payload.
	 * @return void
	 */
	private function merge_candidate( $key, array $candidate ) {
		if ( ! isset( $this->pending[ $key ] ) ) {
			$this->pending[ $key ] = $candidate;
			return;
		}

		$current           = $this->pending[ $key ];
		$current['status'] = $candidate['status'];

		if ( $candidate['previousStatus'] !== $candidate['status'] ) {
			$current['previousStatus'] = $candidate['previousStatus'];
		}

		$current_priority   = isset( self::$event_priority[ $current['event'] ] ) ? self::$event_priority[ $current['event'] ] : 0;
		$candidate_priority = isset( self::$event_priority[ $candidate['event'] ] ) ? self::$event_priority[ $candidate['event'] ] : 0;

		if ( $candidate_priority > $current_priority ) {
			$current['event'] = $candidate['event'];
		}

		$this->pending[ $key ] = $current;
	}

	/**
	 * Limit observer to Directory people.
	 *
	 * @param mixed $post Candidate post.
	 * @return bool
	 */
	private function is_directory_post( $post ) {
		return $post instanceof WP_Post && Directory_Post_Type::POST_TYPE === $post->post_type;
	}
}
