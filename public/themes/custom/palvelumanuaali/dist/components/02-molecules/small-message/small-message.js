/******/ (() => { // webpackBootstrap
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other entry modules.
(() => {
(function(a,b){b.behaviors.smallMessage={attach(){const b=a(".action-flag .use-ajax"),c=a(".action-unflag .use-ajax");a(b).click(function(){const b=a(this).closest(".small-message-wrapper").children(".pill--small-message--add");b.fadeIn("2000ms"),setTimeout(function(){b.fadeOut("2000ms")},3e3)}),a(c).click(function(){const b=a(this).closest(".small-message-wrapper").children(".pill--small-message--remove");b.fadeIn("2000ms"),setTimeout(function(){b.fadeOut("2000ms")},3e3)})}}})(jQuery,Drupal,drupalSettings);
})();

// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
// extracted by mini-css-extract-plugin

})();

/******/ })()
;