<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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
        // Store selected post types
        $this->selected_post_types = wp_fusion()->settings->get( 'custom_post_types', array() );

        // Add the custom tab to WP Fusion settings
        add_filter( 'wpf_configure_sections', array( $this, 'add_custom_sections' ) );
        add_filter( 'wpf_configure_settings', array( $this, 'add_custom_settings' ) );
        
        // Add custom field type handler - changed from wpf_initialize_options
        add_filter( 'wpf_meta_field_types', array( $this, 'add_field_type' ), 10, 1 );
        add_action( 'show_field_post_fields_begin', array( 'WPF_Post_Fields', 'show_field_post_fields_begin' ), 10, 2 );
        add_action( 'show_field_post_fields', array( 'WPF_Post_Fields', 'show_field_post_fields' ), 10, 2 );

        add_filter( "wpf_post_meta_fields", array( $this, "prepare_post_meta_fields" ) );

        // Add the post types configuration filter
        add_filter( 'wpf_configure_setting_custom_post_types', array( $this, 'configure_setting_custom_post_types' ), 10, 2 );
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
                'custom'   => 'Custom Tab',
                'custom2'  => 'Custom 2',
            )
        );

        // Now check for post types with connected boards
        if ( ! empty( $this->selected_post_types ) ) {
            foreach ( $this->selected_post_types as $post_type ) {
                // Check if this post type has a board connected
                $setting_value = wp_fusion()->settings->get( "custom_post_type_{$post_type}_setting" );
                
                if ( ! empty( $setting_value ) ) {
                    $post_type_object = get_post_type_object( $post_type );
                    if ( $post_type_object ) {
                        // Add a new section for this post type
                        $page['sections'][ "custom_post_type_{$post_type}" ] = $post_type_object->labels->name;
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
        $settings['post_fields'] = array(
            'title'   => __( 'Contact Fields', 'wp-fusion-lite' ),
            'std'     => array(),
            'type'    => 'post_fields',
            'section' => 'custom',
            'choices' => array(),
        );

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
                
                if ( ! $post_type_object ) {
                    continue;
                }

                $settings["custom_post_type_{$post_type}_setting"] = array(
                    'title'       => sprintf( __( '%s Setting', 'wp-fusion-lite' ), $post_type_object->labels->singular_name ),
                    'desc'        => sprintf( __( 'Select a list for %s', 'wp-fusion-lite' ), $post_type_object->labels->name ),
                    'type'        => 'select',
                    'section'     => 'custom2',
                    'choices'     => $available_lists,
                    'placeholder' => __( 'Select a list', 'wp-fusion-lite' ),
                );

                // Check if this post type has a board connected
                $setting_value = wp_fusion()->settings->get( "custom_post_type_{$post_type}_setting" );
                
                if ( ! empty( $setting_value ) ) {
                    // Add post fields table to the post type's custom tab
                    $settings["custom_post_type_{$post_type}_fields"] = array(
                        'title'   => sprintf( __( '%s Fields', 'wp-fusion-lite' ), $post_type_object->labels->singular_name ),
                        'desc'    => sprintf( __( 'Configure field mapping for %s', 'wp-fusion-lite' ), $post_type_object->labels->name ),
                        'std'     => array(),
                        'type'    => 'post_fields',
                        'section' => "custom_post_type_{$post_type}",
                        'choices' => array(),
                    );
                }
            }
        }
        
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
    public function prepare_post_meta_fields( $meta_fields ) {
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
        $all_fields = $this->get_post_meta_keys('post');
    
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
            if ( substr( $key, 0, 1 ) === '_' || substr( $key, 0, 5 ) === 'hide_' || substr( $key, 0, 3 ) === 'wp_' ) {
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
} 