(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jqueryDatetimePicker = {
    attach(context, settings) {
      let widget = '.hel-tpm-service-dates-weekday-and-time-field-elements';
      formatTimePickers(widget);

      function formatTimePickers(widget) {
        let parentElement = widget + ' input.form-text';
        let timeFormat = 'H:i';
        $(parentElement).each(function() {
          let placeholder = this.getAttribute('placeholder');
          if (placeholder === null) {
            return;
          }
          if (placeholder === timeFormat || placeholder === 'hh:mm') {
            $(this).attr('placeholder',  'hh:mm');
            $(this).timepicker({
              timeFormat: timeFormat,
              minTime: '05:00',
              maxTime: '22:00',
              step: 15
            });
          }
        })
      }
    }
  }
})(jQuery, Drupal, drupalSettings);
