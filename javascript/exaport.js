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

/**
 * $.disablescroll
 * Author: Josh Harrison - aloof.co
 *
 * Disables scroll events from mousewheels, touchmoves and keypresses.
 * Use while jQuery is animating the scroll position for a guaranteed super-smooth ride!
 */
(function (e) {
  "use strict";

  function r(t, n) {
    this.opts = e.extend({handleWheel: !0, handleScrollbar: !0, handleKeys: !0, scrollEventKeys: [32, 33, 34, 35, 36, 37, 38, 39, 40]}, n);
    this.$container = t;
    this.$document = e(document);
    this.lockToScrollPos = [0, 0];
    this.disable();
  }

  var t, n;
  n = r.prototype;
  n.disable = function () {
    var e = this;
    e.opts.handleWheel && e.$container.on("mousewheel.disablescroll DOMMouseScroll.disablescroll touchmove.disablescroll", e._handleWheel);
    if (e.opts.handleScrollbar) {
      e.lockToScrollPos = [e.$container.scrollLeft(), e.$container.scrollTop()];
      e.$container.on("scroll.disablescroll", function () {
        e._handleScrollbar.call(e);
      });
    }
    e.opts.handleKeys && e.$document.on("keydown.disablescroll", function (t) {
      e._handleKeydown.call(e, t);
    });
  };
  n.undo = function () {
    var e = this;
    e.$container.off(".disablescroll");
    e.opts.handleKeys && e.$document.off(".disablescroll");
  };
  n._handleWheel = function (e) {
    e.preventDefault();
  };
  n._handleScrollbar = function () {
    this.$container.scrollLeft(this.lockToScrollPos[0]);
    this.$container.scrollTop(this.lockToScrollPos[1]);
  };
  n._handleKeydown = function (e) {
    for (var t = 0; t < this.opts.scrollEventKeys.length; t++) {
      if (e.keyCode === this.opts.scrollEventKeys[t]) {
        e.preventDefault();
        return
      }
    }
  };
  e.fn.disablescroll = function (e) {
    !t && (typeof e == "object" || !e) && (t = new r(this, e));
    t && typeof e == "undefined" ? t.disable() : t && t[e] && t[e].call(t);
  };
  window.UserScrollDisabler = r
})(jQuery);

