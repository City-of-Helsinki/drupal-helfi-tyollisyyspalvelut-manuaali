(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.userForm = {
    attach(context) {
      $(document).ready(function() {
          $('#edit-preferred-langcode').select2();
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
