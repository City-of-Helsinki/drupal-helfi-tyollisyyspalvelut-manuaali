(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.loginMove = {
    attach: function (context, settings) {
        let loginBlock = $(".header__primary >.block-user-login-block");
        if (loginBlock.length === 0) {
          return
        }
        let subMenu = $(".main-menu__item--active.main-menu__item--with-sub >.main-menu--sub-1");
        if (subMenu.length === 0) {
          return
        }
        $(loginBlock).appendTo(subMenu);
      }
  };
})(jQuery, Drupal, this);
