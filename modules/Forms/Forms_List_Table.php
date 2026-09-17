<?php
/**
 * Editorial list-table enhancements for Forms Core.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms_List_Table {
	/** Register Forms list columns and Quick Edit controls. */
	public function register() {
		add_filter( 'manage_' . Forms_Post_Type::FORM_POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Forms_Post_Type::FORM_POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit' ), 10, 2 );
		add_action( 'save_post_' . Forms_Post_Type::FORM_POST_TYPE, array( $this, 'save_quick_edit' ), 30, 2 );
		add_action( 'admin_footer-edit.php', array( $this, 'quick_edit_script' ) );
	}

	/** Keep the table useful for editors without exposing implementation noise. */
	public function columns( array $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['form_key']     = __( 'Key semántico', 'wp-headless-api-core' );
				$out['form_slug']    = __( 'Slug', 'wp-headless-api-core' );
				$out['form_enabled'] = __( 'Estado', 'wp-headless-api-core' );
				$out['form_fields']  = __( 'Campos', 'wp-headless-api-core' );
			}
		}
		return $out;
	}

	/** Render concise operational information for each form. */
	public function column_content( $column, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$key     = Forms_Identity::get_key( $post );
		$enabled = (bool) get_post_meta( $post_id, Forms_Post_Type::META_ENABLED, true );
		$active  = 'publish' === $post->post_status && $enabled;

		switch ( $column ) {
			case 'form_key':
				printf(
					'<span class="headless-form-row-data" data-form-key="%1$s" data-form-enabled="%2$d" hidden></span><code>%3$s</code>',
					esc_attr( $key ),
					$enabled ? 1 : 0,
					esc_html( $key ?: '—' )
				);
				break;
			case 'form_slug':
				echo '<code>' . esc_html( sanitize_title( $post->post_name ) ?: '—' ) . '</code>';
				break;
			case 'form_enabled':
				if ( $active ) {
					echo '<span aria-label="' . esc_attr__( 'Formulario activo', 'wp-headless-api-core' ) . '">● ' . esc_html__( 'Activo', 'wp-headless-api-core' ) . '</span>';
				} elseif ( $enabled ) {
					echo '<span aria-label="' . esc_attr__( 'Habilitado pero no publicado', 'wp-headless-api-core' ) . '">○ ' . esc_html__( 'No publicado', 'wp-headless-api-core' ) . '</span>';
				} else {
					echo '<span aria-label="' . esc_attr__( 'Formulario deshabilitado', 'wp-headless-api-core' ) . '">○ ' . esc_html__( 'Deshabilitado', 'wp-headless-api-core' ) . '</span>';
				}
				break;
			case 'form_fields':
				$fields = Forms_Schema::normalize_fields( get_post_meta( $post_id, Forms_Post_Type::META_FIELDS, true ) );
				echo esc_html( (string) count( $fields ) );
				break;
		}
	}

	/** Add semantic identity and enabled state to WordPress Quick Edit. */
	public function quick_edit( $column_name, $post_type ) {
		if ( Forms_Post_Type::FORM_POST_TYPE !== $post_type || 'form_key' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<span class="title"><?php esc_html_e( 'Forms Core', 'wp-headless-api-core' ); ?></span>
				<input type="hidden" name="headless_form_quick_edit" value="1">
				<label>
					<span class="title"><?php esc_html_e( 'Key semántico', 'wp-headless-api-core' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="headless_form_key_quick" value="" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" autocomplete="off"></span>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="headless_form_enabled_quick" value="1">
					<span class="checkbox-title"><?php esc_html_e( 'Formulario habilitado', 'wp-headless-api-core' ); ?></span>
				</label>
				<p class="description" style="clear:both;margin-top:8px"><?php esc_html_e( 'El key identifica el propósito entre sitios; el slug sigue siendo la ruta de esta instalación.', 'wp-headless-api-core' ); ?></p>
			</div>
		</fieldset>
		<?php
	}

	/** Persist only the fields owned by our Quick Edit extension. */
	public function save_quick_edit( $post_id, WP_Post $post ) {
		if ( empty( $_POST['headless_form_quick_edit'] ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_inline_edit'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
			return;
		}

		$existing_key = Forms_Identity::get_key( $post );
		$raw_key      = isset( $_POST['headless_form_key_quick'] ) ? trim( (string) wp_unslash( $_POST['headless_form_key_quick'] ) ) : '';
		$key          = Forms_Identity::sanitize_key( $raw_key );
		$enabled      = isset( $_POST['headless_form_enabled_quick'] );

		if ( '' === $key ) {
			$key = $existing_key ?: Forms_Identity::ensure_persisted_key( $post );
		}

		$is_public_candidate = 'publish' === $post->post_status && $enabled;
		if ( $is_public_candidate && ! Forms_Identity::is_active_key_available( $key, $post_id ) ) {
			$key     = $existing_key ?: $key;
			$enabled = false;
			set_transient(
				Forms_Identity::NOTICE_PREFIX . get_current_user_id(),
				array(
					'message' => __( 'El key semántico indicado ya pertenece a otro formulario activo. El formulario se guardó deshabilitado.', 'wp-headless-api-core' ),
					'type'    => 'error',
				),
				60
			);
		}

		update_post_meta( $post_id, Forms_Identity::META_KEY, $key );
		update_post_meta( $post_id, Forms_Post_Type::META_ENABLED, $enabled );
	}

	/** Populate our Quick Edit controls from the values already rendered in the row. */
	public function quick_edit_script() {
		$screen = get_current_screen();
		if ( ! $screen || Forms_Post_Type::FORM_POST_TYPE !== $screen->post_type || 'edit' !== $screen->base ) {
			return;
		}
		?>
		<script>
		(function ($) {
			'use strict';
			if (typeof inlineEditPost === 'undefined') return;
			var originalEdit = inlineEditPost.edit;
			inlineEditPost.edit = function (id) {
				originalEdit.apply(this, arguments);
				var postId = 0;
				if (typeof id === 'object') postId = parseInt(this.getId(id), 10) || 0;
				else postId = parseInt(id, 10) || 0;
				if (!postId) return;
				var row = $('#post-' + postId);
				var data = row.find('.headless-form-row-data');
				var editRow = $('#edit-' + postId);
				editRow.find('input[name="headless_form_key_quick"]').val(data.attr('data-form-key') || '');
				editRow.find('input[name="headless_form_enabled_quick"]').prop('checked', data.attr('data-form-enabled') === '1');
			};
		})(jQuery);
		</script>
		<?php
	}
}
