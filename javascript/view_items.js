
function long_preview_show(i) {
document.getElementById("short-preview-" + i).style.display = "none";
document.getElementById("long-preview-" + i).style.display = "block";
}
function long_preview_hide(i) {
document.getElementById("short-preview-" + i).style.display = "block";
document.getElementById("long-preview-" + i).style.display = "none";
}

jQueryExaport(function($){
	$(".excomdos_tiletable .excomdos_tile").draggable({
		scroll: true, // scroll when dragging
		helper: "clone",
		start: function( event, ui ) { // when dragging
			// set background of the current tile to white, background needed when dragging
			ui.helper.css('background-color', '#fff');
		}
    });
	
	$(".excomdos_tiletable .excomdos_tile_category").droppable({
		activeClass: "ui-state-active",
		hoverClass: "ui-state-hover",
		drop: function( event, ui ) {
			// dropping a category or an item
			var moveCat = ui.draggable.is('.excomdos_tile_category');

			$.ajax({
				url: M.cfg.wwwroot + '/blocks/exaport/' + (moveCat ? 'category.php' : 'item.php'),
				type: 'POST',
				data: {
					action: 'movetocategory',
					'id': ui.draggable[0].className.replace(/.*(^|\s)id-([0-9]+).*/, '$2'),
					'categoryid': this.className.replace(/.*(^|\s)id-([0-9]+).*/, '$2'),
					'courseid': document.location.href.replace(/.*([&?])courseid=([0-9]+).*/, '$2'),
					sesskey: M.cfg.sesskey
				},
				success: function(res) {
					ui.draggable.fadeOut();
				}
			});
		}
    })
});
