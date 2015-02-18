<?php

/**
 * Main GraphFlow class, handles all the hooks to integrate with GraphFlow
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_GraphFlow' ) ) {
	class WC_GraphFlow {
		protected $api;

		public static $file;

		/**
		 * Constructor
		 *
		 * @param string $file
		 * @return void
		 */
		public function __construct( $file ) {
			self::$file = $file;
			$this->includes();

			// Add product to API on page view event in case it hasn't been exported already
			add_action( 'shutdown', array( $this, 'capture_product_on_view_event' ) );

			// Add product to API on cart event in case it hasn't been exported already
			//add_action( 'woocommerce_add_to_cart', array( $this, 'capture_product_on_cart_event' ), 99, 6 );

			// Load Textdomain
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			// Track checkout actions
			add_action( 'woocommerce_thankyou', array( $this, 'capture_sale_event' ), 10, 1 );

			// Admin specific actions
			if ( is_admin() ) {
				// Load settings class
				add_filter( 'woocommerce_get_settings_pages', array( $this, 'load_settings_class' ), 10, 1 );

				// Export product on quick edit save
				add_action( 'save_post', array( $this, 'capture_product_quick_edit_save' ), 20, 2 );

				// Export product on save
				add_action( 'save_post', array( $this, 'capture_product_save' ), 20, 2 );

				// Delete product on delete
				add_action( 'delete_post', array( $this, 'capture_product_delete' ), 10, 1 );

				// Show notice on first install
				add_action('admin_notices', array( $this, 'gf_install_notice' ), 10 );
			}

			// Export customer when they register
			add_action( 'woocommerce_created_customer', array( $this, 'capture_customer' ), 10, 1 );

			// Export customers that are not already exported on page loads
			add_action( 'shutdown', array( $this, 'maybe_capture_customer' ) );

			// Enqueue Script
			add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
			// Add JS params
			add_filter( 'woocommerce_params', array( $this, 'js_params' ), 10, 1 );

			// Register user alias when logging in.
			add_action( 'wp_login', array( $this, 'register_user_alias' ), 10, 2 );

			// Add widgets
			add_action( 'widgets_init', array( $this, 'include_widgets' ), 11 );

			// Register hooks for displaying on product/cart pages
			add_action( 'plugins_loaded', array( $this, 'display_recommendations_based_on_settings' ) );

			// Update product availability based on stock status
			add_action( 'woocommerce_product_set_stock_status', array( $this, 'update_product_based_on_stock_status' ), 10, 2 );

			// Register shortcode
			add_shortcode( 'graphflow_recommendations', array( $this, 'recommendations_shortcode' ) );

			// Create a temp user ID and store in cookie
			add_action( 'init', array( $this, 'create_temp_user_cookie' ) );

			// Listen for request to export products.
			add_action( 'init', array( $this, 'custom_form_handler' ) );
		}

		/**
		 * Show install message when plugin is activated
		 * @return void
		 */
		public function gf_install_notice() {
			if (get_option('woocommerce_graphflow_install_notice') == false) {
				$admin_url = admin_url();
				echo '<div id="gf-message" class="update-nag fade"><p><strong>' .__( "Thanks for activating Graphflow recommendations! Please head over to the <a href='" . $admin_url . "admin.php?page=wc-settings&tab=graphflow'>settings</a> page to configure the plugin.", 'wc-graphflow' ) . '</strong></p></div>';
				update_option( 'woocommerce_graphflow_install_notice', true );
			}
		}

		/**
		 * Include files
		 * @return void
		 */
		public function includes() {
			include 'graphflow-template-functions.php';
		}

		/**
		 * Listen for form request to export products
		 * @return void
		 */
		public function custom_form_handler() {
			// first check auth if button is clicked
			if ( isset( $_REQUEST['gf_export'] ) ) {
				$auth = $this->get_api()->test_auth();
				if ( !$auth ) {
					echo '<div id="message" class="error fade"><p><strong>' .__( 'Authorization failed, cannot export data to Graphflow. Please check your access credentials and try again.', 'wc-graphflow' ) . '</strong></p></div>';
					return;
				}
			}
			// handle export button clicks
			if ( isset( $_REQUEST['gf_export'] ) && 'products' == $_REQUEST['gf_export'] ) {
				$this->capture_all_products();
				$http_query = remove_query_arg( 'gf_export' );
				wp_redirect( add_query_arg( array( 'gf_exported' => 'products' ), $http_query ) );
			} else if ( isset( $_REQUEST['gf_export'] ) && 'orders' == $_REQUEST['gf_export'] ) {
				$this->capture_all_orders();
				$http_query = remove_query_arg( 'gf_export' );
				wp_redirect( add_query_arg( array( 'gf_exported' => 'orders' ), $http_query ) );
			} else if ( isset( $_REQUEST['gf_exported'] ) && 'products' == $_REQUEST['gf_exported'] ) {
				echo '<div id="message" class="updated fade"><p><strong>' .__( 'Your products have been exported.', 'wc-graphflow' ) . '</strong></p></div>';
			} else if ( isset( $_REQUEST['gf_exported'] ) && 'orders' == $_REQUEST['gf_exported'] ) {
				echo '<div id="message" class="updated fade"><p><strong>' .__( 'Your orders have been exported.', 'wc-graphflow' ) . '</strong></p></div>';
			}
		}

		/**
		 * Load the textdomain for translation
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'wc-graphflow', false, dirname( plugin_basename( self::$file ) ) . '/languages/' );
		}

		/**
		 * Capture product details on view event, if not already captured
		 *
		 * @return void
		 */
		public function capture_product_on_view_event() {
			if ( is_product() ) {
				if ( is_feed() ) {
					return;
				}
				global $product;
				$this->maybe_capture_product( $product->id );
			}
		}

		/**
		 * Capture and log when customer adds product to cart
		 *
		 * @param  string $cart_item_key
		 * @param  int $product_id
		 * @param  int $quantity
		 * @param  int $variation_id
		 * @param  array $variation
		 * @param  array $cart_item_data
		 * @return void
		 */
		public function capture_product_on_cart_event( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
			$this->maybe_capture_product( $product_id );
		}

		/**
		 * Add product to Graphflow API if not aleady captured
		 * @param  int $product_id
		 * @return void
		 */
		public function maybe_capture_product( $product_id ) {
			$product = get_product( $product_id );
			if ( ! $product ) {
				$this->get_api()->log->add("graphflow", "Failed to get_product for id: " . $product_id);
				return;
			}
			// Capture product if not already captured.
			if ( 'yes' !== get_post_meta( $product->id, '_wc_graphflow_exported', true ) ) {
				$this->capture_product( $product );
			}
		}

		public function capture_product( $product ) {
			$product_data = $this->extract_product_data ( $product );
			$instock = $product->is_in_stock();
			// if the product is not visible, mark it as 'inactive'
			if ( !$product->is_visible() ) {
				$instock = false;
			}
			$this->get_api()->add_item( $product->id, apply_filters( 'woocommerce_graphflow_product_data', $product_data ), $instock );
			update_post_meta( $product->id, '_wc_graphflow_exported', 'yes' );
		}

		public function capture_products( $products ) {
			$this->get_api()->update_items( apply_filters( 'woocommerce_graphflow_product_data', $products ) );
			foreach ( $products as $product ) {
				update_post_meta( $product['itemId'], '_wc_graphflow_exported', 'yes' );
			}
		}

		public function extract_product_data( $product ) {
			$product_data = array(
				'name'				=> $product->get_title(),
				'regular_price' 	=> floatval( $product->regular_price ),
				'sale_price'		=> floatval( $product->sale_price ),
				'sku'				=> $product->get_sku(),
				'url'				=> $product->get_permalink(),
				'product_cat'		=> '[' . implode( '|', wp_get_object_terms( $product->id, 'product_cat', array( 'fields' => 'names' ) ) ) . ']',
				'product_cat_ids'	=> '[' . implode( '|', wp_get_object_terms( $product->id, 'product_cat', array( 'fields' => 'ids' ) ) ) . ']',
				'product_tag'		=> '[' . implode( '|', wp_get_object_terms( $product->id, 'product_tag', array( 'fields' => 'names' ) ) ) . ']',
				'product_tag_ids'	=> '[' . implode( '|', wp_get_object_terms( $product->id, 'product_tag', array( 'fields' => 'ids' ) ) ) . ']',
				'image_url'			=> $this->get_product_images( $product ),
				'description'		=> $product->post->post_content,
				'post_type'         => get_post_type( $product->id ),
			);
			return $product_data;
		}

		public function capture_sale_event( $order_id ) {
			$this->capture_sale_events( array( $order_id ) );
		}

		/**
		 * Capture and log when customer completes checkout
		 *
		 * @param  int $order_id
		 * @return void
		 */
		public function capture_sale_events( $order_ids, $historic = false ) {
			$products = array();
			foreach ( $order_ids as $order_id ) {
				$exported = get_post_meta( $order_id, '_wc_graphflow_exported', true );
				if ( 'yes' !== $exported ) {

					$order = new WC_Order( $order_id );
					if ( ! $order ) {
						return;
					}
					$order_currency = $order->get_order_currency();
					$order_id = $order->id;
					$customer_ip_address = $order->customer_ip_address;
					$customer_user_agent = $order->customer_user_agent;
					// extract order status, different for WC 2.1 vs 2.2
					if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			     		$order_status = $order->get_status();
					} else {
			     		$order_status = $order->status;
					}
					
					foreach ( $order->get_items() as $order_item_id => $order_item ) {
						// extract the user id for the order
						if ($historic == true) { 
							$order_user = isset($order->customer_user) ? $order->customer_user : $order->billing_email;
							if ($order_user == 0) {
								$order_user = $order->billing_email;
							}
						} else {
							$order_user = $this->get_user_id();
						}

						// check if we can get the product, log an error message if not.
						$product = get_product( $order_item['product_id'] ); 
						if ( ! $product ) {
							$this->get_api()->log->add(
								"graphflow", 
								"Failed to get_product for id: " . $order_item['product_id'] . " during order export for order id: " . $order->id );
							continue;
						}

						$this->maybe_capture_product ( $order_item['product_id'] ); 

						$graphflow_order_item = array(
							'fromId' => $order_user,
							'toId' => $order_item['product_id'],
							'interactionType' => 'purchase',
							'price' => $product->get_price(),
							'quantity' => $order_item['qty'],
							'interactionData' => array(
								'order_currency' => $order_currency,
								'transactionId' => $order_id,
								'remoteAddr' => $customer_ip_address,
								'uaRaw' => $customer_user_agent,
								'order_status' => $order_status,
								),
							'timestamp' => strtotime($order->order_date) * 1000
						);
						$products[] = $graphflow_order_item;
					}
					// For historical orders, only capture if not already captured
					if ( $historic == false  || 'yes' != get_user_meta( $order_user, '_wc_graphflow_exported', true ) ) {
						$this->capture_customer( $order_user, $historic );
					} 
				}
			}
			if ( ! empty( $products ) ) {
				$this->get_api()->add_user_interactions( $products );
			}
			foreach ( $order_ids as $order_id ) {
				// Set a meta field so we do not export again when visiting thanks page for this order
				update_post_meta( $order_id, '_wc_graphflow_exported', 'yes' );
			}
		}

		/**
		 * Get a CSV list of product image urls
		 * @param  object $product
		 * @return string
		 */
		public function get_product_images( $product ) {
			$img_urls = array();
			if ( has_post_thumbnail( $product->id ) ) {
				$img_urls[] = wp_get_attachment_url( get_post_thumbnail_id( $product->id ) );
			}

			$attachment_ids = $product->get_gallery_attachment_ids();
			if ( $attachment_ids ) {
				foreach ( $attachment_ids as $attachment_id ) {
					$img_urls[] = wp_get_attachment_url( $attachment_id );
				}
			}

			return implode( ',', $img_urls );
		}

		/**
		 * Load the settings class
		 * @param  array $settings
		 * @return array
		 */
		public function load_settings_class( $settings ) {
			$settings[] = include 'class-wc-settings-graphflow.php';
			return $settings;
		}

		/**
		 * Send all products in your store to Graphflow
		 *
		 * @return void
		 */
		public function capture_all_products() {
			// Get all products, paged for memory efficiency
			$current_page = 1;
			$finished = false;
			while ( !$finished ) {
				$query_args = array(
					'posts_per_page' => 50,
					'post_status' 	 => 'publish',
					'post_type' 	 => 'product',
					'paged'			 => $current_page,
				);
				$query = new WP_Query( $query_args );
				if ( $current_page >= $query->max_num_pages ) {
					$finished = true;
				}
				$products = array();
				while ( $query->have_posts() ) {
					$query->the_post();
					$product = get_product( get_the_ID() );
					if ( ! $product ) {
						$this->get_api()->log->add(
							"graphflow", 
							"Failed to get_product for id: " . get_the_ID() . " during product export" );
						continue;
					}
					$product_data = $this->extract_product_data( $product );
					$instock = $product->is_in_stock();
					// if the product is not visible, mark it as 'inactive'
					if ( !$product->is_visible() ) {
						$instock = false;
					}
					$item_data = array(
						'itemId' 	=> (string) $product->id,
						'itemData'  => $product_data,
						'active' 	=> $instock,
					);
					$products[] = $item_data;
				}
				$this->capture_products( $products );
				$current_page = $current_page + 1;
			}
		}

		/**
		 * Send most recent 1000 orders in your store to Graphflow
		 *
		 * @return void
		 */
		public function capture_all_orders() {
			// WC 2.2 changes the 'post_status' mechanism so we need this version check
			if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			     $post_status = array_keys( wc_get_order_statuses() );
			} else {
			     $post_status = 'publish';
			}
			// page 50 orders at a time for memory efficiency, up to max of 1000
			$current_page = 1;
			$max_pages = 20;
			$finished = false;
			while ( !$finished ) {
				$query_args = array(
					'posts_per_page' 	=> 50,
					'post_type' 	 	=> 'shop_order',
					'post_status' 		=> $post_status,
					'paged'				=> $current_page,
				);
				$query = new WP_Query( $query_args );
				if ( $current_page >= $query->max_num_pages || $current_page >= $max_pages ) {
					$finished = true;
				}
				$sales = array();
				while ( $query->have_posts() ) {
					$query->the_post();
					$sales[] = get_the_ID();
				}
				$this->capture_sale_events( $sales, true );
				$current_page = $current_page + 1;
			}
		}

		/**
		 * Capture and log when a product is updated through bulk edit
		 *
		 * @param  int $post_id
		 * @param  object $post
		 * @return void
		 */
		public function capture_product_quick_edit_save( $post_id, $post ) {
			if ( ! $_POST || is_int( wp_is_post_revision( $post_id ) ) || is_int( wp_is_post_autosave( $post_id ) ) ) return $post_id;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
			if ( ! isset( $_POST['woocommerce_quick_edit_nonce'] ) || ! wp_verify_nonce( $_POST['woocommerce_quick_edit_nonce'], 'woocommerce_quick_edit_nonce' ) ) return $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return $post_id;
			if ( $post->post_type != 'product' ) return $post_id;

			$product = get_product( $post_id );
			
			if ( ! $product ) {
				$this->get_api()->log->add(
					"graphflow", 
					"Failed to get_product for id: " . $post_id . " during capture_product_quick_edit_save");
				return $post_id;
			}

			$this->capture_product( $product );
		}

		/**
		 * Capture and log when a product is updated or added
		 *
		 * @param  int $post_id
		 * @param  object $post
		 * @return void
		 */
		public function capture_product_save( $post_id, $post ) {
			if ( ! $_POST || is_int( wp_is_post_revision( $post_id ) ) || is_int( wp_is_post_autosave( $post_id ) ) ) return $post_id;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
			if ( $post->post_type != 'product' ) return $post_id;

			$product = get_product( $post_id );
			
			if ( ! $product ) {
				$this->get_api()->log->add(
					"graphflow", 
					"Failed to get_product for id: " . $post_id . " during capture_product_save");				
				return $post_id;
			}

			$this->capture_product( $product );
		}

		/**
		 * Capture and log when a product is deleted
		 *
		 * @param  int $post_id
		 * @return void
		 */
		public function capture_product_delete( $post_id ) {
			global $post_type;
			if ( $post_type != 'product' ) return;
			if ( ! current_user_can( 'delete_posts', $post_id ) ) return;

			$this->get_api()->delete_item( $post_id );
		}

		/**
		 * Capture and log when a new customer registers
		 *
		 * @param  int $customer_id
		 * @return void
		 */
		public function capture_customer( $customer_id, $historic = false ) {
			$user_data = get_userdata( $customer_id );
			if ($user_data == false) {
				// log an error 
				$this->get_api()->log->add(
					"graphflow", 
					"Failed to get_userdata for id: " . $customer_id . " during capture_customer (historic=" . $historic . ")");					
				return;
			}
			$customer_data = array(
				'otherIds' => array( $this->get_temp_user_id() ),
				'userId' => $customer_id,
				'userData' => array(
					'name'			=> isset( $_POST['billing_first_name'] ) ? wc_clean( $_POST['billing_first_name'] ) . ' ' . wc_clean( $_POST['billing_last_name'] ) : $user_data->user_login,
					'username' 		=> $user_data->user_login,
					'first_name'	=> isset( $_POST['billing_first_name'] ) ? wc_clean( $_POST['billing_first_name'] ) : $user_data->first_name,
					'last_name' 	=> isset( $_POST['billing_last_name'] ) ? wc_clean( $_POST['billing_last_name'] ) : $user_data->last_name,
					'email' 		=> $user_data->user_email,
					'company'		=> isset( $_POST['billing_company'] ) ? wc_clean( $_POST['billing_company'] ) : '',
					'address_1'		=> isset( $_POST['billing_address_1'] ) ? wc_clean( $_POST['billing_address_1'] ) : '',
					'address_2'		=> isset( $_POST['billing_address_2'] ) ? wc_clean( $_POST['billing_address_2'] ) : '',
					'city'			=> isset( $_POST['billing_city'] ) ? wc_clean( $_POST['billing_city'] ) : '',
					'state'			=> isset( $_POST['billing_state'] ) ? wc_clean( $_POST['billing_state'] ) : '',
					'postcode'		=> isset( $_POST['billing_postcode'] ) ? wc_clean( $_POST['billing_postcode'] ) : '',
					'country'		=> isset( $_POST['billing_country'] ) ? wc_clean( $_POST['billing_country'] ) : '',
				),
			);

			$this->get_api()->add_user( apply_filters( 'woocommerce_graphflow_user_data', $customer_data, $customer_id ) );
			update_usermeta( $customer_id, '_wc_graphflow_exported', 'yes' );

			// Set alias for real-time customer registrations/updates, not for historical orders
			if ($historic == false) {
				$this->get_api()->add_user_alias( $customer_id, $this->get_temp_user_id() );
			}
		}

		/**
		 * Capture customers on page loads when they have not been captured before.
		 * @return void
		 */
		public function maybe_capture_customer() {

			// Only capture logged in users
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return;
			}

			// Only capture if not already captured
			if ( 'yes' == get_user_meta( $user_id, '_wc_graphflow_exported', true ) ) {
				return;
			}

			$this->capture_customer( $user_id, false );

			update_usermeta( $user_id, '_wc_graphflow_exported', 'yes' );
		}

		/**
		 * Return the API object
		 *
		 * @return object WC_GraphFlow_API object
		 */
		public function get_api() {
			if ( is_object( $this->api ) ) {
				return $this->api;
			}

			require 'class-wc-graphflow-api.php';
			$client_key = get_option( 'woocommerce_graphflow_client_key' );
			$api_key = get_option( 'woocommerce_graphflow_api_key' );

			return $this->api = new WC_GraphFlow_API( $client_key, $api_key );
		}

		/**
		 * Get the current user id
		 *
		 * @return int
		 */
		public function get_user_id() {
			if ( is_user_logged_in() ) {
				$user_id = get_current_user_id();
			} else {
				$user_id = $this->get_temp_user_id();
			}

			return $user_id;
		}

		/**
		 * Create a cookie on page with temp user id
		 * @return  void
		 */
		public function create_temp_user_cookie() {
			if ( ! isset( $_COOKIE['graphflow'] ) ) {
				if ( ! is_user_logged_in() ) {
					$user_id = wp_generate_password( 32, false );
					wc_setcookie( 'graphflow', $user_id, time() + ( 60 * 60 * 24 * 365 ) );
					$_REQUEST['graphflow_req_id'] = $user_id;
				}
			}
		}

		/**
		 * Get and set if not exist a custom userId if not logged in
		 * @return mixed
		 */
		public function get_temp_user_id() {
			if ( ! isset( $_COOKIE['graphflow'] ) ) {
				if ( ! is_user_logged_in() ) {
					//$user_id = wp_generate_password( 32, false );
					//wc_setcookie( 'graphflow', $user_id, time() + ( 60 * 60 * 24 * 365 ) );
					$user_id = $_REQUEST['graphflow_req_id'];
				} else {
					$user_id = get_current_user_id();
				}
			} else {
				$user_id = $_COOKIE['graphflow'];
			}
			return $user_id;
		}

		/**
		 * Register JS scripts
		 * @return void
		 */
		public function register_scripts() {
			wp_register_script( 'graphflow', plugins_url( '/assets/js/graphflow.js', self::$file ), array( 'jquery' ), '1.0', true );

			if ( is_product() || isset( $_REQUEST['add-to-cart'] ) ) {
				wp_enqueue_script( 'graphflow' );
			}
		}

		/**
		 * Add Graphflow data to JS params for use with JS tracking pixel.
		 * @param  array $params
		 * @return array
		 */
		public function js_params( $params ) {
			$params['gf_client_key'] = get_option( 'woocommerce_graphflow_client_key' );
			$params['gf_url'] = $this->get_api()->endpoint . 'beacon/beacon.gif';
			$params['gf_user'] = $this->get_user_id();

			// Page views
			if ( is_product() && ! isset( $_REQUEST['add-to-cart'] ) ) {
				global $post;
				$params['gf_action'] = 'view';
				$params['gf_product_id'] = $post->ID;
			}

			// Add to cart
			if ( isset( $_REQUEST['add-to-cart'] ) ) {
				$product_id = absint( $_REQUEST['add-to-cart'] );
				$params['gf_action'] = 'addtobasket';
				$params['gf_qty'] = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;
				$params['gf_product_id'] = $product_id;
				$product = get_product( $product_id );
				$params['gf_product_price'] = $product->get_price();
			}

			return $params;
		}

		/**
		 * Set a user alias when logging in
		 * @param  string $user_login
		 * @param  object $user
		 * @return void
		 */
		public function register_user_alias( $user_login, $user ) {
			$this->get_api()->add_user_alias( $user->ID, $this->get_temp_user_id() );
		}

		/**
		 * Register our widgets
		 * @return void
		 */
		public function include_widgets() {
			include_once( 'widgets/class-wc-widget-graphflow-recommended-products.php' );
		}

		/**
		 * Show related products based on settings
		 * @return void
		 */
		public function display_recommendations_based_on_settings() {
			if ( 'yes' == get_option( 'woocommerce_graphflow_show_on_product' ) ) {
				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
				add_action( 'woocommerce_after_single_product_summary', 'woocommerce_graphflow_related_products_display', 20, 0 );

			}

			if ( 'yes' == get_option( 'woocommerce_graphflow_show_on_cart' ) ) {
				remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
				add_action( 'woocommerce_after_cart', 'woocommerce_graphflow_related_products_display', 10, 0 );
			}

			if ( 'yes' == get_option( 'woocommerce_graphflow_show_on_shop' ) ) {
				add_action('woocommerce_before_shop_loop', 'woocommerce_graphflow_related_products_display', 10, 0 );
			}
		}

		/**
		 * Update stock status on graphflow when product stock changes.
		 * @param  int $product_id
		 * @param  string $status
		 * @return void
		 */
		public function update_product_based_on_stock_status( $product_id, $status ) {
			switch ( $status ) {
				case 'outofstock':
					$this->get_api()->toggle_item( $product_id, false );
				break;
				case 'instock':
					$this->get_api()->toggle_item( $product_id, true );
				break;
			}
		}

		/**
		 * graphflow_recommendations shortcode
		 * @param  array $atts
		 * @return string
		 */
		public function recommendations_shortcode( $atts ) {
			extract( shortcode_atts( array(
				'per_page' 	=> '4',
				'columns' 	=> '4',
				'orderby' 	=> 'post__in',
				'product' 	=> '',
				'title'		=> '',
			), $atts ) );

			if ( ! empty( $title ) ) {
				$gf_title = $title;
			} else {
				$gf_title = get_option( 'woocommerce_graphflow_shortcode_rec_title' );
			}

			ob_start();

			woocommerce_graphflow_related_products_display( $per_page, $columns, $orderby, $product, $gf_title );

			return '<div class="woocommerce columns-' . $columns . '">' . ob_get_clean() . '</div>';
		}
	}
}
