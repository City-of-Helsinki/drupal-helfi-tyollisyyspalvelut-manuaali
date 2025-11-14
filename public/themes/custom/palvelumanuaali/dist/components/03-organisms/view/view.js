/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 453:
/***/ (function() {

(function(a,b){"use strict";b.behaviors.pagerItem={attach:function(){let b;a(".pager--load-more a")&&a(".view-solr-service-search .taxonomy-card")&&a(".pager--load-more a").on("click",function(){b=a(".view-solr-service-search .taxonomy-card").length,b&&setTimeout(function(){let c=a(".view-solr-service-search .taxonomy-card")[b],d=a(c).children(".card")[0],e=a(d).children(".card__link")[0];a(e).focus(),a(c).scrollIntoView({behavior:"smooth",block:"start"})},1e3)})}}})(jQuery,Drupal,this);

/***/ }),

/***/ 819:
/***/ (() => {

"use strict";
// extracted by mini-css-extract-plugin


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	__webpack_modules__[453].call(__webpack_exports__);
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__[819]();
/******/ 	
/******/ })()
;