<?php
/**
 * Isolated Directory serializer regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		public $ID;
		public $menu_order;
		public function __construct( $id, $order = 0 ) { $this->ID = $id; $this->menu_order = $order; }
	}

	class WP_Term {
		public $term_id;
		public $slug;
		public $name;
		public function __construct( $id, $slug, $name ) { $this->term_id = $id; $this->slug = $slug; $this->name = $name; }
	}

	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_textarea_field( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", strip_tags( (string) $value ) );
		return trim( $value );
	}
	function sanitize_email( $value ) { return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ); }
	function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', trim( (string) $value ) ) ); }
	function esc_url_raw( $value ) { return (string) $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function is_wp_error( $value ) { return false; }
	function wp_kses( $value, $allowed_html ) {
		$allowed = '';
		foreach ( array_keys( $allowed_html ) as $tag ) {
			$allowed .= '<' . $tag . '>';
		}
		$value = strip_tags( (string) $value, $allowed );
		return preg_replace_callback(
			'/<\/?([a-z0-9]+)(?:\s[^>]*)?>/i',
			static function ( $matches ) use ( $allowed_html ) {
				$tag = strtolower( $matches[1] );
				if ( ! isset( $allowed_html[ $tag ] ) ) {
					return '';
				}
				return 0 === strpos( $matches[0], '</' ) ? '</' . $tag . '>' : '<' . $tag . '>';
			},
			$value
		);
	}

	$GLOBALS['directory_test_meta'] = array();
	$GLOBALS['directory_test_terms'] = array();
	$GLOBALS['directory_test_term_meta'] = array();
	$GLOBALS['directory_test_titles'] = array();
	$GLOBALS['directory_test_thumbnails'] = array();
	$GLOBALS['directory_test_images'] = array();

	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $single );
		return isset( $GLOBALS['directory_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['directory_test_meta'][ $post_id ][ $key ] : '';
	}
	function get_post_thumbnail_id( $post ) { return isset( $GLOBALS['directory_test_thumbnails'][ $post->ID ] ) ? $GLOBALS['directory_test_thumbnails'][ $post->ID ] : 0; }
	function wp_get_attachment_image_src( $attachment_id, $size ) { unset( $size ); return isset( $GLOBALS['directory_test_images'][ $attachment_id ] ) ? $GLOBALS['directory_test_images'][ $attachment_id ] : false; }
	function wp_get_post_terms( $post_id, $taxonomy ) { unset( $taxonomy ); return isset( $GLOBALS['directory_test_terms'][ $post_id ] ) ? $GLOBALS['directory_test_terms'][ $post_id ] : array(); }
	function get_term_meta( $term_id, $key, $single = false ) { unset( $key, $single ); return isset( $GLOBALS['directory_test_term_meta'][ $term_id ] ) ? $GLOBALS['directory_test_term_meta'][ $term_id ] : 0; }
	function get_the_title( $post ) { return isset( $GLOBALS['directory_test_titles'][ $post->ID ] ) ? $GLOBALS['directory_test_titles'][ $post->ID ] : ''; }
}

namespace HeadlessApiCore\Modules\Directory {
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Serializer.php';

	$post = new \WP_Post( 10, 5 );
	$GLOBALS['directory_test_titles'][10] = 'Dra. Ejemplo';
	$GLOBALS['directory_test_thumbnails'][10] = 55;
	$GLOBALS['directory_test_images'][55] = array( 'https://cms.example.org/person.jpg', 900, 1200 );
	$GLOBALS['directory_test_meta'][55]['_wp_attachment_image_alt'] = 'Alt de Medios';
	$GLOBALS['directory_test_meta'][10] = array(
		Directory_Post_Type::META_ROLE             => 'Directora Ejecutiva',
		Directory_Post_Type::META_JOINED_AT        => '2026-09-11',
		Directory_Post_Type::META_POLICE_JOINED_AT => '1995',
		Directory_Post_Type::META_RECOGNITION      => 'Mérito Policial · 2025',
		Directory_Post_Type::META_PHONE            => '(809) 555-0000',
		Directory_Post_Type::META_EMAIL            => 'persona@example.org',
		Directory_Post_Type::META_SUMMARY          => "Primer párrafo.\n\nSegundo párrafo.",
		Directory_Post_Type::META_SUMMARY_HTML     => '<p>Primer <strong>párrafo</strong>.</p><p>Segundo <em>párrafo</em>.<script>x</script></p>',
		Directory_Post_Type::META_ALT              => 'Retrato institucional',
		Directory_Post_Type::META_GROUP_ORDER      => array( '12' => 1 ),
		Directory_Post_Type::META_EXTERNAL_ID      => 'PRIVATE-001',
	);
	$GLOBALS['directory_test_terms'][10] = array(
		new \WP_Term( 12, 'directores', 'Directores' ),
		new \WP_Term( 13, 'medicos', 'Médicos' ),
	);
	$GLOBALS['directory_test_term_meta'][12] = 2;
	$GLOBALS['directory_test_term_meta'][13] = 0;

	$serializer = new Directory_Serializer();
	$item = $serializer->item( $post, 12 );

	if ( ! is_array( $item ) || 10 !== $item['id'] || 'Dra. Ejemplo' !== $item['name'] ) {
		fwrite( STDERR, "Directory serializer failed identity fields.\n" ); exit( 1 );
	}
	if ( 1 !== $item['order'] || 'Retrato institucional' !== $item['image']['alt'] || 900 !== $item['image']['width'] ) {
		fwrite( STDERR, "Directory serializer failed image/effective order fields.\n" ); exit( 1 );
	}
	if ( 13 !== $item['groups'][0]['id'] || 12 !== $item['groups'][1]['id'] ) {
		fwrite( STDERR, "Directory serializer failed group ordering.\n" ); exit( 1 );
	}
	if ( 'persona@example.org' !== $item['email'] || '2026-09-11' !== $item['joinedAt'] ) {
		fwrite( STDERR, "Directory serializer failed public contact/date fields.\n" ); exit( 1 );
	}
	if ( '1995' !== $item['policeJoinedAt'] || 'Mérito Policial · 2025' !== $item['recognition'] ) {
		fwrite( STDERR, "Directory serializer failed institutional profile fields.\n" ); exit( 1 );
	}
	if ( "Primer párrafo.\n\nSegundo párrafo." !== $item['summary'] ) {
		fwrite( STDERR, "Directory serializer did not preserve summary paragraph breaks.\n" ); exit( 1 );
	}
	if ( '<p>Primer <strong>párrafo</strong>.</p><p>Segundo <em>párrafo</em>.x</p>' !== $item['summaryHtml'] ) {
		fwrite( STDERR, "Directory serializer failed safe summaryHtml output.\n" ); exit( 1 );
	}
	if ( array_key_exists( 'external_id', $item ) || array_key_exists( 'externalId', $item ) ) {
		fwrite( STDERR, "Directory serializer exposed private external_id.\n" ); exit( 1 );
	}

	unset( $GLOBALS['directory_test_meta'][10][ Directory_Post_Type::META_POLICE_JOINED_AT ] );
	unset( $GLOBALS['directory_test_meta'][10][ Directory_Post_Type::META_RECOGNITION ] );
	unset( $GLOBALS['directory_test_meta'][10][ Directory_Post_Type::META_SUMMARY_HTML ] );
	$item = $serializer->item( $post );
	if ( null !== $item['policeJoinedAt'] || null !== $item['recognition'] || null !== $item['summaryHtml'] ) {
		fwrite( STDERR, "Directory serializer failed null semantics for optional fields.\n" ); exit( 1 );
	}

	$GLOBALS['directory_test_thumbnails'][10] = 0;
	if ( null !== $serializer->item( $post ) ) {
		fwrite( STDERR, "Directory serializer exposed a person without portrait.\n" ); exit( 1 );
	}

	echo "Directory serializer test passed.\n";
}
