
define(['jquery', 'core/notification', 'core/toast'], function($, notification, toast) {

  const init = () => {

    $('#admin-block_exaport_button_with_js').hide();
    checkEnabledWpSSO();

    $('body').on('click', '.block_exaport_request_wp_sso_passphrase, .block_exaport_remove_passphrase, .block_exaport_test_passphrase', function (e) {
      e.preventDefault();

      var theButton = $(this);

      var action = '-NO-';
      if (theButton.hasClass('block_exaport_request_wp_sso_passphrase')) {
        action = 'requestPassphrase';
      } else if (theButton.hasClass('block_exaport_remove_passphrase')) {
        action = 'removePassphrase';
      } else if (theButton.hasClass('block_exaport_test_passphrase')) {
        action = 'testPassphrase';
      }

      var data = {
        wpAction: 1,
        action: action
      };

      switch (action) {
        case 'requestPassphrase':
          var secret = $.trim($('.block_exaport_secret_input').val());
          if (!secret) {
            alert('Enter the secret!');
            return false;
          }
          data.secret = secret;
          break;
        case 'removePassphrase':
          break;
        case 'testPassphrase':
          break;
      }

      $.ajax({
        url: M.cfg.wwwroot + '/blocks/exaport/importexport.php',
        type: 'POST',
        dataType: 'json',
        data: data,
        xhrFields: { withCredentials: true }   // keep Moodle session
      })
        .done(function (data) {
          if (data.result === 'success') {
            $('.block_exaport_message').replaceWith(data.html);
          } else if (data.result === 'alert') {
            toast.add(data.message, {
              type: 'success',       // 'success' | 'info' | 'warning' | 'danger'
              autohide: true,        // disappears after a few seconds
            });
            /*notification.alert(
              'Done',
              data.message,
              'Close'
            );*/

          } else if (data.result === 'error') {
            toast.add('Error <br>' + data.message, {
              type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
              autohide: false,        // disappears after a few seconds
              closeButton: true,
            });
            /*notification.alert(
              'Done',
              'Error: ' + data.message,
              'Close'
            );*/
            // alert('Error: ' + data.message);
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          toast.add('Something went wrong!', {
            type: 'danger',       // 'success' | 'info' | 'warning' | 'danger'
            autohide: false,        // disappears after a few seconds
            closeButton: true,
          });
          console.log('error:', errorThrown, 'code: 1745419297633');
          // alert('Something was wrong!');
        });
    });


    $('body').on('change', '#id_s__block_exaport_wp_sso_enabled', function(e) {
      checkEnabledWpSSO();
    })

    function checkEnabledWpSSO() {

      var theValue = $('#id_s__block_exaport_wp_sso_enabled').prop('checked');

      var relatedOptions = ['admin-block_exaport_wp_sso_url', 'admin-block_exaport_wp_sso_passphrase'];

      if (theValue) {
        // show related options
        $(relatedOptions).each(function(i, elementId) {
          $('#'+elementId).show(50);
        })
      } else {
        // hide related options
        $(relatedOptions).each(function(i, elementId) {
          $('#'+elementId).hide(50);
        })
      }

    }


  };

  return { init };
});

