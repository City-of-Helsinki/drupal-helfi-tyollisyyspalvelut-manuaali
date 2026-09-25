(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.reset_additional_filters = {
    attach: function (context, settings) {

      toggleResetButton();

      resetAdditionalFilters();

      /**
       * Toggles the visibility of the reset button based on active multiselect filters.
       *
       * @return {void} This function does not return a value.
       */
      function toggleResetButton() {
        const resetButtonSelector = '.control-wrapper button[name="reset_additional_filters"]';
        const activeMultiselectSelector = '.filters-wrapper .multi-select-container.active';

        $(document).ready(function() {
          // Toggle showing reset button.
          const hasSelectedMultiselect = $(activeMultiselectSelector).length > 0;
          $(resetButtonSelector).toggle(hasSelectedMultiselect);
        });
      }

      /**
       * Resets additional filters within the specified context by clearing the
       * values of select elements, radio buttons, and checkboxes inside the
       * "main-filters" area. Triggers a form submission once the reset is completed.
       *
       * @return {void} This method does not return a value.
       */
      function resetAdditionalFilters() {
        let form = $(context);
        let controlWrapper = $('.control-wrapper', form);
        let mainFilters = $('.main-filters', form);
        let btn = $('button[name="reset_additional_filters"]', controlWrapper);
        once('additional-reset-button-click', btn.click(function (event) {
          event.preventDefault();
          mainFilters.find('select').val('');
          mainFilters.find('input[type=radio]').prop('checked', false);
          mainFilters.find('input[type=checkbox]').prop('checked', false);
          form.find('[id^="edit-submit-"]').click();
        }));
      }
    }
  }
})(jQuery, Drupal, drupalSettings);
