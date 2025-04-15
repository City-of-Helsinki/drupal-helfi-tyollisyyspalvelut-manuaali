(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.customDatetimePicker = {
    attach(context, settings) {
      let widgets = settings.hel_tpm_service_dates.field_name;
      $(widgets).each(function () {
        console.log(this);
        formatDatePicker(this);
        formatTimePicker(this);
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
