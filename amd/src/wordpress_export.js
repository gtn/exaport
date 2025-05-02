/* eslint-disable jsdoc/require-jsdoc,no-console,max-len */
import $ from 'jquery';
// import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import Config from 'core/config';

function buttonLoading(button, isLoading) {
  // disable the button to prevent double click
  button.prop('disabled', isLoading);
  // for a-tags (disable the link with bootstrap .disabled-css-class)
  button.toggleClass('disabled', isLoading);

  var buttonIcon = button.find('.exaport-icon');
  buttonIcon.toggleClass('fa-spin', isLoading);
}

export function init() {
  // Regular login button: silent creating of the wordpress account
  $(document).on('click', '.exaport-wp-login, .exaport-wp-loginUpdate', function (e) {
    e.preventDefault();
    $('.exaport-wp-error').remove();
    var theButton = $(this);
    var action = 'login';
    var successMessage = 'Logged in';
    if (theButton.hasClass('exaport-wp-loginUpdate')) {
      action = 'loginUpdate';
      successMessage = 'Updated';
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

      addToast('Error: 1745225660311', {
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
    var successMessage = 'Exported';
    if (theButton.hasClass('exaport-wp-viewUpdate')) {
      action = 'viewUpdate';
      var successMessage = 'Updated';
    } else if (theButton.hasClass('exaport-wp-viewRemove')) {
      action = 'viewRemove';
      var successMessage = 'Removed';
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
        addToast('Request error!', {
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
      addToast('Request error!', {
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
    var successMessage = 'Exported';
    if (theButton.hasClass('exaport-wp-cvUpdate')) {
      action = 'cvUpdate';
      var successMessage = 'Updated';
    } else if (theButton.hasClass('exaport-wp-cvRemove')) {
      action = 'cvRemove';
      var successMessage = 'Removed';
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
        addToast('Request error!', {
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
      addToast('Request error (code: 1745230772232)!', {
        type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
        autohide: false,        // disappears after a few seconds
        closeButton: true,
      });
      // showExaportToaster('Request error (code: 1745230772232)!', 'error');
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
