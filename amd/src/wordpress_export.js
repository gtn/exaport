/* eslint-disable jsdoc/require-jsdoc,no-console,max-len */
import $ from 'jquery';
// import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import Config from 'core/config';
import {get_strings as getStrings} from 'core/str';

// Load all strings at initialization
var strings = {};

function loadStrings() {
  var stringKeys = [
    {key: 'wp_logged_in', component: 'block_exaport'},
    {key: 'wp_updated', component: 'block_exaport'},
    {key: 'wp_exported', component: 'block_exaport'},
    {key: 'wp_removed', component: 'block_exaport'},
    {key: 'wp_request_error', component: 'block_exaport'},
    {key: 'wp_request_error_code', component: 'block_exaport'},
  ];

  getStrings(stringKeys). then(function(results) {
    strings. logged_in = results[0];
    strings.updated = results[1];
    strings. exported = results[2];
    strings.removed = results[3];
    strings.request_error = results[4];
    strings. request_error_code = results[5];
    return true;
  }).fail(function() {
    // Fallback to English
    strings.logged_in = 'Logged in';
    strings.updated = 'Updated';
    strings.exported = 'Exported';
    strings.removed = 'Removed';
    strings.request_error = 'Request error! ';
    strings.request_error_code = 'Request error (code: {$a})!';
  });
}

function buttonLoading(button, isLoading) {
  // disable the button to prevent double click
  button.prop('disabled', isLoading);
  // for a-tags (disable the link with bootstrap .disabled-css-class)
  button.toggleClass('disabled', isLoading);

  var buttonIcon = button.find('.exaport-icon');
  buttonIcon.toggleClass('fa-spin', isLoading);
}

