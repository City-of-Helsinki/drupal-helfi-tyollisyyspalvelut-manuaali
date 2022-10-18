(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_search_dropdown_multiselect = {
    attach: function (context, settings) {
      $(document).ready(function() {
        $('select.dropdownMultiselect').each(function () {
          $(this).multiSelect({
              noneText: $(this).labels().text()
          })
          console.log($(this).labels());
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);