<?php
/**
 * Safe administrative test box for reusable mail templates.
 *
 * WordPress wraps the whole post editor in a single <form>. Rendering another
 * form inside a meta box produces invalid nested-form markup and browsers can
 * close the main editor form early, causing template settings (including the
 * selected logo) to be omitted from the normal Update request.
 *
 * This class replaces only the visual test box. The existing
 * Mail_Template_Test class remains responsible for the protected admin-post
 * delivery handler.
 *
 * @package HeadlessApiCore
 */

namespace HeadlessApiCore\Modules\Forms;

use HeadlessApiCore\Modules\Mail\Mail_Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Mail_Template_Test_Box {
	/** @var Mail_Settings */
	private $mail_settings;

	public function __construct( Mail_Settings $mail_settings ) {
		$this->mail_settings = $mail_settings;
	}

	/** Register the safe UI replacement and editor redirect guard. */
	public function register() {
		add_action( 'add_meta_boxes_' . Forms_Post_Type::TEMPLATE_POST_TYPE, array( $this, 'replace_meta_box' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'redirect_post_location', array( $this, 'keep_editor_after_save' ), 20, 2 );
	}

	/** Replace the legacy nested-form box before WordPress renders meta boxes. */
	public function replace_meta_box() {
		remove_meta_box( 'headless-mail-template-test', Forms_Post_Type::TEMPLATE_POST_TYPE, 'side' );
		add_meta_box(
			'headless-mail-template-test',
			__( 'Correo de prueba', 'wp-headless-api-core' ),
			array( $this, 'render' ),
			Forms_Post_Type::TEMPLATE_POST_TYPE,
			'side',
			'default'
		);
	}

	/** Render controls without introducing a nested HTML form. */
	public function render( WP_Post $post ) {
		$result = get_transient( Mail_Template_Test::RESULT_KEY . get_current_user_id() );
		if ( is_array( $result ) && isset( $result['templateId'] ) && (int) $result['templateId'] === (int) $post->ID ) {
			delete_transient( Mail_Template_Test::RESULT_KEY . get_current_user_id() );
			$class = ! empty( $result['success'] ) ? 'notice notice-success inline' : 'notice notice-error inline';
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( isset( $result['message'] ) ? $result['message'] : '' )
			);
		}

		$user        = wp_get_current_user();
		$recipient   = $user && is_email( $user->user_email ) ? $user->user_email : get_option( 'admin_email' );
		$forms       = get_posts(
			array(
				'post_type'      => Forms_Post_Type::FORM_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array( 'title' => 'ASC', 'ID' => 'ASC' ),
			)
		);
		$is_ready    = $this->mail_settings->is_ready();
		$is_publish  = 'publish' === $post->post_status;
		$is_disabled = ! $is_ready || ! $is_publish;
		?>
		<div
			data-headless-mail-template-test
			data-action-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			data-action="<?php echo esc_attr( Mail_Template_Test::ACTION ); ?>"
			data-template-id="<?php echo esc_attr( $post->ID ); ?>"
			data-nonce-name="<?php echo esc_attr( Mail_Template_Test::NONCE_NAME ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( Mail_Template_Test::NONCE_ACTION ) ); ?>"
		>
			<p><?php esc_html_e( 'Envía la versión guardada de esta plantilla usando datos de ejemplo. No expone ni modifica las credenciales SMTP.', 'wp-headless-api-core' ); ?></p>
			<?php if ( ! $is_publish ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Guarda y publica la plantilla antes de enviar una prueba.', 'wp-headless-api-core' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $is_ready ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Mail Core no está listo para enviar.', 'wp-headless-api-core' ); ?></p></div>
			<?php endif; ?>
			<p>
				<label for="headless-mail-test-recipient"><strong><?php esc_html_e( 'Destinatario', 'wp-headless-api-core' ); ?></strong></label>
				<input id="headless-mail-test-recipient" class="widefat" type="email" data-mail-test-recipient value="<?php echo esc_attr( $recipient ); ?>" required>
			</p>
			<p>
				<label for="headless-mail-test-form"><strong><?php esc_html_e( 'Datos de ejemplo', 'wp-headless-api-core' ); ?></strong></label>
				<select id="headless-mail-test-form" class="widefat" data-mail-test-form>
					<option value="0"><?php esc_html_e( 'Ejemplo genérico', 'wp-headless-api-core' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( $form->ID ); ?>"><?php echo esc_html( get_the_title( $form ) . ' — ' . $form->post_status ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p><button type="button" class="button button-secondary" data-mail-test-submit <?php disabled( $is_disabled ); ?>><?php esc_html_e( 'Enviar prueba', 'wp-headless-api-core' ); ?></button></p>
		</div>
		<?php
	}

	/** Add the detached admin-post submission behavior after the Forms admin JS. */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || Forms_Post_Type::TEMPLATE_POST_TYPE !== $screen->post_type ) {
			return;
		}

		$script = <<<'JS'
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-mail-test-submit]');
    if (!button || button.disabled) return;

    var box = button.closest('[data-headless-mail-template-test]');
    if (!box) return;

    var recipient = box.querySelector('[data-mail-test-recipient]');
    var sampleForm = box.querySelector('[data-mail-test-form]');
    if (!recipient || !recipient.checkValidity()) {
      if (recipient) recipient.reportValidity();
      return;
    }

    var form = document.createElement('form');
    form.method = 'post';
    form.action = box.getAttribute('data-action-url') || '';
    form.style.display = 'none';

    function add(name, value) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value == null ? '' : String(value);
      form.appendChild(input);
    }

    add('action', box.getAttribute('data-action'));
    add('template_id', box.getAttribute('data-template-id'));
    add(box.getAttribute('data-nonce-name'), box.getAttribute('data-nonce'));
    add('recipient', recipient.value);
    add('form_id', sampleForm ? sampleForm.value : '0');

    document.body.appendChild(form);
    form.submit();
  });
})();
JS;

		wp_add_inline_script( 'headless-forms-admin', $script, 'after' );
	}

	/** Keep a normal Update/Publish action on the same mail-template editor. */
	public function keep_editor_after_save( $location, $post_id ) {
		if ( Forms_Post_Type::TEMPLATE_POST_TYPE !== get_post_type( $post_id ) ) {
			return $location;
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'editpost' !== $action ) {
			return $location;
		}

		$editor = get_edit_post_link( $post_id, 'raw' );
		if ( ! $editor ) {
			return $location;
		}

		$query = wp_parse_url( $location, PHP_URL_QUERY );
		$args  = array();
		if ( is_string( $query ) ) {
			parse_str( $query, $args );
		}
		if ( isset( $args['message'] ) ) {
			$editor = add_query_arg( 'message', absint( $args['message'] ), $editor );
		}

		return $editor;
	}
}
