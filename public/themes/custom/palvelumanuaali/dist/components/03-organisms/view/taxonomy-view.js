/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 833:
/***/ (function() {

(function(a,b,c){b.behaviors.searchPageFilters={attach(){function b(b){let c=d(b),e=a(b).innerWidth(),f=e,g=[],h=0;return g[h]=[],a(c).each(function(a,b){f-=b.width,b.row_width=f,0<=f?g[h].push(b):(f=e-b.width,b.row_width=f,h++,g[h]=[],g[h].push(b))}),g}function d(b){let c=[];return a(".form-item",b).each(function(){let b={object:this,width:a(this).outerWidth()};c.push(b)}),c}function e(c){let d=b(c),e=34*d.length,g=f();1080>g?a(".form-checkboxes").css("margin-bottom","0px"):a(".form-checkboxes").css("margin-bottom",e+"px")}function f(){return Math.max(document.body.scrollWidth,document.documentElement.scrollWidth,document.body.offsetWidth,document.documentElement.offsetWidth,document.documentElement.clientWidth)}const g=".municipality-taxonomy-view-filters .bef-nested .highlight + .filter-buttons";a(document).ready(function(){e(g)}),a(c).on("load",function(){setTimeout(function(){e(g)},.2)}),a(c).on("resize",function(){e(g)})}}})(jQuery,Drupal,this);

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__[833].call(__webpack_exports__);
/******/ 	
/******/ })()
;