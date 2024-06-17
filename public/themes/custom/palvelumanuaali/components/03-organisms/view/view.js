(function ($, Drupal, window) {
  'use strict';
  Drupal.behaviors.pagerItem = {
    attach: function (context, settings) {
        let firstLength;
        if ($('.pager--load-more a')  &&  $('.view-solr-service-search .taxonomy-card')) {
          $('.pager--load-more a').on('click', function() {
          firstLength = $('.view-solr-service-search .taxonomy-card').length;
            setTimeout(function() {
              var choice = $('.view-solr-service-search .taxonomy-card')[firstLength];
              var choice2 = $(choice).children('.card')[0];
              var choice3 = $(choice2).children('.card__link')[0];
              $(choice3).focus();
              $('.view-solr-service-search .taxonomy-card')[firstLength].scrollIntoView({
                behavior: "smooth",
                block: "start"
              });
            }, 1000);
          });
        }

      }
  };

})(jQuery, Drupal, this);
