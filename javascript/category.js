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
jQueryExaport(function ($) {

  $('#sharing-userlist').html('loading userlist...');
  $('#sharing-grouplist').html('loading grouplist...');

  $('#structure_sharing-userlist').html('loading userlist...');
  $('#structure_sharing-grouplist').html('loading grouplist...');

  // Sharing.
  function update_sharing() {
    // var share_text = '';
    var $form = $('#categoryform');

    if ($form.find(':input[name="internshare"]').is(':checked')) {
      $('#internaccess-settings').show();
      $('#internaccess-groups').hide();

      if ($form.find(':input[name=shareall]:checked').val() == 1) {
        $('#internaccess-users').hide();
        $('#internaccess-groups').hide();
      } else if ($form.find(':input[name=shareall]:checked').val() == 2) {
        $('#internaccess-users').hide();
        $('#internaccess-groups').show();
        ExabisEportfolio.load_grouplist('cat_mod');
      } else {
        $('#internaccess-groups').hide();
        $('#internaccess-users').show();
        ExabisEportfolio.load_userlist('cat_mod');
      }
    } else {
      $('#internaccess-settings').hide();
    }
  }

  $(function () {
    // Changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
    $('#categoryform input[type="checkbox"], #categoryform input[type="radio"]').on('click', function () {
      update_sharing();
    });
    update_sharing();
  });
});
