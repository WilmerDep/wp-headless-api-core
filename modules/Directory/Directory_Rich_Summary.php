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

	/** Render a constrained WordPress visual/source editor. */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$value = (string) get_post_meta( $post->ID, Directory_Post_Type::META_SUMMARY_HTML, true );
		?>
		<p class="description">
			<?php esc_html_e( 'Versión opcional para presentación visual. Admite párrafos, negritas, cursivas y saltos de línea. Usa Visual para maquetar o Texto para revisar el HTML permitido. Enter crea un párrafo; Shift + Enter crea un salto de línea.', 'wp-headless-api-core' ); ?>
		</p>
		<?php
		wp_editor(
			$value,
			'headless_directory_summary_html_editor',
			array(
				'textarea_name'    => 'headless_directory_summary_html',
				'textarea_rows'    => 14,
				'media_buttons'    => false,
				'drag_drop_upload' => false,
				'wpautop'          => true,
				'quicktags'        => array(
					'buttons' => 'strong,em',
				),
				'teeny'            => false,
				'tinymce'          => array(
					'menubar'       => false,
					'toolbar1'      => 'formatselect,bold,italic,removeformat,undo,redo',
					'toolbar2'      => '',
					'block_formats' => 'Párrafo=p',
					'valid_elements'=> 'p,strong,b,em,i,br',
					'branding'      => false,
					'resize'        => true,
					'content_style' => 'body{font-size:16px;line-height:1.7;padding:10px 12px;}p{margin:0 0 1.2em;}p:last-child{margin-bottom:0;}strong,b{font-weight:700;}em,i{font-style:italic;}',
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

		$raw = isset( $_POST['headless_directory_summary_html'] ) ? wp_unslash( $_POST['headless_directory_summary_html'] ) : '';

		// Custom meta does not receive the normal post_content formatting pipeline.
		// Normalize intentional line/paragraph breaks before applying the strict whitelist.
		$formatted = '' !== trim( (string) $raw ) ? wpautop( (string) $raw, true ) : '';
		$value     = Directory_Post_Type::sanitize_summary_html( $formatted );
		$old       = (string) get_post_meta( $post_id, Directory_Post_Type::META_SUMMARY_HTML, true );

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
