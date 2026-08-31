(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.handleKeyboard = {
    attach: function (context, settings) {
      makeChoiceRemoversFocusable();
      $(document).on('select2:select', function () {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      });

      $(document).on(
        'keydown',
        '.select2-selection__choice__remove',
        function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            this.click();
          }
        }
      );


      function makeChoiceRemoversFocusable() {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      }
    }
  };

})(jQuery, Drupal, this);
