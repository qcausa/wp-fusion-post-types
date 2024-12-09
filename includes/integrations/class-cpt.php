<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Include the post fields class
require_once dirname( __FILE__ ) . '/class-post-fields.php';

/**
 * Main WP Fusion Custom Tab class.
 *
 * @since 1.0.0
 */

class WPF_Custom_Tab {

    /** Singleton instance */
    private static $instance = null;

    /** Selected post types */
    private $selected_post_types = array();

    /**
     * Get active instance
     *
     * @access public
     * @return object Instance of WPF_Custom_Tab
     */
    public static function instance() {

        if ( empty( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Load option group
		$this->option_group = 'wpf_options';
		$this->options      = get_option( 'wpf_options', array() );
        // Store selected post types
        $this->selected_post_types = wp_fusion()->settings->get( 'custom_post_types', array() );

        $slug = wpf_get_option( 'crm' );
        if ( ! defined( 'WPF_ITEM_ID_META_KEY' ) ) {
			define( 'WPF_ITEM_ID_META_KEY', $slug . '_item_id' );
		}

        // Initialize custom JS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 20 );

        // Add the custom tab to WP Fusion settings
        add_filter( 'wpf_configure_sections', array( $this, 'add_custom_sections' ) );
        add_filter( 'wpf_configure_settings', array( $this, 'add_custom_settings' ) );
        
        // Add custom field type handler - changed from wpf_initialize_options
        //add_filter( 'wpf_meta_field_types', array( $this, 'add_field_type' ), 10, 1 );
        //add_action( 'show_field_post_fields_begin', array( 'WPF_Post_Fields', 'show_field_post_fields_begin' ), 10, 2 );
        //add_action( 'show_field_post_fields', array( 'WPF_Post_Fields', 'show_field_post_fields' ), 10, 2 );
        //add_action( 'show_field_page_fields_begin', array( 'WPF_Post_Fields', 'show_field_post_fields_begin' ), 10, 2 );
        //add_action( 'show_field_page_fields', array( 'WPF_Post_Fields', 'show_field_post_fields' ), 10, 2 );

        // Post type sync button rendering
		add_action( 'show_field_sync_button', array( $this, 'show_field_sync_button' ), 15, 2 );
        // Post type sync button AJAX method
		add_action('wp_ajax_wpf_sync_post_type_fields', array($this, 'ajax_sync_post_type_fields'));

        add_filter( 'wpf_post_meta_fields', array( $this, 'prepare_post_meta_fields' ), 10, 2 );


        // Add the post types configuration filter
        add_filter( 'wpf_configure_setting_custom_post_types', array( $this, 'configure_setting_custom_post_types' ), 10, 2 );

        // Add filter for saving post type fields to custom option
        foreach ( $this->selected_post_types as $post_type ) {
            add_filter( 'wpf_set_setting_crm_' . $post_type . '_fields', function( $value ) use ( $post_type ) {
                return $this->save_crm_post_type_fields( $value, $post_type );
            }, 5, 1 );

            add_filter( 'wpf_get_setting_crm_' . $post_type . '_fields', function( $value ) use ( $post_type ) {
                return $this->handle_get_crm_post_fields( $value, $post_type );
            }, 5, 1 );
        
            add_action( 'show_field_' . $post_type . '_fields_begin', function( $args = null, $options = null ) use ( $post_type ) {
                WPF_Post_Fields::show_field_post_fields_begin( $args, $options, $post_type );
            }, 10, 2 );
        
            add_action( 'show_field_' . $post_type . '_fields', function( $args = null, $options = null ) use ( $post_type ) {
                WPF_Post_Fields::show_field_post_fields( $args, $options, $post_type );
            }, 10, 2 );
            

            // Load the field mapping into memory.
            $this->{$post_type . '_fields'} = wpf_get_option( $post_type . '_fields', array() );
        }
        

        //add_filter( 'wpf_get_setting_crm_post_fields', array( $this, 'handle_get_crm_post_fields' ), 15 );
        //add_filter( 'wpf_get_setting_crm_tribe_events_fields', array( $this, 'handle_get_crm_post_fields' ), 15 );

        // Add filter for resetting options
        add_action( 'wpf_resetting_options', array( $this, 'reset_plugin_options' ) );
        

        // Add validation
        add_filter( 'validate_field_post_fields', array( $this, 'validate_field_post_fields' ), 10, 3 );
        add_filter( 'validate_field_tribe_events_fields', array( $this, 'validate_field_tribe_events_fields' ), 10, 3 );
        add_filter( 'validate_field_custom_reset', array( $this, 'validate_field_custom_reset' ), 10, 2 );


        // Post type actions
		add_action( 'save_post', array( $this, 'post_updated' ), 100, 3 );
        add_action( 'tribe_events_updated', array( $this, 'tribe_events_updated' ), 10, 3 );

        // hook into map_meta_fields, which is usually just for user meta mapping, and override the $update_data for custom post types
        add_filter( 'wpf_map_meta_fields', array( $this, 'wpf_cpt_map_meta_fields' ), 10, 2 );

        // Add filter for handling timeline fields
        add_filter('wpf_monday_sync_post_type_fields', array($this, 'handle_timeline_fields'), 10, 3);
    }
    

    /**
     * Register Admin Scripts
     *
     */

    public function admin_scripts() {
		// Define the path to the JavaScript file using the constant
		$script_url = WPF_CT_DIR_URL . 'assets/js/wpf-post-types.js';
		
		// Enqueue the script
		wp_enqueue_script( 'wpf-post-types', $script_url, array('jquery'), '1.0', true );
	}



    /**
     * Adds custom sections to WP Fusion settings
     *
     * @param array $page The existing sections
     * @return array Modified sections
     */
    public function add_custom_sections( $page ) {
        // First add our main custom tabs
        $page['sections'] = wp_fusion()->settings->insert_setting_after(
            'advanced',
            $page['sections'],
            array(
                'custom2'  => 'Post Type Sync',
            )
        );

        // Now check for post types with connected boards
        if ( ! empty( $this->selected_post_types ) ) {
            foreach ( $this->selected_post_types as $post_type ) {
                // Check if this post type has a board connected
                $setting_value = wp_fusion()->settings->get( "post_type_sync_{$post_type}" );
                
                if ( ! empty( $setting_value ) ) {
                    $post_type_object = get_post_type_object( $post_type );
                    if ( $post_type_object ) {
                        // Add a new section for this post type
                        $page['sections'][ "{$post_type}-fields" ] = $post_type_object->labels->name;
                    }
                }
            }
        }

        return $page;
    }

    /**
     * Adds custom settings fields
     *
     * @param array $settings The existing settings
     * @return array Modified settings
     */
    public function add_custom_settings( $settings ) {
           
        // Add fields to Custom tab
        // $settings['post_fields'] = array(
        //     'title'   => __( 'Contact Fields', 'wp-fusion-lite' ),
        //     'std'     => array(),
        //     'type'    => 'post_fields',
        //     'section' => 'custom',
        //     'choices' => array(),
        // );

        // Add fields to Custom 2 tab
        $settings['custom_post_types'] = array(
            'title'       => __( 'Custom Post Types', 'wp-fusion-lite' ),
            'desc'        => __( 'Select which post types to enable custom functionality for.', 'wp-fusion-lite' ),
            'type'        => 'multi_select',
            'section'     => 'custom2',
            'placeholder' => __( 'Select post types', 'wp-fusion-lite' ),
        );

        // Get available lists from WP Fusion
        $available_lists = wp_fusion()->settings->get( 'available_lists', array() );

        // Add a select field for each selected post type
        if ( ! empty( $this->selected_post_types ) ) {
            foreach ( $this->selected_post_types as $post_type ) {
                $post_type_object = get_post_type_object( $post_type );
                
                // if ( ! $post_type_object ) {
                //BugFu::log("post_type_object not found for " . $post_type);
                //     continue;
                // }

                $label = ucwords( str_replace( '_', ' ', $post_type ) );

                // Define the custom type for the post type with a sync button
                $settings["post_type_sync_{$post_type}"] = array(
                    'title'       => sprintf( __( '%s Link', 'wp-fusion-lite' ), $label ),
                    'type'    => 'sync_button',
                    'section' => 'custom2',
                    'choices'     => $available_lists,
                    'placeholder' => __( 'Select a list', 'wp-fusion-lite' ),
                    'attributes'  => array(
                        'data-post_type' => $post_type,
                        'data-nonce'     => wp_create_nonce('wpf_sync_post_type_fields'),
                    ),
                    'post_fields' => array( 'post_type_sync_' . $post_type ),
                );

                // Check if this post type has a board connected
                $setting_value = wp_fusion()->settings->get( "post_type_sync_{$post_type}" );
                //BugFu::log($setting_value);
                
                if ( ! empty( $setting_value ) ) {
                    // Add post fields table to the post type's custom tab
                    $settings["{$post_type}_fields"] = array(
                        'title'   => sprintf( __( '%s Fields', 'wp-fusion-lite' ), $label ),
                        'desc'    => sprintf( __( 'Configure field mapping for %s', 'wp-fusion-lite' ), $label ),
                        'std'     => array(),
                        'type'    => "{$post_type}-fields",
                        'section' => "{$post_type}-fields",
                        'choices' => array(),
                    );

                    
                }
            }
        }

        // Add reset option at the bottom of Custom 2 tab
        $settings['custom_reset'] = array(
            'title'   => __( 'Reset Settings', 'wp-fusion-lite' ),
            'desc'    => __( 'Check this box and click "Save Changes" below to reset all post type sync settings.', 'wp-fusion-lite' ),
            'type'    => 'checkbox',
            'section' => 'custom2',
        );

        return $settings;
    }

    /**
     * Add custom field type
     *
     * @param array $field_types The registered field types
     * @return array Modified field types
     */
    public function add_field_type( $field_types ) {
        // Add field types for each post type's fields table
        $field_types['post_fields'] = array(
            'title'    => __( 'Post Fields', 'wp-fusion' ),
            'callback' => array( 'WPF_Post_Fields', 'show_field_post_fields' )
        );
        
        return $field_types;
    }

    /**
     * Set the available post types for the custom field.
     *
     * @since 1.0.0
     *
     * @param array $setting  The setting parameters.
     * @param array $options  The options in the DB.
     * @return array The setting parameters.
     */
    public function configure_setting_custom_post_types( $setting, $options ) {

        $post_types = get_post_types( array( 'public' => true ) );

        unset( $post_types['attachment'] );
        unset( $post_types['revision'] );

        $setting['choices'] = $post_types;

        return $setting;
    }

    /**
     * Filters out internal WordPress fields from showing up in syncable meta fields list and sets labels and types for built in fields
     *
     * @since 1.0
     * @return array
     */
    public function prepare_post_meta_fields( $meta_fields, $post_type ) {
        // Load the reference of standard WP field names and types.
        include __DIR__ . '/wordpress-post-fields.php';
    
        // Sets field types and labels for all built in fields.
        foreach ( $wp_fields as $key => $data ) {
            if ( ! isset( $data['group'] ) ) {
                $data['group'] = 'wp';
            }
            $meta_fields[ $key ] = $data;
        }
    
        // Get any additional wp_usermeta data.
        $all_fields = $this->get_post_meta_keys($post_type);
        //BugFu::log($all_fields);
       
    
        // Some fields we can exclude via partials.
        $exclude_fields_partials = array(
            'metaboxhidden_',
            'meta-box-order_',
            'screen_layout_',
            'closedpostboxes_',
            '_contact_id',
            '_tags',
        );
    
        foreach ( $exclude_fields_partials as $partial ) {
            foreach ( $all_fields as $field => $data ) {
                if ( strpos( $field, $partial ) !== false ) {
                    unset( $all_fields[ $field ] );
                }
            }
        }
    
        // Sets field types and labels for all built in fields.
        foreach ( $all_fields as $key ) {
            // Skip hidden fields.
            // if ( substr( $key, 0, 1 ) === '_' || substr( $key, 0, 5 ) === 'hide_' || substr( $key, 0, 3 ) === 'wp_' ) {
            //     continue;
            // }
            if ( substr( $key, 0, 5 ) === 'hide_' || substr( $key, 0, 3 ) === 'wp_' ) {
                continue;
            }
    
            if ( ! isset( $meta_fields[ $key ] ) ) {
                $meta_fields[ $key ] = array(
                    'label' => ucwords( str_replace( '_', ' ', $key ) ),
                    'group' => 'extra',
                    'type'  => 'text',
                );
            }
        }
    
        return $meta_fields;
    }

    function get_post_meta_keys( $post_type ) {
        //BugFu::log("get_post_meta_keys init");
        global $wpdb;
    
        // Get the standard fields dynamically from the wp_posts table
        $standard_fields_query = "SHOW COLUMNS FROM {$wpdb->posts}";
        $columns = $wpdb->get_col($standard_fields_query, 0);
    
        // Filter columns to remove any unnecessary fields (like IDs)
        $excluded_columns = array('ID');
        $standard_fields = array_diff($columns, $excluded_columns);
    
        // Query to get all meta keys for the specified post type
        $query = $wpdb->prepare("
            SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
        ", $post_type);
    
        // Get the results
        $meta_keys = $wpdb->get_col($query);
    
        // Merge standard fields with meta keys
        $meta_keys = array_merge($standard_fields, $meta_keys);
        
        return $meta_keys;
    }

    // public function show_field_sync_button( $id, $field ) {
	// 	$post_type = $field['attributes']['data-post_type'];

	// 	// Retrieve the saved value from options
	// 	$options = get_option('wpf_options');
	// 	$select_value = isset($options[$id]) ? (string) $options[$id] : '';
		
	// 	$boards = wp_fusion()->settings->get( 'available_lists', array() );

	// 	// Render the select field
	// 	echo '<select style="display:inline-block;margin-right:5px;" id="' . esc_attr( $id ) . '" class="select4-crm-field form-control ' . esc_attr( $field['class'] ) . '" name="wpf_options[' . esc_attr( $id ) . ']">';
	// 	echo '<option value="">' . esc_html__( 'Select Board', 'wp-fusion' ) . '</option>';
	// 	foreach ( $boards as $value => $label ) {
	// 		echo '<option value="' . esc_attr( $value ) . '" ' . selected( $select_value, $value, false ) . '>' . esc_html( $label ) . '</option>';
	// 	}
	// 	echo '</select>';

	// 	// Render the sync button
	// 	echo '<a id="sync-post-type-fields-' . esc_attr( $post_type ) . '" class="button button-primary sync-post-type-fields" data-post_type="' . esc_attr( $post_type ) . '" data-nonce="' . esc_attr( $field['attributes']['data-nonce'] ) . '">';
	// 	echo '<span class="dashicons dashicons-update-alt"></span>';
	// 	echo '<span class="text">' . esc_html__( 'Sync Fields', 'wp-fusion' ) . '</span>';
	// 	echo '</a>';
	// }

    public function show_field_sync_button( $id, $field, $subfield_id = null ) {
		// BugFu::log($this->options);
		// BugFu::log($id);

		// Retrieve the value from the options array
		$value = isset( $this->options[ $id ] ) ? $this->options[ $id ] : '';
        $post_type = $field['attributes']['data-post_type'];

		// BugFu::log($value);

		if ( ! isset( $field['allow_null'] ) ) {
			if ( empty( $field['std'] ) ) {
				$field['allow_null'] = true;
			} else {
				$field['allow_null'] = false;
			}
		}

		if ( ! isset( $field['class'] ) ) {
			$field['class'] = '';
		}

		if ( ! isset( $field['disabled'] ) ) {
			$field['disabled'] = false;
		}

		if ( empty( $field['std'] ) && ! empty( $field['placeholder'] ) ) {
			$field['std'] = $field['placeholder'];
		}


		//---------------------------------

		if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
			
			if ( count( $field['choices'] ) > 10 ) {
				$field['class'] .= 'select4-search';
			}
			
			echo '<select id="' . esc_attr( $id ) . '" class="select4 ' . esc_attr( $field['class'] ) . '" name="' . $this->option_group . '[' . esc_attr( $id ) . ']" ' . ( $field['disabled'] ? 'disabled="true"' : '' ) . ' ' . ( $field['placeholder'] ? 'data-placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '' ) . ' ' . ( $field['allow_null'] == false ? 'data-allow-clear="false"' : '' ) . ' ' . ( ! empty( $unlock ) ? 'data-unlock="' . esc_attr( trim( $unlock ) ) . '"' : '' ) . '>';
			
			if ( $field['allow_null'] == true || ! empty( $field['placeholder'] ) ) {
				echo '<option></option>';}
			
				foreach ($field['choices'] as $choice_value => $choice_label) {

					// Check if the current option value is an array and has an 'id' key
					if (is_array($this->options[$id]) && isset($this->options[$id]['id'])) {
						if (is_array($choice_label) && isset($choice_label['title'])) {
							echo '<option value="' . esc_attr($choice_value) . '"' . selected($this->options[$id]['id'], $choice_value, false) . '>' . esc_html($choice_label['title']) . '</option>';
						} else {
							echo '<option value="' . esc_attr($choice_value) . '"' . selected($this->options[$id]['id'], $choice_value, false) . '>' . esc_html($choice_label) . '</option>';
						}
					} else {
						// Handle cases where $this->options[$id] is not an array
						echo '<option value="' . esc_attr($choice_value) . '"' . selected($this->options[$id], $choice_value, false) . '>' . esc_html(is_array($choice_label) && isset($choice_label['title']) ? $choice_label['title'] : $choice_label) . '</option>';
					}
				}

			echo '</select>';

		} else {

			if ( isset( $field['format'] ) && $field['format'] == 'phone' ) {
				echo '<input id="' . esc_attr( $id ) . '" class="form-control bfh-phone ' . esc_attr( $field['class'] ) . '" data-format="(ddd) ddd-dddd" type="text" id="' . esc_attr( $id ) . '" name="' . $this->option_group . '[' . esc_attr( $id ) . ']" placeholder="' . esc_attr( $field['std'] ) . '" value="' . esc_attr( $this->{ $id } ) . '" ' . ( $field['disabled'] ? 'disabled="true"' : '' ) . '>';
			} else {
				echo '<input id="' . esc_attr( $id ) . '"  class="form-control ' . esc_attr( $field['class'] ) . '" style="display:inline-block;margin-right:5px;" type="text" name="' . $this->option_group . '[' . esc_attr( $id ) . ']" placeholder="' . esc_attr( $field['std'] ) . '" value="' . esc_attr( $value ) . '" ' . ( $field['disabled'] || $field['input_disabled'] ? 'disabled="true"' : '' ) . '>';
			}

		}

		

		// Render the sync button
        $tip = sprintf( __( 'Refresh available workspaces from %s. Does not modify any user data or permissions.', 'wp-fusion-lite' ), wp_fusion()->crm->name );
		echo '<a id="sync-post-type-fields-' . esc_attr( $post_type ) . '" class="button button-primary wpf-tip wpf-tip-right sync-post-type-fields" data-post_type="' . esc_attr( $post_type ) . '" data-nonce="' . esc_attr( $field['attributes']['data-nonce'] ) . '">';
		echo '<span class="dashicons dashicons-update-alt"></span>';
		echo '<span class="text">' . sprintf( esc_html__( 'Sync %s Fields', 'wp-fusion-lite' ), esc_html( $post_type ) ) . '</span>';
		echo '</a>';
		
	}

    public function ajax_sync_post_type_fields() {
		//BugFu::log("ajax_sync_post_type_fields init");
		check_ajax_referer('wpf_sync_post_type_fields', '_ajax_nonce');
	
		$post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
	
		if (empty($post_type)) {
			wp_send_json_error('Post type not specified');
			return;
		}

		//BugFu::log("calling sync_post_type_fields");
		$result = $this->sync_post_type_fields($post_type);
	
		if (true === $result) {
			wp_send_json_success();
		} else {
			if (is_wp_error($result)) {
				wp_send_json_error($result->get_error_message());
			} else {
				wp_send_json_error();
			}
		}
	}

    public function sync_post_type_fields($post_type) {
        $api_key = wpf_get_option('monday_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('No API key provided.', 'wp-fusion'));
        }

        $options = get_option('wpf_options');

        // Check if the post_type_sync_ key exists and its value
        if (isset($options['post_type_sync_' . $post_type])) {
            $board = $options['post_type_sync_' . $post_type];
        }

        if (empty($board)) {
            return new WP_Error('no_board_selected', __('No board selected for this post type.', 'wp-fusion'));
        }

        // Modified GraphQL query to include column type
        $query = '{"query": "{ boards (ids: [' . $board . ']) { columns { id title type } } }"}';

        // Make the request
        $response = wp_safe_remote_post(
            'https://api.monday.com/v2',
            array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization' => $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $query,
            )
        );

        // Handle the response
        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for errors in the response
        if (isset($body['errors']) && !empty($body['errors'])) {
            $error_message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('authentication_error', __('Authentication failed: ', 'wp-fusion') . $error_message);
        }

        if (empty($body['data']['boards'][0]['columns'])) {
            return new WP_Error('no_columns_found', __('No columns found for the selected board.', 'wp-fusion'));
        }

        // Process the columns
        $built_in_fields = array();
        $custom_fields = array();

        foreach ($body['data']['boards'][0]['columns'] as $column) {
            $custom_fields[$column['id']] = $column['title'];
        }

        $post_fields = array(
            'Standard Fields' => $built_in_fields,
            'Custom Fields'   => $custom_fields,
        );

        // Allow filtering of post type fields
        $post_fields = apply_filters('wpf_monday_sync_post_type_fields', $post_fields, $body['data']['boards'][0]['columns'], $post_type);

        wp_fusion()->settings->set('crm_'.$post_type.'_fields', $post_fields);

        return true;
    }


    /**
     * Handles timeline fields by splitting them into from/to date fields
     *
     * @access public
     * @param array $post_fields The post fields
     * @param array $columns The columns from Monday.com
     * @param string $post_type The post type
     * @return array Modified post fields
     */
    public function handle_timeline_fields($post_fields, $columns, $post_type) {
		BugFu::log("handle_timeline_fields init");
	
		$timeline_fields = array();
	
		// Loop through columns to find timeline fields
		foreach ($columns as $column) {
			if ($column['type'] === 'timeline') {
				$timeline_fields[$column['id']] = array(
					'title' => $column['title'],
					'from'  => $column['id'] . '_from',
					'to'    => $column['id'] . '_to',
				);
	
				// Remove the original timeline field
				if (isset($post_fields['Custom Fields'][$column['id']])) {
					unset($post_fields['Custom Fields'][$column['id']]);
				}
	
				// Add new split fields
				$post_fields['Custom Fields'][$column['id'] . '_from'] = $column['title'] . ' (From)';
				$post_fields['Custom Fields'][$column['id'] . '_to'] = $column['title'] . ' (To)';
			}
		}
	
		// Store timeline fields metadata for later use
		if (!empty($timeline_fields)) {
			BugFu::log("Storing timeline fields metadata");
			BugFu::log($timeline_fields);
			update_option('wpf_monday_timeline_fields', $timeline_fields, false);
	
			// Re-sort the custom fields alphabetically by title
			uasort($post_fields['Custom Fields'], function($a, $b) {
				// Get the title/label for comparison
				$title_a = is_array($a) ? $a['title'] : $a;
				$title_b = is_array($b) ? $b['title'] : $b;
				return strcmp($title_a, $title_b);
			});
		}
	
		BugFu::log("Modified post fields:");
		BugFu::log($post_fields);
	
		return $post_fields;
	}
	



    /**
     * Save post type fields to custom option
     *
     * @access public
     * @return mixed
     */
    public function save_available_workspaces( $value ) {
		//BugFu::log("save_available_workspaces init");
        // // Save to custom option
        update_option( 'wpf_available_workspaces', $value, false );

        // Return false to prevent saving to wpf_options
        return false;
    }

    public function save_crm_post_type_fields( $value, $post_type ) {
        //BugFu::log( "Saving fields for post type: $post_type" );
    
        // Save to a custom option based on the post type
        update_option( 'wpf_crm_' . $post_type . '_fields', $value, false );
    
        // Return false to prevent saving to wpf_options
        return false;
    }

    /**
	 * Set defaults for contact fields to avoid undefined index errors.
	 *
	 * @since  3.37.30
	 *
	 * @param  array $fields The fields.
	 * @return array  Contact fields
	 */
	public function handle_get_crm_post_fields( $fields, $post_type ) {
        
		$setting = get_option( 'wpf_crm_' . $post_type . '_fields', array() );
        //BugFu::log($setting);

			if ( ! empty( $setting ) ) {

				$this->options['crm_' .$post_type . '_fields'] = $setting;

			} elseif ( empty( $setting ) && empty( $this->options ) ) {

				// Fallback in case the data hasn't been moved yet (pre 3.37).
				$this->options = get_option( 'wpf_options', array() );

			}

		return $setting;
	}

    /**
     * Reset plugin specific options
     *
     * @access public
     * @return void
     */
    public function reset_plugin_options( $options ) {
        if ( ! empty( $options['custom_reset'] ) ) {
            global $wpdb;

            // Get all options that start with wpf_crm_*_fields
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpf_crm_%_fields'" );

            // Clean up wpf_options
            $wpf_options = get_option( 'wpf_options', array() );

            // Remove our specific settings
            foreach ( $wpf_options as $key => $value ) {
                // Remove post fields
                if ( $key === 'post_fields' || $key === 'crm_post_fields' ) {
                    unset( $wpf_options[$key] );
                }
                // Remove post type sync settings
                if ( strpos( $key, 'post_type_sync_' ) === 0 ) {
                    unset( $wpf_options[$key] );
                }
                // Remove custom reset checkbox
                if ( $key === 'custom_reset' ) {
                    unset( $wpf_options[$key] );
                }
            }

            // Save cleaned wpf_options back to database
            update_option( 'wpf_options', $wpf_options );

            // Maybe reset any other plugin-specific options
            delete_option( 'wpf_post_fields' );
        }
    }

    /**
     * Validate reset field and trigger reset if checked
     *
     * @access public
     * @return mixed
     */
    public function validate_field_custom_reset( $input, $setting ) {
        //BugFu::log($input);
        if ( ! empty( $input ) ) {
            // Clean up wpf_options
            $wpf_options = get_option( 'wpf_options', array() );
            //BugFu::log($wpf_options);

            // Remove our specific settings
            foreach ( $wpf_options as $key => $value ) {
                // Remove post fields
                if ( $key === 'post_fields' || $key === 'crm_post_fields' ) {
                    //BugFu::log("removing post_fields");
                    unset( $wpf_options[$key] );
                }
                // Remove post type sync settings
                if ( strpos( $key, 'post_type_sync_' ) === 0 ) {
                    //BugFu::log("removing post_type_sync_");
                    unset( $wpf_options[$key] );
                }
                // Remove post type sync settings
                if ( strpos( $key, 'postType_' ) === 0 ) {
                    //BugFu::log("removing postType_");
                    unset( $wpf_options[$key] );
                }
                // Remove custom reset checkbox
                if ( $key === 'custom_reset' ) {
                    //BugFu::log("removing custom_reset");
                    unset( $wpf_options[$key] );
                }
            }

            // Save cleaned wpf_options back to database
            update_option( 'wpf_options', $wpf_options );

            // Delete any custom options
            global $wpdb;
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpf_crm_%_fields'" );
            delete_option( 'wpf_post_fields' );
        }

        return $input;
    }


    /**
	 * Validation for contact field data
	 *
	 * @access public
	 * @return mixed
	 */
	public function validate_field_post_fields( $input, $setting, $options_class ) {
        //BugFu::log($input);
        // BugFu::log($setting);
        // BugFu::log($options_class);

		// Unset the empty ones.
		foreach ( $input as $field => $data ) {

			if ( 'new_field' === $field ) {
				continue;
			}

			if ( empty( $data['active'] ) && empty( $data['crm_field'] ) ) {
				unset( $input[ $field ] );
			}
		}

		// New fields.
		if ( ! empty( $input['new_field']['key'] ) ) {

			$input[ $input['new_field']['key'] ] = array(
				'active'    => true,
				'type'      => $input['new_field']['type'],
				'crm_field' => $input['new_field']['crm_field'],
			);

			// Track which ones have been custom registered.

			if ( ! isset( $options_class->options['custom_metafields'] ) ) {
				$options_class->options['custom_metafields'] = array();
			}

			if ( ! in_array( $input['new_field']['key'], $options_class->options['custom_metafields'] ) ) {
				$options_class->options['custom_metafields'][] = $input['new_field']['key'];
			}
		}

		unset( $input['new_field'] );
        //BugFu::log($input);

		$input = apply_filters( 'wpf_contact_fields_save', $input );
        //BugFu::log($input);

		return wpf_clean( $input );
	}


    public function validate_field_tribe_events_fields( $input, $setting, $options_class ) {
       //BugFu::log("validate_field_tribe_events_fields");
       //BugFu::log($input);
        // BugFu::log($setting);
        // BugFu::log($options_class);

		// Unset the empty ones.
		foreach ( $input as $field => $data ) {

			if ( 'new_field' === $field ) {
				continue;
			}

			if ( empty( $data['active'] ) && empty( $data['crm_field'] ) ) {
				unset( $input[ $field ] );
			}
		}

		// New fields.
		if ( ! empty( $input['new_field']['key'] ) ) {

			$input[ $input['new_field']['key'] ] = array(
				'active'    => true,
				'type'      => $input['new_field']['type'],
				'crm_field' => $input['new_field']['crm_field'],
			);

			// Track which ones have been custom registered.

			if ( ! isset( $options_class->options['custom_metafields'] ) ) {
				$options_class->options['custom_metafields'] = array();
			}

			if ( ! in_array( $input['new_field']['key'], $options_class->options['custom_metafields'] ) ) {
				$options_class->options['custom_metafields'][] = $input['new_field']['key'];
			}
		}

		unset( $input['new_field'] );
        //BugFu::log($input);

		$input = apply_filters( 'wpf_contact_fields_save', $input );
        //BugFu::log($input);

		return wpf_clean( $input );
	}


    /**
	 * User register.
	 *
	 * Triggered when a new user is registered. Creates the user in the CRM and
	 * stores the user's CRM contact ID for later reference.
	 *
	 * @since  1.0.0
	 *
	 * @param int   $user_id   The user ID.
	 * @param array $post_data The registration data.
	 * @param bool  $force     Whether or not to override role limitations.
	 * @return string|bool The contact ID of the new contact or false on failure.
	 */
	public function post_updated( $post_id, $post_data, $old_post_data ) {

		//BugFu::log("post_updated init");
        BugFu::log($post_data);
  

		 // Avoid infinite loops
		//remove_action('post_updated', 'post_updated', 10, 3);

		// Check if this is a new post (creation)
		if (wp_is_post_revision($post_id) || $post_data->post_status == 'auto-draft') {
			add_action('post_updated', 'post_updated', 10, 3);
			return;
		}

		do_action( 'wpf_post_updated_start', $post_id, $post_data );

		// Get posted data from the registration form.
		if ( empty( $post_data ) && ! empty( $_POST ) && is_array( $_POST ) ) {
			$post_data = (array) wpf_clean( wp_unslash( $_POST ) );
		} elseif ( empty( $post_data ) ) {
			$post_data = array();
		}

		$post_meta = $this->get_post_meta( $post_id );
		BugFu::log($post_meta);
        $post_data = get_object_vars( $post_data );
        //BugFu::log($post_meta);
        //BugFu::log($post_data);
        


		// Merge what's in the database with what was submitted on the form.
		$post_data = array_merge( $post_meta, $post_data );
        //::log($post_data);

		/**
		 * Allow modification of the post data.
		 *
		 * @since 1.0.0
		 *
		 * @see   WPF_User::maybe_set_first_last_name()
		 * @see   WPF_User_Profile::filter_form_fields()
		 * @link  https://wpfusion.com/documentation/filters/wpf_user_register/
		 *
		 * @param array|null $post_data The registration data.
		 * @param int        $user_id   The user ID.
		 */

		$post_type= get_post_type($post_id);
		//BugFu::log($post_type);

		$post_data = apply_filters( 'wpf_post_updated', $post_data, $post_id );
		//$post_meta = get_post_meta($post_id);
		//BugFu::log($post_data->post_title);


		// Allows for cancelling of registration via filter.
		if ( null === $post_data ) {
			return false;
		}

        //BugFu::log($post_data['post_title']);

        if ( empty( $post_data['post_title'] ) ) {

			wpf_log(
				'notice',
				$post_id,
				/* translators: %s: CRM Name */
				sprintf( __( 'Post not synced to %s because Post Title wasn\'t detected in the submitted data.', 'wp-fusion-lite' ), wp_fusion()->crm->name ),
				array(
					'source'              => 'user-register',
					//'meta_array_nofilter' => $post_meta,
				)
			);

			return false;
		}

		// Check if contact already exists in CRM.
		$item_id = $this->get_item_id( $post_id, true );
		//BugFu::log($item_id);

		// if ( ! wpf_get_option( 'create_users' ) && false === $force && empty( $contact_id ) ) {

		// 	wpf_log(
		// 		'notice',
		// 		$user_id,
		// 		/* translators: %s: CRM Name */
		// 		sprintf( __( 'User registration not synced to %s because "Create Contacts" is disabled in the WP Fusion settings. You will not be able to apply tags to this user.', 'wp-fusion-lite' ), wp_fusion()->crm->name )
		// 	);

		// 	return false;

		// }

		// // Get any lists to add.
		// $assign_lists = wpf_get_option( 'assign_lists' );

		// if ( ! empty( $assign_lists ) ) {
		// 	$post_data['lists'] = $assign_lists;
		// }

		if ( empty( $item_id ) ) {

			// Contact does not exist in the CRM.

			// See if user role is elligible for being created as a contact.

			// $valid_roles = wpf_get_option( 'user_roles', array() );

			// $valid_roles = apply_filters( 'wpf_register_valid_roles', $valid_roles, $user_id, $post_data );

			// if ( ! empty( $valid_roles ) && ! in_array( $post_data['role'], $valid_roles ) && false === $force ) {

			// 	wpf_log(
			// 		'notice',
			// 		$user_id,
			// 		/* translators: %1$s: CRM Name, %2$s New user's role slug */
			// 		sprintf( __( 'User not added to %1$s because role %2$s isn\'t enabled for contact creation.', 'wp-fusion-lite' ), wp_fusion()->crm->name, '<strong>' . $post_data['role'] . '</strong>' )
			// 	);
			// 	return false;

			// }

			// Log what's about to happen.

			wpf_log(
				'info',
				$post_id,
				/* translators: %s: CRM Name */
				sprintf( __( 'New post registration. Adding item to %s:', 'wp-fusion-lite' ), wp_fusion()->crm->name ),
				array(
					'source'     => 'post-update',
					// 'meta_array' => $post_meta,
				)
			);

			// Add the item to the CRM.

			$item_id = wp_fusion()->crm->add_object( $post_data, $post_type, $map_meta_fields = true );

			if ( is_wp_error( $item_id ) ) {

				// Error logging.

				wpf_log(
					$item_id->get_error_code(),
					$post_id,
					/* translators: %s: Error message */
					sprintf( __( 'Error adding item: %s', 'wp-fusion-lite' ), $item_id->get_error_message() ),
					array(
						'source' => 'post-update',
					)
				);

				return false;

			}

			$item_id = sanitize_text_field( $item_id );

			update_post_meta( $post_id, WPF_ITEM_ID_META_KEY, $item_id );

		} else {

			// Contact already exists in the CRM, update them.

			wpf_log(
				'info',
				$post_id,
				/* translators: %1$s: Existing contact ID, %2$s CRM name */
				sprintf( __( 'New post registration. Updating item #%1$s in %2$s:', 'wp-fusion-lite' ), $item_id, wp_fusion()->crm->name ),
				array(
					'source'     => 'post-update',
					// 'meta_array' => $post_data,
				)
			);

			// Send the update data.

			$result = wp_fusion()->crm->update_object( $item_id, $post_data, $post_type, $map_meta_fields = true );

			if ( is_wp_error( $result ) ) {

				// If update failed.

				wpf_log(
					$result->get_error_code(),
					$post_id,
					/* translators: %s: Error message */
					sprintf( __( 'Error updating item: %s', 'wp-fusion-lite' ), $result->get_error_message() ),
					array(
						'source' => 'post-update',
					)
				);

				return false;

			}

			// Load the tags from the existing contact record.

			// $this->get_tags( $user_id, true, false );

		}

		// Assign any tags specified in the WPF settings page.
		// $assign_tags = wpf_get_option( 'assign_tags' );

		// if ( ! empty( $assign_tags ) ) {
		// 	wp_fusion()->logger->add_source( 'general-settings' );
		// 	$this->apply_tags( $assign_tags, $user_id );
		// }

		// do_action( 'wpf_user_created', $user_id, $item_id, $post_data );

		return $item_id;

	}



/**
	 * Get all the available metadata from the database for a user
	 *
	 * @access public
	 * @return array User Meta
	 */
	public function get_post_meta( $post_id = false ) {

		if ( false === $post_id ) {
			return array();
		}

		if ( empty( $post_id ) ) {
			return apply_filters( 'wpf_get_user_meta', array(), $post_id );
		}

		// Start by getting everything in the database.

		$post_meta = get_post_meta( $post_id );

		if ( ! $post_meta ) {
			return apply_filters( 'wpf_get_user_meta', array(), $post_id );
		}

		$post_meta = array_map(
			function ( $a ) {
				return maybe_unserialize( $a[0] );
			},
			$post_meta
		);

		// // get_userdata() doesn't work properly during an auto login session.

		// if ( doing_wpf_auto_login() && wpf_get_current_user_id() === $user_id ) {
		// 	return apply_filters( 'wpf_get_user_meta', $user_meta, $user_id );
		// }

		// $userdata = get_userdata( $user_id );

		// if ( false === $userdata ) {
		// 	return array();
		// }

		// $user_meta['user_id']         = $user_id;
		// $user_meta['user_login']      = $userdata->user_login;
		// $user_meta['user_email']      = $userdata->user_email;
		// $user_meta['user_registered'] = $userdata->user_registered;
		// $user_meta['user_nicename']   = $userdata->user_nicename;
		// $user_meta['user_url']        = $userdata->user_url;
		// $user_meta['display_name']    = $userdata->display_name;

		// if ( is_array( $userdata->roles ) ) {
		// 	$user_meta['role'] = reset( $userdata->roles );
		// }

		// if ( ! empty( $userdata->caps ) ) {
		// 	$user_meta[ $userdata->cap_key ] = array_keys( $userdata->caps );
		// }

		// $user_meta['ip'] = $this->get_ip();

		// $user_meta = apply_filters( 'wpf_get_user_meta', $user_meta, $user_id );

		return $post_meta;
	}




    public function tribe_events_updated( $post_id, $post_data, $old_post_data ) {

		//BugFu::log("tribe_events_updated init");

		//  // Avoid infinite loops
		// //remove_action('post_updated', 'post_updated', 10, 3);

		// // Check if this is a new post (creation)
		// if (wp_is_post_revision($post_id) || $post_data->post_status == 'auto-draft') {
		// 	add_action('post_updated', 'post_updated', 10, 3);
		// 	return;
		// }

		// do_action( 'wpf_post_updated_start', $post_id, $post_data );

		// // Get posted data from the registration form.
		// if ( empty( $post_data ) && ! empty( $_POST ) && is_array( $_POST ) ) {
		// 	$post_data = (array) wpf_clean( wp_unslash( $_POST ) );
		// } elseif ( empty( $post_data ) ) {
		// 	$post_data = array();
		// }

		// // $user_meta = $this->get_user_meta( $user_id );

		// // // Merge what's in the database with what was submitted on the form.
		// // $post_data = array_merge( $user_meta, $post_data );

		// /**
		//  * Allow modification of the post data.
		//  *
		//  * @since 1.0.0
		//  *
		//  * @see   WPF_User::maybe_set_first_last_name()
		//  * @see   WPF_User_Profile::filter_form_fields()
		//  * @link  https://wpfusion.com/documentation/filters/wpf_user_register/
		//  *
		//  * @param array|null $post_data The registration data.
		//  * @param int        $user_id   The user ID.
		//  */

		// $post_type= get_post_type($post_id);
		// BugFu::log($post_type);

		// $post_data = apply_filters( 'wpf_post_updated', $post_data, $post_id );
		// $post_meta = get_post_meta($post_id);
		// BugFu::log($post_data->post_title);


		// // Allows for cancelling of registration via filter.
		// if ( null === $post_data ) {
		// 	return false;
		// }

		// if ( empty( $post_data->post_title ) ) {

		// 	wpf_log(
		// 		'notice',
		// 		$post_id,
		// 		/* translators: %s: CRM Name */
		// 		sprintf( __( 'Post not synced to %s because Post Title wasn\'t detected in the submitted data.', 'wp-fusion-lite' ), wp_fusion()->crm->name ),
		// 		array(
		// 			'source'              => 'user-register',
		// 			//'meta_array_nofilter' => $post_meta,
		// 		)
		// 	);

		// 	return false;
		// }

		// // Check if contact already exists in CRM.
		// $item_id = $this->get_item_id( $post_id, true );
		// BugFu::log($item_id);

		// // if ( ! wpf_get_option( 'create_users' ) && false === $force && empty( $contact_id ) ) {

		// // 	wpf_log(
		// // 		'notice',
		// // 		$user_id,
		// // 		/* translators: %s: CRM Name */
		// // 		sprintf( __( 'User registration not synced to %s because "Create Contacts" is disabled in the WP Fusion settings. You will not be able to apply tags to this user.', 'wp-fusion-lite' ), wp_fusion()->crm->name )
		// // 	);

		// // 	return false;

		// // }

		// // // Get any lists to add.
		// // $assign_lists = wpf_get_option( 'assign_lists' );

		// // if ( ! empty( $assign_lists ) ) {
		// // 	$post_data['lists'] = $assign_lists;
		// // }

		// if ( empty( $item_id ) ) {

		// 	// Contact does not exist in the CRM.

		// 	// See if user role is elligible for being created as a contact.

		// 	// $valid_roles = wpf_get_option( 'user_roles', array() );

		// 	// $valid_roles = apply_filters( 'wpf_register_valid_roles', $valid_roles, $user_id, $post_data );

		// 	// if ( ! empty( $valid_roles ) && ! in_array( $post_data['role'], $valid_roles ) && false === $force ) {

		// 	// 	wpf_log(
		// 	// 		'notice',
		// 	// 		$user_id,
		// 	// 		/* translators: %1$s: CRM Name, %2$s New user's role slug */
		// 	// 		sprintf( __( 'User not added to %1$s because role %2$s isn\'t enabled for contact creation.', 'wp-fusion-lite' ), wp_fusion()->crm->name, '<strong>' . $post_data['role'] . '</strong>' )
		// 	// 	);
		// 	// 	return false;

		// 	// }

		// 	// Log what's about to happen.

		// 	wpf_log(
		// 		'info',
		// 		$post_id,
		// 		/* translators: %s: CRM Name */
		// 		sprintf( __( 'New post registration. Adding item to %s:', 'wp-fusion-lite' ), wp_fusion()->crm->name ),
		// 		array(
		// 			'source'     => 'post-update',
		// 			// 'meta_array' => $post_meta,
		// 		)
		// 	);

		// 	// Add the item to the CRM.

		// 	$item_id = wp_fusion()->crm->add_object( $post_data, $post_type, $map_meta_fields = true );

		// 	if ( is_wp_error( $item_id ) ) {

		// 		// Error logging.

		// 		wpf_log(
		// 			$item_id->get_error_code(),
		// 			$post_id,
		// 			/* translators: %s: Error message */
		// 			sprintf( __( 'Error adding item: %s', 'wp-fusion-lite' ), $item_id->get_error_message() ),
		// 			array(
		// 				'source' => 'post-update',
		// 			)
		// 		);

		// 		return false;

		// 	}

		// 	$item_id = sanitize_text_field( $item_id );

		// 	update_post_meta( $post_id, WPF_ITEM_ID_META_KEY, $item_id );

		// } else {

		// 	// Contact already exists in the CRM, update them.

		// 	wpf_log(
		// 		'info',
		// 		$post_id,
		// 		/* translators: %1$s: Existing contact ID, %2$s CRM name */
		// 		sprintf( __( 'New post registration. Updating item #%1$s in %2$s:', 'wp-fusion-lite' ), $item_id, wp_fusion()->crm->name ),
		// 		array(
		// 			'source'     => 'post-update',
		// 			// 'meta_array' => $post_data,
		// 		)
		// 	);

		// 	// Send the update data.

		// 	$result = wp_fusion()->crm->update_object( $item_id, $post_data, 'post', $map_meta_fields = true );;

		// 	if ( is_wp_error( $result ) ) {

		// 		// If update failed.

		// 		wpf_log(
		// 			$result->get_error_code(),
		// 			$post_id,
		// 			/* translators: %s: Error message */
		// 			sprintf( __( 'Error updating item: %s', 'wp-fusion-lite' ), $result->get_error_message() ),
		// 			array(
		// 				'source' => 'post-update',
		// 			)
		// 		);

		// 		return false;

		// 	}

		// 	// Load the tags from the existing contact record.

		// 	// $this->get_tags( $user_id, true, false );

		// }

		// // Assign any tags specified in the WPF settings page.
		// // $assign_tags = wpf_get_option( 'assign_tags' );

		// // if ( ! empty( $assign_tags ) ) {
		// // 	wp_fusion()->logger->add_source( 'general-settings' );
		// // 	$this->apply_tags( $assign_tags, $user_id );
		// // }

		// // do_action( 'wpf_user_created', $user_id, $item_id, $post_data );

		// return $item_id;

	}

    /**
	 * Gets item ID from post ID.
	 *
	 * @since  1.0.0
	 *
	 * @param  int|bool $post_id      The post ID or false to use current post.
	 * @param  bool     $force_update Whether or not to force-check the contact
	 *                                ID by making an API call to the CRM.
	 * @return bool|string Contact ID or false if not found.
	 */
	public function get_item_id( $post_id, $force_update = false ) {

	
		if ( empty( $post_id ) ) {
			return false;
		}

		do_action( 'wpf_get_item_id_start', $post_id );

		$item_id = get_post_meta( $post_id, WPF_ITEM_ID_META_KEY, true );

		if ( empty( $item_id ) ) {
			$item_id = false;
		}

		// If the contact was created in staging mode and we're no longer in staging mode.
		if ( 0 === strpos( $item_id, 'staging_' ) && ! wpf_is_staging_mode() && 'staging' !== wp_fusion()->crm->slug ) {
			$item_id = false;
		}

		// We need the email address for the wpf_get_contact_id_email filter.

		// $user = get_user_by( 'id', $user_id );

		// if ( ! empty( $user ) ) {
		// 	$email_address = $user->user_email;
		// } elseif ( doing_wpf_auto_login() ) {
		// 	$email_address = get_user_meta( $user_id, 'user_email', true );
		// } else {
		// 	$email_address = false;
		// }

		// // Allow filtering the email used for lookups.
		// $email_address = apply_filters( 'wpf_get_contact_id_email', $email_address, $user_id );

		// if ( empty( $contact_id ) && empty( $email_address ) ) {
		// 	// We don't know the user or contact ID, so quit.
		// 	return false;
		// }

		// If contact ID is already set.
		// if ( false === $force_update ) {
		// 	return apply_filters( 'wpf_contact_id', $contact_id, $email_address );
		// }

		// // If no user email set, don't bother with an API call.
		// if ( ! is_email( $email_address ) ) {
		// 	return false;
		// }

		// $loaded_contact_id = wp_fusion()->crm->get_contact_id( $email_address );

		// if ( is_wp_error( $loaded_contact_id ) ) {

		// 	wpf_log( $loaded_contact_id->get_error_code(), $user_id, 'Error getting contact ID for <strong>' . $email_address . '</strong>: ' . $loaded_contact_id->get_error_message() );
		// 	return $contact_id; // in case there was a contact ID already cached.

		// }

		// $contact_id = apply_filters( 'wpf_contact_id', $loaded_contact_id, $email_address );

		if ( empty( $item_id ) ) {

			// Error logging.
			wpf_log( 'info', $post_id, 'No item found in ' . wp_fusion()->crm->name . ' for <strong>Post:' . $post_id . '</strong>' );
			delete_post_meta( $post_id, WPF_ITEM_ID_META_KEY, $item_id );
			//delete_post_meta( $post_id, WPF_TAGS_META_KEY, $contact_id );

		} else {

			$item_id = sanitize_text_field( $item_id );

			// Save it for later.
			update_post_meta( $post_id, WPF_ITEM_ID_META_KEY, $item_id );
		}

		do_action( 'wpf_got_item_id', $post_id, $item_id );

		return $item_id;

	}

    // hooks into map_meta_fields, which is usually just for user meta mapping, and override the $update_data for custom post types
    // $update_data will always be empty here

    public function wpf_cpt_map_meta_fields( $update_data, $post_meta ) {
      
        $post_type = get_post_type($post_meta['ID']);
      
        $update_data = $this->map_cpt_meta_fields($post_meta, $post_type);
       
        return $update_data;
    }

    /**
	 * Maps local fields to CRM field names
	 *
	 * @access public
	 * @return array
	 */

	 public function map_cpt_meta_fields( $user_meta, $post_type ) {
        BugFu::log("map_cpt_meta_fields");
        //BugFu::log($user_meta);
		

		if ( ! is_array( $user_meta ) || empty( $user_meta ) ) {
			return array();
		}

		$update_data = array();

		// Lists pass straight through unless mapped.

		if ( ! empty( $user_meta['lists'] ) ) {
			$update_data['lists'] = $user_meta['lists'];
		}

		foreach ( $this->{$post_type . '_fields'} as $field => $field_data ) {

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

		$update_data = apply_filters( 'wpf_map_cpt_meta_fields', $update_data, $user_meta );
        

		return $update_data;

	}

    

    
}   
