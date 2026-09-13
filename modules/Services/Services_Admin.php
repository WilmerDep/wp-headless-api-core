<?php
/**
 * Services guided admin workspace.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Services;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Services_Admin {
	const NONCE_ACTION = 'headless_service_profile_save';
	const NONCE_NAME   = 'headless_service_profile_nonce';

	/** Register admin hooks. */
	public function register() {
		add_action( 'add_meta_boxes_' . Services_Post_Type::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Services_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Add the guided service profile box. */
	public function add_meta_boxes() {
		add_meta_box(
			'headless-service-profile',
			__( 'Ficha del servicio', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Services_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Enqueue lightweight repeater behavior only on service editor screens. */
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Services_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/** Render service fields. */
	public function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$values = array(
			'description' => get_post_meta( $post->ID, Services_Post_Type::META_DESCRIPTION, true ),
			'audience'    => get_post_meta( $post->ID, Services_Post_Type::META_AUDIENCE, true ),
			'department'  => get_post_meta( $post->ID, Services_Post_Type::META_DEPARTMENT, true ),
			'procedure'   => get_post_meta( $post->ID, Services_Post_Type::META_PROCEDURE, true ),
			'schedule'    => get_post_meta( $post->ID, Services_Post_Type::META_SCHEDULE, true ),
			'cost'        => get_post_meta( $post->ID, Services_Post_Type::META_COST, true ),
			'duration'    => get_post_meta( $post->ID, Services_Post_Type::META_DURATION, true ),
			'channel'     => get_post_meta( $post->ID, Services_Post_Type::META_CHANNEL, true ),
			'phone'       => get_post_meta( $post->ID, Services_Post_Type::META_PHONE, true ),
			'email'       => get_post_meta( $post->ID, Services_Post_Type::META_EMAIL, true ),
			'address'     => get_post_meta( $post->ID, Services_Post_Type::META_ADDRESS, true ),
			'alt'         => get_post_meta( $post->ID, Services_Post_Type::META_ALT, true ),
			'external_id' => get_post_meta( $post->ID, Services_Post_Type::META_EXTERNAL_ID, true ),
		);
		$requirements = Services_Post_Type::sanitize_requirements( get_post_meta( $post->ID, Services_Post_Type::META_REQUIREMENTS, true ) );
		if ( empty( $requirements ) ) {
			$requirements = array( '' );
		}
		?>
		<style>
			.hac-service-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.hac-service-card{border:1px solid #dcdcde;border-radius:8px;padding:16px;background:#fff}.hac-service-card--full{grid-column:1/-1}.hac-service-field{margin:0 0 14px}.hac-service-field:last-child{margin-bottom:0}.hac-service-field label{display:block;font-weight:600;margin-bottom:6px}.hac-service-field input,.hac-service-field textarea{width:100%}.hac-service-help{color:#646970;font-size:12px;margin:5px 0 0}.hac-service-requirements{margin:0;padding:0;list-style:none}.hac-service-requirement{display:flex;gap:8px;align-items:flex-start;margin-bottom:8px}.hac-service-requirement textarea{flex:1}.hac-service-drag{cursor:move;padding:8px 4px;color:#646970}.hac-service-remove{margin-top:3px}.hac-service-add{margin-top:6px}@media(max-width:782px){.hac-service-grid{grid-template-columns:1fr}}
		</style>
		<div class="hac-service-grid">
			<div class="hac-service-card hac-service-card--full">
				<div class="hac-service-field"><label for="headless_service_description">Descripción del servicio</label><textarea id="headless_service_description" name="headless_service_description" rows="5"><?php echo esc_textarea( $values['description'] ); ?></textarea></div>
				<div class="hac-service-field"><label for="headless_service_audience">A quién va dirigido</label><textarea id="headless_service_audience" name="headless_service_audience" rows="4"><?php echo esc_textarea( $values['audience'] ); ?></textarea></div>
				<div class="hac-service-field"><label for="headless_service_department">Departamento que lo ofrece</label><input id="headless_service_department" name="headless_service_department" type="text" value="<?php echo esc_attr( $values['department'] ); ?>"></div>
			</div>

			<div class="hac-service-card hac-service-card--full">
				<div class="hac-service-field"><label>Requerimientos o requisitos</label><p class="hac-service-help">Cada requisito es un renglón independiente. Puedes arrastrarlos para cambiar el orden.</p>
					<ul class="hac-service-requirements" id="hac-service-requirements">
						<?php foreach ( $requirements as $requirement ) : ?>
						<li class="hac-service-requirement"><span class="dashicons dashicons-menu hac-service-drag" aria-hidden="true"></span><textarea name="headless_service_requirements[]" rows="2"><?php echo esc_textarea( $requirement ); ?></textarea><button type="button" class="button-link-delete hac-service-remove">Quitar</button></li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="button hac-service-add" id="hac-service-add-requirement">Añadir requisito</button>
				</div>
				<div class="hac-service-field"><label for="headless_service_procedure">Procedimientos a seguir</label><textarea id="headless_service_procedure" name="headless_service_procedure" rows="5"><?php echo esc_textarea( $values['procedure'] ); ?></textarea></div>
			</div>

			<div class="hac-service-card">
				<div class="hac-service-field"><label for="headless_service_schedule">Horario</label><textarea id="headless_service_schedule" name="headless_service_schedule" rows="3"><?php echo esc_textarea( $values['schedule'] ); ?></textarea></div>
				<div class="hac-service-field"><label for="headless_service_cost">Costo</label><input id="headless_service_cost" name="headless_service_cost" type="text" value="<?php echo esc_attr( $values['cost'] ); ?>"></div>
				<div class="hac-service-field"><label for="headless_service_duration">Tiempo estimado</label><input id="headless_service_duration" name="headless_service_duration" type="text" value="<?php echo esc_attr( $values['duration'] ); ?>"></div>
				<div class="hac-service-field"><label for="headless_service_channel">Canal</label><input id="headless_service_channel" name="headless_service_channel" type="text" value="<?php echo esc_attr( $values['channel'] ); ?>"></div>
			</div>

			<div class="hac-service-card">
				<div class="hac-service-field"><label for="headless_service_phone">Teléfono</label><input id="headless_service_phone" name="headless_service_phone" type="text" value="<?php echo esc_attr( $values['phone'] ); ?>"></div>
				<div class="hac-service-field"><label for="headless_service_email">Correo</label><input id="headless_service_email" name="headless_service_email" type="email" value="<?php echo esc_attr( $values['email'] ); ?>"></div>
				<div class="hac-service-field"><label for="headless_service_address">Dirección</label><textarea id="headless_service_address" name="headless_service_address" rows="3"><?php echo esc_textarea( $values['address'] ); ?></textarea></div>
			</div>

			<div class="hac-service-card hac-service-card--full">
				<div class="hac-service-field"><label for="headless_service_alt">Texto alternativo de la imagen</label><input id="headless_service_alt" name="headless_service_alt" type="text" value="<?php echo esc_attr( $values['alt'] ); ?>"><p class="hac-service-help">La imagen principal se gestiona desde “Imagen destacada”.</p></div>
				<div class="hac-service-field"><label for="headless_service_external_id">ID externo</label><input id="headless_service_external_id" name="headless_service_external_id" type="text" value="<?php echo esc_attr( $values['external_id'] ); ?>"><p class="hac-service-help">Privado. Se utilizará para importación/upsert y nunca se expondrá en la API pública.</p></div>
			</div>
		</div>
		<script>
		jQuery(function($){
			var $list=$('#hac-service-requirements');
			$list.sortable({handle:'.hac-service-drag'});
			$('#hac-service-add-requirement').on('click',function(){
				$list.append('<li class="hac-service-requirement"><span class="dashicons dashicons-menu hac-service-drag" aria-hidden="true"></span><textarea name="headless_service_requirements[]" rows="2"></textarea><button type="button" class="button-link-delete hac-service-remove">Quitar</button></li>');
			});
			$list.on('click','.hac-service-remove',function(){
				var $rows=$list.children('.hac-service-requirement');
				if($rows.length===1){$(this).siblings('textarea').val('');return;}
				$(this).closest('.hac-service-requirement').remove();
			});
		});
		</script>
		<?php
	}

	/** Save the service profile. */
	public function save( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || Services_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$map = array(
			'headless_service_description' => array( Services_Post_Type::META_DESCRIPTION, 'sanitize_textarea_field' ),
			'headless_service_audience'    => array( Services_Post_Type::META_AUDIENCE, 'sanitize_textarea_field' ),
			'headless_service_department'  => array( Services_Post_Type::META_DEPARTMENT, 'sanitize_text_field' ),
			'headless_service_procedure'   => array( Services_Post_Type::META_PROCEDURE, 'sanitize_textarea_field' ),
			'headless_service_schedule'    => array( Services_Post_Type::META_SCHEDULE, 'sanitize_textarea_field' ),
			'headless_service_cost'        => array( Services_Post_Type::META_COST, 'sanitize_text_field' ),
			'headless_service_duration'    => array( Services_Post_Type::META_DURATION, 'sanitize_text_field' ),
			'headless_service_channel'     => array( Services_Post_Type::META_CHANNEL, 'sanitize_text_field' ),
			'headless_service_phone'       => array( Services_Post_Type::META_PHONE, 'sanitize_text_field' ),
			'headless_service_email'       => array( Services_Post_Type::META_EMAIL, array( Services_Post_Type::class, 'sanitize_email_value' ) ),
			'headless_service_address'     => array( Services_Post_Type::META_ADDRESS, 'sanitize_textarea_field' ),
			'headless_service_alt'         => array( Services_Post_Type::META_ALT, 'sanitize_text_field' ),
			'headless_service_external_id' => array( Services_Post_Type::META_EXTERNAL_ID, array( Services_Post_Type::class, 'sanitize_external_id' ) ),
		);

		foreach ( $map as $field => $config ) {
			$raw   = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
			$value = call_user_func( $config[1], $raw );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $config[0] );
			} else {
				update_post_meta( $post_id, $config[0], $value );
			}
		}

		$raw_requirements = isset( $_POST['headless_service_requirements'] ) && is_array( $_POST['headless_service_requirements'] ) ? wp_unslash( $_POST['headless_service_requirements'] ) : array();
		$requirements     = Services_Post_Type::sanitize_requirements( $raw_requirements );
		if ( empty( $requirements ) ) {
			delete_post_meta( $post_id, Services_Post_Type::META_REQUIREMENTS );
		} else {
			update_post_meta( $post_id, Services_Post_Type::META_REQUIREMENTS, $requirements );
		}
	}
}
