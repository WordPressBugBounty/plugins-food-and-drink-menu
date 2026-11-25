/* Shared compatibility functions and retained Ultimate payment/options. */
var cart_location = typeof fdm_ordering_data !== 'undefined' ? fdm_ordering_data.cart_location : '';
function fdm_get_fulfillment_mode() {
	return jQuery( 'input[name="fdm-fulfillment-mode"]' ).val() || jQuery( 'input[name="fdm-delivery-toggle"]:checked' ).val() || ( jQuery( 'input[name="fdm-delivery-toggle"]' ).length ? '' : 'pickup' );
}

jQuery(document).ready(function() {

	fdm_move_stuff_in_cart();

	jQuery( '.fdm-options-add-to-cart-button' ).attr( 'tabindex', '0' );
	jQuery( '.fdm-add-to-cart-button' ).attr( 'tabindex', '0' );

	jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-submit, .cart-location-bottom #fdm-order-payment-form-div' ).addClass ( 'fdm-hidden' );

	if ( jQuery( '#fdm-ordering-sidescreen-items > div' ).length ) {

		if ( cart_location == 'bottom' ) {

			jQuery( '.fdm-ordering-bottom-bar' ).removeClass( 'fdm-hidden' );
		}
		else {

			jQuery( '.fdm-ordering-sidescreen' ).removeClass( 'fdm-hidden' );
			jQuery( '.fdm-ordering-sidescreen' ).css( 'right', '0px' );

			fdm_move_stuff_in_cart();
		}

		fdm_update_cart_total();
	}

	jQuery( '#fdm-ordering-sidescreen-close, .fdm-continue-shopping-button' ).on( 'click', function() {

		if ( cart_location == 'bottom' ) {
			jQuery( '.fdm-ordering-bottom-cart-overlay' ).fadeOut( 750 );
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'bottom':'-325px' }, {duration:750} ).promise().done(function () {
				jQuery( '.fdm-ordering-sidescreen' ).addClass( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar' ).removeClass( 'fdm-ordering-bottom-bar-open' );
				jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-submit, .cart-location-bottom #fdm-order-payment-form-div' ).addClass ( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).removeClass( 'dashicons-arrow-down-alt2' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).addClass( 'dashicons-arrow-up-alt2' );
			});
		}
		else {
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'right':'-400px' }, {duration:750} ).promise().done(function () {
				jQuery( '.fdm-ordering-sidescreen' ).addClass( 'fdm-hidden' );
				jQuery( '#fdm-ordering-sidescreen-tab' ).removeClass( 'fdm-hidden' );
			});
		}
	});

	jQuery( '#fdm-ordering-sidescreen-tab' ).on( 'click', function() {

		jQuery( '#fdm-ordering-sidescreen-tab' ).addClass( 'fdm-hidden' );
		jQuery( '.fdm-ordering-sidescreen' ).removeClass( 'fdm-hidden' );
		jQuery( '.fdm-ordering-sidescreen' ).animate( { 'right':'0px' }, {duration:750} );

		fdm_move_stuff_in_cart();
	});

	jQuery( '.fdm-ordering-bottom-bar-toggle-inside' ).on( 'click', function() {
		if ( jQuery( '.fdm-ordering-sidescreen' ).hasClass( 'fdm-hidden' ) ) {
			jQuery( '.fdm-ordering-bottom-cart-overlay' ).fadeIn( 750 );
			jQuery( '.fdm-ordering-sidescreen' ).removeClass( 'fdm-hidden' );
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'bottom':'80px' }, {duration:750} );
			jQuery( '.fdm-ordering-bottom-bar' ).addClass( 'fdm-ordering-bottom-bar-open' );
			jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).removeClass( 'dashicons-arrow-up-alt2' );
			jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).addClass( 'dashicons-arrow-down-alt2' );
		}
		else {
			jQuery( '.fdm-ordering-bottom-cart-overlay' ).fadeOut( 750 );
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'bottom':'-325px' }, {duration:750} ).promise().done(function () {
				jQuery( '.fdm-ordering-sidescreen' ).addClass( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar' ).removeClass( 'fdm-ordering-bottom-bar-open' );
				jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-submit, .cart-location-bottom #fdm-order-payment-form-div' ).addClass ( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).removeClass( 'dashicons-arrow-down-alt2' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).addClass( 'dashicons-arrow-up-alt2' );
			});
		}
	});

	fdm_set_add_to_cart_actions();

	jQuery( '#fdm-ordering-popup-submit button' ).on( 'click', function( event ) {
		var post_id = jQuery( this ).data( 'post_id' );

		if ( jQuery( '.fdm-admin-order-items-table' ).length ) { 

			event.preventDefault();

			fdm_admin_add_to_cart( post_id ); 
		}
		else { fdm_add_to_cart( post_id );}
	});

	jQuery(document).on('change', '.fdm-item-quantity-wrapper input', function(ev) {
		fdm_update_cart_quantities();
	});

	jQuery( '.fdm-clear-cart-button' ).on( 'click', function() {

		jQuery( '#fdm-ordering-sidescreen-items' ).html( '' );

		if ( cart_location == 'bottom' ) {
			jQuery( '.fdm-ordering-bottom-cart-overlay' ).fadeOut( 750 );
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'bottom':'-325px' }, {duration:750} ).promise().done(function () {
				jQuery( '.fdm-ordering-sidescreen' ).addClass( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar' ).removeClass( 'fdm-ordering-bottom-bar-open' );
				jQuery( '.fdm-ordering-bottom-bar' ).addClass( 'fdm-hidden' );
				jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-submit, .cart-location-bottom #fdm-order-payment-form-div' ).addClass ( 'fdm-hidden' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).removeClass( 'dashicons-arrow-down-alt2' );
				jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).addClass( 'dashicons-arrow-up-alt2' );
			});
		}
		else {
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'right':'-400px' }, {duration:750} ).promise().done(function () {
				jQuery( '.fdm-ordering-sidescreen' ).addClass( 'fdm-hidden' );
				jQuery( '#fdm-ordering-sidescreen-tab' ).addClass( 'fdm-hidden' );
			});
		}

		fdm_update_cart_total();

		var data = 'nonce=' + fdm_ordering_popup_data.nonce + '&action=fdm_clear_cart';
		jQuery.post( ajaxurl, data, function( response ) {});
	});

	jQuery( '.fdm-tip-amount' ).on( 'change', function() {

		fdm_update_cart_total();
	} );

	jQuery( '#fdm-order-submit-button' ).on( 'click', function() {

		// disable the submit button to prevent repeated clicks
		jQuery( this ).prop( "disabled", true );

		var permalink = jQuery( this ).data( 'permalink' );

		var name = jQuery( 'input[name="fdm_ordering_name"]' ).val();
		var email = jQuery( 'input[name="fdm_ordering_email"]' ).val();
		var phone = jQuery( 'input[name="fdm_ordering_phone"]' ).val();
		var note = jQuery( 'textarea[name="fdm_ordering_note"]' ).val();
		var pickup_time = jQuery( 'input[name="fdm_pickup_time"]' ).length ? jQuery( 'input[name="fdm_pickup_time"]' ).val() : false;
		var delivery = fdm_get_fulfillment_mode();
		var tip_amount = jQuery( '.fdm-tip-amount' ).length ? jQuery( '.fdm-tip-amount' ).val() : 0;
		var discount_code = jQuery( '.fdm-discount-code' ).length ? jQuery( '.fdm-discount-code' ).val() : '';

		var custom_fields = {};

		jQuery( '.fdm-ordering-custom-fields' ).find( 'input:not([type="checkbox"]), textarea, select' ).each( function() {
			custom_fields[ this.name ] = jQuery( this ).val();
		} );
		jQuery( '.fdm-ordering-custom-fields' ).find( 'input:checked' ).each( function() {
			let index = jQuery( this ).data( 'slug' );
			custom_fields[ index ] = Array.isArray( custom_fields[ index ] ) ? custom_fields[ index ] : [];
			custom_fields[ index ].push( jQuery( this ).val() );
		}).get();

		var valid = fdm_validate_ordering_fields();

		// If any fields fail to validate, don't submit
		if ( ! valid ) {

			jQuery( '#fdm-order-submit-button' ).prop( "disabled", false );

			return false;
		}

		var data = jQuery.param({
			permalink: permalink,
			name: name,
			email: email,
			phone: phone,
			note: note,
			pickup_time: pickup_time,
			delivery: delivery,
			tip_amount: tip_amount,
			discount_code: discount_code,
			custom_fields: custom_fields,
			nonce: fdm_ordering_popup_data.nonce,
			action: 'fdm_submit_order'
		});
		jQuery.post( ajaxurl, data, function( response ) {
			
			if ( ! response.success ) { 
				jQuery( '#fdm-order-submit-button' ).prop( "disabled", false );

				jQuery( '#fdm-order-submit-button' ).before( '<p class="fdm-message">Order could not be processed. Please contact the site administrator.</p>' );

				return;
			}

			var url = new URL( window.location.href );

			url.searchParams.append( 'order_success', true );

			window.location = url;
		});
	});

	setInterval( fdm_update_order_status, 10000 );

	fdm_handle_delete_clicks();

	jQuery(function(){
		jQuery(window).resize(function(){
			var quantity_area_height = jQuery('#fdm-ordering-sidescreen-quantity').outerHeight();
			var clear_button_height = jQuery('.fdm-clear-cart-button').outerHeight();
			var clear_button_margin = ( ( quantity_area_height - clear_button_height ) / 2 ) - 1;
			jQuery('.fdm-clear-cart-button').css('top', clear_button_margin+'px');
			var sidescreen_heading_height = jQuery('#fdm-ordering-sidescreen-header').outerHeight();
			var sidescreen_heading_margin = ( sidescreen_heading_height - 30 ) / 2;
			jQuery('#fdm-ordering-sidescreen-close').css('top', sidescreen_heading_margin+'px');
		}).resize();

		jQuery('.fdm-menu-image .fdm-item-panel').each(function(){
			var thisPanel = jQuery(this);
			var thisPanelWidth = thisPanel.width();
			var thisAddToCartWidth = thisPanel.find('.fdm-add-to-cart-button').outerWidth();
			var thisOptionsAddToCartWidth = thisPanel.find('.fdm-options-add-to-cart-button').outerWidth();
			var thisAddToCartMargin = ( thisPanelWidth - thisAddToCartWidth ) / 2;
			var thisOptionsAddToCartMargin = ( thisPanelWidth - thisOptionsAddToCartWidth ) / 2;
			thisPanel.find('.fdm-add-to-cart-button').css('margin-left', thisAddToCartMargin+'px');
			thisPanel.find('.fdm-options-add-to-cart-button').css('margin-left', thisOptionsAddToCartMargin+'px');
		});
	});

	jQuery( '.fdm-ordering-popup-close, .fdm-ordering-popup-background, .fdm-ordering-popup' ).on( 'click', function() {
		
		jQuery( '.fdm-ordering-popup-background' ).addClass( 'fdm-hidden' );
		jQuery( '.fdm-ordering-popup' ).addClass( 'fdm-hidden' );

		jQuery( '#fdm-ordering-popup-options > div' ).remove();
	});

	jQuery( '.fdm-ordering-popup-inside' ).on( 'click', function( event ) {

		event.stopPropagation();
	} );

	jQuery( '.fdm-delivery-toggle' ).on( 'click', function() {

		if ( jQuery( this ).val() == 'delivery' ) {
			jQuery( '.fdm-delivery-fee' ).removeClass( 'fdm-hidden' );
		}
		else {
			jQuery( '.fdm-delivery-fee' ).addClass( 'fdm-hidden' );
		}

		fdm_update_cart_total();
	});

	jQuery( '.fdm-payment-type-toggle' ).on( 'click', function() {

		if ( jQuery( this ).val() == 'pay-in-store' ) {
			jQuery( '#fdm-order-submit' ).removeClass( 'fdm-hidden' );
			jQuery( '#fdm-order-payment-form-div' ).addClass( 'fdm-hidden' );
		}
		else {
			jQuery( '#fdm-order-submit' ).addClass( 'fdm-hidden' );
			jQuery( '#fdm-order-payment-form-div' ).removeClass( 'fdm-hidden' );
		}
	});

	jQuery( '.fdm-check-discount-code' ).on( 'click', function() {

		var discount_code = jQuery( '.fdm-discount-code' ).val();
		var order_value = jQuery( '.fdm-ordering-sidescreen-total-value' ).html();

		var params = {
			discount_code: discount_code,
			order_value: order_value,
			nonce: fdm_ordering_popup_data.nonce,
			action: 'fdm_check_discount_code',
		};

		var data = jQuery.param( params );

		jQuery.post( ajaxurl, data, function( response ) {
			if ( ! response ) { return; }

			jQuery( '.fdm-discount-code-response' ).remove();

			if ( response.success ) { 

				jQuery( '.fdm-discount-amount' ).val( response.data.discount_amount );
				jQuery( '.fdm-discount-type' ).val( response.data.discount_type );
				jQuery( '.fdm-discount-order-minimum' ).val( response.data.discount_minimum );
			}
 
			jQuery( '<span class="fdm-discount-code-response">' + response.data.msg + '</span>' ).insertBefore( '.fdm-check-discount-code' ); 

			fdm_update_cart_total();
		});
	});

	jQuery( '.fdm-ordering-bottom-bar-checkout-button' ).on( 'click', function() {

		if ( jQuery( '.fdm-ordering-sidescreen' ).hasClass( 'fdm-hidden' ) ) {
			jQuery( '.fdm-ordering-bottom-cart-overlay' ).fadeIn( 750 );
			jQuery( '.fdm-ordering-sidescreen' ).removeClass( 'fdm-hidden' );
			jQuery( '.fdm-ordering-sidescreen' ).animate( { 'bottom':'80px' }, {duration:750} );
			jQuery( '.fdm-ordering-bottom-bar' ).addClass( 'fdm-ordering-bottom-bar-open' );
			jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).removeClass( 'dashicons-arrow-up-alt2' );
			jQuery( '.fdm-ordering-bottom-bar-toggle-inside span' ).addClass( 'dashicons-arrow-down-alt2' );
		}

		if (  fdm_ordering_data.enable_payment && ( jQuery( '.fdm-payment-type-toggle[value="pay-online"]' ).is( ':checked' ) || ! fdm_ordering_data.payment_optional ) ) {

			jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-payment-form-div' ).removeClass ( 'fdm-hidden' );
		}
		else {

			jQuery( '.cart-location-bottom #fdm-ordering-contact-details, .cart-location-bottom #fdm-order-payment-toggle, .cart-location-bottom #fdm-order-submit' ).removeClass ( 'fdm-hidden' );
		}

		var y = jQuery( '.cart-location-bottom' ).scrollTop();
		var sidescreen_header_height = jQuery( '#fdm-ordering-sidescreen-header' ).outerHeight( true );
		var sidescreen_items_height = jQuery( '#fdm-ordering-sidescreen-items-and-button' ).outerHeight( true );
		var total_height = sidescreen_header_height + sidescreen_items_height;
		jQuery( '.cart-location-bottom' ).scrollTop( y + total_height );
	});
});


