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

  if (!$('#item-share-settings').length) {
    // Item sharing UI is only rendered when the user has the shareintern capability.
    return;
  }

  $('#sharing-userlist').html('loading userlist...');
  $('#sharing-grouplist').html('loading grouplist...');

  // Sharing.
  function update_sharing() {
    var $form = $('#item-share-settings').closest('form');

    // Parent "Share" checkbox controls visibility of the sub-options container.
    if ($form.find(':input[name="shareenabled"]').is(':checked')) {
      $('#item-share-settings').show();
    } else {
      $('#item-share-settings').hide();
      return;
    }

    if ($form.find(':input[name=shareall]:checked').val() == 1) {
      $('#item-internaccess-users').hide();
      $('#item-internaccess-groups').hide();
    } else if ($form.find(':input[name=shareall]:checked').val() == 2) {
      $('#item-internaccess-users').hide();
      $('#item-internaccess-groups').show();
      ExabisEportfolio.load_grouplist('cat_mod');
    } else {
      $('#item-internaccess-groups').hide();
      $('#item-internaccess-users').show();
      ExabisEportfolio.load_userlist('cat_mod');
    }
  }

  $(function () {
    // Changing the checkboxes / radiobuttons update the visible options.
    $('#item-share-settings').closest('form').find('input[type="checkbox"], input[type="radio"]').on('click', function () {
      update_sharing();
    });
    update_sharing();
  });
});
