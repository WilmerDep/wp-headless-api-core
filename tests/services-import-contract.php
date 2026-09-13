<?php
/**
 * Services CSV import contract regression test.
 */

$path = dirname( __DIR__ ) . '/modules/Services/Services_Importer.php';
$source = file_get_contents( $path );

$needles = array(
	"'groups'",
	"'featured'",
	"'featured_order'",
	'Services_Features::META_FEATURED',
	'Services_Features::META_FEATURED_ORDER',
	'Services_Features::TAXONOMY',
	'resolve_group_ids',
	'wp_insert_term',
	'wp_set_object_terms',
	"'image_url'",
	"'image_alt'",
);

foreach ( $needles as $needle ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Services import contract missing: {$needle}\n" );
		exit( 1 );
	}
}

$group_pos = strpos( $source, '$group_ids = $this->resolve_group_ids' );
$post_pos = strpos( $source, '$postarr = array' );
if ( false === $group_pos || false === $post_pos || $group_pos > $post_pos ) {
	fwrite( STDERR, "Services import must resolve groups before writing the service.\n" );
	exit( 1 );
}

echo "Services complete CSV import contract test passed.\n";
