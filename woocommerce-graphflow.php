<?php
/*
 	Plugin Name: WooCommerce Recommendations by Graphflow
 	Plugin URI: http://www.woothemes.com/products/woocommerce-recommendations/
	Description: Recommendations for WooCommerce, powered by the Graphflow recommendation engine
	Author: Gerhard Potgieter / Graphflow
	Author URI: http://www.graphflow.com/
	Version: 1.0.7
	Requires at least: 3.9
	Tested up to: 4.1

	@package WooCommerce
	@author WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '7bf22a58d7f8281cbd07f7f68231b307', '524956' );

// Check if WooCommerce is active
if ( ! is_woocommerce_active() )
	return;

function gf_activation_hook() {
	update_option( 'woocommerce_graphflow_install_notice', false );
}
register_activation_hook( __FILE__, 'gf_activation_hook' );

require_once( 'includes/class-wc-graphflow.php' );

$GLOBALS['wc_graphflow'] = new WC_GraphFlow( __FILE__ );