/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 413:
/***/ (function() {

(function(a,b){"use strict";b.behaviors.mainMenu={attach:function(b){let c=a(".header__inner >.toggle-expand"),d=a(".header-main-nav .toggle-expand"),f=a(".header-main-nav .main-nav"),g=a(".header__primary"),h=a(".header__primary .additional-links-menu-nav"),i=a("body");if("undefined"!=typeof f||null==f){let a=f[0].getElementsByClassName("expand-sub"),b=f[0].getElementsByClassName("main-menu__item--with-sub");Array.from(b).forEach(a=>{if(a.classList.contains("main-menu__item--active")){const b=a.querySelector(".expand-sub"),c=b.nextElementSibling;b&&b.classList.toggle("expand-sub--open"),c&&c.classList.toggle("main-menu--sub-open")}}),Array.from(a).forEach(a=>{a.addEventListener("click",a=>{const b=a.currentTarget,c=b.nextElementSibling;b&&b.classList.toggle("expand-sub--open"),c&&c.classList.toggle("main-menu--sub-open")})}),c[0].addEventListener("click",()=>{c[0]&&d[0].classList.toggle("toggle-expand--open"),h[0]&&h[0].classList.toggle("additional-links-menu-nav--open"),i[0].classList.toggle("no-scroll"),f[0].classList.toggle("main-nav--open"),g[0].classList.toggle("header__primary-mobile"),g.removeClass("slide-out"),g[0].classList.toggle("slide-in")}),d[0].addEventListener("click",a=>{d[0]&&d[0].classList.toggle("toggle-expand--open"),h[0]&&h[0].classList.toggle("additional-links-menu-nav--open"),f[0].classList.toggle("main-nav--open"),g[0].classList.toggle("slide-in"),g[0].classList.toggle("slide-out"),i[0].classList.toggle("no-scroll"),setTimeout(function(){g[0].classList.toggle("header__primary-mobile")},1e3),a.preventDefault()})}a(b).ajaxStop(function(){let b=a(".header__inner >.toggle-expand"),c=a(".header-main-nav .toggle-expand"),d=a(".header-main-nav .main-nav"),f=a(".header__primary"),g=a(".header__primary .additional-links-menu-nav"),h=a("body");if("undefined"!=typeof d||null==d){let a=d[0].getElementsByClassName("expand-sub"),e=d[0].getElementsByClassName("main-menu__item--with-sub");Array.from(e).forEach(a=>{if(a.classList.contains("main-menu__item--active")){const b=a.querySelector(".expand-sub"),c=b.nextElementSibling;b&&b.classList.toggle("expand-sub--open"),c&&c.classList.toggle("main-menu--sub-open")}}),Array.from(a).forEach(a=>{a.addEventListener("click",a=>{const b=a.currentTarget,c=b.nextElementSibling;b&&b.classList.toggle("expand-sub--open"),c&&c.classList.toggle("main-menu--sub-open")})}),b[0].addEventListener("click",a=>{b[0]&&c[0].classList.toggle("toggle-expand--open"),g[0]&&g[0].classList.toggle("additional-links-menu-nav--open"),h[0].classList.toggle("no-scroll"),d[0].classList.toggle("main-nav--open"),f[0].classList.toggle("header__primary-mobile"),f.removeClass("slide-out"),f[0].classList.toggle("slide-in"),a.preventDefault()}),c[0].addEventListener("click",a=>{c[0]&&c[0].classList.toggle("toggle-expand--open"),g[0]&&g[0].classList.toggle("additional-links-menu-nav--open"),d[0].classList.toggle("main-nav--open"),f[0].classList.toggle("slide-in"),f[0].classList.toggle("slide-out"),h[0].classList.toggle("no-scroll"),setTimeout(function(){f[0].classList.toggle("header__primary-mobile")},1e3),a.preventDefault()})}})}}})(jQuery,Drupal,this);

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__[413].call(__webpack_exports__);
/******/ 	
/******/ })()
;