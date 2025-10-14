(function ($, Drupal, window) {

  Drupal.behaviors.searchPageFilters = {
    attach(context, settings) {

      const FILTER_WRAPPER = '.service-search .exposed-filters .main-filters';
      /**
       * Provides logic for showing and hiding additional filters.
       *
       * @param filterWrapper
       * @param isCollapsed
       */
      function showHideAdditionalFilters(filterWrapper, isCollapsed) {
        let filterRows = calculateFilterRows(filterWrapper);
        // Maximum number of rows when filters are collapsed
        let minRows= 1;
        $(filterRows).each(function(i, filterRow) {
          $(filterRow).each(function(j, item) {
            if (i < minRows) {
              $(item.object).show();
              return;
            }
            if (isCollapsed === "true")
              $(item.object).hide();
            else
              $(item.object).show();
          })
        })
      }

      /**
       * Calculate filter rows from element widths.
       *
       * @param filterWrapper
       * @returns {*[]}
       */
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

      /**
       * Returns array of filters and their element widths.
       *
       * @param filterWrapper
       * @returns {*[]}
       */
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

      /**
       * Filter toggler.
       *
       * @param filterWrapper
       */
      function toggleFilters(filterWrapper) {
        $('.collapse-toggler').click(function() {
          let isCollapsed = $(filterWrapper).attr('data-is-collapsed');
          if (isCollapsed === "true")
            isCollapsed = "false";
          else
            isCollapsed = "true";

          $(filterWrapper).attr('data-is-collapsed', isCollapsed);
          localStorage.setItem('searchFiltersIsCollapsed', isCollapsed);
          showHideAdditionalFilters(filterWrapper, isCollapsed);
          setAriaExpanded(isCollapsed);
          setTogglerLabel(isCollapsed);
        });
      }

      /**
       * Updates the label text of a toggler based on its collapsed state.
       *
       * @param {string} isCollapsed
       *    A string that determines the state of the toggler.
       *    Should be "true" for collapsed or any other value for expanded.
       * @return {void}
       *    This function does not return a value.
       */
      function setTogglerLabel(isCollapsed) {
        let label = isCollapsed === "true" ? Drupal.t("Show more") : Drupal.t("Show less");
        let toggler = ".collapse-toggler";
        $(toggler).text(label);
      }

      /**
       * Set aria-expanded value for collapse-toggler.
       *
       * @param elem
       * @param isCollapsed
       */
      function setAriaExpanded(isCollapsed) {
        let toggler = ".collapse-toggler";
        let expanded = "false";
        if (isCollapsed === "false") {
          expanded = "true";
        }
        $(toggler).attr('aria-expanded', expanded);
      }

      /**
       * Initialize filter toggling.
       *
       * @param filterWrapper
       * @returns {boolean}
       */
      function initFilterToggle(filterWrapper) {
        let isCollapsed = localStorage.getItem('searchFiltersIsCollapsed');
        if (!isCollapsed) {
          isCollapsed = "true";
        }
        $(filterWrapper).attr('data-is-collapsed', isCollapsed);
        showHideAdditionalFilters(filterWrapper, isCollapsed);
        setAriaExpanded(isCollapsed)
      }

      /**
       * Set filter toggler on document ready.
       */
      $(document).ready(function() {
        toggleFilters(FILTER_WRAPPER);
      })

      /**
       * Initialize filter toggle on page load.
       */
      $(window).on('load', function() {
        // Without this bubblegum fix elements get wrong widths
        // due to flex items.
        setTimeout(function() {
          initFilterToggle(FILTER_WRAPPER);
        }, 0.2);
      });

      /**
       * Toggle filters on window resize event.
       */
      $(window).on('resize', function() {
        initFilterToggle(FILTER_WRAPPER);
      });

      /**
       * Set filters on ajax complete.
       */
      $(document).ajaxComplete(function (event, xhr, settings) {
        let elems = once('bef-filter-wrapper', FILTER_WRAPPER, context);
        if (elems.length === 0) {
          return;
        }
        setTimeout(function() {
          initFilterToggle(elems);
        }, 0.001)
      });
    }
  }
})(jQuery, Drupal, this);
