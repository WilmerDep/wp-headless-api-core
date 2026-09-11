<?php
/**
 * Isolated Hero controller/query/cache regression test.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post {
		public $ID;
		public $menu_order = 0;
		public function __construct( $id ) {
			$this->ID = $id;
		}
	}

	class WP_REST_Request {
		private $route;
		public function __construct( $route = '' ) {
			$this->route = $route;
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
		public $posts = array();
		public function __construct( array $args ) {
			$GLOBALS['hero_query_args'] = $args;
			$this->posts = array( new WP_Post( 1 ), new WP_Post( 2 ) );
		}
	}
}

namespace HeadlessApiCore\Core {
	class Plugin {
		const REST_NAMESPACE = 'headless-core/v1';
	}
}

namespace HeadlessApiCore\Modules\Hero {
	class Hero_Post_Type {
		const POST_TYPE = 'headless_hero';
	}

	class Hero_Serializer {
		public function item( $post ) {
			return 2 === $post->ID ? null : array( 'id' => $post->ID );
		}
	}

	require_once dirname( __DIR__ ) . '/modules/Hero/Hero_Controller.php';

	$controller = new Hero_Controller( new Hero_Serializer() );
	$response   = $controller->get_items( new \WP_REST_Request( '/headless-core/v1/hero' ) );
	$args       = $GLOBALS['hero_query_args'];

	if (
		'headless_hero' !== ( $args['post_type'] ?? null ) ||
		'publish' !== ( $args['post_status'] ?? null ) ||
		false !== ( $args['has_password'] ?? null ) ||
		false !== ( $args['cache_results'] ?? null ) ||
		-1 !== ( $args['posts_per_page'] ?? null )
	) {
		fwrite( STDERR, "Hero query must be fresh, public-only and unpaginated.\n" );
		exit( 1 );
	}

	$orderby = $args['orderby'] ?? array();
	if ( array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) !== $orderby ) {
		fwrite( STDERR, "Hero query ordering must be menu_order ASC then ID ASC.\n" );
		exit( 1 );
	}

	if ( ! ( $response instanceof \WP_REST_Response ) || array( array( 'id' => 1 ) ) !== $response->data['items'] ) {
		fwrite( STDERR, "Hero controller must exclude serializer-invalid items.\n" );
		exit( 1 );
	}

	$controller->prevent_hero_http_cache( $response, new \WP_REST_Server(), new \WP_REST_Request( '/headless-core/v1/hero' ) );
	if ( 'no-store, no-cache, must-revalidate, max-age=0' !== ( $response->headers['Cache-Control'] ?? null ) ) {
		fwrite( STDERR, "Hero endpoint must advertise no-store cache policy.\n" );
		exit( 1 );
	}

	$other = new \WP_REST_Response( array(), 200 );
	$controller->prevent_hero_http_cache( $other, new \WP_REST_Server(), new \WP_REST_Request( '/headless-core/v1/news' ) );
	if ( ! empty( $other->headers ) ) {
		fwrite( STDERR, "Hero cache filter must not mutate unrelated REST routes.\n" );
		exit( 1 );
	}

	echo "Hero controller test passed.\n";
}
