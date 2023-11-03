(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_search_dropdown_multiselect = {
    attach: function (context, settings) {
      $(document).ready(function() {
        $('select.dropdownMultiselect').each(function () {
          let parent = $(this).parents('.js-form-type-select');
          let label = $('label.form-item__label', parent);
          $(this).multiSelect({
              noneText: $(label).text()
          });
          $(label).hide();
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
