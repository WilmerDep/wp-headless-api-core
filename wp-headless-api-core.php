<?php
/**
 * Plugin Name: Headless API Core
 * Description: Modular REST API core for using WordPress as a headless CMS.
 * Version: 0.4.0
 * Author: WilmerDep
 * Text Domain: wp-headless-api-core
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'HEADLESS_API_CORE_VERSION', '0.4.0' );
define( 'HEADLESS_API_CORE_FILE', __FILE__ );
define( 'HEADLESS_API_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once HEADLESS_API_CORE_PATH . 'includes/Core/Plugin.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Rest/Health_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Revalidation/Revalidation_Client.php';
require_once HEADLESS_API_CORE_PATH . 'modules/News/News_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/News/News_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/News/News_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/News/News_Module.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Post_Type.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Admin.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Hero/Hero_Module.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Post_Type.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Order.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Importer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Import_Batch.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Csv_Mode.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Admin.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Module.php';

add_action( 'plugins_loaded', array( 'HeadlessApiCore\\Core\\Plugin', 'boot' ) );
