!function(e){var n={};function t(r){if(n[r])return n[r].exports;var o=n[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,t),o.l=!0,o.exports}t.m=e,t.c=n,t.d=function(e,n,r){t.o(e,n)||Object.defineProperty(e,n,{enumerable:!0,get:r})},t.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.t=function(e,n){if(1&n&&(e=t(e)),8&n)return e;if(4&n&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(t.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&n&&"string"!=typeof e)for(var o in e)t.d(r,o,function(n){return e[n]}.bind(null,o));return r},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},t.p="",t(t.s=0)}([function(e,n){!function(e,n){"use strict";Drupal.behaviors.loginForm={attach:function(){var n=e(".header__secondary .block-user-login-block"),t=(e(".header__primary"),e(".header__primary .additional-links-menu-nav"),e("body")),r=e(".header__secondary .block-user >.btn-menu"),o=e(".header__secondary .slide-in-loginform .btn-menu-close"),l=e(".header__secondary .slide-in-loginform");(void 0!==n||null==menu)&&(r[0].addEventListener("click",(function(e){t.toggleClass("no-scroll"),l.toggleClass("slide-in"),e.preventDefault()})),o[0].addEventListener("click",(function(e){l.toggleClass("slide-in"),l.toggleClass("slide-out-right"),t.toggleClass("no-scroll"),setTimeout((function(){l.removeClass("slide-out-right")}),1e3),e.preventDefault()})))}}}(jQuery)}]);