function fdm_render_order_options( options ) {
	Object.keys( options ).forEach(function( index ) {
		var el = options[index];
		var checked = ( el.default != null && el.default != '' ) ? 'checked' : '';

		var html = '<div class="fdm-ordering-popup-option">';
		html += '<input type="checkbox" value="' + index + '" ' + checked + '/> ';
		html += el.name;
		if ( el.cost != null && el.cost != 0 ) { html += ' ( ' + fdm_ordering_data.price_prefix + el.cost + fdm_ordering_data.price_suffix + ' )'; }
		html += '</div>';

		jQuery( '#fdm-ordering-popup-options' ).append( html );
	});

}

function fdm_sync_payment_totals( total_price ) {
	jQuery( '#fdm-ordering-sidescreen-paypal-total' ).val( ( Math.round( total_price * 100 ) / 100 ).toFixed( 2 ) );
	jQuery( '#fdm-ordering-sidescreen-stripe-total' ).val(  Math.round( total_price * 100 ).toFixed( 0 ) );
}

jQuery(document).ready(function() {
	jQuery( '.fdm-payment-type-toggle' ).on( 'click', function() {

		if ( jQuery( this ).val() == 'pay-in-store' ) {
			jQuery( '#fdm-order-submit' ).removeClass( 'fdm-hidden' );
			jQuery( '#fdm-order-payment-form-div' ).addClass( 'fdm-hidden' );
		}
		else {
			jQuery( '#fdm-order-submit' ).addClass( 'fdm-hidden' );
			jQuery( '#fdm-order-payment-form-div' ).removeClass( 'fdm-hidden' );
		}
	});

});

