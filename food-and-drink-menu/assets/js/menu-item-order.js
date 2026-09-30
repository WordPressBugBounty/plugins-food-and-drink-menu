jQuery(function($) {
	var list = $('#fdm-reorder-items');
	var field = $('#fdm-menu-item-order');
	function sync() {
		field.val(list.children('li').map(function() { return $(this).data('id'); }).get().join(','));
	}
	list.sortable({ axis: 'y', update: sync });
	list.on('click', '.fdm-move-up, .fdm-move-down', function() {
		var row = $(this).closest('li');
		if ($(this).hasClass('fdm-move-up') && row.prev().length) row.insertBefore(row.prev());
		if ($(this).hasClass('fdm-move-down') && row.next().length) row.insertAfter(row.next());
		sync();
		row.focus();
	});
});
