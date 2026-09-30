jQuery(document).ready(function() {
	jQuery( '.fdm-filtering' ).each( function() {
		var filtering = jQuery( this );
		filtering.find( '.fdm-filtering-text-input, .fdm-filtering-min-price-input, .fdm-filtering-max-price-input' ).on( 'keyup', function() { fdm_filtering( this ); } );
		filtering.find( '.fdm-filtering-sorting-input' ).on( 'change', function() { fdm_sorting( this ); } );
		filtering.find( '.fdm-filtering-section-input' ).on( 'change', function() {
			var context = fdm_filtering_context( this );
			var section = jQuery( this ).val();
			context.menu.find( '.fdm-section' ).each( function() {
				jQuery( this ).toggleClass( 'fdm-hidden', section !== '' && jQuery( this ).data( 'fdm-section' ) !== section );
			} );
			// The sidebar can size image wrappers while their sections are hidden.
			// Restore the square wrapper height after this filter reveals them.
			context.menu.find( '.fdm-section:not(.fdm-hidden) .fdm-image-style-image-wrapper' ).each( function() {
				var wrapper = jQuery( this );
				wrapper.css( 'height', wrapper.width() + 'px' );
			} );
		} );
		if ( filtering.find( '.fdm-filtering-min-price-input' ).length ) fdm_initiate_price_boxes( filtering );
		if ( filtering.find( '.fdm-filtering-price-slider' ).length ) fdm_initiate_price_slider( filtering );
	} );
} );

function fdm_filtering_context( control ) {
	var filtering = jQuery( control ).hasClass( 'fdm-filtering' ) ? jQuery( control ) : jQuery( control ).closest( '.fdm-filtering' );
	var instance = filtering.closest( '.fdm-menu-instance' );
	var menu = instance.find( '.fdm-menu' ).first();
	var pricing = instance.find( '.fdm-pricing-info' ).first();
	return { filtering: filtering, instance: instance, menu: menu, pricing: pricing };
}

function fdm_filtering( control ) {
	var context = fdm_filtering_context( control );
	var text_input = context.filtering.find( '.fdm-filtering-text-input' );
	var text = text_input.length ? String( text_input.val() ).toLowerCase() : '';
	var search = text_input.length ? String( text_input.data( 'search' ) ) : '';
	var min_price = 0;
	var max_price = 1000000;
	if ( context.filtering.find( '.fdm-filtering-min-price-input' ).length ) {
		min_price = context.filtering.find( '.fdm-filtering-min-price-input' ).val();
		max_price = context.filtering.find( '.fdm-filtering-max-price-input' ).val();
	} else if ( context.filtering.find( '.fdm-filtering-price-slider' ).length ) {
		min_price = context.filtering.find( '.fdm-filtering-price-slider' ).slider( 'values', 0 );
		max_price = context.filtering.find( '.fdm-filtering-price-slider' ).slider( 'values', 1 );
	}
	context.menu.find( '.fdm-item' ).each( function() {
		var item = jQuery( this );
		var title = item.find( '.fdm-item-title' ).first().text();
		var desc = item.find( '.fdm-item-content' ).first().text();
		var hidden = text !== ''
			&& ( title.toLowerCase().indexOf( text ) === -1 || search.indexOf( 'name' ) === -1 )
			&& ( desc.toLowerCase().indexOf( text ) === -1 || search.indexOf( 'description' ) === -1 );
		var price = item.find( '.fdm-item-price-wrapper' );
		if ( ( min_price !== '' && price.data( 'max_price' ) < min_price ) || ( max_price !== '' && price.data( 'min_price' ) > max_price ) ) hidden = true;
		item.toggleClass( 'fdm-hidden', hidden );
	} );
}

function fdm_initiate_price_slider( filtering ) {
	var context = fdm_filtering_context( filtering );
	var prefix = context.pricing.data( 'currency_symbol_location' ) === 'before' ? context.pricing.data( 'currency_symbol' ) : '';
	var suffix = context.pricing.data( 'currency_symbol_location' ) === 'after' ? context.pricing.data( 'currency_symbol' ) : '';
	var min = context.pricing.data( 'min_price' );
	var max = context.pricing.data( 'max_price' );
	context.filtering.find( '.fdm-filtering-min-price-display' ).html( prefix + min + suffix );
	context.filtering.find( '.fdm-filtering-max-price-display' ).html( prefix + max + suffix );
	context.filtering.find( '.fdm-filtering-price-slider' ).slider( {
		range: true, min: min, max: max, values: [ min, max ],
		slide: function( event, ui ) {
			context.filtering.find( '.fdm-filtering-min-price-display' ).html( prefix + ui.values[0] + suffix );
			context.filtering.find( '.fdm-filtering-max-price-display' ).html( prefix + ui.values[1] + suffix );
			setTimeout( function() { fdm_filtering( context.filtering ); }, 100 );
		}
	} );
}

function fdm_initiate_price_boxes( filtering ) {
	var context = fdm_filtering_context( filtering );
	context.filtering.find( '.fdm-filtering-min-price-input' ).val( context.pricing.data( 'min_price' ) );
	context.filtering.find( '.fdm-filtering-max-price-input' ).val( context.pricing.data( 'max_price' ) );
}

function fdm_sorting( control ) {
	var context = fdm_filtering_context( control );
	var action = jQuery( control ).val();
	if ( action === '' ) return;
	if ( action === 'section_asc' || action === 'section_desc' ) {
		var sections = context.menu.find( '.fdm-section' ).get();
		sections.sort( function( a, b ) {
			var left = jQuery( a ).find( 'li.fdm-section-header h3' ).first().text().toLowerCase();
			var right = jQuery( b ).find( 'li.fdm-section-header h3' ).first().text().toLowerCase();
			return fdm_sort_compare( left, right, action === 'section_desc' );
		} );
		context.menu.append( sections );
		return;
	}
	context.menu.find( '.fdm-section' ).each( function() {
		var section = jQuery( this );
		var items = section.find( 'li.fdm-item' ).get();
		items.sort( function( a, b ) {
			var left;
			var right;
			if ( action.indexOf( 'name_' ) === 0 ) {
				left = jQuery( a ).find( '.fdm-item-title' ).first().text().toLowerCase();
				right = jQuery( b ).find( '.fdm-item-title' ).first().text().toLowerCase();
			} else if ( action.indexOf( 'price_' ) === 0 ) {
				left = parseFloat( jQuery( a ).find( '.fdm-item-price' ).first().text().replace( /[^\d.]+/g, '' ) );
				right = parseFloat( jQuery( b ).find( '.fdm-item-price' ).first().text().replace( /[^\d.]+/g, '' ) );
			} else if ( action.indexOf( 'date_' ) === 0 ) {
				left = jQuery( a ).data( 'timeadded' );
				right = jQuery( b ).data( 'timeadded' );
			} else return 0;
			return fdm_sort_compare( left, right, action.slice( -5 ) === '_desc' );
		} );
		section.append( items );
	} );
}

function fdm_sort_compare( left, right, descending ) {
	if ( left < right ) return descending ? 1 : -1;
	if ( left > right ) return descending ? -1 : 1;
	return 0;
}