function fdm_display_order_details_popup() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_display_order_details_popup === 'function' ) return window.fdmPremiumOrdering.fdm_display_order_details_popup.apply( null, arguments );
	return false;
}

function fdm_set_add_to_cart_actions() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_set_add_to_cart_actions === 'function' ) return window.fdmPremiumOrdering.fdm_set_add_to_cart_actions.apply( null, arguments );
	return false;
}

function fdm_add_to_cart() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_add_to_cart === 'function' ) return window.fdmPremiumOrdering.fdm_add_to_cart.apply( null, arguments );
	return false;
}

function fdm_admin_add_to_cart() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_admin_add_to_cart === 'function' ) return window.fdmPremiumOrdering.fdm_admin_add_to_cart.apply( null, arguments );
	return false;
}

function fdm_update_order_status() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_update_order_status === 'function' ) return window.fdmPremiumOrdering.fdm_update_order_status.apply( null, arguments );
	return false;
}

function fdm_handle_delete_clicks() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_handle_delete_clicks === 'function' ) return window.fdmPremiumOrdering.fdm_handle_delete_clicks.apply( null, arguments );
	return false;
}

function fdm_delete_from_cart() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_delete_from_cart === 'function' ) return window.fdmPremiumOrdering.fdm_delete_from_cart.apply( null, arguments );
	return false;
}

function fdm_update_cart_total() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_update_cart_total === 'function' ) return window.fdmPremiumOrdering.fdm_update_cart_total.apply( null, arguments );
	return false;
}

function fdm_update_cart_quantities() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_update_cart_quantities === 'function' ) return window.fdmPremiumOrdering.fdm_update_cart_quantities.apply( null, arguments );
	return false;
}

function fdm_validate_ordering_fields() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_validate_ordering_fields === 'function' ) return window.fdmPremiumOrdering.fdm_validate_ordering_fields.apply( null, arguments );
	return false;
}

function fdm_return_random_string() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_return_random_string === 'function' ) return window.fdmPremiumOrdering.fdm_return_random_string.apply( null, arguments );
	return false;
}

function fdm_move_stuff_in_cart() {
	if ( window.fdmPremiumOrdering && typeof window.fdmPremiumOrdering.fdm_move_stuff_in_cart === 'function' ) return window.fdmPremiumOrdering.fdm_move_stuff_in_cart.apply( null, arguments );
	return false;
}
