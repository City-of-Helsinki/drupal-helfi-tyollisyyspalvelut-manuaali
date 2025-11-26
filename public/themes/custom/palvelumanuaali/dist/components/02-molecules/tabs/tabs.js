/******/ (() => { // webpackBootstrap
Drupal.behaviors.tabs={attach(a){function b(a){a!==g&&0<=a&&a<=e.length&&(e[g].classList.remove("is-active"),e[a].classList.add("is-active"),f[g].classList.remove("is-active"),f[a].classList.add("is-active"),g=a)}function c(a,c){a.addEventListener("click",a=>{a.preventDefault(),b(c)})}const d=a.querySelectorAll(".tabs"),e=a.querySelectorAll(".tabs__link"),f=a.querySelectorAll(".tabs__tab");let g=0;for(let b=0;b<d.length;b+=1)d[b].classList.remove("no-js");for(let b=0;b<e.length;b+=1){const a=e[b];c(a,b)}}};
/******/ })()
;