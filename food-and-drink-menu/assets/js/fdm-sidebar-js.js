jQuery(document).ready(function(){
 jQuery('.fdm-sidebar').each(function(){
  var sidebar=jQuery(this), instance=sidebar.closest('.fdm-menu-instance');
  if (!instance.length) instance=sidebar.closest('.fdm-the-menu').parent();
  var titles=sidebar.find('.fdm-menu-sidebar-section-title');
  function select(control, scroll){
   var title=jQuery(control), term=title.attr('data-fdm-term-id'), slug=title.attr('id');
   titles.removeClass('fdm-menu-sidebar-section-title-selected').attr('aria-expanded','false');
   title.addClass('fdm-menu-sidebar-section-title-selected').attr('aria-expanded','true');
   sidebar.find('.fdm-menu-sidebar-section-description').each(function(){
    var node=jQuery(this), matches=term ? node.attr('data-fdm-term-id') === term : node.attr('id') === slug;
    node.toggleClass('fdm-hidden',!matches);
   });
   if (fdmFromSettings.sidebar_click_action === 'onlyselected') {
    instance.find('.fdm-the-menu').addClass('onlyselected');
    instance.find('.fdm-section').each(function(){
     var section=jQuery(this), matches=term ? section.attr('data-fdm-term-id') === term : section.hasClass('fdm-section-'+slug);
     section.toggleClass('fdm-hidden',!matches);
    });
   }
   if (scroll && fdmFromSettings.sidebar_click_action === 'scroll') {
    var anchor=title.attr('data-fdm-anchor'), target=anchor ? document.getElementById(anchor) : instance.find('[id]').filter(function(){return this.id==='fdm-section-header-'+slug;})[0];
    if (target) jQuery('html, body').animate({scrollTop:jQuery(target).offset().top-120},500);
   }
   instance.find('.fdm-section:not(.fdm-hidden) .fdm-image-style-image-wrapper').each(function(){var wrapper=jQuery(this);wrapper.css('height',wrapper.width()+'px');});
  }
  titles.attr('tabindex','0').attr('role','button').on('click',function(){select(this,true);}).on('keydown',function(event){
   if (event.key==='Enter' || event.key===' ') {event.preventDefault();select(this,true);}
   if (event.key==='ArrowDown' || event.key==='ArrowUp') {event.preventDefault();var index=titles.index(this)+(event.key==='ArrowDown'?1:-1);titles.eq((index+titles.length)%titles.length).trigger('focus');}
  });
  if (titles.length) select(titles[0],false);
 });
	// HIDDEN/EXPANDABLE MOBILE SIDEBAR
	jQuery( '.fdm-sidebar-mobile-expand-button' ).on( 'click', function() {
		var button = jQuery(this), menu = button.closest('.fdm-menu-instance'), sidebar = menu.find('.fdm-sidebar');
		button.toggleClass('open');
		if (!button.hasClass('open')) sidebar.hide();
		else if (fdmFromSettings.menu_style == 'ordering') sidebar.css('display','flex');
		else sidebar.show();
	});

	if ( fdmFromSettings.menu_style == 'ordering' ) {
		jQuery( window ).bind( 'resize', mobileAlternateSidebarResize );
	}
	else {
		jQuery( window ).bind( 'resize', mobileSidebarResize );
	}
});

function mobileSidebarResize() {
	jQuery('.fdm-menu-instance').each(function(){
		var menu=jQuery(this), button=menu.find('.fdm-sidebar-mobile-expand-button'), sidebar=menu.find('.fdm-sidebar-mobile-expand-1 .fdm-sidebar');
		if(jQuery(window).width()>568){sidebar.show();button.removeClass('open');}
		else sidebar.toggle(button.hasClass('open'));
	});
}

function mobileAlternateSidebarResize() {
	jQuery('.fdm-menu-instance').each(function(){
		var menu=jQuery(this), button=menu.find('.fdm-sidebar-mobile-expand-button'), sidebar=menu.find('.fdm-sidebar-mobile-expand-1 .fdm-sidebar');
		if(jQuery(window).width()>768){sidebar.css('display','flex');button.removeClass('open');}
		else if(button.hasClass('open')) sidebar.css('display','flex'); else sidebar.hide();
	});
}
