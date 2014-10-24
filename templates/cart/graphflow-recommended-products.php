<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $woocommerce, $woocommerce_loop;

if ( ! empty( $title ) ) {
	$gf_title = $title;
} else {
	$gf_title = get_option( 'woocommerce_graphflow_cart_rec_title' );
}

if ( ! empty( $posts_per_page ) && $posts_per_page > 0 ) {
	$gf_num = $posts_per_page;
} else {
	$gf_num = get_option( 'woocommerce_graphflow_cart_rec_num' );
}

if ( ! empty( $columns ) ) {
	$gf_columns = $columns;
} else {
	$gf_columns = get_option( 'woocommerce_graphflow_cart_rec_col' );
}

$gf_products = array();
$cart_products = array();
$gf_user = $GLOBALS['wc_graphflow']->get_user_id();

$total_prods = WC()->cart->get_cart_contents_count();
// if no contents in cart, return
if ( $total_prods <= 0 ) return;
$num_per_prod = ceil( $gf_num / $total_prods );

foreach ( WC()->cart->get_cart() as $cart_item ) {
	$cart_products[] = $cart_item['product_id'];
	$gf_recommendations = $GLOBALS['wc_graphflow']->get_api()->get_user_product_recommendations( $cart_item['product_id'], $gf_user, apply_filters( 'woocommerce_graphflow_cart_recommended_products_total', $num_per_prod ) );
	if ( isset( $gf_recommendations->result ) ) {
		foreach ( $gf_recommendations->result as $item ) {
			$gf_products[] = $item->itemId;
		}
	}
}

if ( sizeof( $gf_products ) == 0 ) return;

$gf_products = array_unique( $gf_products );

$gf_products = array_slice( $gf_products, 0, apply_filters( 'woocommerce_graphflow_cart_recommended_products_total', $gf_num ) );

$args = array(
	'post_type'           => 'product',
	'ignore_sticky_posts' => 1,
	'no_found_rows'       => 1,
	'orderby'             => $orderby,
	'post__not_in'		  => $cart_products,
	'post__in'            => $gf_products,
);

$products = new WP_Query( $args );

$woocommerce_loop['columns'] = apply_filters( 'woocommerce_graphflow_cart_recommended_products_columns', $gf_columns );

if ( $products->have_posts() ) : ?>

	<div class="graphflow_recommendations">

		<h2><?php echo $gf_title ?></h2>

		<?php woocommerce_product_loop_start(); ?>

			<?php while ( $products->have_posts() ) : $products->the_post(); ?>

				<?php wc_get_template_part( 'content', 'product' ); ?>

			<?php endwhile; // end of the loop. ?>

		<?php woocommerce_product_loop_end(); ?>

	</div>

<?php endif;

wp_reset_query();