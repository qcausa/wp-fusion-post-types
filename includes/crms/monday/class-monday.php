<?php

class WPF_PT_Monday {

	

	/**
	 * Lets pluggable functions know which features are supported by the CRM
	 */

	public $supports;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function init() {
		// BugFu::log("class WPF_PT_Monday init");

		$this->supports = array();

		// add_filter( 'wpf_configure_sections', array( $this, 'configure_sections' ), 20, 2 );
		// add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		// // add_filter( 'validate_field_deals_enabled', array( $this, 'validate_deals_enabled' ), 10, 2 );
		// // add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );

		// // add_action( 'wpf_sync', array( $this, 'sync' ) );

		// // // Sync data on first run
		// // $pipelines_stages = wp_fusion()->settings->get( 'ac_pipelines_stages' );

		// // if ( $pipelines_stages != null && ! is_array( $pipelines_stages ) ) {
		// // 	$this->sync();
		// // }

        // // Register dynamic actions for all post types
		// $this->register_dynamic_actions();

	}

	public function add_object( $properties, $object_type_id, $map_meta_fields = true ) {
		BugFu::log("add_object postType2 init");
		// $object_type_id = strtolower($object_type_id);
		
		// BugFu::log($object_type_id);

		// $options = get_option('wpf_options');

		// // Ensure the API key and board ID are available
		// $api_key = wpf_get_option('monday_key');
		// if ( empty($api_key) || empty($options['post_type_sync_' . $object_type_id]) ) {
		// 	return new WP_Error('missing_api_key_or_associated_crm_object_id', __('API key or associated_crm_object_id is missing.', 'wp-fusion'));
		// }

		// BugFu::log($options['post_type_sync_' . $object_type_id]);

		// // TODO: Get all monday.com object types which would be a list of all boards
		// $boards = wp_fusion()->settings->get( 'available_lists', array() );
		// $this->log_crm_methods();

		// try {
		// 	if (isset(wp_fusion()->crm->map_post_meta_fields)) {
        //         $item_data = wp_fusion()->crm->map_post_meta_fields($properties, $object_type_id);
        //         BugFu::log($item_data);
		// 		// Do something with $result
        //     } else {
        //         throw new Exception("Method map_post_meta_fields does not exist.");
        //     }

		// } catch (Exception $e) {
		// 	BugFu::log($e->getMessage());
		// }


		// // If set to true, WP Fusion will convert the field keys from WordPress meta keys into the field names in the CRM.
		// if ( $map_meta_fields ) {
		// 	$item_data = wp_fusion()->crm->map_post_meta_fields( $properties, $object_type_id );
		// }
		// BugFu::log($item_data);

		// $properties = array(
		// 	'properties' => $properties,
		// );
		

		// $params         = $this->get_params();
		// $params['body'] = wp_json_encode( $properties );
		// BugFu::log($params['body']);

		// $response = wp_safe_remote_post( 'https://api.hubapi.com/crm/v3/objects/' . $object_type_id, $params );

		// if ( is_wp_error( $response ) ) {
		// 	return $response;
		// }

		// $response = json_decode( wp_remote_retrieve_body( $response ) );

		// return $response->id;
	}
    
    public function test() {
		BugFu::log('Hello from Another Integration');
		return 'Hello from Another Integration';
	}

	/**
	 * Maps local fields to CRM field names
	 *
	 * @access public
	 * @return array
	 */

