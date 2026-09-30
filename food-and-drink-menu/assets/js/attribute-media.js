jQuery(function($) {
	$(document).on('click', '.fdm-attribute-select-icon', function() {
		var wrapper = $(this).closest('.fdm-attribute-media');
		var frame = wp.media({ title: 'Choose attribute icon', multiple: false, library: { type: 'image' } });
		frame.on('select', function() { wrapper.find('input').val(frame.state().get('selection').first().id); });
		frame.open();
	});
	$(document).on('click', '.fdm-attribute-remove-icon', function() { $(this).closest('.fdm-attribute-media').find('input').val(''); });
});
