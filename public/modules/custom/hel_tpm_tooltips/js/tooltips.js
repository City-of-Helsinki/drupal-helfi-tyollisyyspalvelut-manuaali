(function ($, Drupal) {
  'use strict';

  /**
   * The tooltip info box plugin.
   */
  Drupal.behaviors.fieldDescriptionTooltip = {
    attach: function (context, settings) {
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

      });

      function moveDescriptionAfterLabel(item) {
        let context = $(item).closest('div[class*=field--name]').first();
        let label = $("label[class!=option]", context).first();
        if (label.length <= 0) {
          return;
        }

        $(item).insertAfter(label);
      }

      function addTooltip(item) {

        // Get the description text to be prepared as tooltip.
        let tooltipText = $(item).html().trim();
        let lineBreak = '<br />';

        // Remove the description text and move it to the "title" attribute.
        $(item).attr('title', tooltipText);
        $(item).html('<img width="20" src="/' + settings.fieldDescriptionTooltip.img + '" />');

        // Set the tooltip position.
        let position_my = settings.fieldDescriptionTooltip.position.my_1 + ' ' + settings.fieldDescriptionTooltip.position.my_2;
        let position_at = settings.fieldDescriptionTooltip.position.at_1 + ' ' + settings.fieldDescriptionTooltip.position.at_2;

        // Add the tooltip js trigger.
        $(item).tooltip(
          {
            position: {
              my: position_my,
              at: position_at
            },
            effect: "slideDown",
            show: { effect: "slideDown" },
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