(function () {

  window.jQueryExaport = jQuery;
  var $ = jQuery;
  $.empty = function (obj) {
    if (!obj) {
      return true;
    }

    for (key in obj) {
      return false;
    }
    return true;
  };

  window.block_exaport = window.ExabisEportfolio = $E = {
    courseid: 1,

    translations: null,

    translate: function (key) {
      if (!this.translations || this.translations[key] === undefined) {
        return '[[js[' + key + ']js]]';
      }
      return this.translations[key];
    },

    setTranslations: function (translations) {
      this.translations = translations;
    },

    // Reads the "always notify when share" admin setting, rendered as a hidden field by every
    // page that offers share/notify checkboxes (views_mod.php, category.php, item edit form and
    // the user search page, see block_exaport_sharing_user_search_page() in lib/sharelib.php).
    always_notify_when_share: function () {
      var alwaysNotify = document.getElementById('alwaysnotifywhenshare')?.value ?? "false";
      return alwaysNotify === "true" || alwaysNotify === "1";
    },

    // Enables/disables and synchronises the notify controls belonging to one share checkbox.
    // Shared by the course grouped sharing dialog (load_userlist()) and the "search any user"
    // sharing page (init_share_user_search()) so the notify behaviour - including the
    // alwaysNotifyBool handling and the "disabled checkboxes are not submitted" hidden field
    // workaround - exists exactly once.
    //
    // Only ever sets DOM state via .prop()/.attr(), never by re-triggering click/change events,
    // so this cannot recurse back into the handlers that call it.
    update_notify_state: function ($shareCheckbox, alwaysNotifyBool, notifyChecked) {
      var checked = $shareCheckbox.prop('checked');
      var $row = $shareCheckbox.closest('tr');
      var $notifyCheckbox = $row.find('.notifyusers[type="checkbox"]');
      var $notifyHidden = $row.find('.notifyusers[type="hidden"]');

      // Based on alwaysNotifyBool the CHECKBOXES should always be disabled, but the hidden
      // field should be enabled/disabled - see the workaround note in renderCourseUsers().
      if (alwaysNotifyBool) {
        $notifyHidden.attr('disabled', !checked);
        $notifyCheckbox.prop('checked', checked);
      } else {
        $notifyCheckbox.attr('disabled', !checked);
        if (!checked) {
          $notifyCheckbox.prop('checked', false);
        } else if (notifyChecked !== undefined) {
          $notifyCheckbox.prop('checked', notifyChecked);
        }
      }
    },

    // "Search any moodle user and share with them" page, see
    // block_exaport_sharing_user_search_page() in lib/sharelib.php. Its rows are rendered by PHP
    // (there is no course grouping and no lazy loading here, so every user appears exactly
    // once), only the notify enable/disable synchronisation is needed - and that is the very
    // same one the normal sharing UI uses, see update_notify_state().
    init_share_user_search: function () {
      var $form = $('#share-user-search-results');
      if (!$form.length) {
        return;
      }

      var alwaysNotifyBool = this.always_notify_when_share();

      $form.find('.shareusers:checkbox').on('click', function () {
        $E.update_notify_state($(this), alwaysNotifyBool);
      });
    },

    userlist_loaded: false,
    load_userlist: function (type) {
      if (this.userlist_loaded) {
        return;
      }
      this.userlist_loaded = true;

      var $userlist = $('#sharing-userlist');
      var $root = $userlist.closest('[data-region="exaport-sharing"]');
      var $ready = $root.find('[name="exaport_userlist_ready"]');
      var initialLoaded = false;

      var alwaysNotifyBool = this.always_notify_when_share();

      // Undefined = unopened; partial = selected recipients only; loading/full = roster state.
      // Reopening a partial course must not fetch everyone: only its load-more button does that.
      var courseStates = {};
      var courseUsers = {};

      // Direct user shares are entity-wide (one entity/user relation, not one per course - see
      // block_exaport_ajax_sharing_userlist_course() in lib/sharelib.php), so the same Moodle
      // user id can legitimately appear in several course groups here. These maps hold the
      // client-side selection by user id, independent of which/how many rows currently render
      // that user, so that:
      //  - checking/unchecking one occurrence can be propagated to every other rendered
      //    occurrence of the same user, and
      //  - a course loaded later (lazily, on first expand) inherits an already made but not yet
      //    saved selection instead of only reflecting whatever the last save left in the DB.
      // Values are only ever seeded from the server the first time a given user id is
      // encountered (see renderCourseUsers()) so an unsaved client-side change is never
      // overwritten by a later lazy-loaded course's server state.
      var selectedUsers = {};
      var selectedNotifications = {};

      // Recomputes one course's "check all" checkbox from its currently rendered rows. Needs to
      // run for every course affected by applyUserSelections(), not only the course the
      // originating click happened in, because syncing a user across courses can change rows in
      // courses whose own check-all checkbox was not touched directly.
      function updateCourseCheckAllState(courseid) {
        var $courseCheckboxes = $userlist.find('.shareusers:checkbox[courseid="' + courseid + '"]');
        $userlist.find('.shareusers-check-all[courseid="' + courseid + '"]')
          .prop('checked', $courseCheckboxes.length > 0 && $courseCheckboxes.not(':checked').length === 0);
      }

      // Applies a (un)checked state for user ids to every rendered occurrence of those users,
      // in every course group - not only the course the change originated in - since the
      // underlying share is entity-wide. Only ever sets DOM state via .prop()/.attr(), never by
      // re-triggering click/change events, so this cannot recurse back into the handlers below.
      function applyUserSelections(userids, checked) {
        var changedUsers = {};
        $.each(userids, function (index, userid) {
          changedUsers[userid] = true;
          selectedUsers[userid] = checked;
        });

        var affectedCourseIds = {};

        // Select-all must not scan every course once per user. Walk the rendered copies once,
        // then refresh each affected course's select-all state after the whole batch is applied.
        // Keep the targeted lookup for an ordinary single-user click.
        var selector = '.shareusers:checkbox';
        if (userids.length === 1) {
          selector += '[value="' + userids[0] + '"]';
        }
        $userlist.find(selector).each(function () {
          var userid = String($(this).val());
          if (!Object.prototype.hasOwnProperty.call(changedUsers, userid)) {
            return;
          }
          $(this).prop('checked', checked);

          var courseid = $(this).attr('courseid');
          if (courseid !== undefined) {
            affectedCourseIds[courseid] = true;
          }

          $E.update_notify_state($(this), alwaysNotifyBool,
            Object.prototype.hasOwnProperty.call(selectedNotifications, userid)
              ? selectedNotifications[userid] : undefined);
        });

        $.each(affectedCourseIds, function (courseid) {
          updateCourseCheckAllState(courseid);
        });
      }


      // Same idea as applyUserSelections(), but for the per-user notify checkbox of views/
      // categories: propagates to every rendered occurrence and remembers the choice for courses
      // loaded later.
      function applyNotifySelection(userid, checked) {
        selectedNotifications[userid] = checked;
        $userlist.find('.notifyusers[type="checkbox"][value="' + userid + '"]').prop('checked', checked);
        $userlist.find('.notifyusers[type="hidden"][value="' + userid + '"]').prop('checked', checked);
      }

      // Wires up all the checkbox behaviours (check-all, entity-wide user/notify sync,
      // alwaysNotifyBool handling) for one already-rendered course's
      // user table. Scoped to $content instead of the whole #sharing-userlist, and called once
      // per rendered course (both for courses rendered eagerly and courses rendered lazily on
      // click), because with lazy-loading not every course's table exists yet when the userlist
      // first renders.
      function wireCourseCheckboxes($content) {
        // CHECK ALL button.
        $content.find('.shareusers-check-all').on('click', function () {
          var checked = $(this).is(':checked');
          var courseid = $(this).attr('courseid');
          var userids = $content.find('.shareusers:checkbox[courseid=' + courseid + ']').map(function () {
            return String($(this).val());
          }).get();
          applyUserSelections(userids, checked);
        });

        // Check/uncheck this user everywhere it is currently rendered - direct user shares are
        // entity-wide, not course-specific, see block_exaport_ajax_sharing_userlist_course() in
        // lib/sharelib.php.
        $content.find('.shareusers:checkbox').on('click', function () {
          applyUserSelections([String($(this).val())], $(this).prop('checked'));
        });

        if (type == 'views_mod' || type == 'cat_mod') {
          $content.find('.notifyusers[type="checkbox"]').on('click', function () {
            applyNotifySelection(String($(this).val()), $(this).prop('checked'));
          });
        }

        var flag = 0;
        $content.find('table > tbody > tr > td > input.shareusers').each(function () {
          if (flag == 1) {
            return false;
          }
          if ($(this).prop('checked') == false) {
            flag = 1;
          }
        });
        if (flag == 0) {
          $content.find('table > tbody > tr > td > input.shareusers-check-all').prop('checked', true);
        }

        // Disable notifyusers if alwaysNotify is true.
        if (alwaysNotifyBool) {
          $content.find('.notifyusers[type="checkbox"]').prop('disabled', true);
        }
      }

      // Reuse the same rows and selection maps for the initial selection and full rosters.
      function renderCourseUsers($content, courseid, users, complete) {
        if (complete) {
          var returnedUsers = {};
          $.each(users, function (index, user) { returnedUsers[String(user.id)] = true; });
          // Enrolments, filtering or account status may change while the form is open. A full
          // roster can omit a saved recipient; never let replacing the table revoke that share.
          $.each(courseUsers[courseid] || [], function (index, user) {
            if (selectedUsers[String(user.id)] && !returnedUsers[String(user.id)]) {
              users.push(user);
            }
          });
        }
        courseUsers[courseid] = users;
        var html = '';
        if (!$.empty(users)) {
          html += "<table width=\"70%\">";
          html += "<tr><th align=\"center\">&nbsp;</th>";
          if (type == 'views_mod' || type == 'cat_mod') {
            html += "<th align=\"center\">&nbsp;</th>";
          }
          html += "<th align=\"left\">" + $E.translate('name') + "</th><th align=\"right\">" + $E.translate('role') + "</th></tr>";

          // A selected-only list must not pretend that every course participant is selected.
          if (complete && courseid != -1) {
            html += '<tr><td align=\"center\" width="5%">';
            html += '<input class="shareusers-check-all" courseid="' + courseid + '" type="checkbox" />';
            html += "<br />" + $E.translate('checkall');
            html += "</td></tr>";
          }

          $.each(users, function (tmp, user) {
            var uid = String(user.id);

            // Seed the selection maps from the server response only the first time this user id
            // is encountered, so a later lazy-loaded course carrying the same user (entity-wide
            // sharing means the server reports the same shared_to for every course a user is
            // enrolled in) never overwrites an unsaved client-side change made in a course
            // rendered earlier.
            var checked;
            if (Object.prototype.hasOwnProperty.call(selectedUsers, uid)) {
              checked = selectedUsers[uid];
            } else {
              checked = !!user.shared_to;
              selectedUsers[uid] = checked;
            }

            var notifyChecked;
            if (Object.prototype.hasOwnProperty.call(selectedNotifications, uid)) {
              notifyChecked = selectedNotifications[uid];
            } else {
              notifyChecked = !!user.notify_user;
              selectedNotifications[uid] = notifyChecked;
            }
            notifyChecked = checked && (alwaysNotifyBool || notifyChecked);

            html += '<tr><td align=\"center\" width="5%">';
            html += '<input class="shareusers" type="checkbox" courseid="' + courseid + '" name="shareusers[' + user.id + ']" ';
            html += ' value="' + user.id + '"' + (checked ? ' checked="checked"' : '') + ' />';
            if (type == 'views_mod' || type == 'cat_mod') {
              html += "<br />" + $E.translate('sharejs');
              html += '</td><td align=\"center\" width="5%" style="padding-right: 20px;">';

              html += '<input class="notifyusers" type="checkbox"' + (checked ? '' : ' disabled="disabled"') + ' name="notifyusers[' + user.id + ']"  ';
              html += ' value="' + user.id + '"' + (notifyChecked ? ' checked="checked"' : '') + ' />';

              // workaround for disabled checkboxes not submittting: https://stackoverflow.com/questions/4727974/how-to-post-submit-an-input-checkbox-that-is-disabled
              if (alwaysNotifyBool) {
                // if the notify state differs from the (pending) shared state, the checkbox should be red and a hover info should write "asdf"
                if (notifyChecked !== checked) {
                  html += ' <span title="' + $E.translate('viewmustbesafed') + '" style="color: red; font-weight: bold;">(!)</span> ';
                }

                html += '<input class="notifyusers" type="hidden"' + (checked ? '' : ' disabled="disabled"') + ' name="notifyusers[' + user.id + ']"  ';
                html += ' value="' + user.id + '"' + (notifyChecked ? ' checked="checked"' : '') + ' />';
              }

              html += "<br />" + $E.translate('notify');
            }
            html += "</td><td align=\"center\" width='45%'>" + user.name + "</td><td align=\"center\" width='45%'>" + user.rolename + "</td></tr>";
          });

          html += "</table>";
        } else {
          html += $E.translate('nousersfound');
        }
        $content.html(html);
        wireCourseCheckboxes($content);
        if (!complete) {
          showCourseAction($content, M.util.get_string('sharing_load_more_users', 'block_exaport'));
        }
      }

      function showCourseAction($content, label, error) {
        $content.find('.exaport-course-actions').remove();
        var $actions = $('<div class="exaport-course-actions p-2"></div>');
        if (error) {
          $('<div class="text-danger" role="alert"></div>').text(M.util.get_string('sharing_load_users_error', 'block_exaport')).appendTo($actions);
        }
        $('<button type="button" class="btn btn-link p-0 exaport-load-course-users"></button>')
          .text(label).appendTo($actions);
        // Long recipient lists need a loading control at both ends.
        // An empty course only needs the existing bottom control.
        if ($content.find('input.shareusers').length) {
          var $topActions = $('<div class="exaport-course-actions p-2"></div>');
          $topActions.append($actions.find('button').clone());
          $content.prepend($topActions);
        }
        $content.append($actions);
      }

      // Keep the selected-recipient inputs while loading or on failure: they must still submit.
      function loadCourseUsers(courseid, $fieldset) {
        if (!initialLoaded || courseStates[courseid] === 'full' || courseStates[courseid] === 'loading') {
          return;
        }
        var previousState = courseStates[courseid];
        courseStates[courseid] = 'loading';
        var $content = $fieldset.find('.course-group-content');
        showCourseAction($content, M.util.get_string('loading', 'moodle'));
        $content.attr('aria-busy', 'true').find('.exaport-load-course-users').prop('disabled', true);

        function failed() {
          courseStates[courseid] = previousState;
          showCourseAction($content, M.util.get_string('retry', 'moodle'), true);
        }
        var courseparams = {action: 'userlistcourse', sesskey: M.cfg.sesskey, usercourseid: courseid};
        if (type == 'cat_mod') {
          courseparams.id = $('input[name="id"]').val() || 0;
        }
        $.getJSON(document.location.href, courseparams).done(function (users) {
          if (!Array.isArray(users)) {
            failed();
            return;
          }
          try {
            renderCourseUsers($content, courseid, users, true);
            courseStates[courseid] = 'full';
          } catch (error) {
            // Unlike a network failure, a rendering error may have removed selected inputs.
            initialLoaded = false;
            $ready.val('0');
            showNotReady();
          }
        }).fail(failed).always(function () {
          $content.attr('aria-busy', 'false');
        });
      }

      function showNotReady() {
        $root.find('.exaport-userlist-incomplete').remove();
        $('<div class="alert alert-warning exaport-userlist-incomplete" role="alert" tabindex="-1"></div>')
          .text(M.util.get_string('sharing_users_not_ready', 'block_exaport')).insertBefore($userlist).trigger('focus');
      }

      // Prevent an incomplete picker from turning an unchanged Save into "remove all shares".
      // The server checks the same readiness field before writing any entity changes.
      $userlist.closest('form').on('submit.exaportSharedUsers', function (event) {
        // Cancelling must remain possible even when the initial request failed.
        if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name === 'cancel') {
          return;
        }
        var $master = $root.find('[name="shareenabled"]');
        var $internal = $root.find('[name="internshare"], [name="internaccess"]');
        var mode = $root.find('[name="shareall"]:checked').val() ||
          $root.find('[data-region="disabled-group-mode"]').val() || '0';
        if ((!$master.length || $master.is(':checked')) && (!$internal.length || $internal.is(':checked')) &&
            mode === '0' && !initialLoaded) {
          event.preventDefault();
          showNotReady();
        }
      });

      function loadInitialUsers() {
        $ready.val('0');
        $userlist.text(M.util.get_string('loading', 'moodle')).attr('aria-busy', 'true');
        var params = {action: 'userlist', sesskey: M.cfg.sesskey};
        if (type == 'cat_mod') {
          params.id = $('input[name="id"]').val() || 0;
        }
        function failed() {
          $userlist.empty();
          $('<div class="alert alert-danger" role="alert"></div>').text(M.util.get_string('sharing_load_users_error', 'block_exaport')).appendTo($userlist);
          $('<button type="button" class="btn btn-link"></button>').text(M.util.get_string('retry', 'moodle'))
            .on('click', loadInitialUsers).appendTo($userlist);
        }
        $.getJSON(document.location.href, params).done(function (courses) {
          if (!Array.isArray(courses)) {
            failed();
            return;
          }
          try {
            renderInitialUsers(courses);
            initialLoaded = true;
            $ready.val('1');
            $root.find('.exaport-userlist-incomplete').remove();
          } catch (error) {
            failed();
          }
        }).fail(failed).always(function () {
          $userlist.attr('aria-busy', 'false');
        });
      }

      function renderInitialUsers(courses) {
        var html = '';
        if (!$.empty(courses)) {
          $.each(courses, function (tmp, course) {
            // Headings alone are not a complete saved selection (including cached old responses).
            if (course.has_shared_users && $.empty(course.users)) {
              throw new Error('Missing selected recipients');
            }
            html += '<fieldset class="course-group" courseid="' + course.id + '"><legend class="course-group-title">';
            html += ($E.courseid == course.id ? '<b>' : '');
            html += course.fullname;
            html += ($E.courseid == course.id ? '</b>' : '');
            html += '</legend><div class="course-group-content"></div></fieldset>';
          });
        } else {
          html += '<b>' + $E.translate('nousersfound') + '</b>';
        }
        $userlist.html(html);
        $.each(courses, function (tmp, course) {
          var $fieldset = $userlist.find('.course-group[courseid="' + course.id + '"]');
          if (!$.empty(course.users)) {
            courseStates[course.id] = course.users_complete ? 'full' : 'partial';
            renderCourseUsers($fieldset.find('.course-group-content'), course.id, course.users, !!course.users_complete);
            $fieldset.addClass('course-group-open');
          }
        });
      }

      // Delegate within this widget only, so user/group pickers never toggle each other's rows.
      $userlist.on('click', '.course-group-title', function () {
        var $fieldset = $(this).closest('.course-group');
        $fieldset.toggleClass('course-group-open');
        var courseid = $fieldset.attr('courseid');
        if ($fieldset.hasClass('course-group-open') && courseStates[courseid] === undefined) {
          loadCourseUsers(courseid, $fieldset);
        }
      });
      $userlist.on('click', '.exaport-load-course-users', function () {
        var $fieldset = $(this).closest('.course-group');
        loadCourseUsers($fieldset.attr('courseid'), $fieldset);
      });
      loadInitialUsers();
    },

    grouplist_loaded: false,
    load_grouplist: function (type) {
      if (this.grouplist_loaded) {
        return;
      }
      this.grouplist_loaded = true;

      $('#sharing-grouplist').html('loading grouplist...');

      // The sesskey is always sent, because all sharing endpoints (view/category/item) require it.
      var params = {action: 'grouplist', sesskey: M.cfg.sesskey};
      if (type == 'cat_mod') {
        params.id = $('input[name="id"]').val() || 0;
      }
      $.getJSON(document.location.href, params, function (courses) {
        var html = '';

        if (!$.empty(courses)) {
          $.each(courses, function (tmp, course) {
            html += '<fieldset class="course-group"><legend class="course-group-title">';
            html += ($E.courseid == course.id ? '<b>' : '');
            html += course.name;
            html += ($E.courseid == course.id ? '</b>' : '');
            html += '</legend>';

            html += '<div class="course-group-content">';
            if (!$.empty(course.groups)) {
              html += "<table width=\"70%\">";
              html += "<tr><th align=\"center\">&nbsp;</th>";
              if (type == 'views_mod') {
                html += "<th align=\"center\">&nbsp;</th>";
              }
              html += "<th align=\"left\">" + $E.translate('grouptitle') + "</th><th align=\"right\">" + $E.translate('membercount') + "</th></tr>";

              html += '<tr><td align=\"center\" width="5%">';
              html += '<input class="sharegroups-check-all" courseid="' + course.id + '" type="checkbox" />';
              html += "<br />" + $E.translate('checkall');
              html += "</td></tr>";

              $.each(course.groups, function (tmp, group) {
                html += '<tr><td align=\"center\" width="5%">';
                html += '<input class="sharegroups" type="checkbox" courseid="' + course.id + '" name="sharegroups[' + group.id + ']" ';
                html += ' value="' + group.id + '"';
                html += (group.shared_to ? ' checked="checked"' : '') + ' />';
                html += "</td><td align=\"center\" width='45%'>" + group.name + "</td><td align=\"center\" width='45%'>" + group.member_cnt + "</td></tr>";
              });

              html += "</table>";
            } else {
              html += $E.translate('nogroupsfound');
            }
            html += '</div>';
            html += "</fieldset>";
          });
        } else {
          html += '<b>' + $E.translate('nogroupsfound') + '</b>';
        }

        $('#sharing-grouplist').html(html);

        $('#sharing-grouplist .sharegroups-check-all').click(function () {
          // Check/uncheck all groups in this course.
          $('#sharing-grouplist .sharegroups:checkbox[courseid=' + $(this).attr('courseid') + ']')
            .prop('checked', $(this).is(':checked'))
            // Execute click handler.
            .each(function () {
              // Wrapped in each, because triggerHandler only works on first element.
              $(this).triggerHandler('click');
            });
        });

        // Stop slow loading.
        $('#sharing-grouplist .sharegroups:checkbox').click(function () {
          // Check/uncheck all groups.
          var $courseCheckboxes = $('#sharing-grouplist .sharegroups:checkbox[courseid=' + $(this).attr('courseid') + ']');
          $('#sharing-grouplist .sharegroups-check-all[courseid=' + $(this).attr('courseid') + ']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
        });
        $('#sharing-grouplist .course-group-content').each(function () {
          var flag = 0;
          $(this).find('table > tbody > tr > td > input.sharegroups').each(function () {
            if (flag == 1) {
              return false;
            }
            if ($(this).prop('checked') == false) {
              flag = 1;
            }
          });
          if (flag == 0) {
            $(this).find('table > tbody > tr > td > input.sharegroups-check-all').prop('checked', true);
          }
        });

        // Open/close course group.
        // Scoped to #sharing-grouplist (this widget) only: an unscoped $('.course-group-title')
        // selector here would also match the (hidden but still-in-DOM) userlist's course titles
        // once load_userlist() has run, binding this handler twice on those elements and making
        // the collapse/expand toggle silently cancel itself out - see load_userlist().
        $('#sharing-grouplist .course-group-title').on('click', function () {
          $(this).closest('.course-group').toggleClass('course-group-open');
        });
        // Open all shared courses.
        $('#sharing-grouplist .course-group').has('input:checked').addClass('course-group-open');
      });
    },

    popup: function (config) {

      var popup = this.last_popup = new M.core.dialogue({
        headerContent: config.headerContent || config.title || 'Popup',

        body_content: '',
        visible: true, // By default it is not displayed.
        modal: false, // Sollte true sein, aber wegen moodle bug springt dann das fenster immer nach oben.
        zIndex: 1000,
        height: config.height || '80%',
        width: config.width || '85%',
      });

      // Disable scrollbars.
      $(window).disablescroll();

      popup.$body = $(popup.bodyNode.getDOMNode());
      popup.$body.css('overflow', 'auto');
      // Add id exaport, needed for css.
      popup.$body.attr('id', 'exaport');

      // Body mit jquery injecten, dadurch werden z.b. auch javascripts ausgeführt
      // bei anabe im popup constructor eben nicht.
      if (config.body_content) {
        popup.$body.html(config.body_content);
      }

      // Hack my own overlay, because moodle dialogue modal is not working.
      var overlay = $('<div style="opacity:0.7; filter: alpha(opacity=20); background-color:#000; width:100%; height:100%; z-index:10; top:0; left:0; position:fixed;"></div>')
        .appendTo('body');
      // Hide popup when clicking overlay.
      overlay.click(function () {
        popup.hide();
      });

      var orig_hide = popup.hide;
      popup.hide = function () {

        if (config.onhide) {
          config.onhide();
        }

        // Remove overlay, when hiding popup.
        overlay.remove();

        // Enable scrolling.
        $(window).disablescroll('undo');

        // Call original popup.hide().
        orig_hide.call(popup);
      };

      popup.remove = function () {
        if (this.$body.is(':visible')) {
          this.hide();
        }

        this.destroy();
      };

      return popup;
    },
  };

  $(function () {
    if ($('body').attr('class').match(/course-([^\s]+)/)) {
      $E.courseid = RegExp.$1;
    }

    // Only does anything on the user search sharing page, which is the only page carrying that
    // form - no separate per page script is needed for those few lines.
    $E.init_share_user_search();
  });

})();


