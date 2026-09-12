<?php
/**
 * Directory public payload serializer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Directory_Serializer {
	/**
	 * Serialize one public Directory person.
	 *
	 * Returns null when the required portrait is missing/invalid.
	 *
	 * @param WP_Post $post     Person post.
	 * @param int     $group_id Optional active group for effective ordering.
	 * @return array|null
	 */
	public function item( WP_Post $post, $group_id = 0 ) {
		$portrait_id  = (int) get_post_thumbnail_id( $post );
		$explicit_alt = trim( (string) get_post_meta( $post->ID, Directory_Post_Type::META_ALT, true ) );
		$image        = $this->image( $portrait_id, $explicit_alt );

		if ( null === $image ) {
			return null;
		}

		$role             = sanitize_text_field( (string) get_post_meta( $post->ID, Directory_Post_Type::META_ROLE, true ) );
		$joined_at        = Directory_Post_Type::sanitize_date( get_post_meta( $post->ID, Directory_Post_Type::META_JOINED_AT, true ) );
		$police_joined_at = sanitize_text_field( (string) get_post_meta( $post->ID, Directory_Post_Type::META_POLICE_JOINED_AT, true ) );
		$recognition      = sanitize_text_field( (string) get_post_meta( $post->ID, Directory_Post_Type::META_RECOGNITION, true ) );
		$phone            = sanitize_text_field( (string) get_post_meta( $post->ID, Directory_Post_Type::META_PHONE, true ) );
		$email            = Directory_Post_Type::sanitize_email_value( get_post_meta( $post->ID, Directory_Post_Type::META_EMAIL, true ) );
		$summary          = sanitize_textarea_field( (string) get_post_meta( $post->ID, Directory_Post_Type::META_SUMMARY, true ) );

		return array(
			'id'             => (int) $post->ID,
			'name'           => sanitize_text_field( get_the_title( $post ) ),
			'role'           => '' !== $role ? $role : null,
			'joinedAt'       => '' !== $joined_at ? $joined_at : null,
			'policeJoinedAt' => '' !== $police_joined_at ? $police_joined_at : null,
			'recognition'    => '' !== $recognition ? $recognition : null,
			'image'          => $image,
			'phone'          => '' !== $phone ? $phone : null,
			'email'          => '' !== $email ? $email : null,
			'summary'        => '' !== $summary ? $summary : null,
			'order'          => $this->effective_order( $post, (int) $group_id ),
			'groups'         => $this->groups( (int) $post->ID ),
		);
	}

	/**
	 * Calculate the effective public order.
	 *
	 * @param WP_Post $post     Person post.
	 * @param int     $group_id Active group term ID.
	 * @return int
	 */
	public function effective_order( WP_Post $post, $group_id = 0 ) {
		$group_id = (int) $group_id;
		if ( $group_id > 0 ) {
			$map = get_post_meta( $post->ID, Directory_Post_Type::META_GROUP_ORDER, true );
			$map = Directory_Post_Type::sanitize_group_order_map( $map );

			if ( array_key_exists( (string) $group_id, $map ) ) {
				return (int) $map[ (string) $group_id ];
			}
		}

		return (int) $post->menu_order;
	}

	/**
	 * Normalize groups attached to a person.
	 *
	 * @param int $post_id Person post ID.
	 * @return array
	 */
	public function groups( $post_id ) {
		$terms = wp_get_post_terms( (int) $post_id, Directory_Post_Type::TAXONOMY );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$groups = array();
		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}

			$groups[] = $this->group( $term );
		}

		usort(
			$groups,
			static function ( $a, $b ) {
				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] <=> $b['order'];
				}
				$name_compare = strcasecmp( $a['name'], $b['name'] );
				return 0 !== $name_compare ? $name_compare : ( $a['id'] <=> $b['id'] );
			}
		);

		return $groups;
	}

	/**
	 * Normalize one Directory group.
	 *
	 * @param WP_Term $term Group term.
	 * @return array
	 */
	public function group( WP_Term $term ) {
		return array(
			'id'    => (int) $term->term_id,
			'slug'  => sanitize_title( $term->slug ),
			'name'  => sanitize_text_field( $term->name ),
			'order' => max( 0, (int) get_term_meta( $term->term_id, Directory_Post_Type::TERM_META_ORDER, true ) ),
		);
	}

	/**
	 * Normalize one portrait attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $explicit_alt  Optional person-level alt override.
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
		$alt            = '' !== trim( (string) $explicit_alt ) ? trim( (string) $explicit_alt ) : $attachment_alt;

		return array(
			'url'    => esc_url_raw( (string) $source[0] ),
			'alt'    => sanitize_text_field( $alt ),
			'width'  => isset( $source[1] ) ? (int) $source[1] : 0,
			'height' => isset( $source[2] ) ? (int) $source[2] : 0,
		);
	}
}
