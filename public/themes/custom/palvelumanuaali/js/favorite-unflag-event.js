(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.unFlaggingEvent = {
    attach: function (context, settings) {
      if ($('.view-id-cart').length <= 0) {
        return;
      }
      $('.action-unflag a.use-ajax').click(function() {
        $(document).ajaxSuccess(function (event, xhr, settings) {
          let triggeringElement = getTriggeringElement(xhr);
          if ($(triggeringElement).hasClass('flag-cart')) {
            $('.view-cart').triggerHandler('RefreshView');
            let data = xhr.responseJSON[0].data;
            createPopup(Drupal.t('Service removed from favorites'), getCancelUrl(data), triggeringElement)
      //      showUnflaggedNotice(xhr, triggeringElement);
          }
        });
      })

      function createPopup(message, cancelUrl, triggeringElement) {
        // Create a unique identifier for each popup
        const popupId = `popup${triggeringElement}`;

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

        // Append the popup to the body
        let elem = once(popupId,  "body");
        $(elem).append(popupHTML);

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

      function getTriggeringElement(xhr) {
        return once('trigger-once', xhr.responseJSON[0].selector);
      }

      function getCancelUrl(data) {
        return $('a', data).attr('href');
      }
    },
  };
})(jQuery, Drupal, this);
