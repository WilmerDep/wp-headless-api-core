<?php
/**
 * Directory editorial admin workspace.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Directory;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Directory_Admin {
	const NONCE_ACTION = 'headless_directory_person_meta';
	const NONCE_NAME   = 'headless_directory_person_nonce';

	/** @var Directory_Serializer */
	private $serializer;

	/** @var bool */
	private $saving = false;

	/**
	 * @param Directory_Serializer $serializer Serializer used for effective order.
	 */
	public function __construct( Directory_Serializer $serializer ) {
		$this->serializer = $serializer;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Directory_Post_Type::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Directory_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'register_submenus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'restrict_manage_posts', array( $this, 'group_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_group_filter' ) );
		add_filter( 'manage_' . Directory_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . Directory_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
	}

	/**
	 * Add Directory workspace pages under the CPT menu.
	 *
	 * @return void
	 */
	public function register_submenus() {
		$parent = 'edit.php?post_type=' . Directory_Post_Type::POST_TYPE;

		add_submenu_page(
			$parent,
			__( 'Ordenar directorio', 'wp-headless-api-core' ),
			__( 'Ordenar', 'wp-headless-api-core' ),
			'edit_posts',
			'headless-directory-order',
			array( $this, 'render_order_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Importar directorio', 'wp-headless-api-core' ),
			__( 'Importar', 'wp-headless-api-core' ),
			'edit_posts',
			'headless-directory-import',
			array( $this, 'render_import_page' )
		);
	}

	/**
	 * Add guided person editor.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'headless-directory-person',
			__( 'Perfil del directorio', 'wp-headless-api-core' ),
			array( $this, 'render_meta_box' ),
			Directory_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render person editor cards.
	 *
	 * @param \WP_Post $post Person post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$portrait_id = (int) get_post_thumbnail_id( $post );
		$role        = (string) get_post_meta( $post->ID, Directory_Post_Type::META_ROLE, true );
		$joined_at   = (string) get_post_meta( $post->ID, Directory_Post_Type::META_JOINED_AT, true );
		$phone       = (string) get_post_meta( $post->ID, Directory_Post_Type::META_PHONE, true );
		$email       = (string) get_post_meta( $post->ID, Directory_Post_Type::META_EMAIL, true );
		$summary     = (string) get_post_meta( $post->ID, Directory_Post_Type::META_SUMMARY, true );
		$alt         = (string) get_post_meta( $post->ID, Directory_Post_Type::META_ALT, true );
		$external_id = (string) get_post_meta( $post->ID, Directory_Post_Type::META_EXTERNAL_ID, true );
		$selected    = wp_get_post_terms( $post->ID, Directory_Post_Type::TAXONOMY, array( 'fields' => 'ids' ) );
		$selected    = is_wp_error( $selected ) ? array() : array_map( 'intval', $selected );
		$groups      = get_terms( array( 'taxonomy' => Directory_Post_Type::TAXONOMY, 'hide_empty' => false ) );
		$groups      = is_wp_error( $groups ) ? array() : $groups;
		?>
		<div class="headless-directory-editor">
			<div class="headless-directory-intro">
				<strong><?php esc_html_e( 'Completa la ficha pública de la persona.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'El nombre se escribe arriba. La foto es obligatoria para que la persona aparezca en la API pública.', 'wp-headless-api-core' ); ?></span>
			</div>

			<div class="headless-directory-editor-grid">
				<section class="headless-directory-section headless-directory-section--portrait">
					<div class="headless-directory-section-heading">
						<div><h3><?php esc_html_e( 'Retrato', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Usa una foto vertical, clara y de buena calidad.', 'wp-headless-api-core' ); ?></p></div>
						<span class="headless-directory-badge is-required"><?php esc_html_e( 'Obligatoria', 'wp-headless-api-core' ); ?></span>
					</div>
					<?php $this->render_portrait_control( $portrait_id ); ?>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Información profesional', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Cargo, fecha y breve presentación pública.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field">
						<label for="headless-directory-role"><?php esc_html_e( 'Cargo o función', 'wp-headless-api-core' ); ?></label>
						<input id="headless-directory-role" class="widefat" type="text" name="headless_directory_role" value="<?php echo esc_attr( $role ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Directora Ejecutiva', 'wp-headless-api-core' ); ?>" />
					</div>
					<div class="headless-directory-field">
						<label for="headless-directory-joined-at"><?php esc_html_e( 'Fecha de ingreso', 'wp-headless-api-core' ); ?></label>
						<input id="headless-directory-joined-at" type="date" name="headless_directory_joined_at" value="<?php echo esc_attr( $joined_at ); ?>" />
						<p class="description"><?php esc_html_e( 'Opcional. Se guarda como fecha, no como texto libre.', 'wp-headless-api-core' ); ?></p>
					</div>
					<div class="headless-directory-field">
						<label for="headless-directory-summary"><?php esc_html_e( 'Resumen', 'wp-headless-api-core' ); ?></label>
						<textarea id="headless-directory-summary" class="widefat" rows="4" name="headless_directory_summary" placeholder="<?php esc_attr_e( 'Breve descripción pública de la persona.', 'wp-headless-api-core' ); ?>"><?php echo esc_textarea( $summary ); ?></textarea>
					</div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Grupos', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Una persona puede pertenecer a uno o varios grupos.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-groups">
						<?php if ( empty( $groups ) ) : ?>
							<p class="description"><?php esc_html_e( 'Aún no hay grupos. Créalo desde Directorio → Grupos.', 'wp-headless-api-core' ); ?></p>
						<?php else : ?>
							<?php foreach ( $groups as $group ) : ?>
								<label class="headless-directory-group-option">
									<input type="checkbox" name="headless_directory_groups[]" value="<?php echo esc_attr( (int) $group->term_id ); ?>" <?php checked( in_array( (int) $group->term_id, $selected, true ) ); ?> />
									<span><?php echo esc_html( $group->name ); ?></span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Contacto público', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Solo agrega información que realmente deba mostrarse en el sitio.', 'wp-headless-api-core' ); ?></p></div><span class="headless-directory-badge is-optional"><?php esc_html_e( 'Opcional', 'wp-headless-api-core' ); ?></span></div>
					<div class="headless-directory-field">
						<label for="headless-directory-phone"><?php esc_html_e( 'Teléfono', 'wp-headless-api-core' ); ?></label>
						<input id="headless-directory-phone" class="widefat" type="text" name="headless_directory_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="(809) 555-0000 Ext. 0000" />
					</div>
					<div class="headless-directory-field">
						<label for="headless-directory-email"><?php esc_html_e( 'Correo electrónico', 'wp-headless-api-core' ); ?></label>
						<input id="headless-directory-email" class="widefat" type="email" name="headless_directory_email" value="<?php echo esc_attr( $email ); ?>" placeholder="persona@example.org" />
					</div>
				</section>

				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Accesibilidad', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'Describe brevemente el retrato para lectores de pantalla.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-field">
						<label for="headless-directory-alt"><?php esc_html_e( 'Descripción de la imagen', 'wp-headless-api-core' ); ?></label>
						<input id="headless-directory-alt" class="widefat" type="text" name="headless_directory_alt" value="<?php echo esc_attr( $alt ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Retrato institucional de...', 'wp-headless-api-core' ); ?>" />
						<p class="description"><?php esc_html_e( 'Si lo dejas vacío, se utilizará el texto alternativo guardado en Medios.', 'wp-headless-api-core' ); ?></p>
					</div>
				</section>

				<section class="headless-directory-section headless-directory-section--advanced">
					<div class="headless-directory-section-heading"><div><h3><?php esc_html_e( 'Organización', 'wp-headless-api-core' ); ?></h3><p><?php esc_html_e( 'El drag & drop es la forma recomendada de ordenar. Estos campos son de apoyo.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-form-row">
						<div class="headless-directory-field">
							<label for="headless-directory-order"><?php esc_html_e( 'Orden global', 'wp-headless-api-core' ); ?></label>
							<input id="headless-directory-order" class="small-text" type="number" min="0" step="1" name="headless_directory_order" value="<?php echo esc_attr( (int) $post->menu_order ); ?>" />
						</div>
						<div class="headless-directory-field">
							<label for="headless-directory-external-id"><?php esc_html_e( 'ID externo de importación', 'wp-headless-api-core' ); ?></label>
							<input id="headless-directory-external-id" class="regular-text" type="text" name="headless_directory_external_id" value="<?php echo esc_attr( $external_id ); ?>" placeholder="EMP-001" />
							<p class="description"><?php esc_html_e( 'Privado. Sirve para actualizar esta ficha de forma segura desde Excel.', 'wp-headless-api-core' ); ?></p>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Render portrait picker.
	 *
	 * @param int $attachment_id Current portrait ID.
	 * @return void
	 */
	private function render_portrait_control( $attachment_id ) {
		$preview = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		$source  = $attachment_id > 0 ? wp_get_attachment_image_src( $attachment_id, 'full' ) : false;
		$has     = '' !== (string) $preview;
		?>
		<div class="headless-directory-image-control">
			<input type="hidden" class="headless-directory-image-id" name="headless_directory_portrait_id" value="<?php echo esc_attr( (int) $attachment_id ); ?>" />
			<div class="headless-directory-image-meta">
				<span class="headless-directory-chip"><?php esc_html_e( 'Recomendado: 900 × 1200 px · Vertical', 'wp-headless-api-core' ); ?></span>
				<span class="headless-directory-chip headless-directory-image-actual" <?php echo $source ? '' : 'hidden'; ?>><?php echo $source ? esc_html( sprintf( __( 'Actual: %1$d × %2$d px', 'wp-headless-api-core' ), (int) $source[1], (int) $source[2] ) ) : ''; ?></span>
			</div>
			<div class="headless-directory-image-preview <?php echo $has ? 'has-image' : ''; ?>">
				<div class="headless-directory-image-empty" <?php echo $has ? 'hidden' : ''; ?>><span class="dashicons dashicons-admin-users"></span><span><?php esc_html_e( 'Selecciona un retrato', 'wp-headless-api-core' ); ?></span></div>
				<img class="headless-directory-image-preview-image" <?php echo $has ? 'src="' . esc_url( $preview ) . '"' : 'hidden'; ?> alt="" />
			</div>
			<div class="headless-directory-image-actions">
				<button type="button" class="button button-secondary headless-directory-select-image" data-empty-label="<?php esc_attr_e( 'Seleccionar imagen', 'wp-headless-api-core' ); ?>" data-selected-label="<?php esc_attr_e( 'Cambiar imagen', 'wp-headless-api-core' ); ?>"><?php echo esc_html( $has ? __( 'Cambiar imagen', 'wp-headless-api-core' ) : __( 'Seleccionar imagen', 'wp-headless-api-core' ) ); ?></button>
				<button type="button" class="button-link-delete headless-directory-remove-image" <?php echo $has ? '' : 'hidden'; ?>><?php esc_html_e( 'Quitar', 'wp-headless-api-core' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save guided person fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Update flag.
	 * @return void
	 */
	public function save( $post_id, $post, $update ) {
		unset( $update );
		if ( $this->saving || ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$portrait_id = isset( $_POST['headless_directory_portrait_id'] ) ? absint( wp_unslash( $_POST['headless_directory_portrait_id'] ) ) : 0;
		if ( $portrait_id > 0 && wp_attachment_is_image( $portrait_id ) ) {
			set_post_thumbnail( $post_id, $portrait_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		$fields = array(
			Directory_Post_Type::META_ROLE        => isset( $_POST['headless_directory_role'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_directory_role'] ) ) : '',
			Directory_Post_Type::META_JOINED_AT   => isset( $_POST['headless_directory_joined_at'] ) ? Directory_Post_Type::sanitize_date( wp_unslash( $_POST['headless_directory_joined_at'] ) ) : '',
			Directory_Post_Type::META_PHONE       => isset( $_POST['headless_directory_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_directory_phone'] ) ) : '',
			Directory_Post_Type::META_EMAIL       => isset( $_POST['headless_directory_email'] ) ? Directory_Post_Type::sanitize_email_value( wp_unslash( $_POST['headless_directory_email'] ) ) : '',
			Directory_Post_Type::META_SUMMARY     => isset( $_POST['headless_directory_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['headless_directory_summary'] ) ) : '',
			Directory_Post_Type::META_ALT         => isset( $_POST['headless_directory_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_directory_alt'] ) ) : '',
			Directory_Post_Type::META_EXTERNAL_ID => isset( $_POST['headless_directory_external_id'] ) ? Directory_Post_Type::sanitize_external_id( wp_unslash( $_POST['headless_directory_external_id'] ) ) : '',
		);
		foreach ( $fields as $key => $value ) {
			'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
		}

		$groups = isset( $_POST['headless_directory_groups'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['headless_directory_groups'] ) ) ) ) ) : array();
		$valid_groups = array();
		foreach ( $groups as $term_id ) {
			$term = get_term( $term_id, Directory_Post_Type::TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				$valid_groups[] = $term_id;
			}
		}
		wp_set_object_terms( $post_id, $valid_groups, Directory_Post_Type::TAXONOMY, false );

		$order = isset( $_POST['headless_directory_order'] ) ? max( 0, (int) wp_unslash( $_POST['headless_directory_order'] ) ) : 0;
		if ( (int) $post->menu_order !== $order ) {
			$this->saving = true;
			wp_update_post( array( 'ID' => $post_id, 'menu_order' => $order ) );
			$this->saving = false;
		}
	}

	/**
	 * Enqueue Directory admin assets only where required.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_person = Directory_Post_Type::POST_TYPE === $screen->post_type;
		$is_order  = isset( $_GET['page'] ) && 'headless-directory-order' === sanitize_key( wp_unslash( $_GET['page'] ) );
		$is_import = isset( $_GET['page'] ) && 'headless-directory-import' === sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( ! $is_person && ! $is_order && ! $is_import ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'headless-directory-admin', plugins_url( 'assets/admin/directory.css', HEADLESS_API_CORE_FILE ), array(), HEADLESS_API_CORE_VERSION );
		wp_enqueue_script( 'headless-directory-admin', plugins_url( 'assets/admin/directory.js', HEADLESS_API_CORE_FILE ), array( 'jquery', 'jquery-ui-sortable' ), HEADLESS_API_CORE_VERSION, true );
		wp_localize_script(
			'headless-directory-admin',
			'HeadlessDirectoryAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'orderNonce'  => wp_create_nonce( Directory_Order::NONCE_ACTION ),
				'importNonce' => wp_create_nonce( Directory_Importer::NONCE_ACTION ),
				'strings'     => array(
					'saving'       => __( 'Guardando…', 'wp-headless-api-core' ),
					'saved'        => __( 'Guardado', 'wp-headless-api-core' ),
					'error'        => __( 'No se pudo guardar.', 'wp-headless-api-core' ),
					'validating'   => __( 'Validando archivo…', 'wp-headless-api-core' ),
					'importing'    => __( 'Importando…', 'wp-headless-api-core' ),
					'chooseImage'  => __( 'Seleccionar retrato', 'wp-headless-api-core' ),
					'useImage'     => __( 'Usar esta imagen', 'wp-headless-api-core' ),
				)
			)
		);
	}

	/**
	 * Add group filter to people list.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function group_filter( $post_type ) {
		if ( Directory_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}
		$selected = isset( $_GET['directory_group'] ) ? absint( wp_unslash( $_GET['directory_group'] ) ) : 0;
		wp_dropdown_categories( array( 'show_option_all' => __( 'Todos los grupos', 'wp-headless-api-core' ), 'taxonomy' => Directory_Post_Type::TAXONOMY, 'name' => 'directory_group', 'orderby' => 'name', 'selected' => $selected, 'hierarchical' => true, 'hide_empty' => false, 'value_field' => 'term_id' ) );
	}

	/**
	 * Apply list group filter.
	 *
	 * @param WP_Query $query Admin query.
	 * @return void
	 */
	public function apply_group_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Directory_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$group_id = isset( $_GET['directory_group'] ) ? absint( wp_unslash( $_GET['directory_group'] ) ) : 0;
		if ( $group_id > 0 ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => Directory_Post_Type::TAXONOMY, 'field' => 'term_id', 'terms' => array( $group_id ) ) ) );
		}
	}

	/**
	 * Customize people list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function list_columns( $columns ) {
		return array(
			'cb'                => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'directory_portrait'=> __( 'Foto', 'wp-headless-api-core' ),
			'title'             => __( 'Nombre', 'wp-headless-api-core' ),
			'directory_role'    => __( 'Cargo', 'wp-headless-api-core' ),
			'directory_groups'  => __( 'Grupos', 'wp-headless-api-core' ),
			'directory_order'   => __( 'Orden', 'wp-headless-api-core' ),
			'date'              => isset( $columns['date'] ) ? $columns['date'] : __( 'Fecha', 'wp-headless-api-core' ),
		);
	}

	/**
	 * Render custom list columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_list_column( $column, $post_id ) {
		if ( 'directory_portrait' === $column ) {
			$image = get_the_post_thumbnail( $post_id, array( 72, 88 ), array( 'class' => 'headless-directory-list-thumb' ) );
			echo $image ? wp_kses_post( $image ) : '<span class="headless-directory-no-image">' . esc_html__( 'Sin foto', 'wp-headless-api-core' ) . '</span>';
			return;
		}
		if ( 'directory_role' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, Directory_Post_Type::META_ROLE, true ) );
			return;
		}
		if ( 'directory_groups' === $column ) {
			$terms = wp_get_post_terms( $post_id, Directory_Post_Type::TAXONOMY, array( 'fields' => 'names' ) );
			echo esc_html( is_wp_error( $terms ) ? '' : implode( ', ', $terms ) );
			return;
		}
		if ( 'directory_order' === $column ) {
			echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
		}
	}

	/**
	 * Render drag-and-drop ordering workspace.
	 *
	 * @return void
	 */
	public function render_order_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ordenar el directorio.', 'wp-headless-api-core' ) );
		}

		$view     = isset( $_GET['view'] ) && 'groups' === sanitize_key( wp_unslash( $_GET['view'] ) ) ? 'groups' : 'people';
		$group_id = isset( $_GET['group'] ) ? absint( wp_unslash( $_GET['group'] ) ) : 0;
		$base     = admin_url( 'edit.php?post_type=' . Directory_Post_Type::POST_TYPE . '&page=headless-directory-order' );
		?>
		<div class="wrap headless-directory-workspace">
			<h1><?php esc_html_e( 'Ordenar directorio', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Arrastra los elementos para definir el orden público. Los cambios se guardan sin recargar la página.', 'wp-headless-api-core' ); ?></p>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'people' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Personas', 'wp-headless-api-core' ); ?></a>
				<a class="nav-tab <?php echo 'groups' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'groups', $base ) ); ?>"><?php esc_html_e( 'Grupos', 'wp-headless-api-core' ); ?></a>
			</nav>
			<?php 'groups' === $view ? $this->render_group_order_list() : $this->render_person_order_list( $group_id, $base ); ?>
		</div>
		<?php
	}

	/**
	 * Render person ordering list.
	 *
	 * @param int    $group_id Active group.
	 * @param string $base     Base URL.
	 * @return void
	 */
	private function render_person_order_list( $group_id, $base ) {
		$groups = get_terms( array( 'taxonomy' => Directory_Post_Type::TAXONOMY, 'hide_empty' => false ) );
		$groups = is_wp_error( $groups ) ? array() : $groups;
		$args = array( 'post_type' => Directory_Post_Type::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'cache_results' => false, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) );
		if ( $group_id > 0 ) {
			$args['tax_query'] = array( array( 'taxonomy' => Directory_Post_Type::TAXONOMY, 'field' => 'term_id', 'terms' => array( $group_id ) ) );
		}
		$query = new WP_Query( $args );
		$posts = $query->posts;
		if ( $group_id > 0 ) {
			$serializer = $this->serializer;
			usort( $posts, static function ( $a, $b ) use ( $serializer, $group_id ) { $oa = $serializer->effective_order( $a, $group_id ); $ob = $serializer->effective_order( $b, $group_id ); return $oa === $ob ? ( $a->ID <=> $b->ID ) : ( $oa <=> $ob ); } );
		}
		?>
		<div class="headless-directory-toolbar-card">
			<label for="headless-directory-order-group"><?php esc_html_e( 'Orden que estás editando', 'wp-headless-api-core' ); ?></label>
			<select id="headless-directory-order-group" data-base-url="<?php echo esc_url( $base ); ?>">
				<option value="0"><?php esc_html_e( 'Orden global · Todas las personas', 'wp-headless-api-core' ); ?></option>
				<?php foreach ( $groups as $group ) : ?><option value="<?php echo esc_attr( (int) $group->term_id ); ?>" <?php selected( $group_id, (int) $group->term_id ); ?>><?php echo esc_html( sprintf( __( 'Grupo · %s', 'wp-headless-api-core' ), $group->name ) ); ?></option><?php endforeach; ?>
			</select>
			<p class="description"><?php echo $group_id > 0 ? esc_html__( 'Este orden solo afecta a este grupo. La misma persona puede tener otra posición en otros grupos.', 'wp-headless-api-core' ) : esc_html__( 'Este es el orden general usado cuando la API se consulta sin filtro de grupo.', 'wp-headless-api-core' ); ?></p>
		</div>
		<div class="headless-directory-save-state" aria-live="polite"></div>
		<ul class="headless-directory-sortable" data-order-type="people" data-group-id="<?php echo esc_attr( $group_id ); ?>">
			<?php foreach ( $posts as $post ) :
				$thumb = get_the_post_thumbnail_url( $post, 'thumbnail' );
				$role  = (string) get_post_meta( $post->ID, Directory_Post_Type::META_ROLE, true );
				$term_names = wp_get_post_terms( $post->ID, Directory_Post_Type::TAXONOMY, array( 'fields' => 'names' ) );
				$term_names = is_wp_error( $term_names ) ? array() : $term_names;
				?>
				<li class="headless-directory-sortable-item" data-id="<?php echo esc_attr( (int) $post->ID ); ?>">
					<span class="dashicons dashicons-menu headless-directory-drag-handle" aria-hidden="true"></span>
					<div class="headless-directory-sortable-thumb"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" /><?php else : ?><span class="dashicons dashicons-admin-users"></span><?php endif; ?></div>
					<div class="headless-directory-sortable-copy"><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><span><?php echo esc_html( $role ); ?></span><small><?php echo esc_html( implode( ' · ', $term_names ) ); ?></small></div>
					<span class="headless-directory-status is-<?php echo esc_attr( $post->post_status ); ?>"><?php echo esc_html( $post->post_status ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( empty( $posts ) ) : ?><div class="headless-directory-empty-state"><?php esc_html_e( 'No hay personas en esta vista.', 'wp-headless-api-core' ); ?></div><?php endif; ?>
		<?php
	}

	/**
	 * Render draggable group ordering list.
	 *
	 * @return void
	 */
	private function render_group_order_list() {
		$terms = get_terms( array( 'taxonomy' => Directory_Post_Type::TAXONOMY, 'hide_empty' => false ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		usort( $terms, static function ( $a, $b ) { $oa = (int) get_term_meta( $a->term_id, Directory_Post_Type::TERM_META_ORDER, true ); $ob = (int) get_term_meta( $b->term_id, Directory_Post_Type::TERM_META_ORDER, true ); if ( $oa !== $ob ) { return $oa <=> $ob; } $name = strcasecmp( $a->name, $b->name ); return 0 !== $name ? $name : ( $a->term_id <=> $b->term_id ); } );
		?>
		<div class="headless-directory-toolbar-card"><strong><?php esc_html_e( 'Orden de grupos', 'wp-headless-api-core' ); ?></strong><p class="description"><?php esc_html_e( 'Este orden puede usarse para tabs o secciones dinámicas del Consumer.', 'wp-headless-api-core' ); ?></p></div>
		<div class="headless-directory-save-state" aria-live="polite"></div>
		<ul class="headless-directory-sortable" data-order-type="groups">
			<?php foreach ( $terms as $term ) : ?>
				<li class="headless-directory-sortable-item" data-id="<?php echo esc_attr( (int) $term->term_id ); ?>"><span class="dashicons dashicons-menu headless-directory-drag-handle"></span><div class="headless-directory-sortable-copy"><strong><?php echo esc_html( $term->name ); ?></strong><span><?php echo esc_html( $term->slug ); ?></span><small><?php echo esc_html( sprintf( _n( '%d persona', '%d personas', (int) $term->count, 'wp-headless-api-core' ), (int) $term->count ) ); ?></small></div></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( empty( $terms ) ) : ?><div class="headless-directory-empty-state"><?php esc_html_e( 'Crea primero uno o más grupos.', 'wp-headless-api-core' ); ?></div><?php endif; ?>
		<?php
	}

	/**
	 * Render spreadsheet import workspace.
	 *
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar personas.', 'wp-headless-api-core' ) );
		}
		$template_url = wp_nonce_url( admin_url( 'admin-post.php?action=headless_directory_download_template' ), Directory_Importer::NONCE_ACTION );
		?>
		<div class="wrap headless-directory-workspace headless-directory-import-page">
			<h1><?php esc_html_e( 'Importar directorio', 'wp-headless-api-core' ); ?></h1>
			<p class="headless-directory-lead"><?php esc_html_e( 'Carga muchas personas desde Excel sin crear cada ficha manualmente. Primero validamos y mostramos una vista previa; nada se guarda hasta que confirmes.', 'wp-headless-api-core' ); ?></p>
			<div class="headless-directory-import-layout">
				<section class="headless-directory-section">
					<div class="headless-directory-section-heading"><div><h2><?php esc_html_e( '1. Archivo', 'wp-headless-api-core' ); ?></h2><p><?php esc_html_e( 'Formato recomendado: Excel .xlsx. También aceptamos CSV.', 'wp-headless-api-core' ); ?></p></div><a class="button" href="<?php echo esc_url( $template_url ); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Descargar plantilla Excel', 'wp-headless-api-core' ); ?></a></div>
					<div class="headless-directory-dropzone" tabindex="0"><span class="dashicons dashicons-upload"></span><strong><?php esc_html_e( 'Arrastra aquí tu Excel', 'wp-headless-api-core' ); ?></strong><span><?php esc_html_e( 'o haz clic para seleccionar .xlsx / .csv', 'wp-headless-api-core' ); ?></span><input type="file" class="headless-directory-import-file" accept=".xlsx,.csv" /></div>
					<div class="headless-directory-import-file-name"></div>
					<button type="button" class="button button-primary headless-directory-validate-import" disabled><?php esc_html_e( 'Validar archivo', 'wp-headless-api-core' ); ?></button>
				</section>

				<section class="headless-directory-section headless-directory-import-preview" hidden>
					<div class="headless-directory-section-heading"><div><h2><?php esc_html_e( '2. Vista previa', 'wp-headless-api-core' ); ?></h2><p><?php esc_html_e( 'Revisa warnings y errores antes de escribir en WordPress.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-import-kpis"></div>
					<div class="headless-directory-import-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Fila', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Nombre', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Cargo', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Grupos', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Estado', 'wp-headless-api-core' ); ?></th><th><?php esc_html_e( 'Validación', 'wp-headless-api-core' ); ?></th></tr></thead><tbody></tbody></table></div>
				</section>

				<section class="headless-directory-section headless-directory-import-options" hidden>
					<div class="headless-directory-section-heading"><div><h2><?php esc_html_e( '3. Importar', 'wp-headless-api-core' ); ?></h2><p><?php esc_html_e( 'Elige cómo tratar registros ya existentes.', 'wp-headless-api-core' ); ?></p></div></div>
					<div class="headless-directory-form-row">
						<div class="headless-directory-field"><label for="headless-directory-import-mode"><?php esc_html_e( 'Modo', 'wp-headless-api-core' ); ?></label><select id="headless-directory-import-mode"><option value="create_only"><?php esc_html_e( 'Crear nuevos solamente', 'wp-headless-api-core' ); ?></option><option value="upsert"><?php esc_html_e( 'Crear y actualizar por ID externo', 'wp-headless-api-core' ); ?></option></select></div>
						<label class="headless-directory-toggle"><input type="checkbox" class="headless-directory-create-groups" <?php disabled( ! current_user_can( 'manage_categories' ) ); ?> /> <span><?php esc_html_e( 'Crear grupos que no existan', 'wp-headless-api-core' ); ?></span></label>
						<label class="headless-directory-toggle"><input type="checkbox" class="headless-directory-import-images" <?php disabled( ! current_user_can( 'upload_files' ) ); ?> /> <span><?php esc_html_e( 'Importar image_url como retrato', 'wp-headless-api-core' ); ?></span></label>
					</div>
					<button type="button" class="button button-primary headless-directory-run-import"><?php esc_html_e( 'Comenzar importación', 'wp-headless-api-core' ); ?></button>
					<div class="headless-directory-progress" hidden><div class="headless-directory-progress-bar"><span></span></div><strong class="headless-directory-progress-label">0%</strong></div>
					<div class="headless-directory-import-result" aria-live="polite"></div>
				</section>
			</div>
		</div>
		<?php
	}
}
