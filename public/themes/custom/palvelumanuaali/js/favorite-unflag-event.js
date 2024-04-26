(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.unFlaggingEvent = {
    attach: function (context, settings) {
      if ($('.view-id-cart').length <= 0) {
        return;
      }
      let unflagElem = once('unflag-once', '.action-unflag a.use-ajax', context);
      $(unflagElem).click(function() {
        console.log('clöikk');
        $(document).ajaxComplete(function (event, xhr, settings) {
          console.log(Drupal);
          let triggElem = getTriggeringElement(xhr);
          let data = getXhrData(xhr);
          if ($(triggElem).hasClass('flag-cart')) {
            $('.view-cart').triggerHandler('RefreshView');
            createPopup(Drupal.t('Service removed from favorites'), getCancelUrl(data), triggElem)
          }
          return;
        });
      })

      function createPopup(message, cancelUrl, triggeringElement) {
        // Create a unique identifier for each popup
        let popupId = `popup${triggeringElement}`;
        if ($('.' + popupId).length > 0) {
          return;
        }

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


        console.log($("#" + popupId, document));
        $(popupHTML).appendTo("body");

        // Close function to remove popup
        function closePopup(trigger) {
          //$(trigger).closest(".popup").remove();
        }

        // Event handlers for buttons
        $(`#${popupId} .close, #${popupId} .ok-btn`).click(function() {
          closePopup();
        });

       let cancelBtn = once('cancel-once', 'a.cancel-btn');
        $(cancelBtn).click(function() {
          // Act after successful ajax request.
          $(document).ajaxSuccess(function (event, xhr, settings) {
            let trigger = getTriggeringElement(xhr);
            console.log(this);
            console.log(trigger);
            if ($(trigger).hasClass('.flag-cart')) {
              $('.view-cart').triggerHandler('RefreshView');
            }
          });
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
