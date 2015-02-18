<?php

/**
 * GraphFlow API class, handles all API calls to GraphFlow
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_GraphFlow_API' ) ) {
	class WC_GraphFlow_API {

		/**
		 * Production API endpoint
		 * @var string
		 * @access public
		 * @since 1.0.0
		 */
		const PRODUCTION_ENDPOINT = 'https://api.graphflow.com/';

		public $log;

		/**
		 * Endpoint to use for making calls
		 * @var string
		 * @access public
		 * @since 1.0.0
		 */
		public $endpoint;

		/**
		 * Graphflow Client Key
		 * @var string
		 * @access public
		 * @since 1.0.0
		 */
		public $client_key;

		/**
		 * Graphflow API Key
		 * @var string
		 * @access public
		 * @since 1.0.0
		 */
		public $api_key;

		/**
		 * Constructor
		 * @param string $client_key
		 * @param string $api_key
		 */
		public function __construct( $client_key, $api_key ) {
			$this->client_key = $client_key;
			$this->api_key = $api_key;
			$this->endpoint = WC_GraphFlow_API::PRODUCTION_ENDPOINT;
			$this->log = new WC_Logger();
		}

		/**
		 * Make a call to the Graphflow API
		 * @param  string $endpoint
		 * @param  json $json
		 * @param  string $method
		 * @param  boolean $append_query_args
		 * @return string
		 */
		private function perform_request( $endpoint, $json, $method = 'GET', $append_query_args = false ) {
			$args = array(
				'method' 	  => $method,
				'timeout'     => apply_filters( 'wc_graphflow_api_timeout', 45 ), // default to 45 seconds
				'redirection' => 0,
				'httpversion' => '1.0',
				'sslverify'   => false,
				'blocking'    => true,
				'headers'     => array(
					'accept'       	=> 'application/json',
					'content-type' 	=> 'application/json',
					'api_key' 		=> sanitize_text_field( $this->api_key ),
					'client_key'  	=> sanitize_text_field( $this->client_key )
				),
				'body'        => $json,
				'cookies'     => array(),
				'user-agent'  => "PHP " . PHP_VERSION . '/WooCommerce ' . get_option( 'woocommerce_db_version' )
			);

			// Append data as query args for GET requests
			$query_args = '';
			if ( 'GET' == $method || $append_query_args) {
				$q_data = json_decode( $json );
				$query_args = '?' . http_build_query( $q_data );
			}

			$response = wp_remote_request( $this->endpoint . $endpoint . $query_args, $args );
			
			$api_code = $response['response']['code'];
			if ( $api_code != 200) {
				$json_response = json_decode( $response['body'] );
				$this->log->add("graphflow", "Received error code " . $api_code . " for API call " . $endpoint . "; Message: " . $json_response->message );
			}

			if ( is_wp_error( $response ) ) {
				throw new Exception( print_r( $response, true ) );
			}

			return $response;
		}

		/**
		 * Add product to Graphflow
		 *
		 * @param int $item_id
		 * @param array  $item_data
		 * @return array
		 */
		public function add_item( $item_id, $item_data = array(), $instock = true ) {
			$call_data = array(
				'itemId' => (string)$item_id,
				'itemData' => $item_data,
				'active' => $instock,
			);

			$response = $this->perform_request( 'item/', json_encode( $call_data ), 'PUT' );

			if ( isset( $response['response']['code'] ) ) {
				// Do an update if 409 error, product already exists.
				if ( 409 == $response['response']['code'] ) {
					$update_response = $this->update_item( $item_id, $item_data );
					return $update_response;
				}
			}

			return json_decode( $response['body'] );
		}

		/**
		 * Update product on Graphflow
		 *
		 * @param  int $item_id
		 * @param  array  $item_data
		 * @return array
		 */
		public function update_item( $item_id, $item_data = array() ) {
			$call_data = array(
				'itemId' => (string)$item_id,
				'itemData' => $item_data,
			);

			$response = $this->perform_request( 'item/', json_encode( $call_data ), 'PUT' );
			return json_decode( $response['body'] );
		}

		public function update_items( $items ) {
			$response = $this->perform_request( 'item/itemlist', json_encode( $items ), 'PUT' );
			return json_decode( $response['body'] );
		}

		/**
		 * Delete item on Graphflow
		 *
		 * @param  int $item_id
		 * @return array
		 */
		public function delete_item( $item_id ) {
			$response = $this->perform_request( 'item/' . sanitize_text_field( $item_id ), '', 'DELETE' );
			return json_decode( $response['body'] );
		}

		/**
		 * Toggle item active status
		 * @param  int  $item_id
		 * @param  boolean $active
		 * @return array
		 */
		public function toggle_item( $item_id, $active = true ) {
			$call_data = array(
				'itemId' => (string)$item_id,
				'active' => (boolean)$active,
			);
			$response = $this->perform_request( 'item/activetoggle', json_encode( $call_data ), 'PUT', true );
			return json_decode( $response['body'] );
		}

		/**
		 * Log user interaction on Graphflow
		 *
		 * @param int $item_id
		 * @param int $user_id
		 * @param string $interaction_type
		 * @param int $qty
		 * @param string $price
		 * @return array
		 */
		public function add_user_interaction( $item_id, $user_id, $interaction_type, $qty = '', $price = '' ) {
			$call_data = array(
				'fromId' => (string)$user_id,
				'toId' => (string)$item_id,
				'interactionType' => (string)$interaction_type,
			);
			if ( ! empty( $qty ) ) {
				$call_data['quantity'] = (int)$qty;
			}
			if ( ! empty( $price ) ) {
				$call_data['price'] = (float)$price;
			}

			$response = $this->perform_request( 'user/interaction', json_encode( $call_data ), 'POST' );
			return json_decode( $response['body'] );
		}

		/**
		 * Log multiple user interactions at once on Graphflow
		 *
		 * @param int $user_id
		 * @param array $interactions
		 * @return array
		 */
		public function add_user_interactions( $interactions ) {
			$call_data = array();

			foreach ( $interactions as $interaction ) {
				$interaction_data = array(
					'fromId' => $interaction['fromId'],
					'toId' => $interaction['toId'],
					'interactionType' => $interaction['interactionType'],
					'timestamp' => (int)$interaction['timestamp'],
					'interactionData' => $interaction['interactionData'],
				);
				if ( isset( $interaction['price'] ) ) {
					$interaction_data['price'] = (float)$interaction['price'];
				}
				if ( isset( $interaction['quantity'] ) ) {
					$interaction_data['quantity'] = (int)$interaction['quantity'];
				}
				$call_data[] = $interaction_data;
			}
			$response = $this->perform_request( 'user/interactionlist', json_encode( $call_data ), 'POST' );
			return json_decode( $response['body'] );
		}

		/**
		 * Add a user to Grapflow
		 * @param array $user_data
		 * @return array
		 */
		public function add_user( $user_data ) {
			$response = $this->perform_request( 'user/', json_encode( $user_data ), 'PUT' );
			return json_decode( $response['body'] );
		}

		/**
		 * Get recommendations based on product
		 * @param  int $product_id
		 * @param  mixed $user_id
		 * @return array
		 */
		public function get_product_recommendations( $product_id, $user_id, $number = 5, $filters = '') {
			$response = $this->perform_request( 'recommend/item/' . absint( $product_id ) . '/similar', json_encode( array( 'userId' => $user_id, 'num' => $number, 'filters' => $filters ) ), 'GET' );
			if ( isset( $response['response']['code'] ) &&  404 == $response['response']['code'] ) {
				// if no data or not found, return an empty array
				return array();
			}
			return json_decode( $response['body'] );
		}

		/**
		 * Get recommendations based on user
		 * @param  mixed $user_id
		 * @param  int $number
		 * @return array
		 */
		public function get_user_recommendations( $user_id, $number = 5, $filters = '' ) {
			$response = $this->perform_request( '/recommend/user/' . $user_id, json_encode( array( 'num' => $number, 'filters' => $filters ) ), 'GET' );
			if ( isset( $response['response']['code'] ) &&  404 == $response['response']['code'] ) {
				// if no data or not found, return an empty array
				return array();
			}
			return json_decode( $response['body'] );
		}

		/**
		 * Get recommendations based on the user and product
		 * @param  int $product_id
		 * @param  mixed $user_id
		 * @return array
		 */
		public function get_user_product_recommendations( $product_id, $user_id, $number = 5, $filters = '' ) {
			$response = $this->perform_request( '/recommend/useritem/' . $user_id, json_encode( array( 'itemId' => absint( $product_id ), 'num' => $number, 'filters' => $filters ) ), 'GET' );
			if ( isset( $response['response']['code'] ) &&  404 == $response['response']['code'] ) {
				// if no data or not found, return an empty array
				return array();
			}
			return json_decode( $response['body'] );
		}

		/**
		 * Set an alias user id for the current user id
		 * @param int $user_id Logged in user id
		 * @param string $other_id Previous custom generated user id
		 * @return array
		 */
		public function add_user_alias( $user_id, $other_id ) {
			$call_data = array(
				'userId'  => (string)$user_id,
				'otherId' => (string)$other_id,
			);
			$response = $this->perform_request( '/user/alias', json_encode( $call_data ), 'GET', true );
			return json_decode( $response['body'] );
		}

		public function test_auth() {
			$currency = get_woocommerce_currency();
			$response = $this->perform_request( '/analytics/woocommerce/meta', json_encode( array( 'currency' => $currency) ), 'GET' );
			if ( $response['response']['code'] != 401 ) {
				return true;
			} else {
				return false;
			}

		}
	}
}
