<?php
/**
 * Isolated licensing enforcement test for Hero and Directory REST boundaries.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_REST_Request {
		private $params;
		private $route;
		public function __construct( array $params = array(), $route = '' ) {
			$this->params = $params;
			$this->route  = $route;
		}
		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
		public function get_route() {
			return $this->route;
		}
	}

	class WP_REST_Response {
		public $data;
		public $status;
		public $headers = array();
		public function __construct( $data, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function header( $name, $value ) {
			$this->headers[ $name ] = $value;
		}
	}

	class WP_REST_Server {
		const READABLE = 'GET';
	}

	class WP_Query {
		public function __construct( array $args ) {
			unset( $args );
			$GLOBALS['restricted_query_executed'] = true;
		}
	}

	function sanitize_title( $value ) {
		return strtolower( trim( (string) $value ) );
	}
	function get_term_by() {
		return false;
	}
	function is_wp_error() {
		return false;
	}
}

namespace HeadlessApiCore\Core {
	class Plugin {
		const REST_NAMESPACE = 'headless-core/v1';
	}
}

namespace HeadlessApiCore\Licensing {
	class License_Policy {
		const CAPABILITY_PUBLIC_CONTENT = 'public_content';
	}
	class License_Gate {
		public static $decisions = array();
		public static function evaluate( $capability, $entitlement = null ) {
			unset( $capability );
			return self::$decisions[ $entitlement ] ?? array(
				'allowed' => false,
				'code'    => 'LICENSE_VERIFICATION_REQUIRED',
				'status'  => 'untrusted',
			);
		}
	}
}

namespace HeadlessApiCore\Modules\Hero {
	class Hero_Post_Type {
		const POST_TYPE = 'headless_hero';
	}
	class Hero_Serializer {
		public function item( $post ) {
			unset( $post );
			return array();
		}
	}
	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Controller.php';
}

namespace HeadlessApiCore\Modules\Directory {
	class Directory_Post_Type {
		const POST_TYPE = 'headless_directory';
		const TAXONOMY = 'headless_directory_group';
	}
	class Directory_Serializer {
		public function item( $post, $group_id = 0 ) {
			unset( $post, $group_id );
			return array();
		}
	}
	require_once dirname( __DIR__ ) . '/modules/Directory/Directory_Controller.php';
}

namespace {
	use HeadlessApiCore\Licensing\License_Gate;
	use HeadlessApiCore\Modules\Directory\Directory_Controller;
	use HeadlessApiCore\Modules\Directory\Directory_Serializer;
	use HeadlessApiCore\Modules\Hero\Hero_Controller;
	use HeadlessApiCore\Modules\Hero\Hero_Serializer;

	$GLOBALS['restricted_query_executed'] = false;
	License_Gate::$decisions['hero'] = array(
		'allowed' => false,
		'code'    => 'LICENSE_RENEWAL_REQUIRED',
		'status'  => 'expired',
	);
	License_Gate::$decisions['directory'] = array(
		'allowed' => false,
		'code'    => 'ENTITLEMENT_REQUIRED',
		'status'  => 'active',
	);

	$hero = new Hero_Controller( new Hero_Serializer() );
	$hero_response = $hero->get_items( new WP_REST_Request( array(), '/headless-core/v1/hero' ) );
	if ( 403 !== $hero_response->status || 'hero' !== ( $hero_response->data['module'] ?? null ) || 'LICENSE_RENEWAL_REQUIRED' !== ( $hero_response->data['code'] ?? null ) ) {
		fwrite( STDERR, "Hero must expose explicit licensing restriction response.\n" );
		exit( 1 );
	}

	$directory = new Directory_Controller( new Directory_Serializer() );
	$directory_response = $directory->get_items( new WP_REST_Request( array(), '/headless-core/v1/directory' ) );
	if ( 403 !== $directory_response->status || 'directory' !== ( $directory_response->data['module'] ?? null ) || 'ENTITLEMENT_REQUIRED' !== ( $directory_response->data['code'] ?? null ) ) {
		fwrite( STDERR, "Directory must expose explicit entitlement restriction response.\n" );
		exit( 1 );
	}

	$groups_response = $directory->get_groups( new WP_REST_Request( array(), '/headless-core/v1/directory/groups' ) );
	if ( 403 !== $groups_response->status || 'directory' !== ( $groups_response->data['module'] ?? null ) ) {
		fwrite( STDERR, "Directory groups must use the same licensing gate.\n" );
		exit( 1 );
	}

	if ( ! empty( $GLOBALS['restricted_query_executed'] ) ) {
		fwrite( STDERR, "Restricted public modules must not execute content queries.\n" );
		exit( 1 );
	}

	$hero->prevent_hero_http_cache( $hero_response, new WP_REST_Server(), new WP_REST_Request( array(), '/headless-core/v1/hero' ) );
	$directory->prevent_directory_http_cache( $directory_response, new WP_REST_Server(), new WP_REST_Request( array(), '/headless-core/v1/directory' ) );
	$expected = 'no-store, no-cache, must-revalidate, max-age=0';
	if ( $expected !== ( $hero_response->headers['Cache-Control'] ?? null ) || $expected !== ( $directory_response->headers['Cache-Control'] ?? null ) ) {
		fwrite( STDERR, "Restricted responses must remain non-cacheable.\n" );
		exit( 1 );
	}

	echo "Hero and Directory licensing enforcement test passed.\n";
}
