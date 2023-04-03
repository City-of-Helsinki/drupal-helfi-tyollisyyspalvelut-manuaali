(function ($, Drupal, window) {
  'use strict';
  // Example of Drupal behavior loaded.
  Drupal.behaviors.headerJs = {
    attach: function (context, settings) {
      if (typeof context['location'] !== 'undefined') { // Only fire on document load.
        let nav = $("#page-header");
        let lastScrollY = 0;
        let header =  document.getElementById("page-header");
        if (header == null) {
          return;
        }
        let height = header.offsetHeight;
        $(window).scroll(function () {
          let currentScrollY = $(this).scrollTop();
          if ($(window).width() > 920 ) {
            if (lastScrollY < currentScrollY && currentScrollY > 50) {
              nav.addClass("header--hidden");
              header.style.transform = "translateY(-" + height + "px)"
            } else {
              nav.removeClass("header--hidden");
              header.style.transform = "translateY(0px)"
            }
          }
            lastScrollY = currentScrollY;
        });
      }
    }
  };

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

  Drupal.behaviors.headerHeight = {
    attach: function (context, settings) {
      let header = document.querySelector('#page-header');
      if (header == null) {
        return;
      }
      let height = header.offsetHeight;
      if ($(window).width() > 920 ) {
        let content = document.getElementsByClassName("main--with-sidebar");
        content[0].style.marginTop = height +'px';
      }
    }
  };


})(jQuery, Drupal, this);
