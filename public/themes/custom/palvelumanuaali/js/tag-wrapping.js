(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.tagWrapping = {
    attach: function (context, settings) {
      updateTagWidths();
      function updateTagWidths() {
        document.querySelectorAll('.width-checker').forEach(function (element) {
          const parent = element.parentElement;
          if (!parent) {
            return;
          }
          // Reset widths first so we can measure natural width.
          parent.style.width = '';
          element.style.width = '';
          // Measure child width.
          const width = element.getBoundingClientRect().width;
          const widthPadded = width + 32;
          // Apply width to parent.
          parent.style.width = widthPadded + 'px';
        });
      }
      window.addEventListener('load', updateWidths);
      window.addEventListener('resize', updateWidths);
    }
  };
})(jQuery, Drupal, this);
