(function ($, Drupal, window) {
  'use strict';
  Drupal.behaviors.taxonomyFilters = {
      attach: function (context, settings) {
      // Handles clicking of unchecked taxonomy checkbox
      $('.view-taxonomy-term .form-checkboxes input:not(:checked)').off().click(function() {
        let lastSelection = $(this).closest('.form-checkboxes').find('input:checked');
        lastSelection.prop("checked", false);
        lastSelection.parent().removeClass('highlight');
        $(this).prop("checked", true);
      });
      // Handles clicking of checked taxonomy checkbox
      $('.view-taxonomy-term .form-checkboxes input:checked').off().click(function() {
        $(this).prop("checked", false);
      });
    }
  };
})(jQuery, Drupal, this);
