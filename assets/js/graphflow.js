function parseS4D(sentence) {
	var matches = sentence.match(/(\+|-)?((\d+(\.\d+)?)|(\.\d+))/);
	return matches && matches[0] || null;
}

jQuery( document ).ready(function( $ ) {
	if ( typeof woocommerce_params.gf_client_key != 'undefined' && typeof woocommerce_params.gf_action != 'undefined' ) {
		var gf_url = woocommerce_params.gf_url + '?';

		// User
		if ( typeof woocommerce_params.gf_user != 'undefined' ) {
			gf_url += 'userId=' + woocommerce_params.gf_user + '&';
		}

		// Handle action
		gf_url += 'interactionType=' + woocommerce_params.gf_action + '&';

		if ( woocommerce_params.gf_action == 'view' ) {
			gf_url += 'itemId=' + woocommerce_params.gf_product_id + '&';
		}
		// addtobasket
		else if ( woocommerce_params.gf_action == 'addtobasket' ) {
			gf_url += 'itemId=' + woocommerce_params.gf_product_id + '&';
			gf_url += 'quantity=' + woocommerce_params.gf_qty + '&';
			gf_url += 'price=' + woocommerce_params.gf_product_price + '&';
		}

		// Lastly add API key
		gf_url += 'client_key=' + woocommerce_params.gf_client_key;

		// Output the image
		$( '<img src="'+ gf_url +'">' ).load(function() {
      		$( this ).width(1).height(1).appendTo( 'body' );
    	});
	}

	// intercept the AJAX add to cart request
	$( ".add_to_cart_button" ).bind( "click", function() {
		var $thisbutton = $( this );

		if ( $thisbutton.is( '.product_type_simple' ) ) {

			if ( ! $thisbutton.attr( 'data-product_id' ) )
				return true;

			var price = parseS4D($thisbutton.parent( 'li.product-type-simple' ).find( '.price .amount' ).html());
			if (typeof(price) == 'undefined') {
				price = 0.0;
			}

			var data = {
				product_id: $thisbutton.attr( 'data-product_id' ),
				quantity: $thisbutton.attr( 'data-quantity' )
			};

			var gf_url = woocommerce_params.gf_url + '?';

			// User
			if ( typeof woocommerce_params.gf_user != 'undefined' ) {
				gf_url += 'userId=' + woocommerce_params.gf_user + '&';
			}

			// addtobasket
			gf_url += 'interactionType=addtobasket&';
			gf_url += 'itemId=' + data.product_id + '&';
			gf_url += 'quantity=' + data.quantity + '&';
			gf_url += 'price=' + price.toString() + '&';

			// Lastly add API key
			gf_url += 'client_key=' + woocommerce_params.gf_client_key;

			// Output the image
			$( '<img src="'+ gf_url +'">' ).load(function() {
				$( this ).width(1).height(1).appendTo( 'body' );
			});
		}
	});
});