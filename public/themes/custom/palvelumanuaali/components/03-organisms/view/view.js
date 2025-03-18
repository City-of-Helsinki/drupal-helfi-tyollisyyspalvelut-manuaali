(function ($, Drupal, window) {
  'use strict';
  Drupal.behaviors.pagerItem = {
    attach: function (context, settings) {
        let firstLength;
        if ($('.pager--load-more a')  &&  $('.view-solr-service-search .taxonomy-card')) {
          $('.pager--load-more a').on('click', function() {

          firstLength = $('.view-solr-service-search .taxonomy-card').length;
          if (firstLength) {
            setTimeout(function() {
              let choice = $('.view-solr-service-search .taxonomy-card')[firstLength];
              let choice2 = $(choice).children('.card')[0];
              let choice3 = $(choice2).children('.card__link')[0];
              $(choice3).focus();
              $(choice).scrollIntoView({
                behavior: "smooth",
                block: "start"
              });
            }, 1000);
          }
          });

        }

      }
  };

})(jQuery, Drupal, this);
