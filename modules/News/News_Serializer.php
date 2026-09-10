<?php
/**
 * News payload serializer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\News;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class News_Serializer {
	/**
	 * Build the lightweight collection representation for a post.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return array<string, mixed>
	 */
	public function summary( WP_Post $post ) {
		return array(
			'id'            => (int) $post->ID,
			'slug'          => (string) $post->post_name,
			'title'         => $this->normalize_text( get_the_title( $post ) ),
			'excerpt'       => $this->get_excerpt( $post ),
			'publishedAt'   => get_post_time( DATE_ATOM, false, $post ),
			'modifiedAt'    => get_post_modified_time( DATE_ATOM, false, $post ),
			'featuredImage' => $this->get_featured_image( $post ),
			'categories'    => $this->get_categories( $post ),
			'author'        => $this->get_author( $post ),
		);
	}

	/**
	 * Build the full detail representation for a post.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return array<string, mixed>
	 */
	public function detail( WP_Post $post ) {
		$payload = $this->summary( $post );

		$payload['content'] = (string) apply_filters( 'the_content', $post->post_content );
		$payload['seo']     = $this->get_seo( $post, $payload );

		return $payload;
	}

	/**
	 * Return a clean text excerpt.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return string
	 */
	private function get_excerpt( WP_Post $post ) {
		return $this->normalize_text( get_the_excerpt( $post ) );
	}

	/**
	 * Normalize a public plain-text field.
	 *
	 * WordPress-generated excerpts and titles may contain HTML entities such as
	 * `&hellip;`. The provider contract returns decoded plain text instead of
	 * leaking presentation entities to consumers.
	 *
	 * @param mixed $value Raw text value.
	 * @return string
	 */
	private function normalize_text( $value ) {
		$charset = get_bloginfo( 'charset' );
		$charset = $charset ? $charset : 'UTF-8';
		$text    = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, $charset );
		$text    = wp_strip_all_tags( $text );
		$text    = preg_replace( '/\s+/u', ' ', $text );

		return trim( is_string( $text ) ? $text : '' );
	}

	/**
	 * Remove CMS presentation branding from a public SEO title.
	 *
	 * Headless consumers own public site-name composition. Yoast commonly turns
	 * a post title into "Post title - CMS site name". When the normalized Yoast
	 * title is simply the provider fallback title followed by a separator and
	 * extra branding, the provider returns the fallback title. This avoids
	 * hardcoding any institution or CMS name while preserving genuinely custom
	 * SEO titles that differ from the native title.
	 *
	 * A second pass removes the current WordPress site name when it is available
	 * and actually appears as the suffix.
	 *
	 * @param mixed  $value    Candidate SEO title.
	 * @param string $fallback Native normalized post title.
	 * @return string
	 */
	private function normalize_seo_title( $value, $fallback ) {
		$title          = $this->normalize_text( $value );
		$fallback_title = $this->normalize_text( $fallback );
		$site_name      = $this->normalize_text( get_bloginfo( 'name' ) );

		if ( '' === $title ) {
			return $fallback_title;
		}

		if ( '' !== $fallback_title ) {
			$native_with_suffix = '/^' . preg_quote( $fallback_title, '/' ) . '\s+(?:[-–—|·:»]+)\s+.+$/iu';

			if ( 1 === preg_match( $native_with_suffix, $title ) ) {
				return $fallback_title;
			}
		}

		if ( '' !== $site_name ) {
			$pattern = '/\s*(?:[-–—|·:»]+)\s*' . preg_quote( $site_name, '/' ) . '\s*$/iu';
			$cleaned = preg_replace( $pattern, '', $title );

			if ( is_string( $cleaned ) && '' !== trim( $cleaned ) ) {
				$title = trim( $cleaned );
			} elseif ( 0 === strcasecmp( $title, $site_name ) ) {
				$title = $fallback_title;
			}
		}

		return $title;
	}

	/**
	 * Return normalized featured image metadata.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return array<string, mixed>|null
	 */
	private function get_featured_image( WP_Post $post ) {
		$image_id = get_post_thumbnail_id( $post );

		if ( ! $image_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $image_id, 'full' );

		if ( ! $image ) {
			return null;
		}

		return array(
			'url'    => esc_url_raw( $image[0] ),
			'alt'    => $this->normalize_text( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ),
			'width'  => (int) $image[1],
			'height' => (int) $image[2],
		);
	}

	/**
	 * Return normalized native WordPress categories.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_categories( WP_Post $post ) {
		$terms = get_the_category( $post->ID );

		if ( empty( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'   => (int) $term->term_id,
				'slug' => (string) $term->slug,
				'name' => $this->normalize_text( $term->name ),
			);
		}

		return $categories;
	}

	/**
	 * Return the public author representation.
	 *
	 * The public News contract intentionally exposes only the WordPress
	 * `display_name`. Login, username, email and other user-account fields remain
	 * private implementation details and are never included in this payload.
	 *
	 * @param WP_Post $post WordPress post.
	 * @return array{name:string}
	 */
	private function get_author( WP_Post $post ) {
		return array(
			'name' => $this->normalize_text(
				get_the_author_meta( 'display_name', (int) $post->post_author )
			),
		);
	}

	/**
	 * Build the provider-owned SEO representation.
	 *
	 * Yoast is optional. Canonical URLs, robots directives and Schema are
	 * intentionally excluded until the public frontend URL strategy exists.
	 *
	 * @param WP_Post              $post    WordPress post.
	 * @param array<string, mixed> $payload Normalized post payload.
	 * @return array<string, mixed>
	 */
	private function get_seo( WP_Post $post, array $payload ) {
		$fallback_title = (string) $payload['title'];
		$title          = $fallback_title;
		$description    = (string) $payload['excerpt'];
		$og_title       = $title;
		$og_desc        = $description;
		$source         = 'wordpress';

		if ( function_exists( 'YoastSEO' ) ) {
			try {
				$surface = \YoastSEO()->meta->for_post( $post->ID );

				if ( $surface ) {
					$source = 'yoast';

					if ( ! empty( $surface->title ) ) {
						$title = $this->normalize_seo_title( $surface->title, $fallback_title );
					}

					if ( ! empty( $surface->description ) ) {
						$description = $this->normalize_text( $surface->description );
					}

					if ( ! empty( $surface->open_graph_title ) ) {
						$og_title = $this->normalize_seo_title( $surface->open_graph_title, $title );
					} else {
						$og_title = $title;
					}

					if ( ! empty( $surface->open_graph_description ) ) {
						$og_desc = $this->normalize_text( $surface->open_graph_description );
					} else {
						$og_desc = $description;
					}
				}
			} catch ( \Throwable $exception ) {
				unset( $exception );
			}
		}

		$images = array();

		if ( ! empty( $payload['featuredImage'] ) ) {
			$images[] = $payload['featuredImage'];
		}

		$seo = array(
			'source'      => $source,
			'title'       => $title,
			'description' => $description,
			'openGraph'   => array(
				'title'       => $og_title,
				'description' => $og_desc,
				'images'      => $images,
			),
		);

		/**
		 * Filter normalized News SEO data without changing the core serializer.
		 *
		 * @param array<string, mixed> $seo  SEO payload.
		 * @param WP_Post              $post WordPress post.
		 */
		return (array) apply_filters( 'headless_api_core_news_seo', $seo, $post );
	}
}
