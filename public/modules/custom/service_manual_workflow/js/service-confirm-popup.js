(function ($, Drupal) {

  Drupal.behaviors.serviceConfirmPopup = {
    attach: function (context, settings) {
      let button = $('#edit-submit-popup');
      // call to function to open popup
      $(once('confirmPopup', button)).each(function() {
        button.click(function (e) {
          e.preventDefault();
          Drupal.behaviors.serviceConfirmPopup.addPopup(context, settings);
        });
      });

    },

    addPopup: function(context, settings) {
      let title = '';
      let message = this.getConfirmMessage(context, settings);
      if ($('#edit-title-0-value').val() != '') {
        title = $('#edit-title-0-value').val();
      }

      // If message is undefined treat button as ordinary submit.
      if (message === undefined) {
        $('#edit-submit').click();
        return;
      }

      if (message.length > 0) {
        this.confirmPopup(title, message);
      }
    },

    /**
     *
     * @param title
     * @param message
     */
    confirmPopup: function(title = "", message = "") {
    var content = '<div class="desc">' + message + '</div>';
    confirmationDialog = Drupal.dialog(content, {
      dialogClass: 'confirm-dialog',
      resizable: true,
      closeOnEscape: false,
      width: 600,
      title: title,
      buttons: [
        {
          text: Drupal.t('Cancel'),
          click: function () {
            $(this).dialog('close');
          }
        },
        {
          text: Drupal.t('Approve'),
          class: 'button--primary button',
          click: function () {
            $('#edit-submit').click();
          }
        }
      ],
    });
    confirmationDialog.showModal();
  },

  /**
   * Get confirm message for
   * @param context
   * @returns {*}
   */
  getConfirmMessage: function(context, settings) {
    let selector = 'edit-moderation-state-0';
    let moderationStateWidget = $('div[data-drupal-selector="' + selector + '"]', context);
    let state = this.getState(moderationStateWidget, selector);
    return this.fetchMessageForState(state, settings);
  },

  /**
   * Fetch message for selected state.
   *
   * @param state
   * @returns {*}
   */
  fetchMessageForState: function(state, settings) {
    if (settings.service_manual_workflow.popup_settings[state] === '') {
      return
    }
    return settings.service_manual_workflow.popup_settings[state];
  },

  /**
   * Get selected state.
   *
   * @param widget
   * @param selector
   * @returns {*|string|jQuery}
   */
  getState: function(widget, selector) {
    return $("select", widget).val();
  }
}
})(jQuery, Drupal);
