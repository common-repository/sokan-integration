<?php
/**
 * @package SokanIntegration
 */

/**
 * Plugin Name: sokan Integration
 * Description:  افزونه ای برای استخراج تمامی اطلاعات ووکامرس مورد نیاز پلتفرم سکان
 * Version: 1.6.2
 * Author: Sokan
 * Author URI: https://Sokan.tech/
 * License: MIT
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sokan-Integration
 */

defined( 'ABSPATH' ) or die( 'No access!' );

if ( ! defined( 'SKNG_PLUGIN_NAME' ) ) {
	define( "SKNG_PLUGIN_NAME", "sokan_integration" );
}
if ( ! defined( 'SKNG_PLUGIN_PATH' ) ) {
	define( "SKNG_PLUGIN_PATH", plugin_dir_path( __FILE__ ) );
}

/**
 * Loads the required classes
 * @return void
 * @since 1.1.0
 */
function skng_load_classes() {
	require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-api.php';
	require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-db.php';
	require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-logger.php';
	require_once SKNG_PLUGIN_PATH . 'include/custom/skng-custom.php';
}

add_action( 'skng_auto_sync', 'skng_auto_sync_data' );

function skng_auto_sync_data() {
	if ( ! empty( get_option( SKNG_PLUGIN_NAME . '_token' ) ) and function_exists( 'skng_load_classes' ) ) {
		skng_load_classes();
		require_once SKNG_PLUGIN_PATH . 'job/sync_data.php';
	}
}

if ( function_exists( 'skng_load_classes' ) ) {
	skng_load_classes();
}

class Skng_SokanIntegration {

