<?php
/**
 * Plugin Name: WP Fusion - Custom Tab
 * Description: Adds a custom settings tab to WP Fusion
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wp-fusion-custom-tab
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPF_CT_VERSION', '1.0.0' );
define( 'WPF_CT_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPF_CT_DIR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Gets the instance of WPF_Custom_Tab
 *
 * @since  1.0.0
 *
 * @return WPF_Custom_Tab
 */
function wp_fusion_custom_tab() {
    return WPF_Custom_Tab::instance();
}

/**
 * Initialize the plugin
 */
function wp_fusion_custom_tab_init() {
    // Check if WP Fusion is active
    if ( ! function_exists('wp_fusion') ) {
        return;
    }

    // Include required files
    require_once WPF_CT_DIR_PATH . 'includes/class-custom-tab.php';
    require_once WPF_CT_DIR_PATH . 'class-post-fields.php';

    // Initialize the plugin
    wp_fusion_custom_tab();
}
add_action( 'plugins_loaded', 'wp_fusion_custom_tab_init', 15 ); // Priority 15 to make sure WP Fusion is loaded first