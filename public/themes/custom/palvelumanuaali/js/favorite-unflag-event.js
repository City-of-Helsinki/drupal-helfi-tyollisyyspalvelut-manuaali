(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.unFlaggingEvent = {
    attach: function (context, settings) {
      const cart = once('view-cart-once', '.view-cart');
      if ($('.view-id-cart').length <= 0) {
        return;
      }
      $(context).ajaxComplete(function (event, xhr, settings) {
        let triggElem = getTriggeringElement(xhr);
        let data = getXhrData(xhr);
        if ($(triggElem).hasClass('flag-cart')) {
          createPopup(Drupal.t('Service removed from favorites'), getCancelUrl(data), triggElem)
          $(cart).triggerHandler('RefreshView');
        }
      });

      function createPopup(message, cancelUrl, triggeringElement) {
        // Create a unique identifier for each popup
        let popupId = `popup${triggeringElement}`;
        // HTML structure for the popup
        const popupHTML = `
        <div class="popup ${popupId}" id="${popupId}">
            <div class="popup-content">
                <span class="close">&times;</span>
                <p>${message}</p>
                <button class="ok-btn">OK</button>
                <div class="flag-cart action-unflag">
                  <a class="use-ajax cancel-btn" href="${cancelUrl}" >Cancel</a>
                 </div>
            </div>
        </div>`;

        $(popupHTML).appendTo("body");

        // Close function to remove popup
        function closePopup(trigger) {
          $(trigger).closest(".popup").remove();
        }

        // Event handlers for buttons
        $(`#${popupId} .close, #${popupId} .ok-btn`).click(function () {
          closePopup();
        });

        let cancelBtn = once('cancel-once', 'a.cancel-btn');
        $(cancelBtn).click(function () {
//          closePopup(this);
          $(context).ajaxSuccess(function () {

            console.log('adsffsad');
            $(cart).triggerHandler('RefreshView');
          })
        });
        // Set timeout to automatically close the popup after 20 seconds
        setTimeout(closePopup, 20000);
      }


      function getXhrData(xhr) {
        return xhr.responseJSON[0].data;
      }
      function getTriggeringElement(xhr) {
        return xhr.responseJSON[0].selector;
      }

      function getCancelUrl(data) {
        return $('a', data).attr('href');
      }
    },
  };
})(jQuery, Drupal, this);
