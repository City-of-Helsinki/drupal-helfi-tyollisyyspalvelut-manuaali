(function($, Drupal, drupalSettings, window) {
  Drupal.behaviors.breadcrumbs = {
    attach(context, settings) {
      //tsekit näil

        let serviceView = document.getElementById("views-exposed-form-service-search-page-1");
        let serviceNode = document.getElementById("service-page-full");

      if(typeof serviceView === 'undefined' || serviceView === null ){
          //#serviceViewID element DOES NOT exist
        if(serviceNode && localStorage.getItem("servicelist") != null) {
          //#serviceNode element exists and Servicelist has value
          let breadcrumbElement = document.getElementById("page-breadcrumb");
          let li = document.createElement("li");
          li.className = 'breadcrumb__item';
          li.id = 'generated-service-path';
          breadcrumbElement.insertBefore(li, breadcrumbElement.children[1]);
          let anchor = document.createElement("a");
          anchor.className = 'breadcrumb__link';
          anchor.setAttribute('href', localStorage.getItem("servicelist"));
          anchor.textContent = Drupal.t("Services");
          li.appendChild(anchor);
        }
        localStorage.removeItem("servicelist");
      }
      else {
        //#myElementID element DOES exist
        var url = location.pathname+location.search;
        localStorage.setItem("servicelist", url);
      }
    }
  }
})(jQuery, Drupal);
