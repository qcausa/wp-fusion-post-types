<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_CPT extends WPF_CPT_Integrations_Base {

	public $slug = 'post-type-sync';

	public $name = 'Post Type Sync';

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {
		

		$this->options = get_option( 'wpf_options', array() ); // load the options into memory.

		// Set the constants for the usermeta keys.
		$this->set_constants();

		// Initialize custom JS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 20 );

		// Add tabs for post types
		add_filter( 'wpf_settings_tabs', array( $this, 'add_post_type_tabs' ) );
        add_filter( 'wpf_configure_sections', array( $this, 'configure_sections' ), 20, 2 );

		

		// Global settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		

		// Render field mapping
		add_action( 'wpf_settings_page_content', array( $this, 'render_post_type_field_mapping' ) );

		// Post type sync button AJAX method
		add_action('wp_ajax_wpf_sync_post_type_fields', array($this, 'ajax_sync_post_type_fields'));

		// Post type sync button rendering
		add_action( 'show_field_sync_button', array( $this, 'show_field_sync_button' ), 15, 2 );

		// Save field mappings
		add_action( 'admin_init', array( $this, 'save_field_mappings' ) );

		// Post type actions
		add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );


		// Validation.
		add_filter( 'validate_field_postType_post_fields', array( $this, 'validate_field_post_fields' ), 10, 3 );
		add_filter( 'validate_field_post_type_sync_post', array( $this, 'validate_field_post_type_sync_post' ), 10, 3 );

		add_filter( 'wpf_set_setting_post_fields', array( $this, 'handle_post_type_fields_update' ), 10, 2 );
		add_filter( 'wpf_get_setting_post_fields', array( $this, 'handle_get_post_fields' ) );


		

	
		// Register dynamic actions for all post types
		$this->register_dynamic_actions();
	}

	/**
	 * Sets the constants for the postmeta keys.
	 *
	 * @since 3.38.25
	 * @since 3.41.35 Moved from wpf_crm_init hook to constructor.
	 */
	public function set_constants() {
		// BugFu::log("PASS");

		$slug = wpf_get_option( 'crm' );

		// if ( wpf_get_option( 'multisite_prefix_keys' ) ) {

		// 	global $wpdb;
		// 	$slug = $wpdb->get_blog_prefix() . $slug;

		// }

		if ( ! defined( 'WPF_ITEM_ID_META_KEY' ) ) {
			define( 'WPF_ITEM_ID_META_KEY', $slug . '_item_id' );
		}

		// if ( ! defined( 'WPF_TAGS_META_KEY' ) ) {
		// 	define( 'WPF_TAGS_META_KEY', $slug . '_tags' );
		// }

	}

	public function admin_scripts() {
		// Define the path to the JavaScript file using the constant
		$script_url = WPF_EC_DIR_URL . 'assets/js/wpf-post-types.js';
		
		// Enqueue the script
		wp_enqueue_script( 'wpf-post-types', $script_url, array('jquery'), '1.0', true );
	}

	private function register_dynamic_actions() {
		$post_types = get_post_types(array('public' => true), 'objects');
		$options = get_option('wpf_options');
		
		foreach ($post_types as $post_type) {
			if (isset($options['post_type_sync_' . $post_type->name]) && !empty($options['post_type_sync_' . $post_type->name])) {

				// WPF Post Type Fields Table Rendering
				add_action("show_field_{$post_type->name}-fields", array($this, 'show_field_postType_fields'), 15, 2);
				add_action("show_field_{$post_type->name}-fields_begin", array($this, 'show_field_postType_fields_begin'), 15, 2);
				add_filter( "wpf_{$post_type->name}_meta_fields", array( $this, "prepare_{$post_type->name}_meta_fields" ), 60 );

				// add_filter( "wpf_set_setting_post_type_fields_{$post_type->name}", array( $this, "handle_post_type_fields_update" ), 10, 2 );

				// Hook into post type updates only for post types with a configured list
				// add_action( "save_post_{$post_type->name}", array( $this, 'postType_updated' ), 10 );

				// Add connected crm object_id to on the post type admin screen
				// add_action('add_meta_boxes', 'example_add_meta_box');
				add_action( "wpf_meta_box_content", array( $this, 'add_custom_meta_box_field' ), 10, 2);

				// Export functions with post_type->name passed to export_options
				add_filter( 'wpf_export_options', function($options) use ($post_type) {
					return $this->export_options($options, $post_type->name);
				}, 10 );

				add_action( "wpf_batch_save_post_{$post_type->name}_init", function() use ($post_type) {
					return $this->batch_init($post_type->name);
				}, 10 );

				add_action( "wpf_batch_save_post_{$post_type->name}", function($post_id) use ($post_type) {
					return $this->batch_step_postTypes($post_id, $post_type);
				}, 10 );
				

				// Load the field mapping into memory.
				$this->{$post_type->name . '_fields'} = wpf_get_option( 'postType_' . $post_type->name . '_fields', array() );
			}
		}
	}



	/**
	 * Adds CPT checkboxs to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	 public function export_options($options, $post_type_name) {

		$options[sprintf('save_post_%s', $post_type_name)] = array(
			'label'   => sprintf(__('Export %ss', 'wp-fusion'), $post_type_name),
			'title'   => sprintf(__('Export %ss', 'wp-fusion'), $post_type_name),
			'tooltip' => sprintf(__('All WordPress %ss without a matching %s item record will be exported as new items.', 'wp-fusion' ), $post_type_name, wp_fusion()->crm->name ),
		);
	
		return $options;
	}

	/**
	 * Counts total number of postType objects to be processed
	 *
	 * @access public
	 * @return int Count
	 */

	 public function batch_init($post_type_name) {
		BugFu::log("batch_init init");

		$args = array(
			'numberposts' => -1,
			'post_type'   => strtolower($post_type_name),  // Use the dynamic post type name
			'post_status' => array('publish'),
			'fields'      => 'ids',
			'order'       => 'ASC',
			'meta_query'  => array(
				'relation' => 'OR',
				array(
					'key'     => WPF_CONTACT_ID_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => WPF_CONTACT_ID_META_KEY,
					'value' => false,
				),
			),
		);
	
		$objects = get_posts($args);
	
		wp_fusion()->logger->handle('info', 0, 'Beginning <strong>' . ucfirst($post_type_name) . '</strong> batch operation on ' . count($objects) . ' objects', array('source' => 'batch-process'));
	
		BugFu::log($objects);
		return $objects;
	}
	

	/**
	 * Checks groups for each user and applies tags
	 *
	 * @access public
	 * @return void
	 */

	 public function batch_step_postTypes( $post_id, $post_type ) {
		BugFu::log("batch_step_postTypes init" . $post_id);
		BugFu::log($post_type->name);

		// Get the post data using the post ID
		$post_data = get_post($post_id);

		// Check if the post exists
		if (!$post_data) {
			BugFu::log("No post found for ID " . $post_id);
			return;
		}

		wp_fusion()->crm->add_object( $post_data, $post_type->name, $map_meta_fields = true );

		// $groups = bp_get_user_groups( $user_id );

		// if ( ! empty( $groups ) ) {

		// 	foreach ( $groups as $group ) {

		// 		$this->join_group( $group->group_id, $user_id );

		// 	}
		// }

	}

	function add_custom_meta_box_field( $post, $settings) {
		// Ensure the nonce field is added only once
		if (!isset($settings['custom_field_nonce'])) {
			wp_nonce_field('custom_meta_box_nonce', 'custom_meta_box_nonce');
		}
	
		// Retrieve the existing value from the database
		$custom_field_value = get_post_meta($post->ID, 'monday_item_id', true);

		$edit_url = wp_fusion_postTypes()->crm->get_object_edit_url($post, $custom_field_value);

		// Construct the label HTML
		$label_html = '<label for="custom_meta_field"><b><small>CRM Object ID:</small></b>';

		if (false !== $edit_url) {
			$label_html .= '<small> - <a href="' . esc_url($edit_url) . '" target="_blank">' . sprintf(esc_html__('View in %s', 'wp-fusion-lite'), wp_fusion()->crm->name) . ' &rarr;</a></small>';
		}

		$label_html .= '</label>';
	
		// Display the form field with the clipboard icon inside the input field
		echo '<style>
			.custom-meta-box-container {
				position: relative;
				display: inline-block;
				width: 100%;
			}
			.custom-meta-box-container input {
				width: calc(100% - 30px); /* Adjust input width to leave space for the icon */
				padding-right: 30px; /* Add padding to the right to prevent text overlap with the icon */
			}
			.custom-meta-box-container .dashicons {
				position: absolute;
				right: 10px;
				top: calc(50% + 3px); /* Adjust the position to center the icon vertically */
				transform: translateY(-50%);
				cursor: pointer;
				color: #0073aa; /* Optional: Add color to the icon */
			}
			.copy-confirmation {
				display: none;
				position: absolute;
				top: -25px;
				right: 0;
				background-color: #4caf50;
				color: white;
				padding: 5px 10px;
				border-radius: 3px;
				font-size: 12px;
				box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.2);
			}
		</style>';

		
	
		
		echo $label_html;
		echo '<div class="custom-meta-box-container">';
		echo '<input disabled="disabled" type="text" id="custom_meta_field" name="custom_meta_field" value="' . esc_attr($custom_field_value) . '" />';
		echo '<span class="dashicons dashicons-admin-page" id="copy-icon"></span>';
		echo '<div class="copy-confirmation" id="copy-confirmation">Copied!</div>';
		echo '</div>';
	
		// JavaScript for copying the value to clipboard and showing confirmation
		echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
				const copyIcon = document.getElementById("copy-icon");
				const customMetaField = document.getElementById("custom_meta_field");
				const copyConfirmation = document.getElementById("copy-confirmation");
	
				copyIcon.addEventListener("click", function() {
					// Create a temporary textarea element to hold the value
					const tempTextarea = document.createElement("textarea");
					tempTextarea.value = customMetaField.value;
					document.body.appendChild(tempTextarea);
	
					// Select the text and copy it to clipboard
					tempTextarea.select();
					document.execCommand("copy");
	
					// Remove the temporary textarea
					document.body.removeChild(tempTextarea);
	
					// Show the confirmation message
					copyConfirmation.style.display = "block";
	
					// Hide the confirmation message after 2 seconds
					setTimeout(function() {
						copyConfirmation.style.display = "none";
					}, 2000);
				});
			});
		</script>';
	}
	
	
	
	
	






	/**
	 * Validation for contact field data
	 *
	 * @access public
	 * @return mixed
	 */
	public function validate_field_post_fields( $input, $setting, $options_class ) {
		//BugFu::log("validate_field_post_fields init");
		// BugFu::log($input);

		// Unset the empty ones.
		foreach ( $input as $field => $data ) {

			if ( 'new_field' === $field ) {
				continue;
				// BugFu::log("PASS 1");
			}

			if ( empty( $data['active'] ) && empty( $data['crm_field'] ) ) {
				unset( $input[ $field ] );
				// BugFu::log("UNSET");
			}
		}

		// New fields.
		if ( ! empty( $input['new_field']['key'] ) ) {
			// BugFu::log("new_field not empty");

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

		$input = apply_filters( 'wpf_contact_fields_save', $input );

		return wpf_clean( $input );

	}

	/**
	 * Validation for contact field data
	 *
	 * @access public
	 * @return mixed
	 */
	public function validate_field_post_type_sync_post( $input, $setting, $options_class ) {
		BugFu::log("validate_field_post_type_sync_post init");
		// BugFu::log($input);
		// BugFu::log($setting);
		// BugFu::log($options_class);
		return wpf_clean( $input );

	}


	// /**
	//  * Triggered when post updates.
	//  *
	//  * @since 1.0.0
	//  *
	//  * @param int     $user_id       User ID.
	//  * @param WP_User $old_user_data Object containing user's data prior to update.
	//  * @param array   $userdata      The raw array of data passed to wp_insert_user().
	//  */
	// public function post_updated( $post_id, $post_data, $old_post_data ) {
	// 	BugFu::log("post_updated init");

	// 	 // Avoid infinite loops
	// 	remove_action('post_updated', 'post_updated', 10, 3);

	// 	// Check post type and exclude revisions
	// 	if (wp_is_post_revision($post_id)) {
	// 		add_action('post_updated', 'post_updated', 10, 3);
	// 		return;
	// 	}

	// 	// Check if this is a new post (creation)
	// 	if ($old_post_data->post_status == 'auto-draft') {
	// 		// Your code to handle post creation
	// 		//error_log('Post Created: ' . $post_ID);
	// 		BugFu::log('Post Created: ' . $post_id);
	// 	} else {
	// 		// Your code to handle post update
	// 		//error_log('Post Updated: ' . $post_ID);
	// 		BugFu::log('Post Updated: ' . $post_id);
	// 	}

	// 	$bypass = apply_filters( 'wpf_bypass_post_updated', false, wpf_clean( wp_unslash( $_REQUEST ) ) );

	// 	// This doesn't need to run twice on a page load.
	// 	remove_action( 'post_updated', array( $this, 'post_updated' ), 10, 2 );

	// 	// if ( did_action( 'retrieve_password' ) ) {
	// 	// 	return; // don't do this when a password reset is requested.
	// 	// }

	// 	if ( ! empty( $_POST ) && false === $bypass ) {

	// 		$post_data = wpf_clean( wp_unslash( $_POST ) );

	// 		// Maybe detect email address changes.

	// 		// if ( isset( $userdata['user_email'] ) && is_a( $old_user_data, 'WP_User' ) && strtolower( $userdata['user_email'] ) !== strtolower( $old_user_data->user_email ) ) {
	// 		// 	$post_data['user_email']          = $userdata['user_email'];
	// 		// 	$post_data['previous_user_email'] = $old_user_data->user_email;
	// 		// }

	// 		$this->push_post_meta( $post_id, $post_data );

	// 	}

	// }


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

		BugFu::log("post_updated init");

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

		// $user_meta = $this->get_user_meta( $user_id );

		// // Merge what's in the database with what was submitted on the form.
		// $post_data = array_merge( $user_meta, $post_data );

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
		BugFu::log($post_type);

		$post_data = apply_filters( 'wpf_post_updated', $post_data, $post_id );
		$post_meta = get_post_meta($post_id);
		BugFu::log($post_data->post_title);


		// Allows for cancelling of registration via filter.
		if ( null === $post_data ) {
			return false;
		}

		if ( empty( $post_data->post_title ) ) {

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
		BugFu::log($item_id);

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

			$result = wp_fusion()->crm->update_object( $item_id, $post_data, 'post', $map_meta_fields = true );;

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
	


	public function push_post_meta( $post_id, $post_meta = false ) {
		BugFu::log("push_post_meta init");
		BugFu::log($post_id);

		// if ( ! wpf_get_option( 'push' ) ) {
		// 	return;
		// }

		do_action( 'wpf_push_post_meta_start', $post_id, $post_meta );

		// If nothing's been supplied, get the latest from the DB.

		if ( false === $post_meta ) {
			$post_meta = $this->get_post_meta( $post_id );
		}

		BugFu::log($post_meta);

		$post_meta = apply_filters( 'wpf_post_update', $post_meta, $post_id );
		// BugFu::log($post_meta);

		// Allows for cancelling via filter.

		if ( null === $post_meta ) {
			wpf_log( 'notice', $post_id, 'Push post meta aborted: no metadata found for post.' );
			return false;
		}

		// get connected post type board
		$post_type = get_post_type( $post_id );
		$options = get_option('wpf_options');

		// Check if the post_type_sync_ key exists and its value
		if (isset($options['post_type_sync_' . $post_type])) {
			$associated_crm_object_id = $options['post_type_sync_' . $post_type];
		}

		

		if ( empty( $post_meta ) || empty( $associated_crm_object_id ) ) {
			// BugFu::log("no post meta or associated_crm_object_id");
			return;
		}
		BugFu::log("PASS");

		wpf_log( 'notice', 0, 'TEST' );

		wpf_log( 'info', $post_id, 'Pushing meta data to ' . wp_fusion()->crm->name . ': ', array( 'meta_array' => $post_meta ) );

		// Check if contact already exists in CRM.
		$item_id = $this->get_item_id( $post_id, true );

		$result = $this->update_post( $item_id, $post_type, $associated_crm_object_id, $post_meta );

		if ( is_wp_error( $result ) ) {

			wpf_log( $result->get_error_code(), $post_id, 'Error while updating meta data: ' . $result->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
			return false;

		} elseif ( false === $result ) {

			// If nothing was updated.
			return false;

		}

		do_action( 'wpf_pushed_post_meta', $post_id, $associated_crm_object_id, $post_meta );

		return true;

	}

	/**
	 * Adds a new post
	 *
	 * @access public
	 * @return int|WP_Error Item ID or WP_Error.
	 */
	public function add_post( $post_id, $post_type, $associated_crm_object_id, $post_meta, $map_meta_fields = true ) {
		BugFu::log("add_post init");

		// Ensure the API key and board ID are available
		$api_key = wpf_get_option('monday_key');
		// $board_id = $this->get_selected_board();

		// BugFu::log($api_key);
		// BugFu::log($board_id);
	
		if ( empty($api_key) || empty($associated_crm_object_id) ) {
			return new WP_Error('missing_api_key_or_associated_crm_object_id', __('API key or associated_crm_object_id is missing.', 'wp-fusion'));
		}
	
		// If set to true, WP Fusion will convert the field keys from WordPress meta keys into the field names in the CRM.
		if ( $map_meta_fields ) {
			$item_data = $this->map_post_meta_fields( $post_meta, $post_type );
		}
	
		// Prepare the column values in JSON format dynamically
		$column_values = array();
		foreach ( $item_data as $key => $value ) {
			if ( $key === 'email' ) {
				$column_values[$key] = array(
					'email' => $value,
					'text' => $value
				);
			} else {
				$column_values[$key] = $value;
			}
		}
	
		$column_values_json = json_encode( $column_values, JSON_UNESCAPED_SLASHES );
	
		// Prepare the GraphQL mutation
		$mutation = 'mutation {
			create_item (board_id: ' . $associated_crm_object_id . ', item_name: "' . esc_js( $post_meta['name'] ) . '", column_values: "' . addslashes( $column_values_json ) . '") {
				id
			}
		}';


	
		// Log the mutation for debugging
		error_log('GraphQL Mutation: ' . $mutation);
	
		// Make the request to the Monday.com API
		$response = wp_safe_remote_post(
			'https://api.monday.com/v2',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(array('query' => $mutation)),
			)
		);
	
		// Handle the response
		if ( is_wp_error( $response ) ) {
			error_log('API request error: ' . $response->get_error_message());
			return $response;
		}
	
		$body = wp_remote_retrieve_body( $response );
		error_log('API response body: ' . $body);
	
		$body_json = json_decode( $body, true );
	
		// Check if the body or data is null or empty
		if ( is_null( $body_json ) || !isset( $body_json['data'] ) ) {
			return new WP_Error('api_error', __('API error: Invalid response', 'wp-fusion'));
		}
	
		// Check for errors in the response
		if ( isset($body_json['errors']) && !empty($body_json['errors']) ) {
			$error_message = isset($body_json['errors'][0]['message']) ? $body_json['errors'][0]['message'] : 'Unknown error';
			return new WP_Error('api_error', __('API error: ', 'wp-fusion') . $error_message);
		}
	
		// Ensure the expected data structure is present
		if ( !isset( $body_json['data']['create_item']['id'] ) ) {
			return new WP_Error('api_error', __('API error: Missing item ID in response', 'wp-fusion'));
		}
	
		// Get new item ID out of response
		return $body_json['data']['create_item']['id'];
	}


	



	/**
	 * Update post - Monday
	 *
	 * @access public
	 * @return bool
	 */

	 public function update_post( $item_id, $post_type, $associated_crm_object_id, $post_meta, $map_meta_fields = true ) {
		BugFu::log("update_post init");
		BugFu::log($item_id);
		BugFu::log($post_type);
		BugFu::log($associated_crm_object_id);
		BugFu::log($post_meta);
		// Ensure the API key and board ID are available
		$api_key = wpf_get_option('monday_key');

		// // Check if contact already exists in CRM.
		// $item_id = $this->get_item_id( $post_id, true );
	
		if ( empty($api_key) ) {
			return new WP_Error('missing_api_key', __('API key is missing.', 'wp-fusion'));
			wpf_log( 'notice', 0, 'missing_api_key' );
		}

		// no item ID, no update
		if ( empty($item_id) ) {
			return;
		}
	
		// no data
		if ( ! is_array( $post_meta ) || empty( $post_meta ) ) {
			return array();
		}
	
		// If set to true, WP Fusion will convert the field keys from WordPress meta keys into the field names in the CRM.
		if ( $map_meta_fields ) {
			$data = $this->map_post_meta_fields( $post_meta, $post_type );
		}

		
	
		// Prepare the column values in JSON format dynamically
		$column_values = array();
		foreach ( $data as $key => $value ) {
			if ( $key === 'email' ) {
				$column_values[$key] = array(
					'email' => $value,
					'text' => $value
				);
			} else {
				$column_values[$key] = $value;
			}
		}
	
		$column_values_json = json_encode( $column_values, JSON_UNESCAPED_SLASHES );
		BugFu::log($column_values_json);


		$board_id = $associated_crm_object_id;
	
		// Prepare the GraphQL mutation
		$mutation = 'mutation {
			change_multiple_column_values (board_id: ' . $board_id . ', item_id: ' . $item_id . ', column_values: "' . addslashes( $column_values_json ) . '") {
				id
			}
		}';
	
		// Log the mutation for debugging
		error_log('GraphQL Mutation: ' . $mutation);
	
		// Make the request to the Monday.com API
		$response = wp_safe_remote_post(
			'https://api.monday.com/v2',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(array('query' => $mutation)),
			)
		);
	
		// Handle the response
		if ( is_wp_error( $response ) ) {
			error_log('API request error: ' . $response->get_error_message());
			return $response;
		}
	
		$body = wp_remote_retrieve_body( $response );
		error_log('API response body: ' . $body);
	
		$body_json = json_decode( $body, true );
	
		// Check if the body or data is null or empty
		if ( is_null( $body_json ) || !isset( $body_json['data'] ) ) {
			return new WP_Error('api_error', __('API error: Invalid response', 'wp-fusion'));
		}
	
		// Check for errors in the response
		if ( isset($body_json['errors']) && !empty($body_json['errors']) ) {
			$error_message = isset($body_json['errors'][0]['message']) ? $body_json['errors'][0]['message'] : 'Unknown error';
			return new WP_Error('api_error', __('API error: ', 'wp-fusion') . $error_message);
		}
	
		// Ensure the expected data structure is present
		if ( !isset( $body_json['data']['change_multiple_column_values']['id'] ) ) {
			return new WP_Error('api_error', __('API error: Missing contact ID in response', 'wp-fusion'));
		}
	
		return true;
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

	

	public function register_settings( $settings, $options ) {
		$settings['post_type_sync_header'] = array(
			'title'   => __( 'Post Type Sync', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'post-types',
		);

		$exclude_post_types = array('revision', 'nav_menu_item', 'page'); // Add post types you want to exclude

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$boards = wp_fusion()->settings->get( 'available_lists', array() );
		// BugFu::log($boards);

		foreach ( $post_types as $post_type ) {
			if (in_array($post_type->name, $exclude_post_types)) {
				continue; // Skip excluded post types
			}

			// Define the custom type for the post type with a sync button
			$settings['post_type_sync_' . $post_type->name] = array(
				'title'   => $post_type->label,
				'type'    => 'sync_button',
				'section' => 'post-types',
				'choices' => $boards,
				'attributes'  => array(
					'data-post_type' => $post_type->name,
					'data-nonce'     => wp_create_nonce('wpf_sync_post_type_fields'),
				),
				'post_fields' => array( 'post_type_sync_' . $post_type->name ),
			);
		}

        $settings['postType_post_fields'] = array(
			'title'   => __( 'Post Fields', 'wp-fusion-lite' ),
			'std'     => array(),
			'type'    => 'post-fields',
			'section' => 'post-fields',
			'choices' => array(),
		);

		return $settings;
	}

	public function show_field_postType_fields( $id, $field ) {
		// BugFu::log("show_field_postType_fields init");

		// BugFu::log($field, false);
		
		// Lets group contact fields by integration if we can
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

		// Append ungrouped fields.
		$field_groups['extra'] = array(
			'title'  => __( 'Additional <code>wp_usermeta</code> Table Fields (For Developers)', 'wp-fusion-lite' ),
			'fields' => array(),
			'url'    => 'https://wpfusion.com/documentation/getting-started/syncing-contact-fields/#additional-fields',
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
		// BugFu::log($field['choices']);

		foreach ( wpf_get_option( 'postType_post_fields', array() ) as $key => $data ) {

			if ( ! isset( $field['choices'][ $key ] ) ) {
				$field['choices'][ $key ] = $data;
			}
		}

		if ( empty( $this->options[ $id ] ) ) {
			
			$this->options[ $id ] = array();
		}

		// Set some defaults to prevent notices, and then rebuild fields array into group structure.

		

		foreach ( $field['choices'] as $meta_key => $data ) {

			if ( empty( $this->options[ $id ][ $meta_key ] ) || ! isset( $this->options[ $id ][ $meta_key ]['crm_field'] ) || ! isset( $this->options[ $id ][ $meta_key ]['active'] ) ) {
				$this->options[ $id ][ $meta_key ] = array(
					'active'    => false,
					'pull'      => false,
					'crm_field' => false,
				);
			}

			// Set Pull to on by default.

			if ( ! empty( $this->options[ $id ][ $meta_key ] ) && ! empty( $this->options[ $id ][ $meta_key ]['active'] ) && ! isset( $this->options[ $id ][ $meta_key ]['pull'] ) && empty( $data['pseudo'] ) ) {
				$this->options[ $id ][ $meta_key ]['pull'] = true;
			}

			if ( ! empty( $this->options['custom_metafields'] ) && in_array( $meta_key, $this->options['custom_metafields'] ) ) {

				$field_groups['custom']['fields'][ $meta_key ] = $data;

			} elseif ( isset( $data['group'] ) && isset( $field_groups[ $data['group'] ] ) ) {

				$field_groups[ $data['group'] ]['fields'][ $meta_key ] = $data;

			} else {

				$field_groups['extra']['fields'][ $meta_key ] = $data;

			}
		}

		if ( wp_fusion()->crm->hide_additional ) {

			foreach ( $field_groups['extra']['fields'] as $key => $data ) {

				if ( ! isset( $data['active'] ) || $data['active'] != true ) {
					unset( $field_groups['extra']['fields'][ $key ] );
				}
			}
		}

		/**
		 * This filter is used in the CRM integrations to link up default field
		 * pairings. We used to use wpf_initialize_options but that doesn't work
		 * since it runs before any new fields are added by the wpf_meta_fields
		 * filter (above). This filter will likely be removed in a future update
		 * when we standardize how standard fields are managed.
		 *
		 * @since 3.37.24
		 *
		 * @param array $options The WP Fusion options.
		 */

		$this->options = apply_filters( 'wpf_initialize_options_post_fields', $this->options );

		// These fields should be turned on by default

		if ( empty( $this->options['contact_fields']['user_email']['active'] ) ) {
			$this->options['contact_fields']['first_name']['active'] = true;
			$this->options['contact_fields']['last_name']['active']  = true;
			$this->options['contact_fields']['user_email']['active'] = true;
		}

		$field_types = array( 'text', 'date', 'multiselect', 'checkbox', 'state', 'country', 'int', 'raw', 'tel' );

		$field_types = apply_filters( 'wpf_meta_field_types', $field_types );

		echo '<p>' . sprintf( esc_html__( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion-lite' ), '<a href="https://wpfusion.com/documentation/getting-started/syncing-contact-fields/" target="_blank">', '</a>' ) . '</p>';
		echo '<br />';

		// Display contact fields table.
		echo '<table id="contact-fields-table" class="table table-hover">';

		echo '<thead>';
		echo '<tr>';
		echo '<th class="sync">' . esc_html__( 'Sync', 'wp-fusion-lite' ) . '</th>';
		// echo '<th class="sync">' . esc_html__( 'Pull', 'wp-fusion-lite' ) . '</th>'; @TODO.
		echo '<th>' . esc_html__( 'Name', 'wp-fusion-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Meta Field', 'wp-fusion-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'wp-fusion-lite' ) . '</th>';
		echo '<th>' . sprintf( esc_html__( '%s Field', 'wp-fusion-lite' ), esc_html( wp_fusion()->crm->name ) ) . '</th>';
		echo '</tr>';
		echo '</thead>';

		if ( empty( $this->options['table_headers'] ) ) {
			$this->options['table_headers'] = array();
		}

		foreach ( $field_groups as $group => $group_data ) {

			if ( empty( $group_data['fields'] ) && $group != 'extra' ) {
				continue;
			}

			// Output group section headers.
			if ( empty( $group_data['title'] ) ) {
				$group_data['title'] = 'none';
			}

			$group_slug = strtolower( str_replace( ' ', '-', $group_data['title'] ) );

			if ( ! isset( $this->options['table_headers'][ $group_slug ] ) ) {
				$this->options['table_headers'][ $group_slug ] = false;
			}

			if ( 'standard-wordpress-fields' !== $group_slug ) { // Skip the first one

				echo '<tbody class="labels">';
				echo '<tr class="group-header"><td colspan="5">';
				echo '<label for="' . esc_attr( $group_slug ) . '" class="group-header-title ' . ( $this->options['table_headers'][ $group_slug ] == true ? 'collapsed' : '' ) . '">';
				echo wp_kses_post( $group_data['title'] );

				if ( isset( $group_data['url'] ) ) {
					echo '<a class="table-header-docs-link" href="' . esc_url( $group_data['url'] ) . '" target="_blank">' . esc_html__( 'View documentation', 'wp-fusion-lite' ) . ' &rarr;</a>';
				}

				echo '<i class="fa fa-angle-down"></i><i class="fa fa-angle-up"></i></label><input type="checkbox" ' . checked( $this->options['table_headers'][ $group_slug ], true, false ) . ' name="wpf_options[table_headers][' . $group_slug . ']" id="' . $group_slug . '" data-toggle="toggle">';
				echo '</td></tr>';
				echo '</tbody>';

			}

			$table_class = 'table-collapse';

			if ( $this->options['table_headers'][ $group_slug ] == true ) {
				$table_class .= ' hide';
			}

			if ( ! empty( $group_data['disabled'] ) ) {
				$table_class .= ' disabled';
			}

			echo '<tbody class="' . esc_attr( $table_class ) . '">';

			foreach ( $group_data['fields'] as $user_meta => $data ) {

				if ( ! is_array( $data ) ) {
					$data = array();
				}

				// Allow hiding for internal fields.
				if ( isset( $data['hidden'] ) ) {
					continue;
				}

				echo '<tr' . ( $this->options[ $id ][ $user_meta ]['active'] == true ? ' class="success" ' : '' ) . '>';
				echo '<td><input class="checkbox contact-fields-checkbox"' . ( empty( $this->options[ $id ][ $user_meta ]['crm_field'] ) || 'user_email' == $user_meta ? ' disabled' : '' ) . ' type="checkbox" id="wpf_cb_' . esc_attr( $user_meta ) . '" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $user_meta ) . '][active]" value="1" ' . checked( $this->options[ $id ][ $user_meta ]['active'], 1, false ) . '/></td>';
				// echo '<td><input class="checkbox"' . ( empty( $this->options[ $id ][ $user_meta ]['crm_field'] ) || ! empty( $data['pseudo'] ) ? ' disabled' : '' ) . ' type="checkbox" id="wpf_cb_pull_' . esc_attr( $user_meta ) . '" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $user_meta ) . '][pull]" value="1" ' . checked( $this->options[ $id ][ $user_meta ]['pull'], 1, false ) . '/></td>';
				echo '<td class="wp_field_label">' . ( isset( $data['label'] ) ? esc_html( wp_strip_all_tags( $data['label'] ) ) : '' );

				if ( 'user_pass' === $user_meta ) {

					$pass_message  = 'It is <em>strongly</em> recommended to leave this field disabled from sync. If it\'s enabled: <br /><br />';
					$pass_message .= '1. Real user passwords will be synced in plain text to ' . wp_fusion()->crm->name . ' when a user registers or changes their password. This is a security issue and may be illegal in your jurisdiction.<br /><br />';
					$pass_message .= '2. User passwords will be loaded from ' . wp_fusion()->crm->name . ' when webhooks are received. If not set up correctly this could result in your users\' passwords being unexpectedly reset, and/or password reset links failing to work.<br /><br />';
					$pass_message .= 'If you are importing users from ' . wp_fusion()->crm->name . ' via a webhook and wish to store their auto-generated password in a custom field, it is sufficient to check the box for <strong>Return Password</strong> on the General settings tab. You can leave this field disabled from syncing.';

					echo ' <i class="fa fa-question-circle wpf-tip wpf-tip-right" data-tip="' . esc_attr( $pass_message ) . '"></i>';
				}

				// Tooltips

				if ( isset( $data['tooltip'] ) ) {
					echo ' <i class="fa fa-question-circle wpf-tip wpf-tip-right" data-tip="' . esc_attr( $data['tooltip'] ) . '"></i>';
				}

				// Track custom registered fields.

				if ( ! empty( $this->options['custom_metafields'] ) && in_array( $user_meta, $this->options['custom_metafields'] ) ) {
					echo ' (' . esc_html__( 'Added by user', 'wp-fusion-lite' ) . ')';
				}

				echo '</td>';
				echo '<td><span class="label label-default">' . esc_html( $user_meta ) . '</span></td>';
				echo '<td class="wp_field_type">';

				if ( ! isset( $data['type'] ) ) {
					$data['type'] = 'text';
				}

				// Allow overriding types via dropdown.
				if ( ! empty( $this->options['contact_fields'][ $user_meta ]['type'] ) ) {
					$data['type'] = $this->options['contact_fields'][ $user_meta ]['type'];
				}

				if ( ! in_array( $data['type'], $field_types ) ) {
					$field_types[] = $data['type'];
				}

				asort( $field_types );

				echo '<select class="wpf_type" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $user_meta ) . '][type]">';

				foreach ( $field_types as $type ) {
					echo '<option value="' . esc_attr( $type ) . '" ' . selected( $data['type'], $type, false ) . '>' . esc_html( $type ) . '</option>';
				}

				echo '<td>';
				

				wpf_render_post_field_select( $this->options[ $id ][ $user_meta ]['crm_field'], 'wpf_options', 'postType_post_fields', $user_meta );

				// Indicate pseudo-fields that should only be synced one way.
				if ( isset( $data['pseudo'] ) ) {
					echo '<input type="hidden" name="wpf_options[' . esc_attr( $id ) . '][' . esc_attr( $user_meta ) . '][pseudo]" value="1">';
				}

				echo '</td>';

				echo '</tr>';

			}
		}

		// Add new.
		echo '<tr>';
		echo '<td><input class="checkbox contact-fields-checkbox" type="checkbox" disabled id="wpf_cb_new_field" name="wpf_options[contact_fields][new_field][active]" value="1" /></td>';
		echo '<td class="wp_field_label">Add new field</td>';
		echo '<td><input type="text" id="wpf-add-new-field" name="wpf_options[contact_fields][new_field][key]" placeholder="New Field Key" /></td>';
		echo '<td class="wp_field_type">';

		echo '<select class="wpf_type" name="wpf_options[contact_fields][new_field][type]">';

		foreach ( $field_types as $type ) {
			echo '<option value="' . esc_attr( $type ) . '" ' . selected( 'text', $type, false ) . '>' . esc_html( $type ) . '</option>';
		}

		echo '<td>';

		wpf_render_crm_field_select( false, 'wpf_options', 'contact_fields', 'new_field' );

		echo '</td>';

		echo '</tr>';

		echo '</tbody>';

		echo '</table>';

	}



	/**
	 * Filters out internal WordPress fields from showing up in syncable meta fields list and sets labels and types for built in fields
	 *
	 * @since 1.0
	 * @return array
	 */

	 public function prepare_post_meta_fields( $meta_fields ) {
		// Load the reference of standard WP field names and types.
		include WPF_EC_DIR_PATH . '/includes/wordpress-post-fields.php';
	
		// Sets field types and labels for all built in fields.
		foreach ( $wp_fields as $key => $data ) {
			if ( ! isset( $data['group'] ) ) {
				$data['group'] = 'wp';
			}
			$meta_fields[ $key ] = $data;
		}
	
		// Get any additional wp_usermeta data.
		$all_fields = get_post_meta_keys('post');
		// BugFu::log($all_fields);
	
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
	




	public function show_field_sync_button( $id, $field ) {
		$post_type = $field['attributes']['data-post_type'];

		// Retrieve the saved value from options
		$options = get_option('wpf_options');
		$select_value = isset($options[$id]) ? (string) $options[$id] : '';
		
		$boards = wp_fusion()->settings->get( 'available_lists', array() );

		// Render the select field
		echo '<select style="display:inline-block;margin-right:5px;" id="' . esc_attr( $id ) . '" class="form-control ' . esc_attr( $field['class'] ) . '" name="wpf_options[' . esc_attr( $id ) . ']">';
		echo '<option value="">' . esc_html__( 'Select Board', 'wp-fusion' ) . '</option>';
		foreach ( $boards as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $select_value, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Render the sync button
		echo '<a id="sync-post-type-fields-' . esc_attr( $post_type ) . '" class="button button-primary sync-post-type-fields" data-post_type="' . esc_attr( $post_type ) . '" data-nonce="' . esc_attr( $field['attributes']['data-nonce'] ) . '">';
		echo '<span class="dashicons dashicons-update-alt"></span>';
		echo '<span class="text">' . esc_html__( 'Sync Fields', 'wp-fusion' ) . '</span>';
		echo '</a>';
	}

	public function show_field_sync_button_end( $id, $field ) {

		if ( ! empty( $field['desc'] ) ) {
			echo '<span class="description">' . wp_kses_post( $field['desc'] ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		echo '</table><div id="connection-output"></div>';
		echo '</div>'; // close CRM div.
		// echo '<table class="form-table">';

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
		//BugFu::log("sync_post_type_fields init");

		// Load built in fields first
		// require dirname( __FILE__ ) . '/monday-fields.php';

		$built_in_fields = array();

		// foreach ( $monday_fields as $index => $data ) {
		// 	$built_in_fields[ $data['crm_field'] ] = $data['crm_label'];
		// }

		// asort( $built_in_fields );
        // Fetch the API key

        $api_key = wpf_get_option('monday_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('No API key provided.', 'wp-fusion'));
        }

		$options = get_option('wpf_options');

		// Check if the post_type_sync_ key exists and its value
		if (isset($options['post_type_sync_' . $post_type])) {
			$board = $options['post_type_sync_' . $post_type];
		}

		//BugFu::log("selected board: " . $board);

        if (empty($board)) {
            return new WP_Error('no_board_selected', __('No board selected for this post type.', 'wp-fusion'));
        }

        // Prepare the GraphQL query
        $query = '{"query": "{ boards (ids: [' . $board . ']) { columns { id title } } }"}';

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
		//BugFu::log($body);

        // Check for errors in the response
        if (isset($body['errors']) && !empty($body['errors'])) {
            $error_message = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('authentication_error', __('Authentication failed: ', 'wp-fusion') . $error_message);
        }

        if (empty($body['data']['boards'][0]['columns'])) {
            return new WP_Error('no_columns_found', __('No columns found for the selected board.', 'wp-fusion'));
        }

		

        // Process the columns
        $custom_fields = array();
		

        foreach ($body['data']['boards'][0]['columns'] as $column) {
            $custom_fields[$column['id']] = $column['title'];
        }
		//BugFu::log($custom_fields);

		$post_fields = array(
			'Standard Fields' => $built_in_fields,
			'Custom Fields'   => $custom_fields,
		);
		
		// 'wpf_set_setting_' . $key fired

        wp_fusion()->settings->set($post_type.'_fields', $post_fields);

        return true;
    }

	public function handle_post_type_fields_update( $value ) {
		//BugFu::log("handle_post_type_fields_update init");
		// if ( strpos( $key, 'post_type_fields_' ) === 0 ) {
		// 	// Extract post type from the key.
		// 	$post_type = str_replace( 'post_type_fields_', '', $key );
	
		// 	// Update the specific option for the post type fields.
		// 	update_option( 'post_type_fields_post' . $post_type, $value, false );
		// }
		update_option( 'wpf_post_fields', $value, false );
	
		return $value;
	}

	public function handle_get_post_fields( $fields ) {
		//BugFu::log("handle_get_post_fields init");
		//BugFu::log($fields);
		// Check if the post fields option is already set in the value.
		if ( !empty( $fields ) ) {
			return $fields;
		}
	
		// Retrieve the setting from the custom option.
		$setting = get_option( 'wpf_post_fields', array() );
		//BugFu::log($setting);
		return ! empty( $setting ) ? $setting : $value;
	}
	


	public function show_field_postType_fields_begin( $id, $field ) {

		if ( ! isset( $field['disabled'] ) ) {
			$field['disabled'] = false;
		}

		echo '<tr valign="top"' . ( $field['disabled'] ? ' class="disabled"' : '' ) . '>';
		echo '<td style="padding:0px">';
	}





	// public function add_post_type_tabs( $tabs ) {
		
	// 	$post_types = get_post_types( array( 'public' => true ), 'objects' );

	// 	foreach ( $post_types as $post_type ) {
	// 		$board_id = get_option( 'wpf_post_type_sync_' . $post_type->name );
	// 		if ( $board_id ) {
	// 			$tabs['wpf_' . $post_type->name . '_fields'] = $post_type->label . ' Fields';
	// 		}
	// 	}

		

	// 	return $tabs;
	// }

	// public function render_post_type_field_mapping( $tab ) {
	// 	$post_types = get_post_types( array( 'public' => true ), 'objects' );

	// 	foreach ( $post_types as $post_type ) {
	// 		if ( 'wpf_' . $post_type->name . '_fields' === $tab ) {
	// 			$board_id = get_option( 'wpf_post_type_sync_' . $post_type->name );
	// 			$fields = $this->get_post_type_fields( $post_type->name );
	// 			$board_columns = $this->get_monday_board_columns( $board_id );

	// 			echo '<h2>' . sprintf( __( '%s Fields', 'wp-fusion' ), $post_type->label ) . '</h2>';
	// 			echo '<table class="form-table">';
	// 			foreach ( $fields as $field_key => $field_label ) {
	// 				echo '<tr>';
	// 				echo '<th scope="row">' . esc_html( $field_label ) . '</th>';
	// 				echo '<td>';
	// 				echo '<select name="wpf_field_mapping[' . esc_attr( $post_type->name ) . '][' . esc_attr( $field_key ) . ']">';
	// 				echo '<option value="">' . __( 'Select a column', 'wp-fusion' ) . '</option>';
	// 				foreach ( $board_columns as $column_id => $column_name ) {
	// 					echo '<option value="' . esc_attr( $column_id ) . '">' . esc_html( $column_name ) . '</option>';
	// 				}
	// 				echo '</select>';
	// 				echo '</td>';
	// 				echo '</tr>';
	// 			}
	// 			echo '</table>';
	// 			submit_button();
	// 		}
	// 	}
	// }

	public function get_post_type_fields( $post_type ) {
		// Fetch custom fields for the post type
		// This is a simplified example. You might need to adjust it to fit your actual fields
		return array(
			'field_1' => 'Field 1',
			'field_2' => 'Field 2',
		);
	}

	public function get_monday_board_columns( $board_id ) {
		// Fetch columns from Monday.com API for the specified board
		// Replace with your existing function to get board columns
		return array(
			'col_1' => 'Column 1',
			'col_2' => 'Column 2',
		);
	}

	public function save_field_mappings() {
		if ( isset( $_POST['wpf_field_mapping'] ) ) {
			foreach ( $_POST['wpf_field_mapping'] as $post_type => $fields ) {
				update_option( 'wpf_field_mapping_' . $post_type, $fields );
			}
		}
	}

    public function configure_sections( $page, $options ) {

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
            $board_id = wpf_get_option( 'post_type_sync_' . $post_type->name );
            if ( $board_id ) {
				$page['sections'] = wp_fusion()->settings->insert_setting_after(
					'contact-fields',
					$page['sections'],
					array(
						$post_type->name . '-fields' => sprintf( __( '%s Fields', 'wp-fusion' ), $post_type->label ),
						),
				);
                // $page['sections'][ $post_type->name . '_fields' ] = sprintf( __( '%s Fields', 'wp-fusion' ), $post_type->label ) . ' →';
            }
        }

		$page['sections'] = wp_fusion()->settings->insert_setting_after(
			'advanced',
			$page['sections'],
			array(
				'post-types' => 'Post Type Sync',
				),
		);

		
    
        return $page;
    }

}

new WPF_CPT();
