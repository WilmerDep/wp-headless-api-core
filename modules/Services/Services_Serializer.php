<?php
/**
 * Services public payload serializer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Services_Serializer {
	/** Serialize one public service. */
	public function item( WP_Post $post ) {
		$image_id     = (int) get_post_thumbnail_id( $post );
		$explicit_alt = trim( (string) get_post_meta( $post->ID, Services_Post_Type::META_ALT, true ) );
		$image        = $this->image( $image_id, $explicit_alt );

		$description  = sanitize_textarea_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_DESCRIPTION, true ) );
		$audience     = sanitize_textarea_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_AUDIENCE, true ) );
		$department   = sanitize_text_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_DEPARTMENT, true ) );
		$requirements = Services_Post_Type::sanitize_requirements( get_post_meta( $post->ID, Services_Post_Type::META_REQUIREMENTS, true ) );
		$procedure    = sanitize_textarea_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_PROCEDURE, true ) );
		$schedule     = sanitize_textarea_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_SCHEDULE, true ) );
		$cost         = sanitize_text_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_COST, true ) );
		$duration     = sanitize_text_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_DURATION, true ) );
		$channel      = sanitize_text_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_CHANNEL, true ) );
		$phone        = sanitize_text_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_PHONE, true ) );
		$email        = Services_Post_Type::sanitize_email_value( get_post_meta( $post->ID, Services_Post_Type::META_EMAIL, true ) );
		$address      = sanitize_textarea_field( (string) get_post_meta( $post->ID, Services_Post_Type::META_ADDRESS, true ) );

		return array(
			'id'           => (int) $post->ID,
			'slug'         => sanitize_title( $post->post_name ),
			'title'        => sanitize_text_field( get_the_title( $post ) ),
			'image'        => $image,
			'description'  => '' !== $description ? $description : null,
			'audience'     => '' !== $audience ? $audience : null,
			'department'   => '' !== $department ? $department : null,
			'requirements' => $requirements,
			'procedure'    => '' !== $procedure ? $procedure : null,
			'schedule'     => '' !== $schedule ? $schedule : null,
			'cost'         => '' !== $cost ? $cost : null,
			'duration'     => '' !== $duration ? $duration : null,
			'channel'      => '' !== $channel ? $channel : null,
			'phone'        => '' !== $phone ? $phone : null,
			'email'        => '' !== $email ? $email : null,
			'address'      => '' !== $address ? $address : null,
			'order'        => (int) $post->menu_order,
		);
	}

	/** Normalize one service image attachment. */
	private function image( $attachment_id, $explicit_alt = '' ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$source = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $source ) || empty( $source[0] ) ) {
			return null;
		}

		$attachment_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$alt            = '' !== trim( (string) $explicit_alt ) ? trim( (string) $explicit_alt ) : $attachment_alt;

		return array(
			'url'    => esc_url_raw( (string) $source[0] ),
			'alt'    => sanitize_text_field( $alt ),
			'width'  => isset( $source[1] ) ? (int) $source[1] : 0,
			'height' => isset( $source[2] ) ? (int) $source[2] : 0,
		);
	}
}
