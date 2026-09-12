<?php
/**
 * Restricted rich biography editor for Directory people.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

defined( 'ABSPATH' ) || exit;

final class Directory_Rich_Summary {
	const NONCE_ACTION = 'headless_directory_rich_summary';
	const NONCE_NAME   = 'headless_directory_rich_summary_nonce';

	/** Register editor and save hooks. */
	public function register() {
		add_action( 'add_meta_boxes_' . Directory_Post_Type::POST_TYPE, array( $this, 'add_meta_box' ), 20 );
		add_action( 'save_post_' . Directory_Post_Type::POST_TYPE, array( $this, 'save' ), 20, 3 );
	}

	/** Add the reduced visual editor to the person screen. */
	public function add_meta_box() {
		add_meta_box(
			'headless-directory-rich-summary',
			__( 'Biografía enriquecida', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Directory_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/** Render a minimal WordPress visual editor. */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$value = (string) get_post_meta( $post->ID, Directory_Post_Type::META_SUMMARY_HTML, true );
		?>
		<p class="description">
			<?php esc_html_e( 'Versión opcional para presentación visual. Solo admite párrafos, negritas, cursivas y saltos de línea. Si se deja vacía, el frontend puede usar Resumen.', 'wp-headless-api-core' ); ?>
		</p>
		<?php
		wp_editor(
			$value,
			'headless_directory_summary_html_editor',
			array(
				'textarea_name' => 'headless_directory_summary_html',
				'textarea_rows' => 10,
				'media_buttons' => false,
				'drag_drop_upload' => false,
				'quicktags'     => false,
				'teeny'         => false,
				'tinymce'       => array(
					'menubar'       => false,
					'toolbar1'      => 'bold,italic',
					'toolbar2'      => '',
					'block_formats' => 'Párrafo=p',
					'branding'      => false,
					'resize'        => true,
				),
			)
		);
	}

	/** Save only explicitly whitelisted HTML. */
	public function save( $post_id, $post, $update ) {
		unset( $update );
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$raw   = isset( $_POST['headless_directory_summary_html'] ) ? wp_unslash( $_POST['headless_directory_summary_html'] ) : '';
		$value = Directory_Post_Type::sanitize_summary_html( $raw );
		$old   = (string) get_post_meta( $post_id, Directory_Post_Type::META_SUMMARY_HTML, true );

		if ( '' === $value ) {
			delete_post_meta( $post_id, Directory_Post_Type::META_SUMMARY_HTML );
		} else {
			update_post_meta( $post_id, Directory_Post_Type::META_SUMMARY_HTML, $value );
		}

		if ( $old !== $value && 'publish' === $post->post_status ) {
			do_action( 'headless_api_core_directory_collection_changed', 'summary_html' );
		}
	}
}
