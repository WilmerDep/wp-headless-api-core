<?php
/**
 * Native WordPress Site Identity serializer for headless Consumers.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\SiteIdentity;

defined( 'ABSPATH' ) || exit;

final class Site_Identity_Serializer {
	const SCHEMA_VERSION = 1;

	/** Serialize native WordPress site identity with deterministic fallbacks. */
	public function serialize() {
		$name        = trim( (string) get_bloginfo( 'name' ) );
		$description = trim( (string) get_bloginfo( 'description' ) );
		$language    = str_replace( '_', '-', (string) get_locale() );
		$logo        = $this->logo_contract( $name );
		$favicon     = $this->favicon_contract( $name );

		return array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'site'          => array(
				'name'        => $name,
				'description' => $description,
				'url'         => home_url( '/' ),
				'language'    => $language,
			),
			'branding'      => array(
				'logo'    => $logo,
				'favicon' => $favicon,
			),
		);
	}

	/** Prefer WordPress-native global Site Logo, then classic custom_logo. */
	private function logo_contract( $site_name ) {
		$source  = '';
		$logo_id = absint( get_option( 'site_logo', 0 ) );

		if ( $logo_id > 0 ) {
			$source = 'site_logo';
		} else {
			$logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
			if ( $logo_id > 0 ) {
				$source = 'custom_logo';
			}
		}

		$image = $logo_id > 0 ? $this->image_payload( $logo_id, $site_name ) : null;
		if ( $image ) {
			return array(
				'kind'     => 'image',
				'source'   => $source,
				'image'    => $image,
				'fallback' => array(
					'kind' => 'text',
					'text' => $site_name,
				),
			);
		}

		return array(
			'kind'     => 'text',
			'source'   => 'fallback',
			'image'    => null,
			'fallback' => array(
				'kind' => 'text',
				'text' => $site_name,
			),
		);
	}

	/** Use the native Site Icon and expose an initial fallback when absent. */
	private function favicon_contract( $site_name ) {
		$icon_id = absint( get_option( 'site_icon', 0 ) );
		$image   = $icon_id > 0 ? $this->image_payload( $icon_id, $site_name ) : null;
		$initial = $this->site_initial( $site_name );

		if ( $image ) {
			$sizes = array();
			foreach ( array( 32, 180, 192, 512 ) as $size ) {
				$url = get_site_icon_url( $size );
				if ( $url ) {
					$sizes[ (string) $size ] = esc_url_raw( $url );
				}
			}

			return array(
				'kind'     => 'image',
				'source'   => 'site_icon',
				'image'    => $image,
				'sizes'    => $sizes,
				'fallback' => array(
					'kind'    => 'initial',
					'initial' => $initial,
				),
			);
		}

		return array(
			'kind'     => 'initial',
			'source'   => 'fallback',
			'image'    => null,
			'sizes'    => array(),
			'fallback' => array(
				'kind'    => 'initial',
				'initial' => $initial,
			),
		);
	}

	/** Serialize one Media Library attachment without theme-specific markup. */
	private function image_payload( $attachment_id, $fallback_alt ) {
		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return null;
		}

		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$alt = $fallback_alt;
		}

		$post        = get_post( $attachment_id );
		$modified_at = $post ? mysql_to_rfc3339( $post->post_modified_gmt ) : '';

		return array(
			'id'         => (int) $attachment_id,
			'url'        => esc_url_raw( $src[0] ),
			'alt'        => sanitize_text_field( $alt ),
			'width'      => isset( $src[1] ) ? absint( $src[1] ) : 0,
			'height'     => isset( $src[2] ) ? absint( $src[2] ) : 0,
			'mimeType'   => (string) get_post_mime_type( $attachment_id ),
			'modifiedAt' => $modified_at,
		);
	}

	/** Resolve the first alphanumeric site-name character for favicon fallback UI. */
	private function site_initial( $site_name ) {
		$clean = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $site_name );
		$clean = is_string( $clean ) ? $clean : '';
		if ( '' === $clean ) {
			return 'W';
		}

		$initial = function_exists( 'mb_substr' ) ? mb_substr( $clean, 0, 1, 'UTF-8' ) : substr( $clean, 0, 1 );
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial, 'UTF-8' ) : strtoupper( $initial );
	}
}
