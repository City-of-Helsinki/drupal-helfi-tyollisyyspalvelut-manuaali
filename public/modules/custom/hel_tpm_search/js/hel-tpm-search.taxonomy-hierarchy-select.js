(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.helTpmSearchTaxonomyHierarchySelect = {
    attach: function (context, settings) {
      // Handles clicking of unchecked taxonomy checkbox
      $(document).ready(function () {
        let nestingLevel = ".hierarchy-select-buttons .form-checkboxes ul.nesting-level-1";
        let allInput = "<li><div class='form-item form-item-checkbox select-all'>" +
          "<label>" +  Drupal.t("Show all") + "</label>" +
          "</div></li>";
        $(once("nesting-all", nestingLevel)).prepend(allInput);

      })
      handleFirstLevelSelection();
      handleSecondLevelSelection();
      handleSelectAllSelection();

      /**
       * First selection level.
       */
      function handleFirstLevelSelection() {
        let nestingLevel = ".hierarchy-select-buttons .form-checkboxes ul.nesting-level-0";
        let firstLevel = $("> li > .form-item", nestingLevel);
        $('input:not(:checked)', firstLevel).off().click(function() {
          let selectAll = $(this).closest('li').find('.select-all');
          // Reset all checkboxes when first level of checkboxes change.
          let lastSelection = $(this).closest('.form-checkboxes').find('input:checked');
          lastSelection.prop("checked", false);
          lastSelection.parent().removeClass('highlight');
          $(this).prop("checked", true);
          $(this).parent().addClass('highlight');

          // Reset all select all highlight classes.
          $('.select-all').removeClass('highlight');
          // Select current child select all.
          selectAll.addClass('highlight');
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
          $('.select-all', nestingLevel).removeClass('highlight');
        });
        // Handles clicking of checked taxonomy checkbox
        $('input:checked', secondLevel).off().click(function() {
          $(this).prop("checked", false);
          $(this).parent().removeClass('highlight');
        });
      }

      function handleSelectAllSelection() {
        // Reset previous selection when clicking select all.
        $('.hierarchy-select-buttons .select-all').click(function() {
          let currentSelection = $(this).closest('.nesting-level-1').find('input:checked');
          if(!$(this).hasClass('highlight') && currentSelection.length > 0) {
            $(currentSelection).click();
          }
          $(this).addClass('highlight');
        })
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
