/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./components/02-molecules/menus/main-menu/main-menu.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./components/02-molecules/menus/main-menu/main-menu.js":
/*!**************************************************************!*\
  !*** ./components/02-molecules/menus/main-menu/main-menu.js ***!
  \**************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function(a,b){"use strict";b.behaviors.mainMenu={attach:function attach(){var b=a(".header__inner >.toggle-expand"),c=a(".header-main-nav .toggle-expand"),d=a(".header-main-nav .main-nav"),f=a(".header__primary"),g=a(".header__primary .additional-links-menu-nav"),h=a("body");if("undefined"!=typeof d||null==d){var i=d[0].getElementsByClassName("expand-sub");b[0].addEventListener("click",function(a){b[0]&&c[0].classList.toggle("toggle-expand--open"),g[0]&&g[0].classList.toggle("additional-links-menu-nav--open"),h[0].classList.toggle("no-scroll"),d[0].classList.toggle("main-nav--open"),f[0].classList.toggle("header__primary-mobile"),f.removeClass("slide-out"),f[0].classList.toggle("slide-in"),a.preventDefault()}),c[0].addEventListener("click",function(a){c[0]&&c[0].classList.toggle("toggle-expand--open"),g[0]&&g[0].classList.toggle("additional-links-menu-nav--open"),d[0].classList.toggle("main-nav--open"),f[0].classList.toggle("slide-in"),f[0].classList.toggle("slide-out"),h[0].classList.toggle("no-scroll"),setTimeout(function(){f[0].classList.toggle("header__primary-mobile")},1e3),a.preventDefault()}),Array.from(i[0]).forEach(function(a){a.addEventListener("click",function(a){var b=a.currentTarget,c=b.nextElementSibling;b[0]&&b[0].classList.toggle("expand-sub--open"),c[0]&&c[0].classList.toggle("main-menu--sub-open")})})}}}})(jQuery,Drupal,this);

/***/ })

/******/ });
//# sourceMappingURL=main-menu.js.map