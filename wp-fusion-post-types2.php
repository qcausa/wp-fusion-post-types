<?php
/*
Plugin Name: WP Fusion - Post Types Integration
Description: Boostrap for adding a new plugin integration module to WP Fusion
Plugin URI: https://wpfusion.com/
Version: 1.1
Author: Very Good Plugins
Author URI: https://verygoodplugins.com/
*/

/**
 * @copyright Copyright (c) 2016. All rights reserved.
 *
 * @license   Released under the GPL license http://www.opensource.org/licenses/gpl-license.php
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

define( 'WPF_EC_VERSION', '1.10' );

// deny direct access
if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}


final class WP_Fusion_PostTypes {

	/** Singleton *************************************************************/

	/**
	 * @var WP_Fusion The one true WP_Fusion
	 * @since 1.0
	 */

	private static $instance;


	/**
	 * The integrations handler instance variable
	 *
	 * @var WPF_Integrations
	 * @since 1.0
	 */

	public $integrations;


	/**
	 * Manages configured CRMs
	 *
	 * @var WPF_CRMS
	 * @since 1.0
	 */

	public $crm_base;


	/**
	 * Access to the currently selected CRM
	 *
	 * @var crm
	 * @since 1.0
	 */

	public $crm;


	/**
	 * The settings instance variable
	 *
	 * @var WP_Fusion_Settings
	 * @since 1.0
	 */

	public $settings;


	/**
	 * Main Wp_Fusion Instance
	 *
	 * Insures that only one instance of WP_Fusion exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.0
	 * @static
	 * @staticvar array $instance
	 * @return The one true WP_Fusion
	 */

	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WP_Fusion_PostTypes ) ) {
			// BugFu::log("create new WP_Fusion_PostTypes instance");

			self::$instance = new WP_Fusion_PostTypes();
			self::$instance->setup_constants();
			self::$instance->includes();

			// Hook into wpf_crm_loaded to register extension functions
            //add_action('wp_fusion_init_crm', array(self::$instance, 'register_extension_functions'), 20);

			if ( ! is_wp_error( self::$instance->check_install() ) ) {
				// BugFu::log("create new WPF_PT_CRM_Base instance");

				self::$instance->crm_base = new WPF_PT_CRM_Base();
				self::$instance->crm      = self::$instance->crm_base->crm;

				self::$instance->integrations_includes();
				// self::$instance->updater();

			} else {

				add_action( 'admin_notices', array( self::$instance, 'admin_notices' ) );

			}
		}

		return self::$instance;

	}

	public function register_extension_functions($crm) {
        // if (!isset($crm->custom_methods)) {
        //     $crm->custom_methods = array();
        // }
        // $crm->custom_methods['extension1_function'] = array($this, 'extension1_function');
        // BugFu::log('extension1_function registered.');
        //BugFu::log('CRM object after registering function: ' . print_r($crm, true));
    }

	public function extension1_function() {
        // Your function logic here
		BugFu::log('Hello from Extension 1');
        
    }

	


	/**
	 * Checks if WP Fusion plugin is active, configured correctly, and if it supports the
	 * user chosen CRM. If not, returns error message defining failure.
	 *
	 * @access public
	 * @return mixed True on success, WP_Error on error
	 */

	public function check_install() {

		if ( ! function_exists( 'wp_fusion' ) || ! is_object( wp_fusion()->crm ) ) {
			return new WP_Error( 'error', 'WP Fusion is required for "WP Fusion - Ecommerce Addon" to work.' );
		}

		$crms = self::$instance->get_crms();
		$slug = wp_fusion()->crm->slug;

		if ( empty( $slug ) ) {
			return new WP_Error( 'error', 'WP Fusion must be connected to a CRM for "WP Fusion - Post Types Addon" to work.' );
		}

		if ( ! array_key_exists( $slug, $crms ) ) {
			return new WP_Error( 'error', "We're sorry but the WP Fusion Ecommerce addon does not currently support " . wp_fusion()->crm->name . '.' );
		}

		return true;
	}


	/**
	 * Returns error message and deactivates plugin when error returned.
	 *
	 * @access public
	 * @return mixed error message.
	 */

	public function admin_notices() {

		$return = self::$instance->check_install(); ?>

		<div class="notice notice-error">
			<p><?php echo $return->get_error_message(); ?></p>
		</div>

		<?php

	}



	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @access protected
	 * @return void
	 */

	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-fusion' ), '1.6' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @access protected
	 * @return void
	 */

	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-fusion' ), '1.6' );
	}

	/**
	 * Setup plugin constants
	 *
	 * @access private
	 * @return void
	 */

	private function setup_constants() {

		if ( ! defined( 'WPF_EC_DIR_PATH' ) ) {
			define( 'WPF_EC_DIR_PATH', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'WPF_EC_PLUGIN_PATH' ) ) {
			define( 'WPF_EC_PLUGIN_PATH', plugin_basename( __FILE__ ) );
		}

		if ( ! defined( 'WPF_EC_DIR_URL' ) ) {
			define( 'WPF_EC_DIR_URL', plugin_dir_url( __FILE__ ) );
		}

	}


	/**
	 * Defines default supported plugin integrations
	 *
	 * @access private
	 * @return array Integrations
	 */

	public function get_integrations() {

		return apply_filters(
			'wpf_ec_integrations', array(
				'cpt'            => 'WP_Post_Type',
				'woocommerce'    => 'WooCommerce',
				'rcp'            => 'RCP_Capabilities',
				'lifterlms'      => 'LifterLMS',
				'event-espresso' => 'EE_Base',
			)
		);

	}

	/**
	 * Defines supported CRMs
	 *
	 * @access private
	 * @return array CRMS
	 */

	public function get_crms() {

		return apply_filters(
			'wpf_ec_crms', array(
				'infusionsoft'   => 'WPF_EC_Infusionsoft_iSDK',
				'activecampaign' => 'WPF_EC_ActiveCampaign',
				'ontraport'      => 'WPF_EC_Ontraport',
				'drip'           => 'WPF_EC_Drip',
				'agilecrm'       => 'WPF_EC_AgileCRM',
				'hubspot'        => 'WPF_EC_Hubspot',
				'monday'         => 'WPF_PT_Monday',
			)
		);

	}

	/**
	 * Include required files
	 *
	 * @access private
	 * @return void
	 */

	private function includes() {
		// BugFu::log("includes init");

		

		// Autoload CRMs
		require_once WPF_EC_DIR_PATH . 'includes/crms/class-base.php';

		// Load the methods registration class
		require_once WPF_EC_DIR_PATH . 'includes/class-post-type-methods.php';

		if ( is_admin() ) {
			// require_once WPF_EC_DIR_PATH . 'includes/admin/class-notices.php';
			require_once WPF_EC_DIR_PATH . 'includes/admin/admin-functions.php';
			//require_once WPF_EC_DIR_PATH . 'includes/admin/class-upgrades.php';
		}

		// require_once WPF_EC_DIR_PATH . 'includes/admin-functions.php';

		foreach ( $this->get_crms() as $filename => $integration ) {
			if ( file_exists( WPF_EC_DIR_PATH . 'includes/crms/' . $filename . '/class-' . $filename . '.php' ) ) {
				require_once WPF_EC_DIR_PATH . 'includes/crms/' . $filename . '/class-' . $filename . '.php';
			}
		}

	}

	/**
	 * Includes classes applicable for after the connection is configured
	 *
	 * @access private
	 * @return void
	 */

	private function integrations_includes() {
		// Autoload integrations
		require_once WPF_EC_DIR_PATH . 'includes/integrations/class-base.php';

		// Store integrations for public access
		self::$instance->integrations = new stdClass();

		foreach ( $this->get_integrations() as $filename => $dependency_class ) {

			if ( class_exists( $dependency_class ) ) {

				if ( file_exists( WPF_EC_DIR_PATH . 'includes/integrations/class-' . $filename . '.php' ) ) {
					
					require_once WPF_EC_DIR_PATH . 'includes/integrations/class-' . $filename . '.php';
				}
			}
		}

	}

	/**
	 * Set up EDD updater
	 *
	 * @access public
	 * @return void
	 */

	// public function updater() {

	// 	if ( ! is_admin() ) {
	// 		return;
	// 	}

	// 	$license_status = wp_fusion()->settings->get( 'license_status' );
	// 	$license_key    = wp_fusion()->settings->get( 'license_key' );

	// 	if ( $license_status == 'valid' ) {

	// 		// setup the updater
	// 		$edd_updater = new WPF_Plugin_Updater(
	// 			WPF_STORE_URL, __FILE__, array(
	// 				'version' => WPF_EC_VERSION,
	// 				'license' => $license_key,
	// 				'item_id' => 2762,
	// 				'author'  => 'Very Good Plugins',
	// 			)
	// 		);

	// 	} else {

	// 		global $pagenow;

	// 		if ( 'plugins.php' === $pagenow ) {
	// 			add_action( 'after_plugin_row_' . WPF_EC_PLUGIN_PATH, array( wp_fusion(), 'wpf_update_message' ), 10, 3 );
	// 		}
	// 	}

	// }


}

/**
 * The main function responsible for returning the one true WP Fusion
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $wpf = WP Fusion(); ?>
 *
 * @return object The one true WP Fusion Instance
 */

function wp_fusion_postTypes() {

	return WP_Fusion_PostTypes::instance();

}

add_action( 'plugins_loaded', 'wp_fusion_postTypes', 100 );

