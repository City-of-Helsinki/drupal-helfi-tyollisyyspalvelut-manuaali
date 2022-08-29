(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jqueryDatetimePicker = {
    attach(context, settings) {
      var parentElement = '.field--widget-hel-tpm-editorial-date-recur-custom .date input';
      $(parentElement).each(function() {
        console.log(this);
        var placeholder = this.getAttribute('placeholder');
        if (placeholder === 'hh:mm:ss') {
          $(this).timepicker({
            timeFormat: "H:i",
            step: 5
          });
        }
        else {
          $(this).datepicker({
            dateFormat: "dd.mm.yy"
          });
        }
      })

    }
  }
})(jQuery, Drupal, drupalSettings);