	 public function map_post_meta_fields( $user_meta, $post_type ) {
		BugFu::log("map_post_meta_fields init");
		BugFu::log($post_type);

		if ( ! is_array( $user_meta ) || empty( $user_meta ) ) {
			return array();
		}

		$update_data = array();

		// Lists pass straight through unless mapped.

		if ( ! empty( $user_meta['lists'] ) ) {
			$update_data['lists'] = $user_meta['lists'];
		}

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
	 * Gets the URL to edit the contact in the CRM.
	 *
	 * @since  3.37.29
	 *
	 * @param  string $contact_id The contact ID.
	 * @return bool|string The URL to edit the contact, or false.
	 */
	public function get_object_edit_url( $post, $object_id ) {
		BugFu::log("get_object_edit_url init :" . $object_id);
		BugFu::log($post->post_type);

		//TODO : standardize this "get_crm_object_id" function
		$options = get_option('wpf_options');
		if (isset($options['post_type_sync_' . $post->post_type]) && !empty($options['post_type_sync_' . $post->post_type])) {
			$board_id = $options['post_type_sync_' . $post->post_type];
		} else {
			return false;
		}

		if ( empty( $object_id ) ) {
			return false;
		}

		// TODO: Add this back in later
		// if ( in_array( 'web_id', $this->supports, true ) ) {

		// 	// Mailchimp and Bento.

		// 	$user_id = wpf_get_user_id( $contact_id );

		// 	if ( ! $user_id ) {
		// 		return false;
		// 	}

		// 	$contact_id = get_user_meta( $user_id, wp_fusion()->crm->slug . '_web_id', true );

		// 	if ( empty( $contact_id ) ) {
		// 		return false;
		// 	}
		// }

		if ( isset( wp_fusion()->crm->edit_url ) && ! empty( wp_fusion()->crm->edit_url ) ) {
			$edit_url = sprintf( wp_fusion()->crm->edit_url, "boards/" . $board_id . "/pulses/%s" );
			//BugFu::log($edit_url);

			// take monday account slug and append object_id parameters "board" and "pulse"	
			BugFu::log(sprintf( $edit_url, $object_id ));
			return sprintf( $edit_url, $object_id );
			
		} else {
			return false;
		}

	}





















	/**
	 * Validate deals/pipelines/stages to make sure it's not empty
	 *
	 * @access public
	 * @return string Input
	 */

	public function validate_deals_enabled( $input, $setting ) {

		if ( $input == true && empty( $_POST['wpf_options']['deals_pipeline_stage'] ) ) {
			return new WP_Error( 'error', 'You must specify an initial Pipeline and Stage to send Deals to ActiveCampaign' );
		} else {
			return $input;
		}

	}

	/**
	 * Adds total revenue field to the list of meta fields and activates it
	 *
	 * @since 1.0
	 * @return array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$revenue_field = wp_fusion()->settings->get( 'total_revenue_field' );

		if ( empty( $revenue_field ) ) {
			return $meta_fields;
		}

		// Set field to active and selected CRM field
		$contact_fields = wp_fusion()->settings->get( 'contact_fields' );

		$contact_fields['wpf_total_revenue'] = array(
			'active'    => 1,
			'crm_field' => $revenue_field['crm_field'],
		);

		wp_fusion()->settings->set( 'contact_fields', $contact_fields );

		$meta_fields['wpf_total_revenue'] = array(
			'label'  => 'Total Revenue',
			'type'   => 'text',
			'hidden' => true,
		);

		return $meta_fields;

	}

	/**
	 * Syncs deals and pipelines on plugin install or when Resynchronize is clicked
	 *
	 * @since 1.0
	 * @return void
	 */

	public function sync() {

		$result = wp_fusion()->crm->connect();

		if ( is_wp_error( $result ) ) {
			wp_fusion()->logger->handle( $result->get_error_code(), 0, 'Error initializing sync: ' . $result->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
			return false;
		}

		$pipelines = array();
		$results   = wp_fusion()->crm->app->api( 'deal/pipeline_list' );
		foreach ( $results as $result ) {
			if ( is_object( $result ) ) {
				$pipelines[ $result->id ] = $result->title;
			}
		}

		$choices = array();
		$results = wp_fusion()->crm->app->api( 'deal/stage_list' );
		foreach ( $results as $result ) {
			if ( is_object( $result ) ) {
				$choices[ $result->pipeline . ',' . $result->id ] = $pipelines[ $result->pipeline ] . ' &raquo; ' . $result->title;
			}
		}

		if ( empty( $choices ) ) {
			$choices = array();
		}

		wp_fusion()->settings->set( 'ac_pipelines_stages', $choices );

	}


	/**
	 * Add an order
	 *
	 * @access  public
	 * @return  mixed Invoice ID or WP Error
	 */

	// public function add_order( $order_id, $contact_id, $order_label, $payment_method, $products, $line_items, $total, $currency = 'usd', $order_date, $provider = false ) {

	// 	if ( empty( $order_date ) ) {
	// 		$order_date = current_time( 'timestamp' );
	// 	}

	// 	// Convert date to GMT
	// 	$offset      = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
	// 	$order_date -= $offset;

	// 	// Get totals
	// 	$calc_totals = 0;
	// 	foreach ( $products as $product ) {
	// 		$calc_totals += (int) $product['qty'] * floatval( $product['price'] );
	// 	}

	// 	foreach ( $line_items as $line_item ) {
	// 		$calc_totals += floatval( $line_item['price'] );
	// 	}

	// 	// If we're doing revenue tracking
	// 	$revenue_field = wp_fusion()->settings->get( 'total_revenue_field' );

	// 	if ( ! empty( $revenue_field['crm_field'] ) ) {

	// 		$user_query = get_users(
	// 			array(
	// 				'meta_key'   => wp_fusion()->crm->slug . '_contact_id',
	// 				'meta_value' => $contact_id,
	// 				'fields'     => 'ID',
	// 			)
	// 		);

	// 		if ( ! empty( $user_query ) ) {

	// 			// Registered users
	// 			$totals = get_user_meta( $user_query[0], 'wpf_total_revenue', true );

	// 			if ( ! empty( $totals ) ) {

	// 				$revenue = $calc_totals + floatval( $totals );

	// 			} else {

	// 				$user_meta = wp_fusion()->crm->load_contact( $contact_id );
	// 				if ( $user_meta != false && isset( $user_meta['wpf_total_revenue'] ) && ! empty( $user_meta['wpf_total_revenue'] ) ) {
	// 					$revenue = floatval( $user_meta['wpf_total_revenue'] ) + $calc_totals;
	// 				} else {
	// 					$revenue = $calc_totals;
	// 				}
	// 			}

	// 			update_user_meta( $user_query[0], 'wpf_total_revenue', $revenue );

	// 		} else {

	// 			// Guests
	// 			$user_meta = wp_fusion()->crm->load_contact( $contact_id );

	// 			if ( $user_meta != false && isset( $user_meta['wpf_total_revenue'] ) && ! empty( $user_meta['wpf_total_revenue'] ) ) {
	// 				$revenue = $calc_totals + floatval( $user_meta['wpf_total_revenue'] );
	// 			}
	// 		}

	// 		$revenue = number_format( $revenue, 2, '.', '' );
	// 		wp_fusion()->crm->update_contact( $contact_id, array( 'wpf_total_revenue' => $revenue ) );

	// 	}

	// 	// Add deals
	// 	$pipeline_stage = wp_fusion()->settings->get( 'deals_pipeline_stage' );

	// 	if ( wp_fusion()->settings->get( 'deals_enabled' ) == true && ! empty( $pipeline_stage ) ) {

	// 		$pipeline_stage = explode( ',', $pipeline_stage );

	// 		$pipeline = $pipeline_stage[0];
	// 		$stage    = $pipeline_stage[1];

	// 		$data = array(
	// 			'title'     => $order_label,
	// 			'value'     => $calc_totals,
	// 			'currency'  => strtolower( $currency ),
	// 			'pipeline'  => $pipeline,
	// 			'stage'     => $stage,
	// 			'contactid' => $contact_id,
	// 		);

	// 		$result = wp_fusion()->crm->connect();

	// 		if ( is_wp_error( $result ) ) {
	// 			wp_fusion()->logger->handle( $result->get_error_code(), 0, 'Error adding deal: ' . $result->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
	// 			return false;
	// 		}

	// 		$data = apply_filters( 'wpf_ecommerce_activecampaign_add_deal', $data, $order_id );

	// 		$result = wp_fusion()->crm->app->api( 'deal/add', $data );

	// 		// Add note
	// 		if ( $result->success == 1 ) {

	// 			$note = 'Product(s) purchased:' . PHP_EOL;

	// 			foreach ( $products as $product ) {
	// 				$note .= $product['name'] . ' - ' . $currency . ' ' . number_format( $product['price'], 2, '.', '' ) . ( $product['qty'] > 1 ? ' (x' . $product['qty'] . ')' : '' ) . PHP_EOL;
	// 			}

	// 			$note .= PHP_EOL . admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' );

	// 			$note_result = wp_fusion()->crm->app->api(
	// 				'deal/note_add', array(
	// 					'dealid' => $result->id,
	// 					'note'   => $note,
	// 					'owner'  => $result->owner,
	// 				)
	// 			);

	// 			update_post_meta( $order_id, 'wpf_ac_deal', $result->id );

	// 			if ( wp_fusion()->settings->get( 'deep_data_enabled' ) != true ) {
	// 				return $result->id;
	// 			}
	// 		} else {

	// 			return new WP_Error( 'error', $result->message );

	// 		}
	// 	}

	// 	// Deep data integration
	// 	if ( wp_fusion()->settings->get( 'deep_data_enabled' ) == true ) {

	// 		$connection_id = wp_fusion()->crm->get_connection_id();
	// 		$customer_id   = wp_fusion()->crm->get_customer_id( $contact_id, $connection_id, $order_id );

	// 		$product_objects = array();

	// 		foreach ( $products as $product ) {

	// 			$product_objects[] = (object) array(
	// 				'externalid' => $product['id'],
	// 				'name'       => $product['name'],
	// 				'price'      => ( floatval( $product['price'] ) * 100 ),
	// 				'quantity'   => $product['qty'],
	// 				'productUrl' => get_permalink( $product['id'] ),
	// 				'imageUrl'   => $product['image'],
	// 			);

	// 		}

	// 		$user_id = wp_fusion()->user->get_user_id( $contact_id );

	// 		if ( $user_id == false ) {
	// 			$contact_data = wp_fusion()->crm->load_contact( $contact_id );
	// 			$user_email   = $contact_data['user_email'];
	// 		} else {
	// 			$user       = get_userdata( $user_id );
	// 			$user_email = $user->user_email;
	// 		}

	// 		$order_date = date( 'Y-m-d\TH:i:s', $order_date );

	// 		$body = array(
	// 			'ecomOrder' => array(
	// 				'externalid'    => $order_id,
	// 				'source'        => 1,
	// 				'email'         => $user_email,
	// 				'orderNumber'   => $order_id,
	// 				'orderProducts' => $product_objects,
	// 				'orderUrl'      => admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ),
	// 				'orderDate'     => $order_date,
	// 				'totalPrice'    => $calc_totals * 100,
	// 				'currency'      => strtoupper( $currency ),
	// 				'connectionid'  => $connection_id,
	// 				'customerid'    => $customer_id,
	// 			),
	// 		);

	// 		// Get shipping
	// 		foreach ( $line_items as $line_item ) {

	// 			if ( isset( $line_item['title'] ) && $line_item['title'] == 'Shipping' ) {
	// 				$body['ecomOrder']['shippingMethod'] = $line_item['description'];
	// 			}
	// 		}

	// 		$args = array(
	// 			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
	// 			'body'    => json_encode( $body ),
	// 			'method'  => 'POST',
	// 		);

	// 		$api_url = wp_fusion()->settings->get( 'ac_url' );
	// 		$api_key = wp_fusion()->settings->get( 'ac_key' );

	// 		$response = wp_remote_post( $api_url . '/api/3/ecomOrders?api_key=' . $api_key, $args );
	// 		$body     = json_decode( wp_remote_retrieve_body( $response ) );

	// 		if ( is_object( $body ) ) {

	// 			if ( isset( $body->errors ) ) {

	// 				return new WP_Error( 'error', $body->errors[0]->title );

	// 			} else {

	// 				update_post_meta( $order_id, 'wpf_ac_order_id', $body->ecomOrder->id );
	// 				return $body->ecomOrder->id;

	// 			}
	// 		} else {

	// 			return new WP_Error( 'error', wp_remote_retrieve_body( $response ) );

	// 		}
	// 	}

	// 	// If nothing was sent
	// 	return null;

	// }

	//
	// Deprecated - functions have been moved to core plugin
	//
	/**
	 * Gets or creates an ActiveCampaign deep data connection
	 *
	 * @since 1.2
	 * @return int
	 */

	public function get_connection_id() {

		$connection_id = get_option( 'wpf_ac_connection_id' );

		if ( ! empty( $connection_id ) ) {
			return $connection_id;
		}

		$api_url = wp_fusion()->settings->get( 'ac_url' );
		$api_key = wp_fusion()->settings->get( 'ac_key' );

		$body = array(
			'connection' => array(
				'service'    => 'WP Fusion',
				'externalid' => $_SERVER['SERVER_NAME'],
				'name'       => get_bloginfo(),
				'logoUrl'    => 'https://wpfusionplugin.com/wp-content/uploads/2017/03/fb-profile.png',
				'linkUrl'    => admin_url( 'options-general.php?page=wpf-settings' ),
			),
		);

		$args = array(
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => json_encode( $body ),
		);

		wp_fusion()->logger->handle( 'info', 0, 'Opening ActiveCampaign Deep Data connection', array( 'source' => 'wpf-ecommerce' ) );

		$response = wp_remote_post( $api_url . '/api/3/connections?api_key=' . $api_key, $args );

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		// If Deep Data not enabled
		if ( isset( $body->message ) ) {

			wp_fusion()->logger->handle( 'info', 0, 'Unable to open Deep Data Connection: ' . $body->message, array( 'source' => 'wpf-ecommerce' ) );
			update_option( 'wpf_ac_connection_id', false );

			return false;

		} elseif ( isset( $body->errors ) ) {

			if ( $body->errors[0]->title == 'The integration already exists in the system.' ) {

				// Try to look up an existing connection
				unset( $args['body'] );
				$response = wp_remote_get( $api_url . '/api/3/connections?api_key=' . $api_key, $args );

				$response = json_decode( wp_remote_retrieve_body( $response ) );

				foreach ( $response->connections as $connection ) {

					if ( $connection->service == 'WP Fusion' && $connection->externalid == $_SERVER['SERVER_NAME'] ) {

						update_option( 'wpf_ac_connection_id', $connection->id );

						return $connection->id;

					}
				}
			}

			wp_fusion()->logger->handle( 'info', 0, 'Unable to open Deep Data Connection: ' . $body->errors[0]->title, array( 'source' => 'wpf-ecommerce' ) );
			update_option( 'wpf_ac_connection_id', false );

			return false;

		}

		update_option( 'wpf_ac_connection_id', $body->connection->id );

		return $body->connection->id;

	}

	/**
	 * Deletes a registered connection
	 *
	 * @since 1.2
	 * @return void
	 */

	public function delete_connection( $connection_id ) {

		$api_url = wp_fusion()->settings->get( 'ac_url' );
		$api_key = wp_fusion()->settings->get( 'ac_key' );

		$args = array(
			'method' => 'DELETE',
		);

		wp_fusion()->logger->handle( 'notice', 0, 'Closing ActiveCampaign Deep Data connection ID <strong>' . $connection_id . '</strong>', array( 'source' => 'wpf-ecommerce' ) );

		wp_remote_request( $api_url . '/api/3/connections/' . $connection_id . '?api_key=' . $api_key, $args );

		delete_option( 'wpf_ac_connection_id' );

	}

	/**
	 * Gets or creates an ActiveCampaign deep data customer
	 *
	 * @since 1.2
	 * @return int
	 */

	public function get_customer_id( $contact_id, $connection_id, $order_id ) {

		$user_id = wp_fusion()->user->get_user_id( $contact_id );

		if ( $user_id != false ) {

			$customer_id = get_user_meta( $user_id, 'wpf_ac_customer_id', true );

			if ( ! empty( $customer_id ) ) {
				return $customer_id;
			}
		}

		$customer_id = get_post_meta( $order_id, 'wpf_ac_customer_id', true );

		if ( ! empty( $customer_id ) ) {
			return $customer_id;
		}

		if ( $user_id == false ) {
			$external_id  = 'guest';
			$contact_data = wp_fusion()->crm->load_contact( $contact_id );
			$user_email   = $contact_data['user_email'];
		} else {
			$external_id = $user_id;
			$user        = get_userdata( $user_id );
			$user_email  = $user->user_email;
		}

		$api_url = wp_fusion()->settings->get( 'ac_url' );
		$api_key = wp_fusion()->settings->get( 'ac_key' );

		$body = array(
			'ecomCustomer' => array(
				'connectionid' => $connection_id,
				'externalid'   => $external_id,
				'email'        => $user_email,
			),
		);

		$args = array(
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => json_encode( $body ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url . '/api/3/ecomCustomers?api_key=' . $api_key, $args );
		$body     = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $user_id != false ) {
			update_user_meta( $user_id, 'wpf_ac_customer_id', $body->ecomCustomer->id );
		}

		update_post_meta( $order_id, 'wpf_ac_customer_id', $body->ecomCustomer->id );

		return $body->ecomCustomer->id;

	}

}
