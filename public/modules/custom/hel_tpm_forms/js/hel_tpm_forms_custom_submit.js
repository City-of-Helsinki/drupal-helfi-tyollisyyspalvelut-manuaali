(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_custom_submit = {
    attach: function (context, settings) {
      let submitButton = $("input[data-drupal-selector='edit-submit']");
      let button = $("input#hel-tpm-service-submit-button");
      let moderationState = $("select[data-drupal-selector='edit-moderation-state-0-state']")

      button.click(function (event) {
        event.preventDefault();
        let state = this.getAttribute('data-state')
        moderationState.val(state);
        Drupal.behaviors.serviceConfirmPopup.addPopup(context, settings);
      })
    }
  }
})(jQuery, Drupal, drupalSettings);
