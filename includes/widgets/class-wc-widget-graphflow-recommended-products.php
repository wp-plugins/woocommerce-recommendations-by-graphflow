<?php
/**
 * List recommended products for user.
 *
 * @author 		WooThemes
 * @category 	Widgets
 * @package 	WooCommerce/Widgets
 * @version 	1.0
 * @extends 	WC_Widget
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Widget_Graphflow_Recommended_Products extends WC_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->widget_cssclass    = 'woocommerce widget_products';
		$this->widget_description = __( 'Display a list of recommended products for the current user on your site.', 'wc-graphflow' );
		$this->widget_id          = 'graphflow_recommendations_widget';
		$this->widget_name        = __( 'WooCommerce Graphflow Recommended Products', 'wc-graphflow' );
		$this->settings           = array(
			'title'  => array(
				'type'  => 'text',
				'std'   => __( 'Recommended For You', 'wc-graphflow' ),
				'label' => __( 'Title', 'wc-graphflow' )
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => 10,
				'std'   => 5,
				'label' => __( 'Number of products to show', 'wc-graphflow' )
			),
		);
		parent::__construct();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {

		if ( $this->get_cached_widget( $args ) )
			return;

		ob_start();
		extract( $args );

		$gf_user 	 = $GLOBALS['wc_graphflow']->get_user_id();
		$title       = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number      = absint( $instance['number'] );
		$show_rating = apply_filters( 'woocommerce_graphflow_widget_show_ratings', false );

		$gf_products = array();

		if ( is_product() ) {
			global $product;
			$gf_recommendations = $GLOBALS['wc_graphflow']->get_api()->get_user_product_recommendations( $product->id, $gf_user, $number );
		} else {
			$gf_recommendations = $GLOBALS['wc_graphflow']->get_api()->get_user_recommendations( $gf_user, $number );
		}

		if ( isset( $gf_recommendations->result ) ) {
			foreach ( $gf_recommendations->result as $item ) {
				$gf_products[] = $item->itemId;
			}
		}

		$gf_recId = '';
		if ( isset( $gf_recommendations->recId ) ) {
			$gf_recId = $gf_recommendations->recId;
		}

		$gf_products = array_unique( $gf_products );

		if ( count( $gf_products ) == 0 ) {
			return;
		}

    	$query_args = array(
    		'posts_per_page' => $number,
    		'post_type' 	 => 'product',
    		'no_found_rows'  => 1,
    		'orderby'		 => 'post__in',
    		'post__in' 		 => $gf_products
    	);

		$r = new WP_Query( $query_args );

		if ( $r->have_posts() ) {

			echo $before_widget;

			if ( $title )
				echo $before_title . $title . $after_title;

			echo '<ul class="graphflow_recommendations_widget product_list_widget" gf-recid="' . $gf_recId . '">';

			while ( $r->have_posts()) {
				$r->the_post();
				wc_get_template( 'content-widget-product.php', array( 'show_rating' => $show_rating ) );
			}

			echo '</ul>';

			echo $after_widget;
		}

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget( $args, $content );
	}
}

register_widget( 'WC_Widget_Graphflow_Recommended_Products' );