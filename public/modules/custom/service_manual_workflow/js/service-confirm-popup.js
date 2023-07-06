(function ($, Drupal) {

  Drupal.behaviors.serviceConfirmPopup = {
    attach: function (context, settings) {

      // call to function to open popup
      $("#edit-submit").click(function (e) {
        e.preventDefault();
        let message = getConfirmMessage(context);
        if ($('#edit-title-0-value').val() != '') {
          title = $('#edit-title-0-value').val();
        }
        confirmPopup(title, message);
      });

      /**
       *
       * @param title
       * @param message
       */
      function confirmPopup(title = "", message = "") {
        var content = '<div class="desc">' + message + '</div>';
        confirmationDialog = Drupal.dialog(content, {
          dialogClass: 'confirm-dialog',
          resizable: true,
          closeOnEscape: false,
          width: 600,
          title: title,
          buttons: [
            {
              text: 'Confirm',
              class: 'button--primary button',
              click: function () {
                $('#custom-form-submit-after-check').click();
              }
            },
            {
              text: 'Close',
              click: function () {
                $(this).dialog('close');
              }
            }
          ],
        });
        confirmationDialog.showModal();
      }

      /**
       * Get confirm message for
       * @param context
       * @returns {*}
       */
      function getConfirmMessage(context) {
        let selector = 'edit-moderation-state-0';
        let moderationStateWidget = $('div[data-drupal-selector="' + selector + '"]', context);
        let state = getState(moderationStateWidget, selector);
        return fetchMessageForState(state);
      }

      /**
       * Fetch message for selected state.
       *
       * @param state
       * @returns {*}
       */
      function fetchMessageForState(state) {
        if (settings.service_manual_workflow.popup_settings[state] === '') {
          return
        }
        return settings.service_manual_workflow.popup_settings[state];
      }

      /**
       * Get selected state.
       *
       * @param widget
       * @param selector
       * @returns {*|string|jQuery}
       */
      function getState(widget, selector) {
        return $("select", widget).val();
      }
    }
  }
})(jQuery, Drupal);