export function init() {
  // Load strings first
  loadStrings();

  // Regular login button: silent creating of the wordpress account
  $(document).on('click', '.exaport-wp-login, .exaport-wp-loginUpdate', function (e) {
    e.preventDefault();
    $('.exaport-wp-error').remove();
    var theButton = $(this);
    var action = 'login';
    var successMessage = strings.logged_in || 'Logged in';
    if (theButton.hasClass('exaport-wp-loginUpdate')) {
      action = 'loginUpdate';
      successMessage = strings.updated || 'Updated';
    }

    buttonLoading(theButton, true);

    wpRequest(action, [], function (response) {
      buttonLoading(theButton, false);

      response = JSON.parse(response).response;
      if (response.success) {
        // get the form with WP functionality
        wpRequest('wpForm', [], function (response) {
          theButton.closest('form').html(response);
        });
        // showExaportToaster(successMessage);
        addToast(successMessage, {
          type: 'success',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: true,        // disappears after a few seconds
        });
      }
      if (response.error) {
        // showExaportToaster(response.message, 'error');
        addToast(response.message, {
          type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: false,        // disappears after a few seconds
          closeButton: true,
        });
      }

    }, function () {
      buttonLoading(theButton, false);

      addToast(strings.request_error_code.replace('{$a}', '1745225660311') || 'Error: 1745225660311', {
        type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
        autohide: false,        // disappears after a few seconds
        closeButton: true,
      });
      // showExaportToaster('Error: 1745225660311', 'error');
    });
  });

  // send the view into WP
  $(document).on('click', '.exaport-wp-viewExport, .exaport-wp-viewUpdate, .exaport-wp-viewRemove', function (e) {
    e.preventDefault();
    $('.exaport-wp-error').remove();
    var theButton = $(this);
    var viewId = theButton.attr('data-viewId');
    var data = {
      viewId: viewId
    };
    var action = 'viewExport';
    var successMessage = strings.exported || 'Exported';
    if (theButton.hasClass('exaport-wp-viewUpdate')) {
      action = 'viewUpdate';
      successMessage = strings.updated || 'Updated';
    } else if (theButton.hasClass('exaport-wp-viewRemove')) {
      action = 'viewRemove';
      successMessage = strings.removed || 'Removed';
    }

    buttonLoading(theButton, true);

    wpRequest(action, data, function (response) {
      response = JSON.parse(response).response;
      if (response && response.success) {
        if (action == 'viewRemove') {
          // hide "view" button
          // add/update 'view' button
          $('.exaport-wp-viewPreview[data-viewId=' + viewId + ']').addClass('d-none').attr('href', '#');
          // update the "update" button to "export"
          $('.exaport-wp-viewExport[data-viewId=' + viewId + ']').removeClass('d-none');
          $('.exaport-wp-viewUpdate[data-viewId=' + viewId + ']').addClass('d-none');
          // remove "remove" button
          $('.exaport-wp-viewRemove[data-viewId=' + viewId + ']').addClass('d-none');
          // update icons - hide them at all
          var iconWrapper = $('.wp-exported-icons-wrapper[data-viewId=' + viewId + ']');
          iconWrapper.find('.exaport-icon').addClass('d-none');
          // clear the date
          $('.wp-exported-wptimemodified-wrapper[data-viewId=' + viewId + ']').text('');
        } else {
          // add/update 'view' button
          var viewButton = $('.exaport-wp-viewPreview[data-viewId=' + viewId + ']');
          viewButton.removeClass('d-none');
          var href = response.view.shortUrl ? response.view.shortUrl : response.view.url;
          viewButton.attr('href', href);
          // update the "export" button to "update"
          $('.exaport-wp-viewExport[data-viewId=' + viewId + ']').addClass('d-none');
          $('.exaport-wp-viewUpdate[data-viewId=' + viewId + ']').removeClass('d-none');
          // show "remove" button
          $('.exaport-wp-viewRemove[data-viewId=' + viewId + ']').removeClass('d-none');
          // update icons
          var iconWrapper = $('.wp-exported-icons-wrapper[data-viewId=' + viewId + ']');
          iconWrapper.find('.exaport-icon').addClass('d-none');
          iconWrapper.find('.exaport-icon.wp-exported').removeClass('d-none');
          // change the date
          $('.wp-exported-wptimemodified-wrapper[data-viewId=' + viewId + ']').text(response.view.timemodified);
        }
        // toaster
        // showExaportToaster(successMessage);
        addToast(successMessage, {
          type: 'success',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: true,        // disappears after a few seconds
        });
      }
      if (response && response.error) {
        // toaster
        // showExaportToaster(response.message, 'error');
        addToast(response.message, {
          type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: false,        // disappears after a few seconds
          closeButton: true,
        });
        // theButton.after('<div class="alert alert-danger alert-sm .exaport-wp-error">' + response.message + '</div>');
      }
      if (!response) {
        addToast(strings.request_error || 'Request error!', {
          type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: false,        // disappears after a few seconds
          closeButton: true,
        });
        // showExaportToaster('Request error!', 'error');
      }

      // stop rotation
      buttonLoading(theButton, false);
    }, function () {
      // stop rotation
      buttonLoading(theButton, false);

      // toaster
      addToast(strings.request_error || 'Request error!', {
        type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
        autohide: false,        // disappears after a few seconds
        closeButton: true,
      });
      // showExaportToaster('Request error!', 'error');
    });
  });


  // Send the CV into WP
  $(document).on('click', '.exaport-wp-cvExport, .exaport-wp-cvUpdate, .exaport-wp-cvRemove', function (e) {
    e.preventDefault();
    $('.exaport-wp-error').remove();
    var data = {};
    var theButton = $(this);
    var action = 'cvExport';
    var successMessage = strings.exported || 'Exported';
    if (theButton.hasClass('exaport-wp-cvUpdate')) {
      action = 'cvUpdate';
      successMessage = strings.updated || 'Updated';
    } else if (theButton.hasClass('exaport-wp-cvRemove')) {
      action = 'cvRemove';
      successMessage = strings.removed || 'Removed';
    }
    // rotate the icon
    buttonLoading(theButton, true);

    wpRequest(action, data, function (response) {
      response = JSON.parse(response).response;
      if (response && response.success) {
        if (action == 'cvRemove') {
          // hide Info block
          $('.exaport-wp-cv-info').addClass('d-none');
          // show "export" button
          $('.exaport-wp-cvExport').removeClass('d-none');
        } else if (action == 'cvExport') {
          // hide export button
          $('.exaport-wp-cvExport').addClass('d-none');
          // show info block
          $('.exaport-wp-cv-info').removeClass('d-none');
          // update the data
          $('.exaport-wp-cv-info .date').text(response.cv.timemodified);
          var href = response.cv.shortUrl ? response.cv.shortUrl : response.cv.url;
          $('.exaport-wp-cv-info .exaport-wp-cvView').attr('href', href);
        } else {
          // update the data
          $('.exaport-wp-cv-info .date').text(response.cv.timemodified);
          var href = response.cv.shortUrl ? response.cv.shortUrl : response.cv.url;
          $('.exaport-wp-cv-info .exaport-wp-cvView').attr('href', href);
        }
        // toaster
        addToast(successMessage, {
          type: 'success',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: true,        // disappears after a few seconds
        });
        // showExaportToaster(successMessage);
      }
      if (response && response.error) {
        // toaster
        addToast(response.message, {
          type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: false,        // disappears after a few seconds
          closeButton: true,
        });
        // showExaportToaster(response.message, 'error');
      }
      if (!response) {
        addToast(strings.request_error || 'Request error!', {
          type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
          autohide: false,        // disappears after a few seconds
          closeButton: true,
        });
        // showExaportToaster('Request error!', 'error');
      }

      // stop rotation
      buttonLoading(theButton, false);
    }, function () {
      // stop rotation
      buttonLoading(theButton, false);
      // toaster
      addToast(strings.request_error_code.replace('{$a}', '1745230772232') || 'Request error (code: 1745230772232)!', {
        type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
        autohide: false,        // disappears after a few seconds
        closeButton: true,
      });
      // showExaportToaster('Request error (code: 1745230772232)!', 'error');
    });
  });

  // Remove the whole WordPress profile
  $(document).on('click', '.exaport-wp-profileRemove', function (e) {
    e.preventDefault();
    $('.exaport-wp-error').remove();

    // Confirm before removing
    if (! confirm('Are you sure you want to remove your entire WordPress profile?  This will delete all your data including CV and views.')) {
      return;
    }

    var theButton = $(this);
    var action = 'profileRemove';
    var successMessage = strings.removed || 'Removed';

    buttonLoading(theButton, true);

    wpRequest(action, {}, function (response) {
      response = JSON.parse(response).response;
      if (response && response.success) {
        // Reload the form to show login button again
        wpRequest('wpForm', [], function (response) {
          theButton.closest('form').html(response);
        });
        addToast(successMessage, {
          type: 'success',
          autohide: true,
        });
      }
      if (response && response.error) {
        addToast(response.message, {
          type: 'danger',
          autohide: false,
          closeButton: true,
        });
      }
      if (!response) {
        addToast(strings.request_error || 'Request error!', {
          type: 'danger',
          autohide: false,
          closeButton: true,
        });
      }

      // stop rotation
      buttonLoading(theButton, false);
    }, function () {
      // stop rotation
      buttonLoading(theButton, false);

      addToast(strings.request_error_code. replace('{$a}', '1745590000010') || 'Request error (code: 1745590000010)!', {
        type: 'danger',
        autohide: false,
        closeButton:  true,
      });
    });
  });
}


function wpRequest(action, parameters, successFunction, failureFunction) {

  var ajaxUrl = $('.exaport-wp-form').attr('data-ajaxUrl');

  $.ajax({
    url: ajaxUrl,
    type: 'POST',
    data: {
      wp_action: action,
      parameters: parameters,
      sesskey: Config.sesskey,
    },
    success: function (res) {
      successFunction(res);
    },
    failure: function (res) {
      failureFunction(res);
    }
  });

}
