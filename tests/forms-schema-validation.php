<?php
/**
 * Isolated Forms schema + validation regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DAY_IN_SECONDS', 86400 );

	function apply_filters( $tag, $value ) {
		unset( $tag );
		return $value;
	}
	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
	}
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
	function sanitize_title( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function is_email( $value ) {
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
	function __( $value, $domain = null ) {
		unset( $domain );
		return $value;
	}
	function wp_timezone() {
		return new \DateTimeZone( 'UTC' );
	}
}

namespace HeadlessApiCore\Modules\Forms {
	require_once dirname( __DIR__ ) . '/modules/Forms/Forms_Schema.php';
	require_once dirname( __DIR__ ) . '/modules/Forms/Forms_Validator.php';

	$fields = Forms_Schema::normalize_fields(
		array(
			array(
				'name'       => 'fullName',
				'type'       => 'text',
				'label'      => 'Nombre',
				'required'   => true,
				'width'      => 6,
				'validation' => array( 'minLength' => 2, 'pattern' => 'name', 'safeText' => true ),
			),
			array(
				'name'       => 'email',
				'type'       => 'email',
				'label'      => 'Correo',
				'required'   => true,
				'width'      => 99,
			),
			array(
				'name'       => 'service',
				'type'       => 'select',
				'label'      => 'Servicio',
				'options'    => array(
					array( 'value' => 'cardio', 'label' => 'Cardiología' ),
					array( 'value' => 'neumo', 'label' => 'Neumología' ),
				),
			),
			array(
				'name'       => 'doctor',
				'type'       => 'select',
				'label'      => 'Médico',
				'required'   => true,
				'visibility' => array(
					'mode' => 'show',
					'all'  => array(
						array( 'field' => 'service', 'operator' => 'equals', 'value' => 'cardio' ),
					),
				),
				'options' => array( array( 'value' => 'dr-a', 'label' => 'Dr. A' ) ),
			),
			array( 'name' => 'email', 'type' => 'text', 'label' => 'Duplicado' ),
			array( 'name' => 'custom', 'type' => 'not-supported', 'label' => 'Custom' ),
		)
	);

	if ( 5 !== count( $fields ) ) {
		fwrite( STDERR, "Duplicate field names must be removed.\n" );
		exit( 1 );
	}
	if ( 12 !== $fields[1]['width'] ) {
		fwrite( STDERR, "Field width must be clamped to the 12-column contract.\n" );
		exit( 1 );
	}
	if ( 'text' !== $fields[4]['type'] ) {
		fwrite( STDERR, "Unsupported field types must fall back safely.\n" );
		exit( 1 );
	}

	$notifications = Forms_Schema::normalize_notifications(
		array(
			array(
				'id'       => 'Admin',
				'template' => 'Institutional',
				'to'       => array( 'forms@example.test', '{{field.email}}', 'bad-address' ),
			)
		)
	);
	if ( 2 !== count( $notifications[0]['to'] ) || 'institutional' !== $notifications[0]['template'] ) {
		fwrite( STDERR, "Notification recipients/template must be normalized safely.\n" );
		exit( 1 );
	}

	$validator = new Forms_Validator();
	$result = $validator->validate(
		$fields,
		array(
			'fullName' => 'Ana Pérez',
			'email'    => 'ana@example.test',
			'service'  => 'neumo',
			'custom'   => 'ok',
			'website'  => '',
		),
		'website'
	);
	if ( ! $result['valid'] || isset( $result['fieldErrors']['doctor'] ) ) {
		fwrite( STDERR, "A conditionally hidden required field must not fail validation.\n" );
		exit( 1 );
	}

	$result = $validator->validate(
		$fields,
		array(
			'fullName' => 'Ana Pérez',
			'email'    => 'not-an-email',
			'service'  => 'cardio',
			'custom'   => 'ok',
			'website'  => '',
		),
		'website'
	);
	if ( $result['valid'] || empty( $result['fieldErrors']['email'] ) || empty( $result['fieldErrors']['doctor'] ) ) {
		fwrite( STDERR, "Visible required fields and email validation must be enforced.\n" );
		exit( 1 );
	}

	$result = $validator->validate(
		$fields,
		array(
			'fullName' => 'Ana Pérez',
			'email'    => 'ana@example.test',
			'service'  => 'neumo',
			'custom'   => 'ok',
			'website'  => '',
			'injected' => 'nope',
		),
		'website'
	);
	if ( $result['valid'] || 'unknown_fields' !== $result['error'] ) {
		fwrite( STDERR, "Unknown payload fields must be rejected by default.\n" );
		exit( 1 );
	}

	echo "Forms schema and validation test passed.\n";
}
