(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.customDatetimePicker = {
    attach(context, settings) {
      let widget = '.field--widget-hel-tpm-service-dates-date-range';
      formatDatePicker(widget);
      formatTimePicker(widget);
    }
  }
})(jQuery, Drupal, drupalSettings);
