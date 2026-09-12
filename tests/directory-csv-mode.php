<?php
/**
 * Directory CSV-only import surface regression test.
 *
 * This test intentionally inspects the adapter source without booting WordPress.
 */

$path = dirname( __DIR__ ) . '/modules/Directory/Directory_Csv_Mode.php';
if ( ! is_file( $path ) ) {
	fwrite( STDERR, "Directory_Csv_Mode.php is missing\n" );
	exit( 1 );
}

$source = file_get_contents( $path );
if ( false === $source ) {
	fwrite( STDERR, "Could not read Directory_Csv_Mode.php\n" );
	exit( 1 );
}

$checks = array(
	'CSV template filename' => false !== strpos( $source, 'headless-directory-template.csv' ),
	'CSV content type' => false !== strpos( $source, 'Content-Type: text/csv; charset=UTF-8' ),
	'UTF-8 BOM for Excel interoperability' => false !== strpos( $source, '\\xEF\\xBB\\xBF' ),
	'non-CSV uploads rejected before importer' => false !== strpos( $source, "'csv' !== \$ext" ),
	'preview guard runs at priority 1' => false !== strpos( $source, "wp_ajax_headless_directory_preview_import', array( \$this, 'enforce_csv_upload' ), 1" ),
	'template override runs at priority 1' => false !== strpos( $source, "admin_post_headless_directory_download_template', array( \$this, 'download_template' ), 1" ),
	'admin copy says CSV' => false !== strpos( $source, 'Descargar plantilla CSV' ) && false !== strpos( $source, 'Arrastra aquí tu CSV' ),
	'file picker restricted to CSV' => false !== strpos( $source, "input.setAttribute('accept', '.csv,text/csv')" ),
);

$failed = array();
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		$failed[] = $label;
	}
}

if ( $failed ) {
	fwrite( STDERR, "Directory CSV mode regression failures:\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo "Directory CSV mode regression test passed.\n";
