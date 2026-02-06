function fdm_move_item_flags() {

	jQuery( '.fdm-item' ).each( function() {

		jQuery( this ).find( '.fdm-item-non-image-container' ).append( jQuery( this ).find( '.fdm-menu-item-flags' ) );
	});
}

jQuery( document ).ready( function() {

	fdm_move_item_flags();
});
