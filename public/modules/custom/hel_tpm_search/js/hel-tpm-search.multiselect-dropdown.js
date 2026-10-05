(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_search_dropdown_multiselect = {
    attach: function (context, settings) {
      $(document).ready(function() {
        $('select.dropdownMultiselect[multiple="multiple"]').each(function () {
          // Firefox restores previous form state on reload, overriding the
          // server rendered selection. Reset options to the rendered state
          // before the widget reads them.
          if (!$.data(this, 'plugin_multiSelect')) {
            $('option', this).each(function () {
              this.selected = this.defaultSelected;
            });
          }
          let parent = $(this).parents('.js-form-type-select');
          let label = $('div.form-item__description', parent);

          $(this).multiSelect({
              noneText: $(label).text()
          });
          $(label).hide();
        });
        // Handle clicking not selected input to mimic radiobuttons
        $('.form-item-field-free-service input:not(:checked)').on( 'click', function(event) {
          event.preventDefault();
          let value = $(this).val();
          if (value == 1 ) {
            $(this).closest('.form-item__dropdown').find('select:selected').val('');
            $(this).closest('.form-item__dropdown').find('select').val('1');
          } else {
            $(this).closest('.form-item__dropdown').find('select:selected').val('');
            $(this).closest('.form-item__dropdown').find('select').val('2');
          }
           $(this).closest('form').find('[id^="edit-submit-"]').click();
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
