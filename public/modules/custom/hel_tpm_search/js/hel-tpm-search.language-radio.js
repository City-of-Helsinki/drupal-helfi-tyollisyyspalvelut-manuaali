(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_search_language_radio= {
    attach: function (context, settings) {
      $(document).ready(function () {
        $('.langcode-filter-wrapper .form-radios .form-item--radio-button').each(function () {
          let child = $(this).children('.option');
          child.on('keyup', function (e) {
            const key = e.which;
            const returnKey = 13;
            if (key === returnKey) {
              $(e.currentTarget).click();
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
