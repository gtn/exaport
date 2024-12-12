// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

function long_preview_show(i) {
  document.getElementById("short-preview-" + i).style.display = "none";
  document.getElementById("long-preview-" + i).style.display = "block";
}

function long_preview_hide(i) {
  document.getElementById("short-preview-" + i).style.display = "block";
  document.getElementById("long-preview-" + i).style.display = "none";
}

jQueryExaport(function ($) {
  $(".excomdos_cont-type-mine .excomdos_tiletable .excomdos_tile:not(.excomdos_tile_fixed)").draggable({
    scroll: true, // Scroll when dragging.
    helper: "clone",
    start: function (event, ui) { // When dragging.
      // Set background of the current tile to white, background needed when dragging.
      ui.helper.css('background-color', '#fff');
    }
  });

  $(".excomdos_cont-type-mine .excomdos_tiletable .excomdos_tile_category").droppable({
    activeClass: "ui-state-active",
    hoverClass: "ui-state-hover",
    drop: function (event, ui) {
      // Dropping a category or an item.
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
        success: function (res) {
          ui.draggable.fadeOut();
        }
      });
    }
  });

  $('*[data-toggle="showmore"]').on('click', function (e) {
    e.preventDefault();
    var morecontent = $(this).attr('href');
    if ($(morecontent).length) {
      $(this).hide();
      $(morecontent).show(200);
    }
  });


});
