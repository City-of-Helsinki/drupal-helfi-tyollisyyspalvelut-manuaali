(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jqueryDatetimePicker = {
    attach(context, settings) {
      var parentElement = '.field--widget-hel-tpm-editorial-date-recur-custom input.form-text';
      var timeFormat = 'H.i';
      var dateFormat = 'dd.mm.yy';
      $(parentElement).each(function() {
        var placeholder = this.getAttribute('placeholder');
        if (placeholder === null) {
          return;
        }
        if (placeholder === 'hh:mm:ss' || placeholder === 'hh:mm') {
          $(this).attr('placeholder',  'hh.mm');
          $(this).timepicker({
            timeFormat: timeFormat,
            minTime: '05.00',
            maxTime: '22.00',
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
})(jQuery, Drupal, drupalSettings);
