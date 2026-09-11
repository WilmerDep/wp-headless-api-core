<?php
/**
 * Hero editorial admin controls.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Hero;

defined( 'ABSPATH' ) || exit;

final class Hero_Admin {
	const NONCE_ACTION = 'headless_hero_meta';
	const NONCE_NAME   = 'headless_hero_meta_nonce';

	/**
	 * Register Hero admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Hero_Post_Type::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Hero_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_' . Hero_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . Hero_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
	}

	/**
	 * Add the guided Hero editor panel.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'headless-hero-settings',
			__( 'Configuración del Hero', 'wp-headless-api-core' ),
			array( $this, 'render_meta_box' ),
			Hero_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Hero settings meta box.
	 *
	 * @param \WP_Post $post Hero post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$primary_id       = (int) get_post_thumbnail_id( $post );
		$mobile_id        = (int) get_post_meta( $post->ID, Hero_Post_Type::META_MOBILE_IMAGE_ID, true );
		$href             = (string) get_post_meta( $post->ID, Hero_Post_Type::META_HREF, true );
		$alt              = (string) get_post_meta( $post->ID, Hero_Post_Type::META_ALT, true );
		$object_position  = (string) get_post_meta( $post->ID, Hero_Post_Type::META_OBJECT_POSITION, true );
		$link_mode        = '' === $href ? 'none' : ( 0 === strpos( $href, '/' ) ? 'internal' : 'external' );
		$position_options = $this->position_options();
		$position_mode    = '' === $object_position || isset( $position_options[ $object_position ] ) ? $object_position : 'custom';
		?>
		<div class="headless-hero-editor">
			<div class="headless-hero-intro">
				<strong><?php esc_html_e( 'Completa solo lo necesario para cada slide.', 'wp-headless-api-core' ); ?></strong>
				<span><?php esc_html_e( 'La imagen principal es obligatoria. El resto de los campos son opcionales y pueden dejarse en automático.', 'wp-headless-api-core' ); ?></span>
			</div>

			<section class="headless-hero-section">
				<div class="headless-hero-section-heading">
					<div>
						<h3><?php esc_html_e( 'Imágenes', 'wp-headless-api-core' ); ?></h3>
						<p><?php esc_html_e( 'Selecciona las imágenes que verá el usuario en escritorio y móvil.', 'wp-headless-api-core' ); ?></p>
					</div>
				</div>

				<div class="headless-hero-image-grid">
					<?php
					$this->render_image_control(
						'primary',
						'headless_hero_primary_image_id',
						$primary_id,
						__( 'Imagen principal', 'wp-headless-api-core' ),
						__( 'Obligatoria', 'wp-headless-api-core' ),
						__( 'Esta es la imagen principal del banner. Usa una imagen horizontal de buena calidad.', 'wp-headless-api-core' )
					);

					$this->render_image_control(
						'mobile',
						'headless_hero_mobile_image_id',
						$mobile_id,
						__( 'Imagen para móvil', 'wp-headless-api-core' ),
						__( 'Opcional', 'wp-headless-api-core' ),
						__( 'Si la dejas vacía, el frontend puede reutilizar la imagen principal.', 'wp-headless-api-core' )
					);
					?>
				</div>
			</section>

			<section class="headless-hero-section">
				<div class="headless-hero-section-heading">
					<div>
						<h3><?php esc_html_e( 'Comportamiento', 'wp-headless-api-core' ); ?></h3>
						<p><?php esc_html_e( 'Define si el slide lleva a otra página y cómo debe encuadrarse la imagen.', 'wp-headless-api-core' ); ?></p>
					</div>
				</div>

				<div class="headless-hero-form-grid">
					<div class="headless-hero-field headless-hero-field--wide">
						<label for="headless-hero-link-mode"><?php esc_html_e( 'Al hacer clic en el slide', 'wp-headless-api-core' ); ?></label>
						<select id="headless-hero-link-mode" class="headless-hero-link-mode" name="headless_hero_link_mode">
							<option value="none" <?php selected( $link_mode, 'none' ); ?>><?php esc_html_e( 'No abrir ningún enlace', 'wp-headless-api-core' ); ?></option>
							<option value="internal" <?php selected( $link_mode, 'internal' ); ?>><?php esc_html_e( 'Abrir una página de este sitio', 'wp-headless-api-core' ); ?></option>
							<option value="external" <?php selected( $link_mode, 'external' ); ?>><?php esc_html_e( 'Abrir una página externa', 'wp-headless-api-core' ); ?></option>
						</select>
					</div>

					<div class="headless-hero-field headless-hero-field--wide headless-hero-link-target" <?php echo 'none' === $link_mode ? 'hidden' : ''; ?>>
						<label for="headless-hero-href"><?php esc_html_e( 'Destino del enlace', 'wp-headless-api-core' ); ?></label>
						<input
							id="headless-hero-href"
							class="widefat"
							type="text"
							name="headless_hero_href"
							value="<?php echo esc_attr( $href ); ?>"
							placeholder="<?php echo esc_attr( 'internal' === $link_mode ? '/servicios' : 'https://example.org/path' ); ?>"
						/>
						<p class="description headless-hero-link-help">
							<?php
							echo esc_html(
								'internal' === $link_mode
									? __( 'Ejemplo: /servicios. No necesitas escribir el dominio.', 'wp-headless-api-core' )
									: __( 'Escribe la dirección completa, por ejemplo https://example.org.', 'wp-headless-api-core' )
							);
							?>
						</p>
					</div>

					<div class="headless-hero-field">
						<label for="headless-hero-position-preset"><?php esc_html_e( 'Enfoque de la imagen', 'wp-headless-api-core' ); ?></label>
						<select id="headless-hero-position-preset" class="headless-hero-position-preset" name="headless_hero_object_position_preset">
							<?php foreach ( $position_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position_mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							<option value="custom" <?php selected( $position_mode, 'custom' ); ?>><?php esc_html_e( 'Personalizado', 'wp-headless-api-core' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Elige qué zona de la imagen debe mantenerse visible cuando el banner se recorta.', 'wp-headless-api-core' ); ?></p>
					</div>

					<div class="headless-hero-field headless-hero-custom-position" <?php echo 'custom' === $position_mode ? '' : 'hidden'; ?>>
						<label for="headless-hero-object-position-custom"><?php esc_html_e( 'Posición personalizada', 'wp-headless-api-core' ); ?></label>
						<input
							id="headless-hero-object-position-custom"
							class="regular-text"
							type="text"
							name="headless_hero_object_position_custom"
							value="<?php echo esc_attr( 'custom' === $position_mode ? $object_position : '' ); ?>"
							placeholder="50% 25%"
						/>
						<p class="description"><?php esc_html_e( 'Solo para casos especiales. Puedes usar palabras como center/top o porcentajes.', 'wp-headless-api-core' ); ?></p>
					</div>

					<div class="headless-hero-field">
						<label for="headless-hero-order"><?php esc_html_e( 'Orden del slide', 'wp-headless-api-core' ); ?></label>
						<input id="headless-hero-order" class="small-text" type="number" min="0" step="1" name="headless_hero_order" value="<?php echo esc_attr( (int) $post->menu_order ); ?>" />
						<p class="description"><?php esc_html_e( 'Los números menores aparecen primero. Ejemplo: 1, 2, 3.', 'wp-headless-api-core' ); ?></p>
					</div>
				</div>
			</section>

			<section class="headless-hero-section">
				<div class="headless-hero-section-heading">
					<div>
						<h3><?php esc_html_e( 'Accesibilidad', 'wp-headless-api-core' ); ?></h3>
						<p><?php esc_html_e( 'Ayuda a describir la imagen para lectores de pantalla y otros usuarios.', 'wp-headless-api-core' ); ?></p>
					</div>
				</div>

				<div class="headless-hero-field">
					<label for="headless-hero-alt"><?php esc_html_e( 'Descripción de la imagen', 'wp-headless-api-core' ); ?></label>
					<input id="headless-hero-alt" class="widefat" type="text" name="headless_hero_alt" value="<?php echo esc_attr( $alt ); ?>" placeholder="<?php esc_attr_e( 'Ej.: Personal médico atendiendo a un paciente', 'wp-headless-api-core' ); ?>" />
					<p class="description"><?php esc_html_e( 'Opcional. Si lo dejas vacío, se utilizará el texto alternativo guardado en la imagen principal.', 'wp-headless-api-core' ); ?></p>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render one reusable image picker card.
	 *
	 * @param string $key          UI key.
	 * @param string $field_name   Input name.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $label        Visible label.
	 * @param string $badge        Required/optional badge.
	 * @param string $help         Friendly helper copy.
	 * @return void
	 */
	private function render_image_control( $key, $field_name, $attachment_id, $label, $badge, $help ) {
		$preview_url = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		$has_image   = '' !== (string) $preview_url;
		?>
		<div class="headless-hero-image-card headless-hero-image-control" data-image-key="<?php echo esc_attr( $key ); ?>">
			<div class="headless-hero-image-card-heading">
				<strong><?php echo esc_html( $label ); ?></strong>
				<span class="headless-hero-badge <?php echo 'primary' === $key ? 'is-required' : 'is-optional'; ?>"><?php echo esc_html( $badge ); ?></span>
			</div>
			<p class="description"><?php echo esc_html( $help ); ?></p>

			<input type="hidden" class="headless-hero-image-id" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (int) $attachment_id ); ?>" />

			<div class="headless-hero-image-preview <?php echo $has_image ? 'has-image' : ''; ?>">
				<div class="headless-hero-image-empty" <?php echo $has_image ? 'hidden' : ''; ?>>
					<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Aún no has seleccionado una imagen', 'wp-headless-api-core' ); ?></span>
				</div>
				<img class="headless-hero-image-preview-image" <?php echo $has_image ? 'src="' . esc_url( $preview_url ) . '"' : 'hidden'; ?> alt="" />
			</div>

			<div class="headless-hero-image-actions">
				<button
					type="button"
					class="button button-secondary headless-hero-select-image"
					data-frame-title="<?php echo esc_attr( sprintf( __( 'Seleccionar %s', 'wp-headless-api-core' ), strtolower( $label ) ) ); ?>"
					data-frame-button="<?php esc_attr_e( 'Usar esta imagen', 'wp-headless-api-core' ); ?>"
					data-empty-label="<?php esc_attr_e( 'Seleccionar imagen', 'wp-headless-api-core' ); ?>"
					data-selected-label="<?php esc_attr_e( 'Cambiar imagen', 'wp-headless-api-core' ); ?>"
				>
					<?php echo esc_html( $has_image ? __( 'Cambiar imagen', 'wp-headless-api-core' ) : __( 'Seleccionar imagen', 'wp-headless-api-core' ) ); ?>
				</button>
				<button type="button" class="button-link-delete headless-hero-remove-image" <?php echo $has_image ? '' : 'hidden'; ?>><?php esc_html_e( 'Quitar', 'wp-headless-api-core' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Friendly object-position presets.
	 *
	 * @return array<string,string>
	 */
	private function position_options() {
		return array(
			''              => __( 'Automático (recomendado)', 'wp-headless-api-core' ),
			'center center' => __( 'Centro', 'wp-headless-api-core' ),
			'center top'    => __( 'Arriba', 'wp-headless-api-core' ),
			'center bottom' => __( 'Abajo', 'wp-headless-api-core' ),
			'left center'   => __( 'Izquierda', 'wp-headless-api-core' ),
			'right center'  => __( 'Derecha', 'wp-headless-api-core' ),
			'left top'      => __( 'Arriba a la izquierda', 'wp-headless-api-core' ),
			'right top'     => __( 'Arriba a la derecha', 'wp-headless-api-core' ),
			'left bottom'   => __( 'Abajo a la izquierda', 'wp-headless-api-core' ),
			'right bottom'  => __( 'Abajo a la derecha', 'wp-headless-api-core' ),
		);
	}

	/**
	 * Save structured Hero metadata.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update   Update flag.
	 * @return void
	 */
	public function save( $post_id, $post, $update ) {
		unset( $update );

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || Hero_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$primary_id = isset( $_POST['headless_hero_primary_image_id'] ) ? absint( $_POST['headless_hero_primary_image_id'] ) : 0;
		if ( $primary_id > 0 && wp_attachment_is_image( $primary_id ) ) {
			set_post_thumbnail( $post_id, $primary_id );
		} else {
			delete_post_thumbnail( $post_id );
		}

		$mobile_id = isset( $_POST['headless_hero_mobile_image_id'] ) ? absint( $_POST['headless_hero_mobile_image_id'] ) : 0;
		if ( $mobile_id > 0 && wp_attachment_is_image( $mobile_id ) ) {
			update_post_meta( $post_id, Hero_Post_Type::META_MOBILE_IMAGE_ID, $mobile_id );
		} else {
			delete_post_meta( $post_id, Hero_Post_Type::META_MOBILE_IMAGE_ID );
		}

		$link_mode = isset( $_POST['headless_hero_link_mode'] ) ? sanitize_key( wp_unslash( $_POST['headless_hero_link_mode'] ) ) : 'none';
		$raw_href  = isset( $_POST['headless_hero_href'] ) ? trim( (string) wp_unslash( $_POST['headless_hero_href'] ) ) : '';
		$href      = '';

		if ( 'internal' === $link_mode && '' !== $raw_href ) {
			if ( 0 !== strpos( $raw_href, '/' ) ) {
				$raw_href = '/' . ltrim( $raw_href, '/' );
			}
			$href = Hero_Post_Type::sanitize_href( $raw_href );
			if ( '' !== $href && 0 !== strpos( $href, '/' ) ) {
				$href = '';
			}
		} elseif ( 'external' === $link_mode ) {
			$href = Hero_Post_Type::sanitize_href( $raw_href );
			if ( '' !== $href && 0 === strpos( $href, '/' ) ) {
				$href = '';
			}
		}
		$this->save_or_delete( $post_id, Hero_Post_Type::META_HREF, $href );

		$alt = isset( $_POST['headless_hero_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_hero_alt'] ) ) : '';
		$this->save_or_delete( $post_id, Hero_Post_Type::META_ALT, $alt );

		$position_preset = isset( $_POST['headless_hero_object_position_preset'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_hero_object_position_preset'] ) ) : '';
		$position_value  = 'custom' === $position_preset && isset( $_POST['headless_hero_object_position_custom'] )
			? wp_unslash( $_POST['headless_hero_object_position_custom'] )
			: $position_preset;
		$object_position = Hero_Post_Type::sanitize_object_position( $position_value );
		$this->save_or_delete( $post_id, Hero_Post_Type::META_OBJECT_POSITION, $object_position );

		$order = isset( $_POST['headless_hero_order'] ) ? max( 0, absint( $_POST['headless_hero_order'] ) ) : 0;
		if ( (int) $post->menu_order !== $order ) {
			remove_action( 'save_post_' . Hero_Post_Type::POST_TYPE, array( $this, 'save' ), 10 );
			wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => $order,
				)
			);
			add_action( 'save_post_' . Hero_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 3 );
		}
	}

	/**
	 * Load Hero admin UI assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || Hero_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$base_url = plugin_dir_url( HEADLESS_API_CORE_FILE );
		wp_enqueue_style(
			'headless-api-core-hero-admin',
			$base_url . 'assets/admin/hero.css',
			array(),
			HEADLESS_API_CORE_VERSION
		);

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'headless-api-core-hero-admin',
			$base_url . 'assets/admin/hero.js',
			array( 'jquery' ),
			HEADLESS_API_CORE_VERSION,
			true
		);
	}

	/**
	 * Make the Hero list screen useful for editors.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function list_columns( $columns ) {
		return array(
			'cb'           => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'hero_preview' => __( 'Vista previa', 'wp-headless-api-core' ),
			'title'        => __( 'Título interno', 'wp-headless-api-core' ),
			'hero_order'   => __( 'Orden', 'wp-headless-api-core' ),
			'date'         => isset( $columns['date'] ) ? $columns['date'] : __( 'Fecha', 'wp-headless-api-core' ),
		);
	}

	/**
	 * Render Hero custom list columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_list_column( $column, $post_id ) {
		if ( 'hero_preview' === $column ) {
			$image_id = (int) get_post_thumbnail_id( $post_id );
			if ( $image_id > 0 ) {
				echo wp_kses_post( wp_get_attachment_image( $image_id, array( 120, 68 ), false, array( 'class' => 'headless-hero-list-thumb' ) ) );
			} else {
				echo '<span class="headless-hero-list-empty">' . esc_html__( 'Sin imagen', 'wp-headless-api-core' ) . '</span>';
			}
			return;
		}

		if ( 'hero_order' === $column ) {
			echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
		}
	}

	/**
	 * Persist a non-empty value or remove its meta key.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $value    Sanitized value.
	 * @return void
	 */
	private function save_or_delete( $post_id, $meta_key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}
