(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.unFlaggingEvent = {
    attach: function (context, settings) {
      if ($('.view-id-cart').length <= 0) {
        return;
      }
      $(document).ajaxComplete(function (event, xhr, settings) {
        if (xhr.statusText !== 'success') {
          return;
        }
        console.log(event);
        var response = xhr.responseJSON;
        var triggeringElement = response[0].selector;
        var responseData = response[0].data;
        var parent = $(triggeringElement, document).closest('.views-row');
        if ($(responseData).hasClass('action-flag')) {
          parent.addClass('removed');
        }
        else {
          parent.removeClass('removed');
        }
        console.log($(responseData).hasClass('action-unflag'));
      });
    }
  };
})(jQuery, Drupal, this);
