<?php
/**
 * Hero public payload serializer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Hero_Serializer {
	/**
	 * Serialize one public Hero item.
	 *
	 * Returns null when the required primary image is missing/invalid.
	 *
	 * @param WP_Post $post Hero post.
	 * @return array|null
	 */
	public function item( WP_Post $post ) {
		$primary_id = (int) get_post_thumbnail_id( $post );
		$explicit_alt = trim( (string) get_post_meta( $post->ID, Hero_Post_Type::META_ALT, true ) );
		$image = $this->image( $primary_id, $explicit_alt );

		if ( null === $image ) {
			return null;
		}

		$mobile_id = (int) get_post_meta( $post->ID, Hero_Post_Type::META_MOBILE_IMAGE_ID, true );
		$mobile = $mobile_id > 0 ? $this->image( $mobile_id, $explicit_alt ) : null;

		$href = Hero_Post_Type::sanitize_href( get_post_meta( $post->ID, Hero_Post_Type::META_HREF, true ) );
		$object_position = Hero_Post_Type::sanitize_object_position(
			get_post_meta( $post->ID, Hero_Post_Type::META_OBJECT_POSITION, true )
		);

		return array(
			'id'             => (int) $post->ID,
			'image'          => $image,
			'mobileImage'    => $mobile,
			'href'           => '' !== $href ? $href : null,
			'order'          => (int) $post->menu_order,
			'objectPosition' => '' !== $object_position ? $object_position : null,
		);
	}

	/**
	 * Normalize one image attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $explicit_alt  Optional Hero-level alt override.
	 * @return array|null
	 */
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
		$alt = '' !== trim( (string) $explicit_alt ) ? trim( (string) $explicit_alt ) : $attachment_alt;

		return array(
			'url'    => esc_url_raw( (string) $source[0] ),
			'alt'    => sanitize_text_field( $alt ),
			'width'  => isset( $source[1] ) ? (int) $source[1] : 0,
			'height' => isset( $source[2] ) ? (int) $source[2] : 0,
		);
	}
}
