jQuery( document ).ready( function() {

  jQuery(document).on( 'click', '.fdm-helper-install-notice .notice-dismiss', function( event ) {
    var data = jQuery.param({
      action: 'fdm_hide_helper_notice',
      nonce: fdm_helper_notice.nonce
    });

    jQuery.post( ajaxurl, data, function() {} );
  });
});

/* NEW PLUGIN NOTICE */

jQuery( document ).ready( function( $ ) {

  jQuery(document).on( 'click', '.ait-aiaa-new-plugin-notice .notice-dismiss', function( event ) {
    var data = jQuery.param({
      action: 'fdm_hide_new_plugin_notice',
      plugin: 'ait_aiaa',
      nonce: fdm_hide_helper_notice.nonce
    });

    jQuery.post( ajaxurl, data, function() {} );
  });
});