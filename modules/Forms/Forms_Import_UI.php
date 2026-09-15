<?php
/**
 * SIP-aligned progressive enhancement for the Forms package importer.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

defined( 'ABSPATH' ) || exit;

final class Forms_Import_UI {
	/** Register importer-only assets without coupling them to the importer logic. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 30 );
	}

	/** Load the progressive enhancement only on Forms -> Importar paquete. */
	public function enqueue() {
		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

		if ( Forms_Package_Importer::PAGE_SLUG !== $page || Forms_Post_Type::FORM_POST_TYPE !== $post_type ) {
			return;
		}

		wp_enqueue_script(
			'headless-forms-import-ui',
			plugins_url( 'assets/admin/forms-import.js', HEADLESS_API_CORE_FILE ),
			array(),
			HEADLESS_API_CORE_VERSION,
			true
		);
	}
}