	/**
	 * register function called everytime admin page is loaded
	 * add action for adding sokan item menu to admin panel
	 * add filter for adding sokan setting button in plugin list page
	 * @return void
	 * @since 1.0.0
	 */
	function register() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );
		$this->add_hooks();
	}

	/**
	 * Create sokan setting button on plugin page list
	 * @return array
	 * @since 1.0.0
	 */
	function settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=sokan_integration">راه اندازی</a>';
		array_push( $links, $settings_link );

		return $links;
	}

	/**
	 * Create sokan menu item in wordPress menu panel
	 * @return void
	 * @since 1.0.0
	 */
	function add_admin_pages() {
		add_menu_page(
			'Sokan Integration',
			'سکان',
			'manage_options',
			'sokan_integration',
			array( $this, 'admin_index' ),
			'dashicons-controls-repeat',
			110
		);
	}

	/**
	 * called when sokan integration icon in admin panel clicked
	 * show sokan integration admin page view
	 * @return void
	 * @since 1.0.0
	 */
	function admin_index() {
		require_once SKNG_PLUGIN_PATH . 'admin.php';
	}

	/**
	 * called When admin activates the plugin
	 * check if woocommerce plugin installed and activated on store
	 * job new daily job for sync
	 * create log table
	 * rewrite saved rules if exists
	 * @return void
	 * @since 1.0.0
	 */
	function activate() {

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' )
		     and current_user_can( 'activate_plugins' ) ) {
			wp_die( 'برای فعال سازی پلاگین سکان لطفا ابتدا پلاگین ووکامرس خود را فعال کنید. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo;بازگشت به صفحه افزونه ها</a>' );
		}

		if ( ! wp_next_scheduled( 'skng_auto_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'skng_auto_sync' );
		}

		$this->drop_old_tables();
		$this->create_log_table();

		if ( ! get_option( SKNG_PLUGIN_NAME . '_token' ) ) {
			update_option( SKNG_PLUGIN_NAME . '_token', '' );
			update_option( SKNG_PLUGIN_NAME . '_sale_status', 'wc-completed' );
			update_option( SKNG_PLUGIN_NAME . '_refunded_status', 'wc-refunded' );
			update_option( SKNG_PLUGIN_NAME . '_sync_date', '' );
			update_option( SKNG_PLUGIN_NAME . '_api_limitation', 50 );
			update_option( SKNG_PLUGIN_NAME . '_customer_identity', "id" );
			update_option( SKNG_PLUGIN_NAME . '_sync_mode', "sync" );
		}

		if ( class_exists( "Skng_Sokan_logger" ) ) {
			$logger = new Skng_Sokan_logger();
			$logger->activate( true );
		}

		flush_rewrite_rules();
	}

	/**
	 * called When admin deactivates the plugin
	 * rewrite saved rules and disable daily sync
	 * @return void
	 * @since 1.0.0
	 */
	function deactivate() {
		$timestamp = wp_next_scheduled( 'skng_auto_sync' );
		wp_unschedule_event( $timestamp, 'skng_auto_sync' );

		if ( class_exists( "Skng_Sokan_logger" ) ) {
			$logger = new Skng_Sokan_logger();
			$logger->activate( false );
		}

		flush_rewrite_rules();
	}

	/**
	 * Add hooks to track changes in data
	 * @return Void
	 * @since 1.0.0
	 */
	function add_hooks() {
		$complete_status = str_replace( 'wc-', '', get_option( SKNG_PLUGIN_NAME . '_sale_status' ) );
		$refund_status   = str_replace( 'wc-', '', get_option( SKNG_PLUGIN_NAME . '_refunded_status' ) );
		add_action( 'woocommerce_order_status_' . $complete_status, array( $this, 'order_status_hook' ), 10, 1 );
		add_action( 'woocommerce_order_status_' . $refund_status, array( $this, 'order_status_hook' ), 10, 1 );
	}

	/**
	 * called when any woocommerce order added or changes
	 * and schedule new sync job for 40 second later run in background
	 * @since 1.0.0
	 * param is new or edited order id
	 */
	function order_status_hook( $order_id ) {
		if ( get_option( SKNG_PLUGIN_NAME . '_token' ) != " " ) {
			if ( get_option( SKNG_PLUGIN_NAME . '_sync_mode' ) == 'async' ) {
				wp_schedule_single_event( time() + 40, 'skng_auto_sync' );
			} else {
				$sokanAjax = false;
				require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-api.php';
				require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-db.php';
				require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-logger.php';
				require_once SKNG_PLUGIN_PATH . 'include/custom/skng-custom.php';
				require_once SKNG_PLUGIN_PATH . 'job/sync_data.php';
			}
		}

	}

	/**
	 * create log table if not exist
	 * this function called once on installing plugin
	 * @since 1.0.0
	 */
	function create_log_table() {

		global $wpdb;
		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$log_table_name = "{$wpdb->prefix}" . SKNG_PLUGIN_NAME . "_logs";

		$wpdb->query( " CREATE TABLE IF NOT EXISTS `$log_table_name` (
                    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                    `entity_id` varchar(100) unique ,
                    `error` TEXT(2000)   ,
                    `payload` TEXT(2000) ,
                    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    
                    PRIMARY KEY (`id`)
                )  $collate
            " );
	}

	/**
	 * delete old table that in new version doest exist
	 * this function called once on installing plugin
	 * @since 1.1.0
	 */
	function drop_old_tables() {

		if ( is_admin() ) {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_logs" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_brands" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_customers" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_invoieces" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_productcats" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_products" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_region" );
		}
	}
}

if ( class_exists( 'Skng_SokanIntegration' ) ) {
	$Integration = new Skng_SokanIntegration();
	$Integration->register();
	register_activation_hook( __FILE__, [ $Integration, 'activate' ] );
	register_deactivation_hook( __FILE__, [ $Integration, 'deactivate' ] );
}

add_action( 'wp_ajax_skng_sokan_sync', 'skng_sokan_sync' );

/**
 * ajax controller
 * get ajax request triggered in sync page and handle
 * @return Void
 * @since 1.0.0
 */
function skng_sokan_sync() {

	if ( ! isset( $_POST['item'] ) ) {
		wp_die();
	}

	$item = sanitize_text_field( $_POST['item'] );

	if ( $item == 'sync' ) {
		require_once SKNG_PLUGIN_PATH . "job/sync_data.php";
	} elseif ( $item == 'custom_code' and class_exists( 'Skng_Sokan_logger' ) ) {
		( new Skng_Sokan_api() )->getCustomCode();
	}
	wp_die();
}
