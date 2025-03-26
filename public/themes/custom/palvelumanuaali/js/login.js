(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.searchCollapse = {
    attach: function (context, settings) {
        let loginBlock = $(".header__primary >.block-user-login-block");
    //    let userBlock = $(".header__primary >.menu--account");
        if (loginBlock.length === 0) {
          return
        }

        $(loginBlock).appendTo(".main-menu--sub-1");
//$(userBlock).appendTo(".main-menu--sub-1");
      }
  };
})(jQuery, Drupal, this);
