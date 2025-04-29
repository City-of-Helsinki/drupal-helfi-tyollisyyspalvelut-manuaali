(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jqueryDatetimePickeroni = {
    attach(context, settings) {
      let widget = '.hel-tpm-service-dates-weekday-and-time-field-elements';
      formatTimePicker(widget);
    }
  }
})(jQuery, Drupal, drupalSettings);
