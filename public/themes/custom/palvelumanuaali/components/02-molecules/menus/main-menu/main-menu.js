(function ($, Drupal, window) {
  'use strict';
Drupal.behaviors.mainMenu = {
      attach: function (context, settings) {

      let toggleExpand = $('.header__inner >.toggle-expand');
      let toggleInnerExpand = $('.header-main-nav .toggle-expand');
      let menu = $('.header-main-nav .main-nav');
      let header = $('.header__primary');
      let additionalMenu = $('.header__primary .additional-links-menu-nav');
      let body = $('body');

    if (typeof menu !== 'undefined' || menu == null) {
      const expandMenu = menu[0].getElementsByClassName('expand-sub');

      // Mobile Menu Show/Hide.
      toggleExpand[0].addEventListener('click', (e) => {
        if (toggleExpand[0]) {
        toggleInnerExpand[0].classList.toggle('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.toggle('additional-links-menu-nav--open');
        }
        body[0].classList.toggle('no-scroll');
        menu[0].classList.toggle('main-nav--open');
        header[0].classList.toggle('header__primary-mobile');
        header.removeClass('slide-out');
        header[0].classList.toggle('slide-in');
        e.preventDefault();

      });

      // Mobile Menu Show/Hide.
      toggleInnerExpand[0].addEventListener('click', (e) => {
        if (toggleInnerExpand[0]) {
          toggleInnerExpand[0].classList.toggle('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.toggle('additional-links-menu-nav--open');
        }
        menu[0].classList.toggle('main-nav--open');
        header[0].classList.toggle('slide-in');
        header[0].classList.toggle('slide-out');
        body[0].classList.toggle('no-scroll');
        setTimeout(function() { header[0].classList.toggle('header__primary-mobile'); }, 1000);
        e.preventDefault();
      });



      // Expose mobile sub menu on click.
      Array.from(expandMenu[0]).forEach((item) => {
        item.addEventListener('click', (e) => {
          const menuItem = e.currentTarget;
          const subMenu = menuItem.nextElementSibling;
          if (menuItem[0]) {
              menuItem[0].classList.toggle('expand-sub--open');
          }
          if (subMenu[0]) {
            subMenu[0].classList.toggle('main-menu--sub-open');
          }
        });
      });
    }

    $(context).ajaxStop(function () {
      
      let toggleExpand = $('.header__inner >.toggle-expand');
      let toggleInnerExpand = $('.header-main-nav .toggle-expand');
      let menu = $('.header-main-nav .main-nav');
      let header = $('.header__primary');
      let additionalMenu = $('.header__primary .additional-links-menu-nav');
      let body = $('body');

    if (typeof menu !== 'undefined' || menu == null) {
      const expandMenu = menu[0].getElementsByClassName('expand-sub');

      // Mobile Menu Show/Hide.
      toggleExpand[0].addEventListener('click', (e) => {
        if (toggleExpand[0]) {
        toggleInnerExpand[0].classList.toggle('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.toggle('additional-links-menu-nav--open');
        }
        body[0].classList.toggle('no-scroll');
        menu[0].classList.toggle('main-nav--open');
        header[0].classList.toggle('header__primary-mobile');
        header.removeClass('slide-out');
        header[0].classList.toggle('slide-in');
        e.preventDefault();

      });

      // Mobile Menu Show/Hide.
      toggleInnerExpand[0].addEventListener('click', (e) => {
        if (toggleInnerExpand[0]) {
          toggleInnerExpand[0].classList.toggle('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.toggle('additional-links-menu-nav--open');
        }
        menu[0].classList.toggle('main-nav--open');
        header[0].classList.toggle('slide-in');
        header[0].classList.toggle('slide-out');
        body[0].classList.toggle('no-scroll');
        setTimeout(function() { header[0].classList.toggle('header__primary-mobile'); }, 1000);
        e.preventDefault();
      });



      // Expose mobile sub menu on click.
      Array.from(expandMenu[0]).forEach((item) => {
        item.addEventListener('click', (e) => {
          const menuItem = e.currentTarget;
          const subMenu = menuItem.nextElementSibling;
          if (menuItem[0]) {
              menuItem[0].classList.toggle('expand-sub--open');
          }
          if (subMenu[0]) {
            subMenu[0].classList.toggle('main-menu--sub-open');
          }
        });
      });
    }

    });
  },

};
})(jQuery, Drupal, this);
