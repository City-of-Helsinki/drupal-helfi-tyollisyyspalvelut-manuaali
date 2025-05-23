(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.searchCollapse = {
    attach: function (context, settings) {
      let errorState = $(".main-content .node-form >.status-message-wrapper");
      if (errorState.length === 0) {
        return
      }
      $(errorState).prependTo(".region-content");
    }
  };
})(jQuery, Drupal, this);
