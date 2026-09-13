<?php
/**
 * Services editorial lifecycle -> generic revalidation delivery.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use HeadlessApiCore\Revalidation\Revalidation_Client;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Services_Revalidation {
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

	public function __construct( Revalidation_Client $client ) {
		$this->client = $client;
	}

	/** Register Services lifecycle hooks. */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'save_post_' . Services_Post_Type::POST_TYPE, array( $this, 'on_save' ), 30, 3 );
		add_action( 'added_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ), 10, 2 );
		add_action( 'headless_api_core_services_bulk_start', array( $this, 'on_bulk_start' ), 10, 1 );
		add_action( 'headless_api_core_services_bulk_end', array( $this, 'on_bulk_end' ), 10, 1 );
		add_action( 'headless_api_core_services_collection_changed', array( $this, 'on_collection_changed' ), 10, 1 );
		add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
	}

	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $this->is_service_post( $post ) || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}
		$this->queue_event( $post, 'status_changed', $old_status, $new_status );
	}

	public function on_post_updated( $post_id, $post_after, $post_before ) {
		unset( $post_id );
		if ( ! $this->is_service_post( $post_after ) || ! $this->is_service_post( $post_before ) ) {
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

	public function on_save( $post_id, $post, $update ) {
		unset( $post_id, $update );
		if ( $this->is_service_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	public function on_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$watched = array(
			'_thumbnail_id',
			Services_Post_Type::META_DESCRIPTION,
			Services_Post_Type::META_AUDIENCE,
			Services_Post_Type::META_DEPARTMENT,
			Services_Post_Type::META_REQUIREMENTS,
			Services_Post_Type::META_PROCEDURE,
			Services_Post_Type::META_SCHEDULE,
			Services_Post_Type::META_COST,
			Services_Post_Type::META_DURATION,
			Services_Post_Type::META_CHANNEL,
			Services_Post_Type::META_PHONE,
			Services_Post_Type::META_EMAIL,
			Services_Post_Type::META_ADDRESS,
			Services_Post_Type::META_ALT,
		);
		if ( ! in_array( (string) $meta_key, $watched, true ) ) {
			return;
		}
		$post = get_post( $object_id );
		if ( $this->is_service_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', 'publish', 'publish' );
		}
	}

	public function on_before_delete( $post_id, $post = null ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			$post = get_post( $post_id );
		}
		if ( $this->is_service_post( $post ) ) {
			$this->queue_event( $post, 'deleted', $post->post_status, 'deleted' );
		}
	}

	public function on_bulk_start( $reason = '' ) {
		unset( $reason );
		$this->bulk_mode = true;
	}

	public function on_bulk_end( $reason = '' ) {
		unset( $reason );
		$this->bulk_mode = false;
		if ( $this->bulk_dirty ) {
			$this->bulk_dirty = false;
			$this->queue_collection_event();
		}
	}

	public function on_collection_changed( $reason = '' ) {
		unset( $reason );
		$this->queue_collection_event();
	}

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

	private function queue_event( WP_Post $post, $event, $previous_status, $status ) {
		if ( $this->bulk_mode ) {
			$this->bulk_dirty = true;
			return;
		}
		$key       = (int) $post->ID;
		$candidate = array(
			'resource'       => 'services',
			'postId'         => $key,
			'status'         => (string) $status,
			'previousStatus' => (string) $previous_status,
			'event'          => (string) $event,
		);
		$this->merge_candidate( $key, $candidate );
	}

	private function queue_collection_event() {
		if ( $this->bulk_mode ) {
			$this->bulk_dirty = true;
			return;
		}
		$this->merge_candidate(
			0,
			array(
				'resource'       => 'services',
				'postId'         => 0,
				'status'         => 'publish',
				'previousStatus' => 'publish',
				'event'          => 'content_updated',
			)
		);
	}

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

	private function is_service_post( $post ) {
		return $post instanceof WP_Post && Services_Post_Type::POST_TYPE === $post->post_type;
	}
}
