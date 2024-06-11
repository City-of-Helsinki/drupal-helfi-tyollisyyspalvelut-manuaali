(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.helTpmSearchTaxonomyHierarchySelect = {
    attach: function (context, settings) {
      // Handles clicking of unchecked taxonomy checkbox
      $(document).ready(function () {
        let nestingLevel = ".hierarchy-select-buttons .form-checkboxes ul.nesting-level-1";
        let allInput = "<li><div class='form-item form-item-checkbox select-all'>" +
          "<input class='hierarchy-select-buttons form-checkbox' type='checkbox' value='all'></input>" +
          "<label>" +  Drupal.t("Select all") + "</label>" +
          "</div></li>";
        $(once("nesting-all", nestingLevel)).prepend(allInput);

      })
      handleFirstLevelSelection();
      handleSecondLevelSelection();

      /**
       * First selection level.
       */
      function handleFirstLevelSelection() {
        let firstLevel = ".hierarchy-select-buttons .form-checkboxes ul.nesting-level-0 > li > .form-item";
        $('input:not(:checked)', firstLevel).off().click(function() {
          // Reset all checkboxes when first level of checkboxes change.
          let lastSelection = $(this).closest('.form-checkboxes').find('input:checked');
          lastSelection.prop("checked", false);
          lastSelection.parent().removeClass('highlight');
          $(this).prop("checked", true);

          // Add highlight class as default for select-all element in next nesting level.
          $('ul.nesting-level-1 .select-all').addClass('highlight');
        });
        // Handles clicking of checked taxonomy checkbox
        $('input:checked', firstLevel).off().click(function() {
          let lastSelection = $(this).closest('.form-checkboxes').find('input');
          lastSelection.prop("checked", false);
          lastSelection.parent().removeClass('highlight');
        });
      }

      /**
       * Second level selection.
       */
      function handleSecondLevelSelection() {
        let nestingLevel = ".hierarchy-select-buttons .form-checkboxes ul.nesting-level-1";
        let secondLevel = $("li > .form-item", nestingLevel);

        $('input:not(:checked)', secondLevel).off().click(function() {
          let lastSelection = $(this).closest('ul.nesting-level-1').find('input:checked');
          lastSelection.prop("checked", false);
          lastSelection.parent().removeClass('highlight');
          $(this).prop("checked", true);
        });
        // Handles clicking of checked taxonomy checkbox
        $('input:checked', secondLevel).off().click(function() {
          $(this).prop("checked", false);
          $(this).parent().removeClass('highlight');
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
