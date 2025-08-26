(function ($, Drupal, window) {

  Drupal.behaviors.searchPageFilters = {
    attach(context, settings) {

      const FILTER_WRAPPER = '.municipality-taxonomy-view-filters .bef-nested .highlight + .filter-buttons';
      // /**
      // //  * Calculate filter rows from element widths.
      // //  *
      // //  * @param filterWrapper
      // //  * @returns {*[]}
      // //  */
      function calculateFilterRows(filterWrapper) {
        let filters = getFilterWidths(filterWrapper);
        let wrapperWidth = $(filterWrapper).innerWidth();
        // We want 2 rows so we want 2x wrapper width.
        let containerWidth = wrapperWidth;
        let rows = [];
        let currentRow = 0;
        rows[currentRow] = [];

        $(filters).each(function(index, item) {
          containerWidth = containerWidth - item.width;
          item.row_width = containerWidth;
          if (containerWidth >= 0) {
            // Add item to current row.
            rows[currentRow].push(item);
          }
          else {
            // Reset container width and substract item width.
            containerWidth = wrapperWidth - item.width;
            item.row_width = containerWidth;

            // Add new row.
            currentRow++;
            rows[currentRow] = [];
            // Add current item to new row.
            rows[currentRow].push(item);
          }
        });
        return rows;
      }

      // /**
      //  * Returns array of filters and their element widths.
      //  *
      //  * @param filterWrapper
      //  * @returns {*[]}
      //  */
      function getFilterWidths(filterWrapper) {
        let filterWidths = [];

        $('.form-item', filterWrapper).each(function(index, width) {
          let filter = {
            "object": this,
            "width": $(this).outerWidth()
          }
          filterWidths.push(filter);
        });
        return filterWidths;
      }

      // /**
      //  * Initialize filter toggling.
      //  *
      //  * @param filterWrapper
      //  * @returns {boolean}
      //  */
      function initRows(filterWrapper) {
        let array = calculateFilterRows(filterWrapper);
        let margin = array.length * 34;
        let marginpx = margin+'px';
        let browserWidth = getWidth();
        if  (browserWidth < 1280) {
          $('.form-checkboxes').css("margin-bottom", '0px');
        } else {
          $('.form-checkboxes').css("margin-bottom", marginpx);
        }
      }
      //
      // /**
      //  * Set filter toggler on document ready.
      //  */
      $(document).ready(function() {
        initRows(FILTER_WRAPPER);
      })

      //
      // /**
      //  * Initialize filter toggle on page load.
      //  */
      $(window).on('load', function() {
        // Without this bubblegum fix elements get wrong widths
        // due to flex items.
        setTimeout(function() {
          initRows(FILTER_WRAPPER);
        }, 0.2);
      });
      //
      // /**
      //  * Toggle filters on window resize event.
      //  */
      $(window).on('resize', function() {
        initRows(FILTER_WRAPPER);
      });

      function getWidth() {
        return Math.max(
          document.body.scrollWidth,
          document.documentElement.scrollWidth,
          document.body.offsetWidth,
          document.documentElement.offsetWidth,
          document.documentElement.clientWidth
        );
      }
    }
  }
})(jQuery, Drupal, this);
