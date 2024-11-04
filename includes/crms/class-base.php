<?php

class WPF_PT_CRM_Base {

	/**
	 * Contains the class object for the currently active CRM
	 *
	 * @var api
	 * @since 1.0
	 */

	public $crm;


	public function __construct() {

		// WPF_Post_Type_Methods::instance()->add_method('map_post_meta_fields', array($this, 'map_post_meta_fields'));

		$this->init(); // initiate the CRM and set $this->crm.

		$configured_crms = wp_fusion_postTypes()->get_crms();
		//BugFu::log($configured_crms);

		foreach ( $configured_crms as $slug => $classname ) {

			if ( class_exists( $classname ) ) {
				// BugFu::log("class_exists " . $classname);

				if ( wp_fusion()->crm->slug == $slug ) {

					$crm       = new $classname();
					$this->crm = $crm;
					$this->crm->init();

				}
			}
		}

		

        // BugFu::log(wp_fusion()->crm);

	}

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */

	 private function init() {
		// BugFu::log('class-base init');

		$this->supports = array();

		//add_filter( 'wpf_configure_sections', array( $this, 'configure_sections' ), 20, 2 );
		//add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		// add_filter( 'validate_field_deals_enabled', array( $this, 'validate_deals_enabled' ), 10, 2 );
		// add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );

		// add_action( 'wpf_sync', array( $this, 'sync' ) );

		// // Sync data on first run
		// $pipelines_stages = wp_fusion()->settings->get( 'ac_pipelines_stages' );

		// if ( $pipelines_stages != null && ! is_array( $pipelines_stages ) ) {
		// 	$this->sync();
		// }

        // Register dynamic actions for all post types
		$this->register_dynamic_actions();

	}

	public function register_dynamic_actions() {
		$post_types = get_post_types(array('public' => true), 'objects');
		$options = get_option('wpf_options');
		
		foreach ($post_types as $post_type) {
			if (isset($options['post_type_sync_' . $post_type->name]) && !empty($options['post_type_sync_' . $post_type->name])) {

				// WPF Post Type Fields Table Rendering
				// add_action("show_field_postType_{$post_type->name}_fields", array($this, 'show_field_postType_fields'), 15, 2);
				// add_action("show_field_postType_{$post_type->name}_fields_begin", array($this, 'show_field_postType_fields_begin'), 15, 2);
				// add_filter( "wpf_{$post_type->name}_meta_fields", array( $this, "prepare_{$post_type->name}_meta_fields" ), 60 );

				// add_filter( "wpf_set_setting_post_type_fields_{$post_type->name}", array( $this, "handle_post_type_fields_update" ), 10, 2 );

				// Hook into post type updates only for post types with a configured list
				// add_action( "save_post_{$post_type->name}", array( $this, 'postType_updated' ), 10 );

				// Load the field mapping into memory.
				// BugFu::log('load post fields');
				$this->{$post_type->name . '_fields'} = wpf_get_option( 'postType_' . $post_type->name . '_fields', array() );
				// BugFu::log($this->{'post_fields'});
			}
		}
	}



	public function test() {
		BugFu::log('Hello from WPF_PT_CRM_Base');
		return 'Hello from WPF_PT_CRM_Base';
	}


	/**
	 * Maps local fields to CRM field names
	 *
	 * @access public
	 * @return array
	 */

