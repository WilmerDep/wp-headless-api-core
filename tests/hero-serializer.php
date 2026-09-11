<?php
/**
 * Isolated Hero serializer regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		public $ID;
		public $menu_order;

		public function __construct( $id, $menu_order = 0 ) {
			$this->ID         = $id;
			$this->menu_order = $menu_order;
		}
	}

	$GLOBALS['hero_thumbnail_ids'] = array();
	$GLOBALS['hero_meta']          = array();
	$GLOBALS['hero_images']        = array();

	function get_post_thumbnail_id( $post ) {
		return $GLOBALS['hero_thumbnail_ids'][ $post->ID ] ?? 0;
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $single );
		return $GLOBALS['hero_meta'][ $post_id ][ $key ] ?? '';
	}

	function wp_get_attachment_image_src( $attachment_id, $size ) {
		unset( $size );
		return $GLOBALS['hero_images'][ $attachment_id ] ?? false;
	}

	function esc_url_raw( $value, $protocols = null ) {
		unset( $protocols );
		return trim( (string) $value );
	}

	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}

	function wp_parse_url( $value, $component = -1 ) {
		return parse_url( $value, $component );
	}
}

namespace HeadlessApiCore\Modules\Hero {
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Serializer.php';

	$post = new \WP_Post( 7, 3 );
	$GLOBALS['hero_thumbnail_ids'][7] = 101;
	$GLOBALS['hero_images'][101] = array( 'https://cms.example.org/desktop.jpg', 1920, 760 );
	$GLOBALS['hero_images'][102] = array( 'https://cms.example.org/mobile.jpg', 760, 960 );
	$GLOBALS['hero_meta'][7] = array(
		Hero_Post_Type::META_MOBILE_IMAGE_ID => 102,
		Hero_Post_Type::META_HREF            => '/servicios',
		Hero_Post_Type::META_ALT             => 'Slide accessible',
		Hero_Post_Type::META_OBJECT_POSITION => 'center top',
	);
	$GLOBALS['hero_meta'][101]['_wp_attachment_image_alt'] = 'Primary attachment alt';
	$GLOBALS['hero_meta'][102]['_wp_attachment_image_alt'] = 'Mobile attachment alt';

	$serializer = new Hero_Serializer();
	$item       = $serializer->item( $post );

	if ( ! is_array( $item ) ) {
		fwrite( STDERR, "Hero serializer must return a public item when primary image is valid.\n" );
		exit( 1 );
	}

	$expected = array(
		'id'             => 7,
		'image'          => array(
			'url'    => 'https://cms.example.org/desktop.jpg',
			'alt'    => 'Slide accessible',
			'width'  => 1920,
			'height' => 760,
		),
		'mobileImage'    => array(
			'url'    => 'https://cms.example.org/mobile.jpg',
			'alt'    => 'Slide accessible',
			'width'  => 760,
			'height' => 960,
		),
		'href'           => '/servicios',
		'order'          => 3,
		'objectPosition' => 'center top',
	);

	if ( $item !== $expected ) {
		fwrite( STDERR, "Hero serializer normalized payload mismatch.\n" );
		exit( 1 );
	}

	$GLOBALS['hero_meta'][7][ Hero_Post_Type::META_ALT ] = '';
	$item = $serializer->item( $post );
	if ( 'Primary attachment alt' !== $item['image']['alt'] || 'Mobile attachment alt' !== $item['mobileImage']['alt'] ) {
		fwrite( STDERR, "Hero serializer must fall back to each attachment alt when no explicit slide alt exists.\n" );
		exit( 1 );
	}

	$GLOBALS['hero_thumbnail_ids'][7] = 999;
	if ( null !== $serializer->item( $post ) ) {
		fwrite( STDERR, "Hero serializer must exclude an item whose required primary image is invalid.\n" );
		exit( 1 );
	}

	echo "Hero serializer test passed.\n";
}
