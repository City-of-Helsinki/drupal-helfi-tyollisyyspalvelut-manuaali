(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.reset_additional_filters = {
    attach: function (context, settings) {
      let form = $(context);
      let btn = $('button[name="reset_additional_filters"]', form);
      once('additional-reset-button-click', btn.click(function (event) {
        event.preventDefault();
        form.find('select').val('');
        form.find('input[type=radio]').prop('checked', false);
        form.find('input[type=checkbox]').prop('checked', false);
        form.find('[id^="edit-submit-"]').click();
      }));
    }
  }
})(jQuery, Drupal, drupalSettings);
