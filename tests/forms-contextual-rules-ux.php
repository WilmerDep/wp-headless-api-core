<?php
/**
 * Forms contextual advanced-rules editorial UX contract.
 */

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__ );
$ux   = file_get_contents( $root . '/assets/admin/forms-editorial-ux.js' );
$css  = file_get_contents( $root . '/assets/admin/forms.css' );

foreach (
	array(
		'applyFieldContext',
		'contextHelpForType',
		'setControlVisible',
		'Configuración técnica opcional',
		'Opciones disponibles',
		'Formato permitido',
		'Sin restricción especial',
		'Solo números',
		'Mostrar u ocultar según otra respuesta',
		'Lista desplegable',
		'Una sola opción',
		'Casillas de selección',
	) as $needle
) {
	if ( false === strpos( $ux, $needle ) ) {
		fwrite( STDERR, "Forms contextual UX contract missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach ( array( '.headless-forms-technical', '.headless-forms-context-help' ) as $needle ) {
	if ( false === strpos( $css, $needle ) ) {
		fwrite( STDERR, "Forms contextual UX style missing: {$needle}\n" );
		exit( 1 );
	}
}

// The editorial layer may hide controls, but it must not rewrite the saved form state.
foreach ( array( 'fieldsState', 'delete item.validation', 'item.options =' ) as $forbidden ) {
	if ( false !== strpos( $ux, $forbidden ) ) {
		fwrite( STDERR, "Editorial UX must not mutate saved field data: {$forbidden}\n" );
		exit( 1 );
	}
}

$fixture_path = $root . '/project-docs/forms/hosgedopol-appointment-form.json';
$fixture      = json_decode( file_get_contents( $fixture_path ), true );
$fields       = $fixture['form']['fields'] ?? array();
$by_name      = array();
foreach ( $fields as $field ) {
	if ( isset( $field['name'] ) ) {
		$by_name[ $field['name'] ] = $field;
	}
}

$checks = array(
	'birthDate'        => static function ( $field ) { return true === ( $field['validation']['pastOnly'] ?? false ); },
	'appointmentDate'  => static function ( $field ) { return true === ( $field['validation']['futureOnly'] ?? false ); },
	'experienceRating' => static function ( $field ) { return 0 === ( $field['validation']['min'] ?? null ) && 5 === ( $field['validation']['max'] ?? null ); },
	'message'          => static function ( $field ) { return 150 === ( $field['validation']['maxWords'] ?? null ) && 1800 === ( $field['validation']['maxLength'] ?? null ); },
	'sex'              => static function ( $field ) { return 2 === count( $field['options'] ?? array() ); },
);

foreach ( $checks as $name => $check ) {
	if ( ! isset( $by_name[ $name ] ) || ! $check( $by_name[ $name ] ) ) {
		fwrite( STDERR, "Appointment fixture lost pre-adjusted advanced rules for {$name}.\n" );
		exit( 1 );
	}
}

echo "Forms contextual advanced-rules editorial UX contract test passed.\n";
