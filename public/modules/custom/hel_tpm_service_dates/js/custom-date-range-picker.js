(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.customDatetimePicker = {
    attach(context, settings) {
      let widget = '.field--widget-hel-tpm-service-dates-date-range';
      console.log(widget);
      formatDatePicker(widget);
      formatTimePicker(widget);
    }
  }
})(jQuery, Drupal, drupalSettings);
