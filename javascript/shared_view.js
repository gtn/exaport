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
// (c) 2023 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

jQueryExaport(function ($) {

  $('.view-group-header').on('click', function () {
    $(this).parents('.view-group').toggleClass('view-group-open');
  });

  // font uploader show
  $('#fitem_id_fontuploader_toggler .felement').on('click', function () {
    var shown = $(this).attr('data-shown');
    var uploaderContainer = $('.uploadFont-container');
    if (shown && shown != '0') {
      $(this).attr('data-shown', 0);
      uploaderContainer.hide();
    } else {
      $(this).attr('data-shown', 1);
      uploaderContainer.show();
    }
  });

  // show "upload custom" font form part
  $('.customfont-button').on('click', function () {
    $(this).parents('.view-group').toggleClass('view-group-open');
  });

});
