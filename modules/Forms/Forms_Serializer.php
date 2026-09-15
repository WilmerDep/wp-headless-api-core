<?php
/**
 * Public form serializer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Modules\Mail\Mail_Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms_Serializer {
	/** @var Mail_Settings */
	private $mail_settings;

	public function __construct( Mail_Settings $mail_settings ) {
		$this->mail_settings = $mail_settings;
	}

	/** Serialize one published form into the public headless contract. */
	public function serialize( WP_Post $post ) {
		$enabled       = (bool) get_post_meta( $post->ID, Forms_Post_Type::META_ENABLED, true );
		$notifications = Forms_Schema::normalize_notifications( get_post_meta( $post->ID, Forms_Post_Type::META_NOTIFICATIONS, true ) );
		$delivery_ready = $this->notifications_ready( $notifications );
		$submission_available = $enabled && $delivery_ready && $this->mail_settings->is_ready();
		$submission_available = (bool) apply_filters( 'headless_api_core_form_submission_available', $submission_available, $post, $notifications );

		return array(
			'id'             => (int) $post->ID,
			'slug'           => $post->post_name,
			'title'          => get_the_title( $post ),
			'description'    => (string) get_post_meta( $post->ID, Forms_Post_Type::META_DESCRIPTION, true ),
			'schemaVersion'  => max( 1, absint( get_post_meta( $post->ID, Forms_Post_Type::META_SCHEMA_VERSION, true ) ) ),
			'sections'       => Forms_Schema::normalize_sections( get_post_meta( $post->ID, Forms_Post_Type::META_SECTIONS, true ) ),
			'fields'         => Forms_Schema::normalize_fields( get_post_meta( $post->ID, Forms_Post_Type::META_FIELDS, true ) ),
			'submitLabel'    => (string) get_post_meta( $post->ID, Forms_Post_Type::META_SUBMIT_LABEL, true ),
			'successMessage' => (string) get_post_meta( $post->ID, Forms_Post_Type::META_SUCCESS_MESSAGE, true ),
			'errorMessage'   => (string) get_post_meta( $post->ID, Forms_Post_Type::META_ERROR_MESSAGE, true ),
			'submission'     => array(
				'enabled'   => $enabled,
				'available' => $submission_available,
			),
		);
	}

	/** Require at least one complete, published notification route. */
	private function notifications_ready( array $notifications ) {
		$active = array_values( array_filter( $notifications, static function ( $notification ) {
			return ! empty( $notification['enabled'] );
		} ) );
		if ( empty( $active ) ) {
			return false;
		}

		foreach ( $active as $notification ) {
			if ( empty( $notification['to'] ) || empty( $notification['template'] ) ) {
				return false;
			}
			$template = get_page_by_path( $notification['template'], OBJECT, Forms_Post_Type::TEMPLATE_POST_TYPE );
			if ( ! $template || 'publish' !== $template->post_status ) {
				return false;
			}
		}
		return true;
	}
}
