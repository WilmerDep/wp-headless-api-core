<?php
/**
 * Isolated regression test that News provider remains public-only and fresh.
 */

namespace {
	define( 'ABSPATH', __DIR__ );

	#[\AllowDynamicProperties]
	class WP_Post {
		public $ID = 1;
		public $post_name = 'test';
		public $post_status = 'publish';
		public $post_password = '';
	}

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

		public function header( $key, $value ) {
			$this->headers[ $key ] = $value;
		}
	}

	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code, $message, $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
	}

	class WP_REST_Server {
		const READABLE = 'GET';
	}

	class WP_Query {
		public $posts = array();
		public $found_posts = 0;
		public $max_num_pages = 0;

		public function __construct( array $args ) {
			$GLOBALS['news_query_args'] = $args;

			if ( isset( $args['name'] ) ) {
				$post = $GLOBALS['news_detail_post'] ?? null;

				if (
					$post instanceof WP_Post &&
					'publish' === $post->post_status &&
					empty( $post->post_password ) &&
					$post->post_name === $args['name']
				) {
					$this->posts = array( $post );
				}
			}
		}
	}

	$GLOBALS['news_detail_post'] = null;
}

namespace HeadlessApiCore\Core {
	class Plugin {
		const REST_NAMESPACE = 'headless-core/v1';
	}
}

namespace HeadlessApiCore\Modules\News {
	class News_Serializer {
		public function summary( $post ) {
			unset( $post );
			return array();
		}

		public function detail( $post ) {
			unset( $post );
			return array( 'ok' => true );
		}
	}

	function get_post_status( $post ) {
		return $post->post_status;
	}

	function __( $value, $domain ) {
		unset( $domain );
		return $value;
	}

	require_once dirname( __DIR__ ) . '/modules/News/News_Controller.php';

	$controller = new News_Controller( new News_Serializer() );
	$request    = new \WP_REST_Request(
		array(
			'page'     => 1,
			'per_page' => 12,
			'order'    => 'desc',
			'orderby'  => 'date',
		)
	);

	$controller->get_items( $request );
	$args = $GLOBALS['news_query_args'];

	if ( 'publish' !== ( $args['post_status'] ?? null ) || false !== ( $args['has_password'] ?? null ) ) {
		fwrite( STDERR, "News collection must query only published, non-password-protected posts.\n" );
		exit( 1 );
	}

	if ( false !== ( $args['cache_results'] ?? null ) ) {
		fwrite( STDERR, "News collection must bypass persistent WP_Query result caching.\n" );
		exit( 1 );
	}

	foreach ( array( 'draft', 'pending', 'private', 'trash', 'future' ) as $status ) {
		$post                = new \WP_Post();
		$post->post_name     = 'test';
		$post->post_status   = $status;
		$GLOBALS['news_detail_post'] = $post;
		$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'test' ) ) );

		if ( ! ( $result instanceof \WP_Error ) || 'headless_core_news_not_found' !== $result->code || 404 !== ( $result->data['status'] ?? null ) ) {
			fwrite( STDERR, 'News detail exposed non-public status: ' . $status . "\n" );
			exit( 1 );
		}

		if ( false !== ( $GLOBALS['news_query_args']['cache_results'] ?? null ) ) {
			fwrite( STDERR, "News detail must bypass persistent WP_Query result caching.\n" );
			exit( 1 );
		}
	}

	$protected                = new \WP_Post();
	$protected->post_name     = 'protected';
	$protected->post_status   = 'publish';
	$protected->post_password = 'secret';
	$GLOBALS['news_detail_post'] = $protected;
	$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'protected' ) ) );

	if ( ! ( $result instanceof \WP_Error ) || 'headless_core_news_not_found' !== $result->code ) {
		fwrite( STDERR, "Password-protected News detail must remain hidden.\n" );
		exit( 1 );
	}

	$public                    = new \WP_Post();
	$public->post_name         = 'public';
	$public->post_status       = 'publish';
	$public->post_password     = '';
	$GLOBALS['news_detail_post'] = $public;
	$result = $controller->get_item( new \WP_REST_Request( array( 'slug' => 'public' ) ) );

	if ( ! ( $result instanceof \WP_REST_Response ) || 200 !== $result->status ) {
		fwrite( STDERR, "Published public News detail should remain available.\n" );
		exit( 1 );
	}

	$news_response = new \WP_REST_Response( array( 'ok' => true ), 200 );
	$controller->prevent_news_http_cache(
		$news_response,
		new \WP_REST_Server(),
		new \WP_REST_Request( array(), '/headless-core/v1/news' )
	);

	$expected_cache_control = 'no-store, no-cache, must-revalidate, max-age=0';
	if ( $expected_cache_control !== ( $news_response->headers['Cache-Control'] ?? null ) ) {
		fwrite( STDERR, "News REST responses must explicitly disable HTTP caching.\n" );
		exit( 1 );
	}

	if ( 'no-cache' !== ( $news_response->headers['Pragma'] ?? null ) || '0' !== ( $news_response->headers['Expires'] ?? null ) ) {
		fwrite( STDERR, "News REST no-cache compatibility headers are missing.\n" );
		exit( 1 );
	}

	$other_response = new \WP_REST_Response( array( 'ok' => true ), 200 );
	$controller->prevent_news_http_cache(
		$other_response,
		new \WP_REST_Server(),
		new \WP_REST_Request( array(), '/headless-core/v1/health' )
	);

	if ( ! empty( $other_response->headers ) ) {
		fwrite( STDERR, "News cache policy must not mutate unrelated REST routes.\n" );
		exit( 1 );
	}

	echo "News public visibility and Provider freshness test passed.\n";
}
