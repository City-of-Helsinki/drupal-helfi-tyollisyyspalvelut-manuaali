(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jqueryDatetimePicker = {
    attach(context, settings) {
      let widget = '.hel-tpm-editorial-date-recur-custom-widget';
      formatTimePickers(widget);
      inheritEndDate(widget)

      function inheritEndDate(widget) {
        let startInput = widget + ' .start-date input'

        $(startInput).change(function () {
          let format = $(this).attr('placeholder');
          let dates = $(this).parents('.dates');
          let endDateSelector = '.end-date input[placeholder="'+ format +'"]';
          let endInput = $(dates).find(endDateSelector);
          if (!endInput.val()) {
            endInput.val($(this).val());
          }
        });
      }

      function formatTimePickers(widget) {
        let parentElement = widget + ' input.form-text';
        let timeFormat = 'H:i';
        let dateFormat = 'dd.mm.yy';
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
          else {
            $(this).attr('placeholder', dateFormat);
            $(this).datepicker({
              dateFormat: 'dd.mm.yy'
            });
          }
        })
      }
    }
  }
})(jQuery, Drupal, drupalSettings);
