/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 267:
/***/ (function() {

(function(a,b){"use strict";b.behaviors.userMenu={attach:function(b){let c=a("body"),d=a(".menu--account"),e=a(".logged-in-open"),f=a(".dropdown-mobile-menu",d),g=a(".btn-menu-close",f);"undefined"!=typeof d&&(e.on("click",function(){c.toggleClass("no-scroll"),f.toggleClass("slide-in")}),g.on("click",function(){f.toggleClass("slide-in"),f.toggleClass("slide-out-right"),c.toggleClass("no-scroll"),setTimeout(function(){f.removeClass("slide-out-right")},1e3)})),a(b).ajaxStop(function(){let b=a(".menu--account"),d=a(".dropdown-mobile-menu",b);"undefined"!=typeof b&&(e.on("click",function(){c.toggleClass("no-scroll"),d.toggleClass("slide-in")}),g.on("click",function(){d.toggleClass("slide-in"),d.toggleClass("slide-out-right"),c.toggleClass("no-scroll"),setTimeout(function(){d.removeClass("slide-out-right")},1e3)}))})}}})(jQuery,Drupal,this);

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__[267].call(__webpack_exports__);
/******/ 	
/******/ })()
;