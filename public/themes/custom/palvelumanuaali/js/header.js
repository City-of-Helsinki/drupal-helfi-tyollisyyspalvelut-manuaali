(function ($, Drupal, window) {
  'use strict';
  // Example of Drupal behavior loaded.
  Drupal.behaviors.exampleJS = {
    attach: function (context, settings) {
      if (typeof context['location'] !== 'undefined') { // Only fire on document load.
        const nav = $("#page-header");
        let lastScrollY = 0;
        $(window).scroll(function () {
          let currentScrollY = $(this).scrollTop();
          if (lastScrollY < currentScrollY && currentScrollY > 50) {
            nav.addClass("header--hidden");
          } else {
            nav.removeClass("header--hidden");
          }
            lastScrollY = currentScrollY;
        });
      }
    }
  };

})(jQuery, Drupal, this);
