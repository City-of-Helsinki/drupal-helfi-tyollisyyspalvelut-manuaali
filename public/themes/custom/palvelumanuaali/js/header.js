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
  Drupal.behaviors.pagerItem = {
    attach: function (context, settings) {
        let pageItem = $(".pager__item a");
        const scrollToBtn = document.getElemenstByClassName('pager__item');
        scrollToBtn.addEventListener('click', () => {
          scrollToBtn.scrollIntoView({
            behavior: 'smooth',
          });
        });
      }
  };
})(jQuery, Drupal, this);
