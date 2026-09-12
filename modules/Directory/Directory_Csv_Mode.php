<?php
/**
 * Directory CSV import mode.
 *
 * Keeps the validated v0.4.0 import workflow on the format proven in the
 * real CMS while XLSX support is deferred to a later release.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

defined( 'ABSPATH' ) || exit;

final class Directory_Csv_Mode {
	/**
	 * Register the CSV-only public/admin import surface.
	 *
	 * Directory_Importer owns parsing/normalization/preview. Directory_Import_Batch
	 * owns the final write phase, including atomic rows and Media Library reuse.
	 * This class constrains the exposed file format and replaces the template
	 * download with a CSV template.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_headless_directory_download_template', array( $this, 'download_template' ), 1 );
		add_action( 'wp_ajax_headless_directory_preview_import', array( $this, 'enforce_csv_upload' ), 1 );
		add_filter( 'gettext_wp-headless-api-core', array( $this, 'csv_copy' ), 20, 3 );
		add_action( 'admin_footer', array( $this, 'restrict_file_picker' ), 99 );
	}

	/**
	 * Download the supported CSV template.
	 *
	 * @return void
	 */
	public function download_template() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para descargar esta plantilla.', 'wp-headless-api-core' ), 403 );
		}

		check_admin_referer( Directory_Importer::NONCE_ACTION );

		$headers = array( 'external_id', 'name', 'role', 'joined_at', 'phone', 'email', 'summary', 'groups', 'status', 'order', 'image_url', 'image_alt' );
		$example = array( 'EMP-001', 'Dra. Ejemplo', 'Directora', '2026-09-11', '(809) 555-0000', 'persona@example.org', 'Resumen breve', 'Directores|Médicos', 'draft', '0', 'https://example.org/retrato.jpg', 'Retrato institucional' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="headless-directory-template.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( false === $output ) {
			wp_die( esc_html__( 'No fue posible generar la plantilla CSV.', 'wp-headless-api-core' ) );
		}

		// UTF-8 BOM keeps names/accents readable when opened directly in Excel.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
		fputcsv( $output, $headers );
		fputcsv( $output, $example );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
	}

	/**
	 * Reject formats that are no longer part of the v0.4.0 supported surface.
	 *
	 * Runs before Directory_Importer::preview_import(). Valid CSV requests fall
	 * through to the existing importer unchanged.
	 *
	 * @return void
	 */
	public function enforce_csv_upload() {
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return;
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( 'csv' !== $ext ) {
			wp_send_json_error(
				array( 'message' => __( 'Formato no soportado en esta versión. Usa un archivo CSV (.csv).', 'wp-headless-api-core' ) ),
				400
			);
		}
	}

	/**
	 * Replace legacy XLSX-facing copy and clarify portrait fallback behavior.
	 *
	 * @param string $translation Current translation.
	 * @param string $text        Original source string.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function csv_copy( $translation, $text, $domain ) {
		unset( $domain );

		$map = array(
			'Carga muchas personas desde Excel sin crear cada ficha manualmente. Primero validamos y mostramos una vista previa; nada se guarda hasta que confirmes.' => 'Carga muchas personas desde CSV sin crear cada ficha manualmente. Primero validamos y mostramos una vista previa; nada se guarda hasta que confirmes.',
			'Formato recomendado: Excel .xlsx. También aceptamos CSV.' => 'Formato admitido: CSV (.csv).',
			'Descargar plantilla Excel' => 'Descargar plantilla CSV',
			'Arrastra aquí tu Excel' => 'Arrastra aquí tu CSV',
			'o haz clic para seleccionar .xlsx / .csv' => 'o haz clic para seleccionar .csv',
			'Privado. Sirve para actualizar esta ficha de forma segura desde Excel.' => 'Privado. Sirve para actualizar esta ficha de forma segura desde CSV.',
			'Selecciona un archivo Excel o CSV.' => 'Selecciona un archivo CSV.',
			'Formato no soportado. Usa .xlsx o .csv.' => 'Formato no soportado. Usa un archivo CSV (.csv).',
			'Importar image_url como retrato' => 'Descargar image_url si no existe en Medios',
		);

		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	/**
	 * Align the native file picker with the supported CSV surface.
	 *
	 * @return void
	 */
	public function restrict_file_picker() {
		if ( ! isset( $_GET['page'] ) || 'headless-directory-import' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		?>
		<script>
		(function () {
			var input = document.querySelector('.headless-directory-import-file');
			if (input) {
				input.setAttribute('accept', '.csv,text/csv');
			}
		}());
		</script>
		<?php
	}
}