/**
 *
 * @param selector element (container where we need to get icons)
 */
function block_exaport_update_fontawesome_icons(element) {
  if (typeof FontAwesome === 'undefined') {
    return false;
  }

  // Find the specific block
  // var iconsBlock = document.querySelector('#exaport');
  // var iconsBlock = $('#exaport').get(0);
  if (typeof element !== 'undefined' && element.length) {
    var iconsBlock = element.get(0);

    // Replace icons within this block
    if (iconsBlock) {
      return FontAwesome.dom.i2svg({node: iconsBlock});
    }
  }
}


function showExaportToaster(message, target = "success") {
  if (typeof Toastify === 'undefined') {
    return false;
  }
  var exaportWpToastifyOptions = {
    duration: 3000,
    newWindow: true,
    gravity: "top", // `top` or `bottom`
    position: "right", // `left`, `center` or `right`
    stopOnFocus: true, // Prevents dismissing of toast on hover
    style: {
      background: "none",
    },
    onClick: function () {
    } // Callback after click
  };
  exaportWpToastifyOptions.text = message;
  switch (target) {
    case 'success':
      exaportWpToastifyOptions.className = "bg-success text-white";
      break;
    case 'error':
      exaportWpToastifyOptions.className = "bg-danger text-white";
      exaportWpToastifyOptions.close = true;
      exaportWpToastifyOptions.duration = 30000;
      break;
    case 'info':
      exaportWpToastifyOptions.className = "bg-info text-white";
      break;
  }
  ;
  Toastify(exaportWpToastifyOptions).showToast();
}
