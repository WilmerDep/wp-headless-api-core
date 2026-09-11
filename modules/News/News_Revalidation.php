<?php
/**
 * News editorial lifecycle -> generic revalidation delivery.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

use HeadlessApiCore\Revalidation\Revalidation_Client;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class News_Revalidation {
	/**
	 * Event priority when multiple WordPress hooks describe one editorial save.
	 * Higher priority wins while previous slug/status context is preserved.
	 *
	 * @var array<string,int>
	 */
	private static $event_priority = array(
		'content_updated' => 10,
		'slug_changed'    => 20,
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
	 * One aggregated outbound event per post for the current request.
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
	 * Register News lifecycle hooks.
	 *
	 * transition_post_status covers real status transitions, including WP-Cron
	 * future -> publish and publish -> trash. post_updated captures the old slug.
	 * save/meta/term hooks ensure publish -> publish edits also invalidate.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'save_post_post', array( $this, 'on_save' ), 20, 3 );
		add_action( 'set_object_terms', array( $this, 'on_terms_changed' ), 10, 6 );
		add_action( 'added_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush' ), PHP_INT_MAX );
	}

	/**
	 * Record a visibility-boundary status transition.
	 *
	 * WordPress fires transition_post_status even when old/new are identical;
	 * those pseudo-transitions are intentionally ignored here.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $this->is_news_post( $post ) || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}

		$this->queue_event(
			$post,
			'status_changed',
			$post->post_name,
			$old_status,
			$new_status
		);
	}

	/**
	 * Capture previous slug/status and published in-place edits.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  New post state.
	 * @param WP_Post $post_before Previous post state.
	 * @return void
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		unset( $post_id );

		if ( ! $this->is_news_post( $post_after ) || ! $this->is_news_post( $post_before ) ) {
			return;
		}

		$status_changed = $post_after->post_status !== $post_before->post_status;

		if ( $status_changed && ( 'publish' === $post_after->post_status || 'publish' === $post_before->post_status ) ) {
			$this->queue_event(
				$post_after,
				'status_changed',
				$post_before->post_name,
				$post_before->post_status,
				$post_after->post_status
			);
			return;
		}

		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		$event = $post_after->post_name !== $post_before->post_name ? 'slug_changed' : 'content_updated';

		$this->queue_event(
			$post_after,
			$event,
			$post_before->post_name,
			$post_before->post_status,
			$post_after->post_status
		);
	}

	/**
	 * Ensure a published save queues revalidation even when a plugin changes
	 * metadata later in the same request. Delivery is deferred to shutdown.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public function on_save( $post_id, $post, $update ) {
		unset( $post_id, $update );

		if ( ! $this->is_news_post( $post ) || 'publish' !== $post->post_status ) {
			return;
		}

		$this->queue_event( $post, 'content_updated', $post->post_name, 'publish', 'publish' );
	}

	/**
	 * Category changes alter the public News payload.
	 *
	 * @param int          $object_id Post ID.
	 * @param array|string $terms     Terms.
	 * @param array        $tt_ids    Term taxonomy IDs.
	 * @param string       $taxonomy  Taxonomy.
	 * @param bool         $append    Append flag.
	 * @param array        $old_tt_ids Previous IDs.
	 * @return void
	 */
	public function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $tt_ids, $append, $old_tt_ids );

		if ( 'category' !== $taxonomy ) {
			return;
		}

		$post = get_post( $object_id );

		if ( $this->is_news_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', $post->post_name, 'publish', 'publish' );
		}
	}

	/**
	 * Featured-image and Yoast SEO meta changes alter the public contract.
	 *
	 * @param int    $meta_id    Metadata row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 * @return void
	 */
	public function on_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( '_thumbnail_id' !== $meta_key && 0 !== strpos( (string) $meta_key, '_yoast_wpseo_' ) ) {
			return;
		}

		$post = get_post( $object_id );

		if ( $this->is_news_post( $post ) && 'publish' === $post->post_status ) {
			$this->queue_event( $post, 'content_updated', $post->post_name, 'publish', 'publish' );
		}
	}

	/**
	 * Invalidate a permanently deleted post even when it was already in trash.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when supplied by WordPress.
	 * @return void
	 */
	public function on_before_delete( $post_id, $post = null ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			$post = get_post( $post_id );
		}

		if ( ! $this->is_news_post( $post ) ) {
			return;
		}

		$this->queue_event(
			$post,
			'deleted',
			$post->post_name,
			$post->post_status,
			'deleted'
		);
	}

	/**
	 * Deliver the aggregated request-local events.
	 *
	 * No exception is thrown into WordPress editing. The client converts
	 * transport/consumer errors to false and logs safe diagnostics.
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
	 * Aggregate duplicate hooks for one post/request.
	 *
	 * @param WP_Post $post            Current post object.
	 * @param string  $event           Event name.
	 * @param string  $previous_slug   Previous slug when known.
	 * @param string  $previous_status Previous status when known.
	 * @param string  $status          Current logical status.
	 * @return void
	 */
	private function queue_event( WP_Post $post, $event, $previous_slug, $previous_status, $status ) {
		$post_id = (int) $post->ID;
		$slug    = (string) $post->post_name;

		$candidate = array(
			'resource'       => 'news',
			'postId'         => $post_id,
			'slug'           => $slug,
			'previousSlug'   => (string) $previous_slug,
			'status'         => (string) $status,
			'previousStatus' => (string) $previous_status,
			'event'          => (string) $event,
		);

		if ( ! isset( $this->pending[ $post_id ] ) ) {
			$this->pending[ $post_id ] = $candidate;
			return;
		}

		$current = $this->pending[ $post_id ];

		// Latest slug/status are authoritative at the end of the request.
		$current['slug']   = $candidate['slug'];
		$current['status'] = $candidate['status'];

		// Preserve an actual old slug/status when later hooks only know current.
		if ( $candidate['previousSlug'] !== $candidate['slug'] ) {
			$current['previousSlug'] = $candidate['previousSlug'];
		}

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
	 * News currently wraps native WordPress posts only.
	 *
	 * @param mixed $post Candidate post.
	 * @return bool
	 */
	private function is_news_post( $post ) {
		return $post instanceof WP_Post && 'post' === $post->post_type;
	}
}
