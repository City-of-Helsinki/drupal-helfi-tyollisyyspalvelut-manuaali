(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.printPdf = {
    attach(context, settings) {
      $(context).ready(function() {
        var $pdfButton = context.getElementById("block-hel-print-pdf-block");
        if ($pdfButton) {
          var $location = window.location.pathname.split("/");
          var $link = $pdfButton.querySelector('.button');
          $link.href = "/" + $location[1] + "/print/view/pdf/service_search/page_1" + window.location.search;
        }
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
