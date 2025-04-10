(function ($, Drupal, drupalSettings) {

  formatDatePicker = function(widget) {
    let parentElement = widget + 'input.form-text';
    let dateFormat = 'dd.mm.yy';
    $(parentElement).each(function() {
      let placeholder = this.getAttribute('placeholder');
      if (placeholder === null) {
        return;
      }
      if (placeholder === dateFormat) {
        $(this).attr('placeholder', dateFormat);
        $(this).datepicker({
          dateFormat: 'dd.mm.yy',
          firstDay: 1
        });
      }
    })
  }

  formatTimePicker = function(widget) {
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
            dateFormat: 'dd.mm.yy',
            firstDay: 1
          });
        }
      })
    }
})(jQuery, Drupal, drupalSettings);
