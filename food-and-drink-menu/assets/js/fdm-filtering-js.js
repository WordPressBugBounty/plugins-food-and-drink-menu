jQuery(document).ready(function() {
	jQuery( '.fdm-filtering' ).each( function() {
		var filtering = jQuery( this );
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
	} );
} );


function fdm_filtering_context( control ) {
	var filtering = jQuery( control ).hasClass( 'fdm-filtering' ) ? jQuery( control ) : jQuery( control ).closest( '.fdm-filtering' );
	var instance = filtering.closest( '.fdm-menu-instance' );
	var menu = instance.find( '.fdm-menu' ).first();
	var pricing = instance.find( '.fdm-pricing-info' ).first();
	return { filtering: filtering, instance: instance, menu: menu, pricing: pricing };
}

// Compatibility names dispatch only; paid algorithms are supplied by Helper.
function fdm_filtering( control ) {
	if ( window.fdmPremiumFiltering ) return window.fdmPremiumFiltering.filter( control );
}
function fdm_initiate_price_slider( filtering ) {
	if ( window.fdmPremiumFiltering ) return window.fdmPremiumFiltering.slider( filtering );
}
function fdm_initiate_price_boxes( filtering ) {
	if ( window.fdmPremiumFiltering ) return window.fdmPremiumFiltering.boxes( filtering );
}

function fdm_sorting( control ) {
	var context = fdm_filtering_context( control );
	var action = jQuery( control ).val();
	if ( action !== 'name_asc' && action !== 'name_desc' ) {
		if ( window.fdmPremiumFiltering ) window.fdmPremiumFiltering.sort( control );
		return;
	}
	context.menu.find( '.fdm-section' ).each( function() {
		var section = jQuery( this );
		var items = section.find( 'li.fdm-item' ).get();
		items.sort( function( a, b ) {
			var left = jQuery( a ).find( '.fdm-item-title' ).first().text().toLowerCase();
			var right = jQuery( b ).find( '.fdm-item-title' ).first().text().toLowerCase();
			return fdm_sort_compare( left, right, action === 'name_desc' );
		} );
		section.append( items );
	} );
}

function fdm_sort_compare( left, right, descending ) {
	if ( left < right ) return descending ? 1 : -1;
	if ( left > right ) return descending ? -1 : 1;
	return 0;
}
