(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.handleKeyboard = {
    attach: function (context, settings) {
      makeChoiceRemoversFocusable();
      addLabeledBy();
      updateWidths();

      $(document).on('select2:select', function () {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      }).on('select2:unselect', function () {
        $('.select2-selection__choice__remove').attr('tabindex', '0');
        $('.select2-selection__choice__remove').attr('aria-label', 'Poista valinta');
      }).on('select:highlight' , function(){
        $('.select2-results__option--selected').attr('aria-selected', 'true');
    }).on('select:focus' , function(){
        $('.select2-results__option--selected').attr('aria-selected', 'true');
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


      function addLabeledBy() {
        $(once('.selection', '.select2-selection', context)).each(function() {
          $(this).attr('aria-labeledby', $(this).parent().parent().parent().siblings('label')[0]['id']);
        });
      }
    }
  };
})(jQuery, Drupal, this);
