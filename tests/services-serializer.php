<?php
/**
 * Isolated Services serializer regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		public $ID;
		public $menu_order;
		public $post_name;
		public function __construct( $id, $slug, $order = 0 ) { $this->ID = $id; $this->post_name = $slug; $this->menu_order = $order; }
	}

	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_textarea_field( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", strip_tags( (string) $value ) );
		return trim( $value );
	}
	function sanitize_email( $value ) { return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ); }
	function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function sanitize_title( $value ) { return trim( strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $value ) ), '-' ); }
	function esc_url_raw( $value ) { return (string) $value; }
	function get_the_title( $post ) { return $GLOBALS['service_test_titles'][ $post->ID ] ?? ''; }
	function get_post_meta( $post_id, $key, $single = false ) { unset( $single ); return $GLOBALS['service_test_meta'][ $post_id ][ $key ] ?? ''; }
	function get_post_thumbnail_id( $post ) { return $GLOBALS['service_test_thumbnails'][ $post->ID ] ?? 0; }
	function wp_get_attachment_image_src( $attachment_id, $size ) { unset( $size ); return $GLOBALS['service_test_images'][ $attachment_id ] ?? false; }

	$GLOBALS['service_test_meta'] = array();
	$GLOBALS['service_test_titles'] = array();
	$GLOBALS['service_test_thumbnails'] = array();
	$GLOBALS['service_test_images'] = array();
}

namespace HeadlessApiCore\Modules\Services {
	require_once dirname( __DIR__ ) . '/modules/Services/Services_Post_Type.php';
	require_once dirname( __DIR__ ) . '/modules/Services/Services_Serializer.php';

	$post = new \WP_Post( 21, 'anatomia-patologica', 4 );
	$GLOBALS['service_test_titles'][21] = 'Anatomía Patológica';
	$GLOBALS['service_test_thumbnails'][21] = 90;
	$GLOBALS['service_test_images'][90] = array( 'https://cms.example.org/anatomia.jpg', 1600, 900 );
	$GLOBALS['service_test_meta'][90]['_wp_attachment_image_alt'] = 'Alt de biblioteca';
	$GLOBALS['service_test_meta'][21] = array(
		Services_Post_Type::META_DESCRIPTION  => 'Descripción del servicio.',
		Services_Post_Type::META_AUDIENCE     => 'Todo usuario que requiera el servicio.',
		Services_Post_Type::META_DEPARTMENT   => 'Subdirección Operativa',
		Services_Post_Type::META_REQUIREMENTS => array( 'Cédula de identidad.', '', 'Indicación médica.' ),
		Services_Post_Type::META_PROCEDURE    => 'Presentarse en el área correspondiente.',
		Services_Post_Type::META_SCHEDULE     => 'Lunes a Viernes, 08:00 A.M. a 12:00 M.',
		Services_Post_Type::META_COST         => '0.00',
		Services_Post_Type::META_DURATION     => '30 minutos promedio',
		Services_Post_Type::META_CHANNEL      => 'Presencial',
		Services_Post_Type::META_PHONE        => '(809) 533-8568 Ext. 6088',
		Services_Post_Type::META_EMAIL        => 'a.usuario@example.org',
		Services_Post_Type::META_ADDRESS      => 'Av. Principal No. 1',
		Services_Post_Type::META_ALT          => 'Imagen del servicio de Anatomía Patológica',
		Services_Post_Type::META_EXTERNAL_ID  => 'PRIVATE-SRV-001',
	);

	$serializer = new Services_Serializer();
	$item = $serializer->item( $post );

	if ( 21 !== $item['id'] || 'anatomia-patologica' !== $item['slug'] || 'Anatomía Patológica' !== $item['title'] ) {
		fwrite( STDERR, "Services serializer failed identity fields.\n" ); exit( 1 );
	}
	if ( 4 !== $item['order'] || 'Imagen del servicio de Anatomía Patológica' !== $item['image']['alt'] || 1600 !== $item['image']['width'] ) {
		fwrite( STDERR, "Services serializer failed image/order fields.\n" ); exit( 1 );
	}
	if ( array( 'Cédula de identidad.', 'Indicación médica.' ) !== $item['requirements'] ) {
		fwrite( STDERR, "Services serializer failed requirements normalization.\n" ); exit( 1 );
	}
	if ( 'a.usuario@example.org' !== $item['email'] || 'Presencial' !== $item['channel'] || '0.00' !== $item['cost'] ) {
		fwrite( STDERR, "Services serializer failed operational fields.\n" ); exit( 1 );
	}
	if ( array_key_exists( 'externalId', $item ) || array_key_exists( 'external_id', $item ) ) {
		fwrite( STDERR, "Services serializer exposed private external ID.\n" ); exit( 1 );
	}

	$GLOBALS['service_test_thumbnails'][21] = 0;
	$item = $serializer->item( $post );
	if ( null !== $item['image'] ) {
		fwrite( STDERR, "Services serializer failed optional image null semantics.\n" ); exit( 1 );
	}

	echo "Services serializer test passed.\n";
}
