(function ($, Drupal, window) {
  'use strict';
Drupal.behaviors.userMenu = {
      attach: function (context, settings) {

      let body = $('body');
      let loginBlock = $('.menu--account');
      let toggleLogin = $('.logged-in-open');
      // let header = $('.header__primary');
      // let accountMobileMenu = $('.additional-links-menu-nav', loginBlock);
      let dropdownMobileMenu = $('.dropdown-mobile-menu',loginBlock);
      let toggleInnerLogin = $('.btn-menu-close', dropdownMobileMenu);

    if (typeof loginBlock !== 'undefined' ) {
      // Mobile Menu Show/Hide.
      toggleLogin.on('click', function() {
        body.toggleClass('no-scroll');
        dropdownMobileMenu.toggleClass('slide-in');
      });
      // Mobile Menu Show/Hide.
      toggleInnerLogin.on('click', function() {
        dropdownMobileMenu.toggleClass('slide-in');
        dropdownMobileMenu.toggleClass('slide-out-right');
        body.toggleClass('no-scroll');
        setTimeout(function() { dropdownMobileMenu.removeClass('slide-out-right'); }, 1000);
      });
    }
  },
};
})(jQuery, Drupal, this);
