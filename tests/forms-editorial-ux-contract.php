<?php
/**
 * Forms plain-language editorial UX contract.
 */

define( 'ABSPATH', __DIR__ );

$root   = dirname( __DIR__ );
$compat = file_get_contents( $root . '/modules/Forms/Forms_Compatibility.php' );
$script = file_get_contents( $root . '/assets/admin/forms-editorial-ux.js' );
$plugin = file_get_contents( $root . '/wp-headless-api-core.php' );

foreach (
	array(
		'assets/admin/forms-editorial-ux.js',
		'headless-forms-editorial-ux',
	) as $needle
) {
	if ( false === strpos( $compat, $needle ) ) {
		fwrite( STDERR, "Forms editorial UX loader is missing: {$needle}\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'Protección del formulario',
		'Filtro antispam invisible',
		'Limitar envíos repetidos',
		'Tamaño máximo del envío',
		'Máximo de envíos',
		'Período de control (segundos)',
		'Nombre interno',
		'Autocompletar en navegador',
		'Texto de ejemplo',
		'Componente especial del sitio',
		'Responder al correo de',
		'Copia oculta',
		'Reglas y opciones avanzadas',
		'Bloquear contenido riesgoso',
	) as $needle
) {
	if ( false === strpos( $script, $needle ) ) {
		fwrite( STDERR, "Forms editorial UX contract is missing friendly copy: {$needle}\n" );
		exit( 1 );
	}
}

foreach ( array( 'Honeypot', 'Rate limit', 'Máx. payload (bytes)' ) as $technical ) {
	if ( false === strpos( $script, $technical ) ) {
		fwrite( STDERR, "Forms editorial UX must explicitly translate legacy term: {$technical}\n" );
		exit( 1 );
	}
}

preg_match( '/\* Version:\s*([0-9.]+)/', $plugin, $version_match );
$version = isset( $version_match[1] ) ? $version_match[1] : '';
if ( '' === $version || version_compare( $version, '0.7.7', '<' ) ) {
	fwrite( STDERR, "Forms editorial UX requires plugin version 0.7.7 or newer.\n" );
	exit( 1 );
}

echo "Forms plain-language editorial UX contract test passed.\n";
