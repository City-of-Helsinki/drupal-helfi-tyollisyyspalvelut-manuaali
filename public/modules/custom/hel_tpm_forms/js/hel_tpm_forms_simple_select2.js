(function ($, Drupal, window) {
  'use strict';
  Drupal.behaviors.simpleSelect2 = {
    attach: function (context, settings) {
      $('.simple-select2-widget').each(function () {
        let config = JSON.parse($(this).attr('data-simple-select2-config'));
        $(this).select2({
          'width': '100%',
          'theme': config.theme,
        });
      });
    }
  };
})(jQuery, Drupal, this);