<?php
/**
 * @package SokanIntegration
 */

defined( 'ABSPATH' ) or die( 'No access!' );

/**
 * delete all temp table and saved option when plugin being uninstalled
 * @since 1.0.0
 */
global $wpdb;

require_once SKNG_PLUGIN_PATH . 'include/classes/class-skng-logger.php';

if ( class_exists( "Skng_Sokan_logger" ) ) {
	$logger = new Skng_Sokan_logger();
	$logger->exception( "پلاگین حذف شد" );
}

$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%sokan%';" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sokan_integration_logs" );
wp_cache_flush();
