<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $product, $woocommerce, $woocommerce_loop;

$gf_products = array();
$gf_user = $GLOBALS['wc_graphflow']->get_user_id();

if ( ! empty( $product_id ) ) {
	$gf_product_id = $product_id;
} else if ( is_product() ) {
	$gf_product_id = $product->id;
}

if ( ! empty( $title ) ) {
	$gf_title = $title;
} else if ( is_shop() || is_product_category() ) {
	$gf_title = get_option( 'woocommerce_graphflow_user_rec_title' );
} else {
	$gf_title = get_option( 'woocommerce_graphflow_product_rec_title' );
}

if ( ! empty( $posts_per_page ) && $posts_per_page > 0 ) {
	$gf_num = $posts_per_page;
} else if ( is_shop() || is_product_category() ) {
	$gf_num = get_option( 'woocommerce_graphflow_user_rec_num' );
} else {
	$gf_num = get_option( 'woocommerce_graphflow_product_rec_num' );
}

if ( ! empty( $columns ) ) {
	$gf_columns = $columns;
} else if ( is_shop() || is_product_category() ) {
	$gf_columns = get_option( 'woocommerce_graphflow_user_rec_col' );
} else {
	$gf_columns = get_option( 'woocommerce_graphflow_product_rec_col' );
}

$filters = '';

if ( isset( $gf_product_id ) ) {
	$gf_recommendations = $GLOBALS['wc_graphflow']->get_api()->get_product_recommendations( $gf_product_id, $gf_user, apply_filters( 'woocommerce_graphflow_recommended_products_total', $gf_num ), $filters );
} else {
	if ( is_product_category() && isset( $_REQUEST['product_cat'] ) ) {
		$filters = 'product_cat=' . $_REQUEST['product_cat'];
	} 
	$gf_recommendations = $GLOBALS['wc_graphflow']->get_api()->get_user_recommendations( $gf_user, apply_filters( 'woocommerce_graphflow_recommended_products_total', $gf_num ), $filters );
}

if ( isset( $gf_recommendations->result ) ) {
	foreach ( $gf_recommendations->result as $item ) {
		$gf_products[] = $item->itemId;
	}
}

if ( sizeof( $gf_products ) == 0 ) return;

$gf_products = array_slice( $gf_products, 0, apply_filters( 'woocommerce_graphflow_recommended_products_total', $gf_num ) );

$args = array(
	'post_type'           => 'product',
	'ignore_sticky_posts' => 1,
	'no_found_rows'       => 1,
	'orderby'             => $orderby,
	'post__in'            => $gf_products,
);

$products = new WP_Query( $args );

$woocommerce_loop['columns'] = apply_filters( 'woocommerce_graphflow_product_recommended_products_columns', $gf_columns );

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