	 public function map_post_meta_fields( $user_meta, $post_type ) {
		BugFu::log("WPF_PT_CRM_Base map_post_meta_fields init");
		BugFu::log($user_meta);
		BugFu::log($post_type);

		if ( ! is_array( $user_meta ) || empty( $user_meta ) ) {
			BugFu::log("return empty");
			return array();
		}

		$update_data = array();

		// Lists pass straight through unless mapped.

		if ( ! empty( $user_meta['lists'] ) ) {
			$update_data['lists'] = $user_meta['lists'];
		}

		BugFu::log($this->{'post_fields'});

		foreach ( $this->{$post_type . '_fields'} as $field => $field_data ) {
			BugFu::log($field);

			if ( empty( $field_data['active'] ) || empty( $field_data['crm_field'] ) ) {
				continue;
			}
			//BugFu::log("map_meta_fields PASS 1");

			// Don't send add_tag_ fields to the CRM as fields.
			if ( strpos( $field_data['crm_field'], 'add_tag_' ) !== false ) {
				continue;
			}

			// If field exists in form and sync is active.
			if ( array_key_exists( $field, $user_meta ) ) {

				if ( empty( $field_data['type'] ) ) {
					$field_data['type'] = 'text';
				}

				$field_data['crm_field'] = strval( $field_data['crm_field'] );

				if ( 'datepicker' === $field_data['type'] ) {

					// We'd been using date and datepicker interchangeably up until
					// 3.38.11, which is confusing. We'll just use "date" going forward.

					$field_data['type'] = 'date';
				}

				/**
				 * Format field value.
				 *
				 * @since 1.0.0
				 *
				 * @link  https://wpfusion.com/documentation/filters/wpf_format_field_value/
				 *
				 * @param mixed  $value     The field value.
				 * @param string $type      The field type.
				 * @param string $crm_field The field ID in the CRM.
				 */

				$value = apply_filters( 'wpf_format_field_value', $user_meta[ $field ], $field_data['type'], $field_data['crm_field'] );

				if ( 'raw' === $field_data['type'] ) {

					// Allow overriding the empty() check by setting the field type to raw.

					$update_data[ $field_data['crm_field'] ] = $value;

				} elseif ( is_null( $value ) ) {

					// Allow overriding empty() check by returning null from wpf_format_field_value.

					$update_data[ $field_data['crm_field'] ] = '';

				} elseif ( false === $value ) {

					// Some CRMs (i.e. Sendinblue) need to be able to sync false as a value to clear checkboxes.

					$update_data[ $field_data['crm_field'] ] = false;

				} elseif ( 0 === $value || '0' === $value ) {

					$update_data[ $field_data['crm_field'] ] = 0;

				} elseif ( empty( $value ) && ! empty( $user_meta[ $field ] ) && 'date' === $field_data['type'] ) {

					// Date conversion failed.
					wpf_log( 'notice', wpf_get_current_user_id(), 'Failed to create timestamp from value <code>' . $user_meta[ $field ] . '</code>. Try setting the field type to <code>text</code> instead, or fixing the format of the input date.' );

				} elseif ( ! empty( $value ) ) {

					$update_data[ $field_data['crm_field'] ] = $value;

				}
			}
		}

		$update_data = apply_filters( 'wpf_map_post_meta_fields', $update_data, $user_meta );

		return $update_data;

	}
	
	

	/**
	 * Adds Addons tab if not already present
	 *
	 * @access public
	 * @return void
	 */

    //  public function configure_sections( $page, $options ) {

	// 	$post_types = get_post_types( array( 'public' => true ), 'objects' );

	// 	foreach ( $post_types as $post_type ) {
    //         $board_id = wpf_get_option( 'post_type_sync_' . $post_type->name );
    //         if ( $board_id ) {
	// 			$page['sections'] = wp_fusion()->settings->insert_setting_after(
	// 				'contact-fields',
	// 				$page['sections'],
	// 				array(
	// 					$post_type->name . '-fields' => sprintf( __( '%s Fields', 'wp-fusion' ), $post_type->label ),
	// 					),
	// 			);
    //             // $page['sections'][ $post_type->name . '_fields' ] = sprintf( __( '%s Fields', 'wp-fusion' ), $post_type->label ) . ' →';
    //         }
    //     }

	// 	$page['sections'] = wp_fusion()->settings->insert_setting_after(
	// 		'advanced',
	// 		$page['sections'],
	// 		array(
	// 			'post-types' => 'Post Type Sync',
	// 			),
	// 	);

		
    
    //     return $page;
    // }

	// /**
	//  * Add fields to settings page
	//  *
	//  * @access public
	//  * @return array Settings
	//  */

    //  public function register_settings( $settings, $options ) {
	// 	$settings['post_type_sync_header'] = array(
	// 		'title'   => __( 'Post Type Sync', 'wp-fusion' ),
	// 		'type'    => 'heading',
	// 		'section' => 'post-types',
	// 	);

	// 	$exclude_post_types = array('revision', 'nav_menu_item', 'page'); // Add post types you want to exclude

	// 	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	// 	$boards = wp_fusion()->settings->get( 'available_lists', array() );
	// 	// BugFu::log($boards);

	// 	foreach ( $post_types as $post_type ) {
	// 		if (in_array($post_type->name, $exclude_post_types)) {
	// 			continue; // Skip excluded post types
	// 		}

	// 		// Define the custom type for the post type with a sync button
	// 		$settings['post_type_sync_' . $post_type->name] = array(
	// 			'title'   => $post_type->label,
	// 			'type'    => 'sync_button',
	// 			'section' => 'post-types',
	// 			'choices' => $boards,
	// 			'attributes'  => array(
	// 				'data-post_type' => $post_type->name,
	// 				'data-nonce'     => wp_create_nonce('wpf_sync_post_type_fields'),
	// 			),
	// 			'post_fields' => array( 'post_type_sync_' . $post_type->name ),
	// 		);
	// 	}

    //     $settings['postType_post_fields'] = array(
	// 		'title'   => __( 'Post Fields', 'wp-fusion-lite' ),
	// 		'std'     => array(),
	// 		'type'    => 'post-fields',
	// 		'section' => 'post-fields',
	// 		'choices' => array(),
	// 	);

	// 	return $settings;
	// }

}

// Ensure the base CRM class is extended properly
if (class_exists('WPF_Post_Type_Methods')) {
    new WPF_PT_CRM_Base();
}
	
