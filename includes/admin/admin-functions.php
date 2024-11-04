<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function get_post_meta_keys( $post_type ) {
    // BugFu::log("get_post_meta_keys init");
    // BugFu::log($post_type);

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

    // BugFu::log($meta_keys);
    
    return $meta_keys;
}


function format_post_meta_keys( $post_type ) {
	$meta_keys = get_post_meta_keys( $post_type );
	$standard_fields = get_standard_post_fields();

	// Combine standard fields and custom meta keys
	$all_fields = array_merge( array_keys( $standard_fields ), $meta_keys );
	$meta_fields = array();

	foreach ( $all_fields as $key ) {
		$label = isset( $standard_fields[ $key ] ) ? $standard_fields[ $key ] : $key;
		$meta_fields[ $key ] = array(
			'label' => $label,
			'type'  => 'text', // Assuming all fields are text for simplicity
			'group' => 'post_meta_fields',
		);
	}

	return $meta_fields;
}

// function get_board_fields( $board_id ) {
//     $api_key = wpf_get_option('custom_key');

//     if ( empty( $api_key ) || empty( $board_id ) ) {
//         return array();
//     }

//     $query = '{"query": "{ boards (ids: [' . $board_id . ']) { columns { id title } } }"}';
    
//     $response = wp_safe_remote_post(
//         'https://api.monday.com/v2',
//         array(
//             'method'  => 'POST',
//             'headers' => array(
//                 'Authorization' => $api_key,
//                 'Content-Type'  => 'application/json',
//             ),
//             'body'    => $query,
//         )
//     );

//     if ( is_wp_error( $response ) ) {
//         return array();
//     }

//     $body = json_decode( wp_remote_retrieve_body( $response ), true );

//     if ( isset( $body['errors'] ) && ! empty( $body['errors'] ) ) {
//         return array();
//     }

//     $fields = array();

//     if ( ! empty( $body['data']['boards'][0]['columns'] ) ) {
//         foreach ( $body['data']['boards'][0]['columns'] as $column ) {
//             $fields[ $column['id'] ] = $column['title'];
//         }
//     }

//     return $fields;
// }



function wpf_render_post_field_select( $setting, $meta_name, $field_id = false, $field_sub_id = false ) {
	// BugFu::log("wpf_render_crm_field_select init");
	// BugFu::log($setting);

	if ( doing_action( 'show_field_crm_field' ) ) {
		// Settings page.
		$name = $meta_name . '[' . $field_id . ']';
	} elseif ( false === $field_id ) {
		$name = $meta_name . '[crm_field]';
	} elseif ( false === $field_sub_id ) {
		$name = $meta_name . '[' . $field_id . '][crm_field]';
	} else {
		$name = $meta_name . '[' . $field_id . '][' . $field_sub_id . '][crm_field]';
	}

	// ID.

	if ( false === $field_id ) {
		$id = sanitize_html_class( $meta_name );
	} else {
		$id = sanitize_html_class( $meta_name ) . '-' . $field_id;
	}

	echo '<select id="' . esc_attr( $id . ( ! empty( $field_sub_id ) ? '-' . $field_sub_id : '' ) ) . '" class="select4-crm-field" name="' . esc_attr( $name ) . '" data-placeholder="Select a field">';

	echo '<option></option>';

	$crm_fields = wpf_get_option( 'post_fields' );
    // BugFu::log("wpf_get_option_post_fields");
    // BugFu::log($crm_fields);

	if ( ! empty( $crm_fields ) ) {

		foreach ( $crm_fields as $group_header => $fields ) {

			// For CRMs with separate custom and built in fields, or using the new data storage.
			if ( is_array( $fields ) ) {

				echo '<optgroup label="' . esc_attr( $group_header ) . '">';

				foreach ( $crm_fields[ $group_header ] as $field => $label ) {

					if ( is_array( $label ) ) {

						if ( isset( $label['label'] ) ) {
							$label = $label['label'];
						} else {
							$label = $label['crm_label']; // new 3.42.5 storage.
						}
					}

					$label = str_replace( '(', '<small>', $label ); // (read only) and (compound field)
					$label = str_replace( ')', '</small>', $label );

					echo '<option ' . selected( esc_attr( $setting ), $field, false ) . ' value="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</option>';
				}

				echo '</optgroup>';

			} else {

				$field = $group_header;
				$label = $fields;

				$label = str_replace( '(', '<small>', $label ); // (read only) and (compound field)
				$label = str_replace( ')', '</small>', $label );

				echo '<option ' . selected( esc_attr( $setting ), $field, false ) . ' value="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</option>';

			}
		}
	}

	// Save custom added fields to the DB.
	if ( in_array( 'add_fields', wp_fusion()->crm->supports ) ) {

		$field_check = array();

		// Collapse fields if they're grouped.
		if ( isset( $crm_fields['Custom Fields'] ) ) {

			foreach ( $crm_fields as $field_group ) {

				if ( ! empty( $field_group ) ) {

					foreach ( $field_group as $field => $label ) {
						$field_check[ $field ] = $label;
					}
				}
			}
		} else {

			$field_check = $crm_fields;

		}

		// Check to see if new custom fields have been added.
		if ( ! empty( $setting ) && ! isset( $field_check[ $setting ] ) ) {
			// BugFu::log($setting);

			echo '<option value="' . esc_attr( $setting ) . '" selected="selected">' . esc_html( $setting ) . '</option>';

			if ( isset( $crm_fields['Custom Fields'] ) ) {

				$crm_fields['Custom Fields'][ $setting ] = $setting;
				asort( $crm_fields['Custom Fields'] );

			} else {
				$crm_fields[ $setting ] = $setting;
				asort( $crm_fields );
			}

			wp_fusion()->settings->set( 'post_fields', $crm_fields );

			// Save safe crm field to DB.
			$post_fields                               = wpf_get_option( 'post_fields' );
			$post_fields[ $field_sub_id ]['crm_field'] = $setting;
			wp_fusion()->settings->set( 'post_fields', $post_fields );

		}
	}

	if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

		echo '<optgroup label="Tagging">';

			echo '<option ' . selected( esc_attr( $setting ), 'add_tag_' . $field_id ) . ' value="add_tag_' . esc_attr( $field_id ) . '">+ ' . esc_html__( 'Create tag(s) from value', 'wp-fusion-lite' ) . '</option>';

		echo '</optgroup>';

	}

	echo '</select>';
}
