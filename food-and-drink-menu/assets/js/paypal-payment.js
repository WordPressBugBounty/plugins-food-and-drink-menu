jQuery(document).ready(function($) {
	$("#paypal-payment-form").submit(function(event) {

		if ( ! fdm_validate_ordering_fields() ) { return false; }

		// disable the submit button to prevent repeated clicks
		$('#paypal-submit').attr("disabled", "disabled");

		var form$ = jQuery("#paypal-payment-form");

		var permalink = jQuery( '#paypal-submit' ).data( 'permalink' );

		var name = jQuery( 'input[name="fdm_ordering_name"]' ).val();
		var email = jQuery( 'input[name="fdm_ordering_email"]' ).val();
		var phone = jQuery( 'input[name="fdm_ordering_phone"]' ).val();
		var note = jQuery( 'textarea[name="fdm_ordering_note"]' ).val();
		var pickup_time = jQuery( 'input[name="fdm_pickup_time"]' ).length ? jQuery( 'input[name="fdm_pickup_time"]' ).val() : false;
		var tip_amount = jQuery( '.fdm-tip-amount' ).length ? jQuery( '.fdm-tip-amount' ).val() : 0;
		var discount_code = jQuery( '.fdm-discount-code' ).length ? jQuery( '.fdm-discount-code' ).val() : '';
		var delivery = ( jQuery( '.fdm-order-delivery-toggle-option' ).length && jQuery( 'input[name="fdm-delivery-toggle"]' ).val() == 'delivery' ) ? true : false;

		var custom_fields = {};
		jQuery( '.fdm-ordering-custom-fields' ).find( 'input, textarea, select' ).each( function() {
			custom_fields[ this.name ] = jQuery( this ).val(); 
		});
		jQuery( '.fdm-ordering-custom-fields' ).find( 'input:checked' ).each( function() {
			let index = jQuery( this ).data( 'slug' );
			custom_fields[ index ] = Array.isArray( custom_fields[ index ] ) ? custom_fields[ index ] : [];
			custom_fields[ index ].push( jQuery( this ).val() );
		}).get();

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
			post_status: 'draft',
			nonce: fdm_paypal_payment.nonce,
			action: 'fdm_submit_order'
		});

		jQuery.post( ajaxurl, data, function( response ) {

			if ( ! response.success ) { 
				jQuery( '#fdm-order-submit-button' ).before( '<p>Order could not be processed. Please contact the site administrator.' );

				return;
			}

			form$.append("<input type='hidden' name='custom' value='order_id=" + response.data.order_id + "'/>");

			// submit form
			form$.get(0).submit();
		});

		// prevent the form from submitting with the default action
		return false;
	});
});