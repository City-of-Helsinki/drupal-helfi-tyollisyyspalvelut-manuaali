(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.searchCollapse = {
    attach: function (context, settings) {
        let filterButton = $(".additional-filters");
        if (filterButton.length == 0) {
          return
        }
        filterButton[0].addEventListener('click', (e) => {
          $(".header__primary").toggleClass("filters-open");
        });

      }
  };
})(jQuery, Drupal, this);
