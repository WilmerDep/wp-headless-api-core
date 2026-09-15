<?php
/**
 * Forms section-title admin regression contract.
 */

$root = dirname( __DIR__ );
$js   = file_get_contents( $root . '/assets/admin/forms.js' );

$required = array(
	"function sectionDisplayName(section)",
	"section.title = typeof section.title === 'string' ? section.title : '';",
	"section.eyebrow = typeof section.eyebrow === 'string' ? section.eyebrow : '';",
	"section.title || section.eyebrow || section.id",
	"<label>Subtítulo<input",
	"title: strings.untitledSection || 'Nueva sección'",
);

foreach ( $required as $needle ) {
	if ( false === strpos( $js, $needle ) ) {
		fwrite( STDERR, "Forms section-title contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $js, "section.title = section.title || (strings.untitledSection || 'Nueva sección');" ) ) {
	fwrite( STDERR, "Existing blank section titles are still being overwritten by the new-section fallback.\n" );
	exit( 1 );
}

if ( false === strpos( $js, 'placeholder="Ej.: international-phone"' ) ) {
	fwrite( STDERR, "Consumer component placeholder should remain clearly illustrative.\n" );
	exit( 1 );
}

echo "Forms section-title admin contract test passed.\n";
