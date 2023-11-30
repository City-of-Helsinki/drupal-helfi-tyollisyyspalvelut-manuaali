(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_search_dropdown_multiselect = {
    attach: function (context, settings) {
      $(document).ready(function() {
        $('select.dropdownMultiselect[multiple="multiple"]').each(function () {
          let parent = $(this).parents('.js-form-type-select');
          let label = $('label.form-item__label', parent);
          $(this).multiSelect({
              noneText: $(label).text()
          });
          $(label).hide();
        });
        // HOT FIX: reset default functionality
        // $('.form-item-field-free-service select').each(function () {
        //   $(this).removeAttr('multiple');
        // });
        // $('.form-item-field-free-service input:checked').on( 'click', function(event) {
        //   event.stopPropagation();
        //   event.preventDefault();
        //   $(this).closest('form-item__dropdown').find('select').val('');
        //   $(this).closest('form').find('.cost-reset input[id^="edit-reset--"]').click();
        // });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
