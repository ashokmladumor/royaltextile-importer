<?php
/**
* Plugin Name: Royal Textile Importer
* Description: Add extra functionality to import product remotly
* Version: 1.0
* Author: Royal Textile
* Text Domain: rt-importer
* Author URI: www.royaltextile.nl
**/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define RT_PLUGIN_FILE.
if ( ! defined( 'RT_PLUGIN_FILE' ) ) {
    define( 'RT_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'ALLOW_UNFILTERED_UPLOADS' ) ) {
	define('ALLOW_UNFILTERED_UPLOADS', true);
}
define( 'RT_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'RT_VERSION', '1.0.0' );
define( 'RT_TEMPLATE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
define( 'RT_PLUGIN_PATH', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'RT_MAIN_FILE', __FILE__ );
define( 'RT_ABSPATH', dirname( __FILE__ ) . '/' );
define( 'RT_CSV_DOWNLOADS', WP_CONTENT_DIR . '/uploads/rt-importer' );
define( 'RT_DOWNLOADED_FILE_NAME', 'rt-importer-downloaded.csv' );

/**
 * Check if WooCommerce is active
 **/
//add_action('admin_init', 'rt_diactivate');
function rt_diactivate() {
	if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {
	    
	}
}

function general_admin_notice(){
    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {

         echo '<div class="notice notice-warning is-dismissible">
             <p><strong>'.sprintf( __( 'Royal textile impoter requires the following plugin: %s</strong>', 'woocommerce-additional-variation-images' ), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>' ).'</p>
         </div>';
    }
}
add_action('admin_notices', 'general_admin_notice');

// Include the main WooCommerce class.
if ( ! class_exists( 'RT_Importer', false ) && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {
	include_once RT_ABSPATH . '/includes/class-rt-import.php';

    add_action( 'admin_menu', 'rt_admin_menus', 50);
    function rt_admin_menus() {
        $obj = new RT_Importer;
        $page_title = __('Royal Textile', 'rt-importer');
        $menu_title = __('Royal Textile', 'rt-importer');
        add_menu_page( $page_title, $menu_title,  'manage_options', 'rt-settigns', array($obj, 'rt_settings_html'), '',55);
        add_submenu_page(
             'rt-settigns',
             __('Settings', 'lead-rev'),
             __('Settings', 'rt-importer'),
             'manage_options',
             'rt-settigns',
             array($obj, 'rt_settings_html')
        );
        add_submenu_page(
           'rt-settigns',
           __('Mapping Tool', 'lead-rev'),
           __('Mapping Tool', 'lead-rev'),
           'manage_options',
           'rt-mapping-tool',
           array($obj, 'rt_mapping_tool_html')
        );
    }
}