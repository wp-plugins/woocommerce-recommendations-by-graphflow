<?php

/**
 * Output the related products from Graphflow API
 *
 * @access public
 * @return void
 */
function woocommerce_graphflow_related_products_display( $posts_per_page = '', $columns = '', $orderby = 'post__in', $product_id = '', $title = '' ) {
	if ( is_search() ) {
		return;
	}
	// Cart page
	if ( is_cart() ) {
		wc_get_template( 'cart/graphflow-recommended-products.php', array(
			'posts_per_page' => $posts_per_page,
			'orderby'        => $orderby,
			'columns'        => $columns,
			'title'			 => $title
		), '', untrailingslashit( plugin_dir_path( WC_GraphFlow::$file ) ) . '/templates/' );
	} else {
		wc_get_template( 'single-product/graphflow-recommended-products.php', array(
			'posts_per_page' => $posts_per_page,
			'orderby'        => $orderby,
			'columns'        => $columns,
			'product_id'	 => $product_id,
			'title'			 => $title
		), '', untrailingslashit( plugin_dir_path( WC_GraphFlow::$file ) ) . '/templates/' );
	}
}