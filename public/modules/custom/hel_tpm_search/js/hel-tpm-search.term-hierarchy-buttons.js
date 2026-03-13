(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.helTpmSearchTaxonomyHierarchySelect = {
    attach: function (context, settings) {
      let selectorElement = $('.term-hierarchy-buttons');
      let parentField = $('select[name="' + selectorElement.attr('parent_field') + '"');
      let form = parentField.closest('form');
      let button = $('.hierarchy-select-button', selectorElement);
      let buttonWrapper = form.find('.term-hierarchy-buttons');

      $(window).on('load', function () {
        initialize(selectorElement, parentField);
      })

      once('hierarchyButtonClick', form).forEach(function () {
        button.click(function () {
          handleButtonClick(this, parentField)
        });
      })

      /**
       * Initializes the form with the given selector element and parent field.
       * It processes the initial values, toggles highlights and button groups,
       * and performs form submission logic if required.
       *
       * @param {Object} selectorElement - The element used to select specific parts of the form.
       * @param {Object} parentField - The parent field containing initial values to process.
       * @return {void} Returns nothing.
       */
      function initialize(selectorElement, parentField) {
        let initialValues = parentField.val();

        if (initialValues.length <= 0) {
          return;
        }
        toggleHighlight(initialValues);
        toggleButtonGroups(initialValues);

        once('initializeForm', form).forEach(function() {
          form.find('.form-submit').click();
        });
      }

      /**
       * Handles the button click event within a term hierarchy structure.
       * Modifies the parent field value
       * and triggers form submission based on the button's
       * term identifier and parent term identifier.
       *
       * @param {HTMLElement} button The button element that was clicked.
       * @param {jQuery} parentField A jQuery object representing the parent field input element.
       * @return {void} This function does not return a value.
       */
      function handleButtonClick(button, parentField) {
        let tid = $(button).attr('data-term-id');
        let parentTid = $(button).attr('data-parent-tid');
        let values = [tid];
        let parentFieldValue = parentField.val();

        if (parentTid) {
          values.push(parentTid);
        }

        if ($.inArray(tid, parentFieldValue) === 0) {
          if (parentTid === undefined) {
            values = [];
          }
          else {
            values = [parentTid];
          }
        }

        toggleHighlight(values);
        toggleButtonGroups(values);
        parentField.val(values);

        $(button).closest('form').find('.form-submit').click();
      }

      /**
       * Toggles the highlight class for elements within
       * the specified parentWrapper based on the provided values.
       *
       * @param {Array} values - An array of values representing the data-term-ids to be highlighted.
       * @return {void} This function does not return a value.
       */
      function toggleHighlight(values) {
        $('.hierarchy-select-button', buttonWrapper).removeClass('highlight');

        if (values.length === 0) {
          return;
        }
        values.forEach(function (value, i) {
          $('[data-term-id="' + value + '"]', buttonWrapper).addClass('highlight');
        })
      }

      function toggleButtonGroups(values) {
        $('.term-hierarchy-child-group').each(function () {
          $(this).addClass('hidden');
        })
        console.log(values);
        values.forEach(function (value, i) {
          console.log(value);
          $('[data-group-parent-tid="' + value + '"]', buttonWrapper).removeClass('hidden');
        })
      }
    },
  }})(jQuery, Drupal, drupalSettings);