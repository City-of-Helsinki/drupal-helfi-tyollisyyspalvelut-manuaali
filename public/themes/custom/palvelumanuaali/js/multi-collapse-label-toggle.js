(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.multiCollapseLabelToggle = {
    attach: function (context, settings) {
      $('.button--dropdown-button').click(function(){ //you can give id or class name here for $('button')
        $(this).text(function(i,old){
          return old === Drupal.t('Show more') ?  Drupal.t('Show less') : Drupal.t('Show more');
        });
      });
    }
  };
})(jQuery, Drupal, this);
