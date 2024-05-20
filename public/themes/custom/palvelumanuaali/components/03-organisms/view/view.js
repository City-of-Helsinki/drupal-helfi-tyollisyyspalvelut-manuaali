(function ($, Drupal, window) {
  'use strict';
  Drupal.behaviors.taxonomyFilters = {
      attach: function (context, settings) {
      // Handles clicking of unchecked taxonomy checkbox
      $('.view-taxonomy-term .form-checkboxes input:not(:checked)').off().click(function() {
        let lastSelection = $(this).closest('.form-checkboxes').find('input:checked');
        lastSelection.prop("checked", false);
        lastSelection.parent().removeClass('highlight');
        $(this).prop("checked", true);
      });
      // Handles clicking of checked taxonomy checkbox
      $('.view-taxonomy-term .form-checkboxes input:checked').off().click(function() {
        $(this).prop("checked", false);
      });
    }
  };
  Drupal.behaviors.pagerItem = {
    attach: function (context, settings) {
        let pageItem = $(".pager__item a");
        let firstLength;
        let updateLength;
        let firstNew;
        if ($('.pager--load-more a')  &&  $('.view-solr-service-search .taxonomy-card')) {
          $('.pager--load-more a').on('click', function() {
          firstLength = $('.view-solr-service-search .taxonomy-card').length;
            setTimeout(function() {
              var valinta = $('.view-solr-service-search .taxonomy-card')[firstLength];
              var valinta2 = $(valinta).children('.card')[0];
              var valinta3 = $(valinta2).children('.card__link')[0];
              $(valinta3).focus();
              $('.view-solr-service-search .taxonomy-card')[firstLength].scrollIntoView({
                behavior: "smooth", // or "auto" or "instant"
                block: "start" // or "end"
              });
            }, 1000);
          });
        }

      }
  };

})(jQuery, Drupal, this);
