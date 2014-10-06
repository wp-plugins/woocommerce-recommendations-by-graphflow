<?php
/*
 	Plugin Name: WooCommerce Recommendations by Graphflow
 	Version: 1.0.0
	Plugin URI: http://www.woothemes.com/
	Description: Recommendations for WooCommerce, powered by the Graphflow recommendation engine
	Author: Gerhard Potgieter
	Author URI: http://www.woothemes.com/
	Requires at least: 3.8
	Tested up to: 3.8

	@package WooCommerce
	@author WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function gf_activation_hook() {
	update_option( 'woocommerce_graphflow_install_notice', false );
}
register_activation_hook( __FILE__, 'gf_activation_hook' );

require_once( 'includes/class-wc-graphflow.php' );

$GLOBALS['wc_graphflow'] = new WC_GraphFlow( __FILE__ );