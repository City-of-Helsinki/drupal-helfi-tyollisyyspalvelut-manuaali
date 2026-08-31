(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.handleKeyboard = {
    attach: function (context, settings) {
      selectRemove();
      makeChoiceRemoversFocusable();

      $(document).on('select2:select', function () {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      });

      /**
       * Pager navigation.
       */
      function selectRemove() {
        $('.select2-selection__choice', '.select2-selection__choice__remove', context).each(function() {
          $(this).on("keydown",function(e) {
            if (e.key === "Enter") {
              e.preventDefault();
              e.stopPropagation();
              $(this).trigger('click');
            }
          });

        });
      }

      function makeChoiceRemoversFocusable() {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      }
      $(document).ajaxComplete(function () {
        selectRemove();
      });

    }
  };

})(jQuery, Drupal, this);
