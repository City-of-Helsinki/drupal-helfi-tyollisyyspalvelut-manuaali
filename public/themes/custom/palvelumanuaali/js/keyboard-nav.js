(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.handleKeyboard = {
    attach: function (context, settings) {
      selectRemove();
      makeChoiceRemoversFocusable();
      addLabeledBy();

      $(document).on('select2:select', function () {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      });

      /**
       * Pager navigation.
       */
      function selectRemove() {
        $(once('.select2-selection__choice', '.select2-selection__choice__remove', context)).each(function() {
          $(this).on("keydown",function(e) {
            if (e.key === "Enter") {
              e.preventDefault();
              $(this).trigger('click');
            }
          });

        });
      }

      function makeChoiceRemoversFocusable() {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      }


      function addLabeledBy() {
        $(once('.selection', '.select2-selection', context)).each(function() {
          $(this).attr('aria-labeledby', $(this).parent().parent().parent().siblings('label')[0]['id']);
        });
      }

    }
  };
})(jQuery, Drupal, this);
