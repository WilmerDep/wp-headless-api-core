<?php
/**
 * Plugin Name: Headless API Core
 * Description: Modular REST API core for using WordPress as a headless CMS.
 * Version: 0.8.2
 * Author: PholioDev
 * Text Domain: wp-headless-api-core
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'HEADLESS_API_CORE_VERSION', '0.8.2' );
define( 'HEADLESS_API_CORE_FILE', __FILE__ );
define( 'HEADLESS_API_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once HEADLESS_API_CORE_PATH . 'includes/Core/Plugin.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Rest/Health_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Revalidation/Revalidation_Client.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Instance.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Storage.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Verifier.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Entitlements.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Policy.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Client.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Manager.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Gate.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Admin_Notice.php';
require_once HEADLESS_API_CORE_PATH . 'includes/Licensing/License_Admin_Page.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Mail/Mail_Settings.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Mail/Mail_Admin.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Mail/Mail_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Mail/Mail_Module.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Schema.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Post_Type.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Identity.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_List_Table.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Validator.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Mail_Template_Renderer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Mail_Template_Test.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Submission.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Admin.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Compatibility.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Package_Importer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Import_UI.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Forms/Forms_Module.php';
require_once HEADLESS_API_CORE_PATH . 'modules/SiteIdentity/Site_Identity_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/SiteIdentity/Site_Identity_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/SiteIdentity/Site_Identity_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/SiteIdentity/Site_Identity_Module.php';
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
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Rich_Summary.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Directory/Directory_Module.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Post_Type.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Features.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Serializer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Controller.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Order.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Importer.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Admin.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Revalidation.php';
require_once HEADLESS_API_CORE_PATH . 'modules/Services/Services_Module.php';

add_action( 'plugins_loaded', array( 'HeadlessApiCore\\Core\\Plugin', 'boot' ) );
