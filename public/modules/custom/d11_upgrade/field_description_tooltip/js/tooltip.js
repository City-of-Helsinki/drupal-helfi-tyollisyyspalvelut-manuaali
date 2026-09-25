(function ($, Drupal) {
  'use strict';

  /**
   * The tooltip info box plugin.
   */
  Drupal.behaviors.fieldDescriptionTooltip = {
    attach: function (context, settings) {
      let tooltipFields = $('[data-description-tooltip="1"]', context);

      // Check if there are any fields that are configured as tooltip.
      if (tooltipFields.length) {
        $(once('fieldDescriptionTooltip', tooltipFields, context)).each(function () {
          let description = $(this).find('[data-drupal-field-elements="description"], [class*="description"]');

          // Check if there is a description available in order to start the
          // js manipulations.
          if (description.length) {
            description.each(function() {
              // Get the description text to be prepared as tooltip.
              let tooltipText = $(this).html().trim();
              let lineBreak = '<br />';

              // Remove the description text and move it to the "title" attribute.
              $(this).attr('title', tooltipText);
              $(this).html('<img width="20" src="/' + settings.fieldDescriptionTooltip.img + '" />');

              // Set the tooltip position.
              let position_my = settings.fieldDescriptionTooltip.position.my_1 + ' ' + settings.fieldDescriptionTooltip.position.my_2;
              let position_at = settings.fieldDescriptionTooltip.position.at_1 + ' ' + settings.fieldDescriptionTooltip.position.at_2;

              // Add the tooltip js trigger.
              $(this).tooltip({
                position: {
                  my: position_my,
                  at: position_at
                },
                effect: "slideDown",
                show: { effect: "slideDown" },
                // For any custom styling.
                tooltipClass: "description-tooltip",
                content: function() {
                  let tooltipText = $(this).prop('title');
                  // Convert default line breaks into html breaks.
                  return tooltipText
                    .replaceAll("\r\n", lineBreak)
                    .replaceAll("\r", lineBreak)
                    .replaceAll("\n", lineBreak)
                }
              });
            });
          }
        });
      }
    }
  };
})(jQuery, Drupal);
