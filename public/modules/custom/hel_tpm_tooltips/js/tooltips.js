(function ($, Drupal) {
  'use strict';

  /**
   * The tooltip info box plugin.
   */
  Drupal.behaviors.fieldDescriptionTooltip = {
    attach: function (context, settings) {
      $(document).off('[data-bs-original-title]');
      let tooltipFields = $('[data-description-tooltip="1"]', context);
      // Check if there are any fields that are configured as tooltip.
      if (tooltipFields.length <= 0) {
        return;
      }

      $(once('fieldDescriptionTooltip', tooltipFields, context)).each(function () {
        let description = $(this).find('[data-drupal-field-elements="description"], [class="form-item__description"]');

        // Check if there is a description available in order to start the
        // js manipulations.
        if (description.length <= 0) {
          return
        }

        description.each(function() {
          addTooltip(this);
          moveDescriptionAfterLabel(this);

        });
        fixBootstrapTriggerlist();
      });

      $(document).ajaxComplete(function () {
        let description = $(this).find('[data-drupal-field-elements="description"], [class="form-item__description"]');
        description.each(function() {
          addTooltip(this);
          moveDescriptionAfterLabel(this);
        });
        fixBootstrapTriggerlist();
      });

      /**
       * Fix for bootstrap.
       */
      function fixBootstrapTriggerlist() {
        let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        })
      }

      function moveDescriptionAfterLabel(item) {
        let context = $(item).closest('div[class*=field--name]').first();
        let label = $("label:not(.option)", context).first();
        let legendSpan = $("legend span", context).first();
        if ((label.length <= 0) && (legendSpan.legend <= 0) ) {
          return
        }

        if (!(label.length <= 0) ) {
          $(item).appendTo(label);
        } else {
          $(item).appendTo(legendSpan);
        }
      }

      /**
       * Function to add tooltips.
       * @param item
       */
      function addTooltip(item) {
        $(item).attr('data-bs-toggle', 'tooltip');
        $(item).attr('data-bs-html', 'true');
        $(item).attr('data-bs-placement', 'right');
        $(item).attr('data-bs-custom-class', 'styled-tooltip');
        $(item).attr('data-bs-delay', '200');

        let tooltipText = "";
        // Get the description text to be prepared as tooltip.
        if ($(item).hasClass('tooltipInitiated')) {
          tooltipText = $(item).prop('title');
        } else {
          tooltipText = $(item).html().trim();
        }
        let lineBreak = '<br />';
        $(item).addClass('tooltipInitiated');
        // Remove the description text and move it to the "title" attribute.
        $(item).prop('title', tooltipText);
        $(item).html('<img width="20" src="/' + settings.fieldDescriptionTooltip.img + '" />');
        // Add the tooltip js trigger.
        $(item).tooltip(
          {
            // For any custom styling.
            tooltipClass: "description-tooltip",
            content: function() {
              let tooltipText = $(item).prop('title');
              // Convert default line breaks into html breaks.
              return tooltipText
                .replaceAll("\r\n", lineBreak)
                .replaceAll("\r", lineBreak)
                .replaceAll("\n", lineBreak)
            }
          });
      }
    }
  };
})(jQuery, Drupal);
