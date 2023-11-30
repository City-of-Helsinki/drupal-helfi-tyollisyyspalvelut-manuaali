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
        $('.form-item-field-free-service input:not(:checked)').on( 'click', function(event) {

          $(this).parent().siblings('.multi-select-menuitem').children('input:checked').prop("checked", false);

          $(this).closest('form').find('.text-search-wrapper .form-submit').click();
        });
        $('.form-item-field-free-service input:checked').on( 'click', function(event) {
          event.stopPropagation();
          event.preventDefault();
          $(this).closest('form-item__dropdown').find('select').val('');
          $(this).closest('form').find('.cost-reset input[id^="edit-reset--"]').click();
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
