(function ($, Drupal, window) {
  'use strict';
Drupal.behaviors.userMenu = {
      attach: function (context, settings) {

      let body = $('body');
      let loggedUserBlock = $('.menu--account');
      let toggleLogin = $('.logged-in-open');
      let userMenublock = $('.dropdown-mobile-menu',loggedUserBlock);
      let toggleInnerLogin = $('.btn-menu-close', userMenublock);

    if (typeof loggedUserBlock !== 'undefined' ) {
      // Mobile Menu Show/Hide.
      toggleLogin.on('click', function() {
        body.toggleClass('no-scroll');
        userMenublock.toggleClass('slide-in');
      });
      // Mobile Menu Show/Hide.
      toggleInnerLogin.on('click', function() {
        userMenublock.toggleClass('slide-in');
        userMenublock.toggleClass('slide-out-right');
        body.toggleClass('no-scroll');
        setTimeout(function() { userMenublock.removeClass('slide-out-right'); }, 1000);
      });
    }
  },
};
})(jQuery, Drupal, this);
