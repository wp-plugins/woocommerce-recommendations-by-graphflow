<?php
/**
 * Graphflow Settings
 *
 * @author 		WooThemes
 * @category 	Admin
 * @package 	WooCommerce/Admin
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Settings_Graphflow' ) ) :

/**
 * WC_Settings_Graphflow
 */
class WC_Settings_Graphflow extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'graphflow';
		$this->label = __( 'Graphflow', 'wc-graphflow' );

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'display_top_buttons' ) );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		add_action( 'woocommerce_admin_field_button', array( $this, 'button_field' ), 10, 1 );
		add_action( 'woocommerce_admin_field_number_minmax', array( $this, 'number_minmax' ), 10, 1 );
	}


	/**
	 * Get settings array
	 *
	 * @return array
	 */
	public function get_settings() {
		$shortcode_text = 'You can show recommendations anywhere using the shortcode <code>[graphflow_recommendations]</code>. You can customize the number of products by using the <code>per_page</code> setting: <code>[graphflow_recommendations per_page="6"]</code>. You can customize the title using the <code>title</code> setting: <code>[graphflow_recommendations title="Your custom title"]</code>.';
		$account_text = "You can find this on your Graphflow <a href='https://app.graphflow.com/dashboard/account' target='_blank'>Account Page</a>.";
		$notice_text = '';
		if (get_option('woocommerce_graphflow_client_key') == '' or get_option('woocommerce_graphflow_api_key') == '') {
			$notice_text = "<div class='error'>We see that you don't have your Graphflow keys yet. Please <strong><a href='https://app.graphflow.com/accounts/signup' target='_blank'>sign up</a></strong> for your FREE Graphflow account to get your keys!</div>";	
		}

		return apply_filters( 'woocommerce_graphflow_settings', array(
			array( 'title' => __( 'General', 'wc-graphflow' ), 'type' => 'title', 'desc' => $notice_text, 'id' => 'general_options' ),
			array(
				'title'		=> __( 'Client Key', 'wc-graphflow' ),
				'desc'		=> __( "Your Client Key. " . $account_text , 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_client_key',
				'type'		=> 'text',
			),
			array(
				'title'		=> __( 'API Key', 'wc-graphflow' ),
				'desc'		=> __( "Your API Key. " . $account_text, 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_api_key',
				'type'		=> 'text',
			),
			array(
				'title' 	=> __( 'Export Products', 'wc-graphflow' ),
				'desc'	 	=> __( 'Export product details to Graphflow (recommended when you first install the plugin)', 'wc-graphflow' ),
				'id' 		=> 'woocommerce_graphflow_export_products',
				'text'		=> __( 'Export', 'wc-graphflow' ),
				'href'		=> add_query_arg( array( 'gf_export' => 'products' ) ),
				'type' 		=> 'button',
			),
			array(
				'title' 	=> __( 'Export Orders', 'wc-graphflow' ),
				'desc'	 	=> __( 'Export order details to Graphflow (recommended when you first install the plugin)', 'wc-graphflow' ),
				'id' 		=> 'woocommerce_graphflow_export_orders',
				'text'		=> __( 'Export', 'wc-graphflow' ),
				'href'		=> add_query_arg( array( 'gf_export' => 'orders' ) ),
				'type' 		=> 'button',
			),
			array( 'type' => 'sectionend', 'id' => 'general_options'),
			array( 'title' => __( 'Similar Products', 'wc-graphflow' ), 'type' => 'title', 'desc' => __( 'Graphflow similar product recommendations are shown on product pages. They replace the built-in WooCommerce related products.', 'wc-graphflow' ), 'id' => 'similar_options' ),
			array(
				'title' 	=> __( 'Show Similar Products', 'wc-graphflow' ),
				'desc' 		=> '',
				'id' 		=> 'woocommerce_graphflow_show_on_product',
				'default'	=> 'yes',
				'type' 		=> 'checkbox',
			),
			array(
				'title'		=> __( 'Title', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Title text to display for similar product recommendations', 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_product_rec_title',
				'default'	=> 'You may also like',
				'type'		=> 'text',
			),
			array(
				'title'		=> __( 'Number', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Number of similar product recommendations to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_product_rec_num',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array(
				'title'		=> __( 'Columns', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Number of columns to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_product_rec_col',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array( 'type' => 'sectionend', 'id' => 'similar_options'),
			array( 'title' => __( 'Cart Recommendations', 'wc-graphflow' ), 'type' => 'title', 'desc' => __( 'Graphflow cart recommendations are shown on the cart page, based on the products in the cart and the current user.', 'wc-graphflow' ), 'id' => 'cart_options' ),
			array(
				'title' 	=> __( 'Show Cart Recommendations', 'wc-graphflow' ),
				'desc' 		=> '',
				'id' 		=> 'woocommerce_graphflow_show_on_cart',
				'default'	=> 'yes',
				'type' 		=> 'checkbox',
			),
			array(
				'title'		=> __( 'Title', 'wc-graphflow' ),
				'desc_tip'		=> __( 'Title text to display for cart recommendations', 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_cart_rec_title',
				'default'	=> 'Consider adding these to your order',
				'type'		=> 'text',
			),
			array(
				'title'		=> __( 'Number', 'wc-graphflow' ),
				'desc_tip'		=> __( 'Number of cart recommendations to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_cart_rec_num',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array(
				'title'		=> __( 'Columns', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Number of columns to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_cart_rec_col',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array( 'type' => 'sectionend', 'id' => 'user_rec_options'),
			array( 'title' => __( 'User Recommendations', 'wc-graphflow' ), 'type' => 'title', 'desc' => __( 'Graphflow user recommendations are shown on your main Shop page, as well as Product Category pages. On Category pages, the recommendations are filtered by the relevant Category. <p>Recommendations are personalized to the current user if we have enough user history for them. If not, we base the recommendations on the popularity of your products.', 'wc-graphflow' ), 'id' => 'user_rec_options' ),
			array(
				'title' 	=> __( 'Show User Recommendations', 'wc-graphflow' ),
				'desc' 		=> '',
				'id' 		=> 'woocommerce_graphflow_show_on_shop',
				'default'	=> 'yes',
				'type' 		=> 'checkbox',
			),
			array(
				'title'		=> __( 'Title', 'wc-graphflow' ),
				'desc_tip'		=> __( 'Title text to display for user recommendations on Shop and Category pages', 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_user_rec_title',
				'default'	=> 'Recommended for you',
				'type'		=> 'text',
			),
			array(
				'title'		=> __( 'Number', 'wc-graphflow' ),
				'desc_tip'		=> __( 'Number of user recommendations to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_user_rec_num',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array(
				'title'		=> __( 'Columns', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Number of columns to display', 'wc-graphflow' ),
				'id'		=> 'woocommerce_graphflow_user_rec_col',
				'type'  	=> 'number_minmax',
				'css' 		=> 'max-width:50px;',
				'min'   	=> 1,
				'max'   	=> 12,
				'default'   => 4,
			),
			array( 'type' => 'sectionend', 'id' => 'user_rec_options'),
			array( 'title' => __( 'Recommendations Shortcode', 'wc-graphflow' ), 'type' => 'title', 'desc' => $shortcode_text, 'id' => 'recommendation_options' ),
			array(
				'title'		=> __( 'Default Title', 'wc-graphflow' ),
				'desc_tip'	=> __( 'Default title text to display when using the shortcode', 'wc-graphflow' ),
				'css' 		=> 'min-width:400px;',
				'id'		=> 'woocommerce_graphflow_shortcode_rec_title',
				'default'	=> 'Recommended for you',
				'type'		=> 'text',
			),
			array( 'type' => 'sectionend', 'id' => 'recommendation_options'),
		) ); // End graphflow settings
	}

	/**
	 * Check authentication of access credentials
	 */
	public function gf_auth_check() {
		$auth = $GLOBALS['wc_graphflow']->get_api()->test_auth();
		if ($auth) {
			$notice_text = "Your Graphflow access keys have been verified!";
			WC_Admin_Settings::add_message( $notice_text );	
		} else {
			$notice_text = "Your Graphflow access keys failed verification. Please check your Graphflow Account Page to retrieve the correct keys.";	
			WC_Admin_Settings::add_error( $notice_text );	
		}	
	}

	/**
	 * Save settings
	 */
	public function save() {
		$settings = $this->get_settings();

		WC_Admin_Settings::save_fields( $settings );
		$this->gf_auth_check();
	}

	public function display_top_buttons() {
		?>
			<div id="gf_logo_buttons">
				<a href="http://www.graphflow.com" target="_blank"><?php echo '<img src="' . plugins_url( 'assets/graphflow-logo.png' , dirname(__FILE__) ) . '" style="max-width: 180px; margin-top:20px">'; ?></a>

				<p class="submit"><a href="https://www.graphflow.com/plugin/docs" target="_blank" class="button-primary">Documentation</a> <a class="docs button-primary" href="https://app.graphflow.com/dashboard/account" target="_blank">Account</a></p>

				<p>
			</div>
		<?php
	}

	public function number_minmax( $params ) {
	  $tip = '<img class="help_tip" data-tip="' . esc_attr( $params['desc_tip'] ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
	  ?>
	  <tr valign="top">
	  		<th scope="row" class="titledesc">
	  			<label for="<?php echo esc_attr( $params['id'] ); ?>"><?php echo esc_html( $params['title'] ); ?></label>
	  			<?php echo $tip; ?>
	  		</th>
	  		<td class="forminp forminp-<?php echo sanitize_title( $params['type'] ) ?>">
	  			<input
	  				name="<?php echo esc_attr( $params['id'] ); ?>"
	  				id="<?php echo esc_attr( $params['id'] ); ?>"
	  				type="number"
	  				style="<?php echo esc_attr( $params['css'] ); ?>"
	  				min="<?php echo esc_attr( $params['min'] ); ?>"
	  				max="<?php echo esc_attr( $params['max'] ); ?>"
	  				value="<?php echo esc_attr( $params['default'] ); ?>"
	  				class="<?php echo esc_attr( $params['class'] ); ?>"
	  				/> <?php echo $params['desc']; ?>
	  		</td>
	  	</tr>
	  	<?php
	}

	public function button_field( $params ) {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $params['id'] ); ?>"><?php echo esc_html( $params['title'] ); ?></label>
			</th>
			<td class="forminp forminp-<?php echo sanitize_title( $params['type'] ) ?>">
				<a
					id="<?php echo esc_attr( $params['id'] ); ?>"
	                href="<?php echo esc_url( $params['href'] ); ?>"
	                class="button"
	            /><?php echo $params['text']; ?></a><p style="margin-top:0"><?php echo wp_kses_post( $params['desc'] ); ?></p>
			</td>
		</tr>
		<?php
	}

}

endif;

return new WC_Settings_Graphflow();
