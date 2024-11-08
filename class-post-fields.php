<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Handles displaying and managing the post fields table in the WP Fusion settings
 *
 * @since 1.0.0
 */
class WPF_Post_Fields {

    /**
	 * Opening for post fields table
	 *
	 * @access public
	 * @return mixed
	 */

	public static function show_field_post_fields_begin( $id, $field ) {

		if ( ! isset( $field['disabled'] ) ) {
			$field['disabled'] = false;
		}

		echo '<tr valign="top"' . ( $field['disabled'] ? ' class="disabled"' : '' ) . '>';
		echo '<td>';
	}


    /**
     * Shows post fields table
     *
     * @access public
     * @return mixed
     */
    public static function show_field_post_fields($id, $field) {

        // Lets group post fields by integration if we can
        $field_groups = array(
            'wp' => array(
                'title'  => __( 'Standard WordPress Fields', 'wp-fusion-lite' ),
                'fields' => array(),
            ),
        );

        $field_groups = apply_filters( 'wpf_meta_field_groups', $field_groups );

        $field_groups['custom'] = array(
            'title'  => __( 'Custom Field Keys (Added Manually)', 'wp-fusion-lite' ),
            'fields' => array(),
        );

        // Append ungrouped fields
        $field_groups['extra'] = array(
            'title'  => __( 'Additional Post Meta Fields (For Developers)', 'wp-fusion-lite' ),
            'fields' => array(),
            'url'    => 'https://wpfusion.com/documentation/getting-started/syncing-post-fields/#additional-fields',
        );

        /**
         * Filters the available meta fields.
         *
         * @since 1.0.0
         *
         * @link https://wpfusion.com/documentation/filters/wpf_meta_fields
         *
         * @param array $fields    Tags to be removed from the user
         */
        $field['choices'] = apply_filters( 'wpf_post_meta_fields', $field['choices'] );

        foreach ( wp_fusion()->settings->get( 'post_fields', array() ) as $key => $data ) {
            if ( ! isset( $field['choices'][ $key ] ) ) {
                $field['choices'][ $key ] = $data;
            }
        }

        if ( empty( wp_fusion()->settings->options[ $id ] ) ) {
            wp_fusion()->settings->options[ $id ] = array();
        }

        // Set some defaults to prevent notices, and then rebuild fields array into group structure
        foreach ( $field['choices'] as $meta_key => $data ) {

            if ( empty( wp_fusion()->settings->options[ $id ][ $meta_key ] ) || ! isset( wp_fusion()->settings->options[ $id ][ $meta_key ]['crm_field'] ) || ! isset( wp_fusion()->settings->options[ $id ][ $meta_key ]['active'] ) ) {
                wp_fusion()->settings->options[ $id ][ $meta_key ] = array(
                    'active'    => false,
                    'pull'      => false,
                    'crm_field' => false,
                );
            }

            // Set Pull to on by default
            if ( ! empty( wp_fusion()->settings->options[ $id ][ $meta_key ] ) && ! empty( wp_fusion()->settings->options[ $id ][ $meta_key ]['active'] ) && ! isset( wp_fusion()->settings->options[ $id ][ $meta_key ]['pull'] ) && empty( $data['pseudo'] ) ) {
                wp_fusion()->settings->options[ $id ][ $meta_key ]['pull'] = true;
            }

            if ( ! empty( wp_fusion()->settings->options['custom_metafields'] ) && in_array( $meta_key, wp_fusion()->settings->options['custom_metafields'] ) ) {
                $field_groups['custom']['fields'][ $meta_key ] = $data;
            } elseif ( isset( $data['group'] ) && isset( $field_groups[ $data['group'] ] ) ) {
                $field_groups[ $data['group'] ]['fields'][ $meta_key ] = $data;
            } else {
                $field_groups['extra']['fields'][ $meta_key ] = $data;
            }
        }

        if ( wp_fusion()->settings->hide_additional ) {
            foreach ( $field_groups['extra']['fields'] as $key => $data ) {
                if ( ! isset( $data['active'] ) || $data['active'] != true ) {
                    unset( $field_groups['extra']['fields'][ $key ] );
                }
            }
        }

        /**
         * This filter is used in the CRM integrations to link up default field
         * pairings.
         *
         * @since 3.37.24
         *
         * @param array $options The WP Fusion options.
         */
        wp_fusion()->settings->options = apply_filters( 'wpf_initialize_options_post_fields', wp_fusion()->settings->options );

        // These fields should be turned on by default
        if ( empty( wp_fusion()->settings->options['post_fields']['post_title']['active'] ) ) {
            BugFu::log(wp_fusion()->settings->options['post_fields']);
            wp_fusion()->settings->options['post_fields']['post_title']['active'] = true;
            wp_fusion()->settings->options['post_fields']['ID']['active'] = true;
        }

        $field_types = array( 'text', 'date', 'multiselect', 'checkbox', 'state', 'country', 'int', 'raw', 'tel' );

        $field_types = apply_filters( 'wpf_meta_field_types', $field_types );

        echo '<p>' . sprintf( esc_html__( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion-lite' ), '<a href="https://wpfusion.com/documentation/getting-started/syncing-post-fields/" target="_blank">', '</a>' ) . '</p>';
        echo '<br />';

        // Display post fields table
        echo '<table id="contact-fields-table" class="table table-hover">';

        echo '<thead>';
        echo '<tr>';
        echo '<th class="sync">' . esc_html__( 'Sync', 'wp-fusion-lite' ) . '</th>';
        echo '<th>' . esc_html__( 'Name', 'wp-fusion-lite' ) . '</th>';
        echo '<th>' . esc_html__( 'Meta Field', 'wp-fusion-lite' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'wp-fusion-lite' ) . '</th>';
        echo '<th>' . sprintf( esc_html__( '%s Field', 'wp-fusion-lite' ), esc_html( wp_fusion()->crm->name ) ) . '</th>';
        echo '</tr>';
        echo '</thead>';

        if ( empty( wp_fusion()->settings->options['table_headers'] ) ) {
            wp_fusion()->settings->options['table_headers'] = array();
        }

        foreach ( $field_groups as $group => $group_data ) {

            if ( empty( $group_data['fields'] ) && $group != 'extra' ) {
                continue;
            }

            // Output group section headers
            if ( empty( $group_data['title'] ) ) {
                $group_data['title'] = 'none';
            }

            $group_slug = strtolower( str_replace( ' ', '-', $group_data['title'] ) );

            if ( ! isset( wp_fusion()->settings->options['table_headers'][ $group_slug ] ) ) {
                wp_fusion()->settings->options['table_headers'][ $group_slug ] = false;
            }

            if ( 'standard-wordpress-fields' !== $group_slug ) { // Skip the first one

                echo '<tbody class="labels">';
                echo '<tr class="group-header"><td colspan="5">';
                echo '<label for="' . esc_attr( $group_slug ) . '" class="group-header-title ' . ( wp_fusion()->settings->options['table_headers'][ $group_slug ] == true ? 'collapsed' : '' ) . '">';
                echo wp_kses_post( $group_data['title'] );

                if ( isset( $group_data['url'] ) ) {
                    echo '<a class="table-header-docs-link" href="' . esc_url( $group_data['url'] ) . '" target="_blank">' . esc_html__( 'View documentation', 'wp-fusion-lite' ) . ' &rarr;</a>';
                }

                echo '<i class="fa fa-angle-down"></i><i class="fa fa-angle-up"></i></label><input type="checkbox" ' . checked( wp_fusion()->settings->options['table_headers'][ $group_slug ], true, false ) . ' name="wpf_options[table_headers][' . $group_slug . ']" id="' . $group_slug . '" data-toggle="toggle">';
                echo '</td></tr>';
                echo '</tbody>';

            }

            $table_class = 'table-collapse';

            if ( wp_fusion()->settings->options['table_headers'][ $group_slug ] == true ) {
                $table_class .= ' hide';
            }

            if ( ! empty( $group_data['disabled'] ) ) {
                $table_class .= ' disabled';
            }

            echo '<tbody class="' . esc_attr( $table_class ) . '">';

            foreach ( $group_data['fields'] as $post_meta => $data ) {

                if ( ! is_array( $data ) ) {
                    $data = array();
                }

                // Allow hiding for internal fields
                if ( isset( $data['hidden'] ) ) {
                    continue;
                }

                echo '<tr' . ( wp_fusion()->settings->options[ $id ][ $post_meta ]['active'] == true ? ' class="success" ' : '' ) . '>';
                echo '<td><input class="checkbox post-fields-checkbox"' . ( empty( wp_fusion()->settings->options[ $id ][ $post_meta ]['crm_field'] ) ? ' disabled' : '' ) . ' type="checkbox" id="wpf_cb_' . esc_attr( $post_meta ) . '" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $post_meta ) . '][active]" value="1" ' . checked( wp_fusion()->settings->options[ $id ][ $post_meta ]['active'], 1, false ) . '/></td>';
                echo '<td class="wp_field_label">' . ( isset( $data['label'] ) ? esc_html( wp_strip_all_tags( $data['label'] ) ) : '' );

                // Tooltips
                if ( isset( $data['tooltip'] ) ) {
                    echo ' <i class="fa fa-question-circle wpf-tip wpf-tip-right" data-tip="' . esc_attr( $data['tooltip'] ) . '"></i>';
                }

                // Track custom registered fields
                if ( ! empty( wp_fusion()->settings->options['custom_metafields'] ) && in_array( $post_meta, wp_fusion()->settings->options['custom_metafields'] ) ) {
                    echo ' (' . esc_html__( 'Added by user', 'wp-fusion-lite' ) . ')';
                }

                echo '</td>';
                echo '<td><span class="label label-default">' . esc_html( $post_meta ) . '</span></td>';
                echo '<td class="wp_field_type">';

                if ( ! isset( $data['type'] ) ) {
                    $data['type'] = 'text';
                }

                // Allow overriding types via dropdown
                if ( ! empty( wp_fusion()->settings->options['post_fields'][ $post_meta ]['type'] ) ) {
                    $data['type'] = wp_fusion()->settings->options['post_fields'][ $post_meta ]['type'];
                }

                if ( ! in_array( $data['type'], $field_types ) ) {
                    $field_types[] = $data['type'];
                }

                asort( $field_types );

                echo '<select class="wpf_type" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $post_meta ) . '][type]">';

                foreach ( $field_types as $type ) {
                    echo '<option value="' . esc_attr( $type ) . '" ' . selected( $data['type'], $type, false ) . '>' . esc_html( $type ) . '</option>';
                }

                echo '<td>';

                wpf_render_crm_field_select( wp_fusion()->settings->options[ $id ][ $post_meta ]['crm_field'], 'wpf_options', 'post_fields', $post_meta );

                // Indicate pseudo-fields that should only be synced one way
                if ( isset( $data['pseudo'] ) ) {
                    echo '<input type="hidden" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $post_meta ) . '][pseudo]" value="1">';
                }

                echo '</td>';

                echo '</tr>';
            }
        }

        // Add new
        echo '<tr>';
        echo '<td><input class="checkbox post-fields-checkbox" type="checkbox" disabled id="wpf_cb_new_field" name="wpf_options[post_fields][new_field][active]" value="1" /></td>';
        echo '<td class="wp_field_label">Add new field</td>';
        echo '<td><input type="text" id="wpf-add-new-field" name="wpf_options[post_fields][new_field][key]" placeholder="New Field Key" /></td>';
        echo '<td class="wp_field_type">';

        echo '<select class="wpf_type" name="wpf_options[post_fields][new_field][type]">';

        foreach ( $field_types as $type ) {
            echo '<option value="' . esc_attr( $type ) . '" ' . selected( 'text', $type, false ) . '>' . esc_html( $type ) . '</option>';
        }

        echo '<td>';

        wpf_render_crm_field_select( false, 'wpf_options', 'post_fields', 'new_field' );

        echo '</td>';

        echo '</tr>';

        echo '</tbody>';

        echo '</table>';
    }

} 