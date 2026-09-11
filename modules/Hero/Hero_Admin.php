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
	}

	/**
	 * Add structured presentation fields.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'headless-hero-settings',
			__( 'Hero settings', 'wp-headless-api-core' ),
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

		$mobile_id = (int) get_post_meta( $post->ID, Hero_Post_Type::META_MOBILE_IMAGE_ID, true );
		$href = (string) get_post_meta( $post->ID, Hero_Post_Type::META_HREF, true );
		$alt = (string) get_post_meta( $post->ID, Hero_Post_Type::META_ALT, true );
		$object_position = (string) get_post_meta( $post->ID, Hero_Post_Type::META_OBJECT_POSITION, true );
		?>
		<div class="headless-hero-fields">
			<div class="headless-hero-field">
				<label><strong><?php esc_html_e( 'Mobile image', 'wp-headless-api-core' ); ?></strong></label>
				<p class="description"><?php esc_html_e( 'Optional image used by consumers on small screens. The featured image remains the required primary image.', 'wp-headless-api-core' ); ?></p>
				<div class="headless-hero-mobile-image-control">
					<input type="hidden" class="headless-hero-mobile-image-id" name="headless_hero_mobile_image_id" value="<?php echo esc_attr( $mobile_id ); ?>" />
					<div class="headless-hero-mobile-image-preview">
						<?php
						if ( $mobile_id > 0 ) {
							echo wp_kses_post( wp_get_attachment_image( $mobile_id, 'medium' ) );
						}
						?>
					</div>
					<p>
						<button type="button" class="button headless-hero-select-mobile-image"><?php esc_html_e( 'Select mobile image', 'wp-headless-api-core' ); ?></button>
						<button type="button" class="button-link-delete headless-hero-remove-mobile-image" <?php echo $mobile_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'wp-headless-api-core' ); ?></button>
					</p>
				</div>
			</div>

			<div class="headless-hero-field">
				<label for="headless-hero-href"><strong><?php esc_html_e( 'Target URL', 'wp-headless-api-core' ); ?></strong></label>
				<input id="headless-hero-href" class="widefat" type="text" name="headless_hero_href" value="<?php echo esc_attr( $href ); ?>" placeholder="/servicios or https://example.org/path" />
				<p class="description"><?php esc_html_e( 'Optional. Relative paths and absolute HTTP(S) URLs are supported.', 'wp-headless-api-core' ); ?></p>
			</div>

			<div class="headless-hero-field">
				<label for="headless-hero-alt"><strong><?php esc_html_e( 'Accessible alt text', 'wp-headless-api-core' ); ?></strong></label>
				<input id="headless-hero-alt" class="widefat" type="text" name="headless_hero_alt" value="<?php echo esc_attr( $alt ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional override. When empty, the attachment alt text is used.', 'wp-headless-api-core' ); ?></p>
			</div>

			<div class="headless-hero-field">
				<label for="headless-hero-object-position"><strong><?php esc_html_e( 'Object position', 'wp-headless-api-core' ); ?></strong></label>
				<input id="headless-hero-object-position" class="regular-text" type="text" name="headless_hero_object_position" value="<?php echo esc_attr( $object_position ); ?>" placeholder="center center" />
				<p class="description"><?php esc_html_e( 'Optional. Use one or two position keywords or percentages, for example: center center, center top, 50% 25%.', 'wp-headless-api-core' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save structured Hero metadata.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Update flag.
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

		$mobile_id = isset( $_POST['headless_hero_mobile_image_id'] ) ? absint( $_POST['headless_hero_mobile_image_id'] ) : 0;
		if ( $mobile_id > 0 && wp_attachment_is_image( $mobile_id ) ) {
			update_post_meta( $post_id, Hero_Post_Type::META_MOBILE_IMAGE_ID, $mobile_id );
		} else {
			delete_post_meta( $post_id, Hero_Post_Type::META_MOBILE_IMAGE_ID );
		}

		$href = isset( $_POST['headless_hero_href'] ) ? Hero_Post_Type::sanitize_href( wp_unslash( $_POST['headless_hero_href'] ) ) : '';
		$this->save_or_delete( $post_id, Hero_Post_Type::META_HREF, $href );

		$alt = isset( $_POST['headless_hero_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['headless_hero_alt'] ) ) : '';
		$this->save_or_delete( $post_id, Hero_Post_Type::META_ALT, $alt );

		$object_position = isset( $_POST['headless_hero_object_position'] )
			? Hero_Post_Type::sanitize_object_position( wp_unslash( $_POST['headless_hero_object_position'] ) )
			: '';
		$this->save_or_delete( $post_id, Hero_Post_Type::META_OBJECT_POSITION, $object_position );
	}

	/**
	 * Load the media picker only on Hero editing screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Hero_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		$base_url = plugin_dir_url( HEADLESS_API_CORE_FILE );
		wp_enqueue_script(
			'headless-api-core-hero-admin',
			$base_url . 'assets/admin/hero.js',
			array( 'jquery' ),
			HEADLESS_API_CORE_VERSION,
			true
		);
		wp_enqueue_style(
			'headless-api-core-hero-admin',
			$base_url . 'assets/admin/hero.css',
			array(),
			HEADLESS_API_CORE_VERSION
		);
	}

	/**
	 * Persist a non-empty value or remove its meta key.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $value Sanitized value.
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
