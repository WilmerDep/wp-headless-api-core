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
		$mail_required = false;

		foreach ( $notifications as $notification ) {
			if ( ! empty( $notification['enabled'] ) ) {
				$mail_required = true;
				break;
			}
		}

		$submission_available = $enabled && ( ! $mail_required || $this->mail_settings->is_ready() );

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
}
