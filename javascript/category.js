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

  // Override load_userlist to pass the category id so the server can include
  // shared_to / notify_user information for each user.
  ExabisEportfolio.load_userlist = (function (original) {
    return function (type) {
      if (this.userlist_loaded) {
        return;
      }
      this.userlist_loaded = true;

      $('#sharing-userlist').html('loading userlist...');

      var categoryId = $('input[name="id"]').val() || 0;
      $.getJSON(document.location.href, {action: 'userlist', id: categoryId}, function (courses) {
        var html = '';
        var alwaysNotify = document.getElementById('alwaysnotifywhenshare') ? document.getElementById('alwaysnotifywhenshare').value : "false";
        var alwaysNotifyBool = alwaysNotify === "true" || alwaysNotify === "1";

        if (!$.empty(courses)) {
          $.each(courses, function (tmp, course) {
            html += '<fieldset class="course-group"><legend class="course-group-title">';
            html += (ExabisEportfolio.courseid == course.id ? '<b>' : '');
            html += course.fullname;
            html += (ExabisEportfolio.courseid == course.id ? '</b>' : '');
            html += '</legend>';

            html += '<div class="course-group-content">';
            if (!$.empty(course.users)) {
              html += "<table width=\"70%\">";
              html += "<tr><th align=\"center\">&nbsp;</th><th align=\"center\">&nbsp;</th>";
              html += "<th align=\"left\">" + ExabisEportfolio.translate('name') + "</th><th align=\"right\">" + ExabisEportfolio.translate('role') + "</th></tr>";

              html += '<tr><td align=\"center\" width="5%">';
              html += '<input class="shareusers-check-all" courseid="' + course.id + '" type="checkbox" />';
              html += "<br />" + ExabisEportfolio.translate('checkall');
              html += "</td></tr>";

              $.each(course.users, function (tmp, user) {
                html += '<tr><td align=\"center\" width="5%">';
                html += '<input class="shareusers" type="checkbox" courseid="' + course.id + '" name="shareusers[' + user.id + ']" ';
                html += ' value="' + user.id + '"' + (user.shared_to ? ' checked="checked"' : '') + ' />';
                html += "<br />" + ExabisEportfolio.translate('sharejs');
                html += '</td><td align=\"center\" width="5%" style="padding-right: 20px;">';

                html += '<input class="notifyusers" type="checkbox"' + (user.shared_to ? '' : ' disabled="disabled"') + ' name="notifyusers[' + user.id + ']"  ';
                html += ' value="' + user.id + '"' + (user.notify_user ? ' checked="checked"' : '') + ' />';

                if (alwaysNotifyBool) {
                  if ((user.notify_user && !user.shared_to) || (!user.notify_user && user.shared_to)) {
                    html += ' <span title="' + ExabisEportfolio.translate('viewmustbesafed') + '" style="color: red; font-weight: bold;">(!)</span> ';
                  }
                  html += '<input class="notifyusers" type="hidden"' + (user.shared_to ? '' : ' disabled="disabled"') + ' name="notifyusers[' + user.id + ']"  ';
                  html += ' value="' + user.id + '"' + (user.notify_user ? ' checked="checked"' : '') + ' />';
                }

                html += "<br />" + ExabisEportfolio.translate('notify');
                html += "</td><td align=\"center\" width='45%'>" + user.name + "</td><td align=\"center\" width='45%'>" + user.rolename + "</td></tr>";
              });

              html += "</table>";
            } else {
              html += ExabisEportfolio.translate('nousersfound');
            }
            html += '</div>';
            html += "</fieldset>";
          });
        } else {
          html += '<b>' + ExabisEportfolio.translate('nousersfound') + '</b>';
        }

        $('#sharing-userlist').html(html);

        // Set default checkboxes for category.
        if (typeof sharedusersarr != 'undefined') {
          if (sharedusersarr.length > 0) {
            $.each(sharedusersarr, function (tmp, userid) {
              $('form #internaccess-users input:checkbox[value=' + userid + ']').attr("checked", true);
            });
          }
        }

        // CHECK ALL buttons.
        $('#sharing-userlist .shareusers-check-all').click(function () {
          $('#sharing-userlist .shareusers:checkbox[courseid=' + $(this).attr('courseid') + ']')
            .prop('checked', $(this).is(':checked'))
            .each(function () {
              $(this).triggerHandler('click');
            });
        });

        // Enable/disable notify checkbox when share checkbox changes.
        $('#sharing-userlist .shareusers:checkbox').click(function () {
          var $notifyboxeshidden = $(this).closest('tr').find('.notifyusers[type="hidden"]');
          var $notifyboxescheckbox = $(this).closest('tr').find('.notifyusers[type="checkbox"]');

          if (alwaysNotifyBool) {
            $notifyboxeshidden.attr('disabled', !this.checked);
            $notifyboxescheckbox.prop('checked', this.checked);
          } else {
            $notifyboxescheckbox.attr('disabled', !this.checked);
            if (!this.checked) {
              $notifyboxescheckbox.prop('checked', false);
            }
          }

          var $courseCheckboxes = $('#sharing-userlist .shareusers:checkbox[courseid=' + $(this).attr('courseid') + ']');
          $('#sharing-userlist .shareusers-check-all[courseid=' + $(this).attr('courseid') + ']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
        });

        $('.course-group-content').each(function () {
          var flag = 0;
          $(this).find('table > tbody > tr > td > input.shareusers').each(function () {
            if (flag == 1) {
              return false;
            }
            if ($(this).prop('checked') == false) {
              flag = 1;
            }
            var $notifyboxes = $(this).closest('tr').find('.notifyusers');
            if (alwaysNotifyBool) {
              $notifyboxes.prop('checked', this.checked);
            } else {
              $notifyboxes.attr('disabled', !this.checked);
              if (!this.checked) {
                $notifyboxes.prop('checked', false);
              }
            }
          });
          if (flag == 0) {
            $(this).find('table > tbody > tr > td > input.shareusers-check-all').prop('checked', true);
          }
        });

        // Open/close course group.
        $('.course-group-title').on('click', function () {
          $(this).closest('.course-group').toggleClass('course-group-open');
        });
        // Open all shared courses.
        $('.course-group').has('input:checked').addClass('course-group-open');

        // Disable notifyusers checkboxes if alwaysNotify is true.
        if (alwaysNotifyBool) {
          $('.notifyusers[type="checkbox"]').prop('disabled', true);
        }
      });
    };
  })(ExabisEportfolio.load_userlist);

  $(function () {
    // Changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
    $('#categoryform input[type="checkbox"], #categoryform input[type="radio"]').on('click', function () {
      update_sharing();
    });
    update_sharing();
  });
});
