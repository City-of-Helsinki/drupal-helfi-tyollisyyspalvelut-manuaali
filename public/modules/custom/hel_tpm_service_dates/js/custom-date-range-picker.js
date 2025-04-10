(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.customDatetimePicker = {
    attach(context, settings) {
      let widget = '.field--widget-hel-tpm-service-dates-custom-date-and-time-range-widget';
      formatDatePicker(widget);
      formatTimePicker(widget);
    }
  }
})(jQuery, Drupal, drupalSettings);
