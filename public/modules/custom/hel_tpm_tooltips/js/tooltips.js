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
          $(this).attr('data-bs-toggle', 'tooltip');
          $(this).attr('data-bs-html', 'true');
          $(this).attr('data-bs-placement', 'right');
          $(this).attr('data-bs-custom-class', 'styled-tooltip');
          $(this).attr('data-bs-delay', '200');
            addTooltip(this);
            moveDescriptionAfterLabel(this);

        });
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        })
      });

      clearOnceFromIEF();
      clearOnceFromServicePlace();

    function clearOnceFromIEF () {
      let tooltipFieldsIEF = $('[data-description-tooltip="1"].field--widget-municipality-specific-ief-widget', context);
      $(tooltipFieldsIEF, context).each(function () {
        $(this).attr('data-once',"");

      });
    }

    function clearOnceFromServicePlace () {
      let tooltipFieldsIEF = $('[data-description-tooltip="1"].field--widget-hel-tpm-service-dates-service-time-and-place-widget', context);
      $(tooltipFieldsIEF, context).each(function () {
        $(this).attr('data-once',"");

      });
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

      function addTooltip(item) {

        // Get the description text to be prepared as tooltip.
        if ($(item).hasClass('tooltipInitiated')) {
            var tooltipText = $(item).prop('title');
        } else {
            var tooltipText = $(item).html().trim();
        }
        let lineBreak = '<br />';
        $(item).addClass('tooltipInitiated');

        // Remove the description text and move it to the "title" attribute.
        $(item).prop('title', tooltipText);
        $(item).html('<img width="20" src="/' + settings.fieldDescriptionTooltip.img + '" />');

        // Set the tooltip position.
        let position_my = settings.fieldDescriptionTooltip.position.my_1 + ' ' + settings.fieldDescriptionTooltip.position.my_2;
        let position_at = settings.fieldDescriptionTooltip.position.at_1 + ' ' + settings.fieldDescriptionTooltip.position.at_2;

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

      function addTooltipNoImage(item) {

        // Get the description text to be prepared as tooltip.
        var tooltipText = $(item).html().trim();
        let lineBreak = '<br />';


        // Remove the description text and move it to the "title" attribute.
        $(item).prop('title', tooltipText);
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
})(jQuery, Drupal, bootstrap);
