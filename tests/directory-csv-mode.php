<?php
/**
 * Directory CSV import surface and final-write regression test.
 *
 * These checks intentionally inspect source without booting WordPress.
 */

$root = dirname( __DIR__ );
$csv_path = $root . '/modules/Directory/Directory_Csv_Mode.php';
$batch_path = $root . '/modules/Directory/Directory_Import_Batch.php';
$plugin_path = $root . '/wp-headless-api-core.php';

foreach ( array( $csv_path, $batch_path, $plugin_path ) as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, basename( $path ) . " is missing\n" );
		exit( 1 );
	}
}

$source = file_get_contents( $csv_path );
$batch = file_get_contents( $batch_path );
$plugin = file_get_contents( $plugin_path );
if ( false === $source || false === $batch || false === $plugin ) {
	fwrite( STDERR, "Could not read Directory import sources\n" );
	exit( 1 );
}

$resolve_position = strpos( $batch, '$term_ids = $this->resolve_groups' );
$insert_position = strpos( $batch, 'wp_insert_post' );
$groups_before_write = false !== $resolve_position && false !== $insert_position && $resolve_position < $insert_position;

$checks = array(
	'CSV template filename' => false !== strpos( $source, 'headless-directory-template.csv' ),
	'CSV content type' => false !== strpos( $source, 'Content-Type: text/csv; charset=UTF-8' ),
	'UTF-8 BOM for Excel interoperability' => false !== strpos( $source, '\\xEF\\xBB\\xBF' ),
	'non-CSV uploads rejected before importer' => false !== strpos( $source, "'csv' !== \$ext" ),
	'preview guard runs at priority 1' => false !== strpos( $source, "wp_ajax_headless_directory_preview_import', array( \$this, 'enforce_csv_upload' ), 1" ),
	'template override runs at priority 1' => false !== strpos( $source, "admin_post_headless_directory_download_template', array( \$this, 'download_template' ), 1" ),
	'admin copy says CSV' => false !== strpos( $source, 'Descargar plantilla CSV' ) && false !== strpos( $source, 'Arrastra aquí tu CSV' ),
	'file picker restricted to CSV' => false !== strpos( $source, "input.setAttribute('accept', '.csv,text/csv')" ),
	'image option means fallback download only' => false !== strpos( $source, 'Descargar image_url si no existe en Medios' ),
	'atomic batch is loaded' => false !== strpos( $plugin, "modules/Directory/Directory_Import_Batch.php" ),
	'atomic batch wins AJAX at priority 1' => false !== strpos( $batch, "wp_ajax_headless_directory_import_batch', array( \$this, 'import_batch' ), 1" ),
	'groups resolve before person write' => $groups_before_write,
	'failed writes have rollback support' => false !== strpos( $batch, 'rollback_person' ) && false !== strpos( $batch, 'wp_delete_post( $post_id, true )' ),
	'existing media matched by WordPress attachment path' => false !== strpos( $batch, "'_wp_attached_file'" ) && false !== strpos( $batch, 'upload_relative_path' ),
	'media reuse ignores host through upload path' => false !== strpos( $batch, '/wp-content/uploads/' ),
	'existing media is preferred before sideload' => false !== strpos( $batch, '$existing_id = $this->find_existing_attachment( $url )' ) && false !== strpos( $batch, "'source' => 'existing'" ),
	'remote image download is fallback only' => false !== strpos( $batch, 'if ( ! $download_images )' ) && false !== strpos( $batch, 'media_sideload_image' ),
);

$failed = array();
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		$failed[] = $label;
	}
}

if ( $failed ) {
	fwrite( STDERR, "Directory CSV/import regression failures:\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo "Directory CSV/import regression test passed.\n";
