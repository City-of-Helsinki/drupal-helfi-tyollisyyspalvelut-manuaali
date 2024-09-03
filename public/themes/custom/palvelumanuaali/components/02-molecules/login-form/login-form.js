(function ($, Drupal, window) {
  'use strict';
Drupal.behaviors.loginForm = {
      attach: function (context, settings) {

      let loginBlock = $('.header__secondary .block-user-login-block');
      let header = $('.header__primary');
      let additionalMenu = $('.header__primary .additional-links-menu-nav');
      let body = $('body');
      let toggleLogin = $('.header__secondary .block-user >.btn-menu');
      let toggleInnerLogin = $('.header__secondary .slide-in-loginform .btn-menu-close');
      let sidebarLogin = $('.header__secondary .slide-in-loginform');

    if (typeof loginBlock !== 'undefined' || menu == null) {

      // Mobile Menu Show/Hide.
      toggleLogin[0].addEventListener('click', (e) => {
        body.toggleClass('no-scroll');
        sidebarLogin.toggleClass('slide-in');
        e.preventDefault();
      });

      // Mobile Menu Show/Hide.
      toggleInnerLogin[0].addEventListener('click', (e) => {
        sidebarLogin.toggleClass('slide-in');
        sidebarLogin.toggleClass('slide-out-right');
        body.toggleClass('no-scroll');
        setTimeout(function() { sidebarLogin.removeClass('slide-out-right'); }, 1000);
        e.preventDefault();
      });
    }
  },
};
})(jQuery, Drupal, this);
