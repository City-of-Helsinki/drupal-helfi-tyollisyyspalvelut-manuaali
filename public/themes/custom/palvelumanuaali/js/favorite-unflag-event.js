(function ($, Drupal, window) {
  'use strict';

  Drupal.behaviors.unFlaggingEvent = {
    attach: function (context, settings) {
      const cart = '.view-cart';
      const popupWrapper = '.unflag-popup-wrapper';
      const popupTimeout = 20000;

      if ($('.view-id-cart').length <= 0) {
        return;
      }

      // Create popup wrapper on load.
      createPopupWrapper();

      // Act on ajaxComplete event when flagging action is invoked.
      $(context).ajaxComplete(function (event, xhr, settings) {
        let triggElem = getTriggeringElement(xhr);
        if (triggElem === false || typeof triggElem === 'undefined') {
          return;
        }
        let data = getXhrData(xhr);

        // Returning data has action-flag class. Create popup and trigger RefreshView.
        if ($(data).hasClass('action-flag')) {
          let card = ($(triggElem, context).closest('.card--taxonomy-card'));
          createPopup(card, getCancelUrl(data), triggElem)
          $(cart).triggerHandler('RefreshView');
        }

        // When data has action-unflag refreshview and close popup.
        if ($(data).hasClass('action-unflag')) {
          $(cart).triggerHandler('RefreshView');
          closePopup(triggElem);
        }
      });

      /**
       * Create a popup to notify user when item is unflagged.
       * User can cancel the unflag action from cancel button.
       *
       * @param message
       * @param cancelUrl
       * @param triggeringElement
       */
      function createPopup(card, cancelUrl, triggeringElement) {
        // Create a unique identifier for each popup
        triggeringElement = triggeringElement.split('.').join("");
        let popupId =  `popup-${triggeringElement}`;
        let title = $.trim($('.card__heading', card).text());
        let message = Drupal.t('@title removed from favorites', {'@title': title});

        // HTML structure for the popup
        let popupHTML = `
          <div class="popup unflag-confirm-popup ${popupId}" id="${popupId}">
            <span class="close">&times;</span>
            <p>${message}</p>
            <button class="ok-btn">OK</button>
            <div class="flag-cart action-unflag">
              <a class="use-ajax cancel-btn" href="${cancelUrl}" >Cancel</a>
             </div>
          </div>`;

        $(popupHTML).appendTo(popupWrapper, context);

        // Event handlers for buttons
        $('.ok-btn').click(function () {
          $(this).closest('.popup').remove();
        });

        $('.cancel-btn').click(function () {
          $(this).closest('.popup').remove();
        })

        setTimeout(function() {
          $(".popup").each(function () {
            if ($(this).attr('id') === popupId) {
              $(this).remove();
            }
          })
        }, popupTimeout);
      }

      /**
       * Creates popup wrapper.
       */
      function createPopupWrapper() {
        if ($(popupWrapper).length >= 0) {
          $('body', context).append("<div class='unflag-popup-wrapper'></div>")
        }
      }

      /**
       * Remove popup element.
       *
       * @param triggeringElement
       * @param context
       */
      function closePopup(triggeringElement, context) {
        // Create a unique identifier for each popup
        let popupId = `.popup${triggeringElement}`;
        $(popupId).remove();
      }

      /**
       * Fetch response data from xhr.
       *
       * @param xhr
       * @returns {*|boolean}
       */
      function getXhrData(xhr) {
        if (xhr.responseJSON.length <= 0) {
          return false;
        }
        return xhr.responseJSON[0].data;
      }

      /**
       * Get element triggering ajax callback.
       *
       * @param xhr
       * @returns {*|boolean}
       */
      function getTriggeringElement(xhr) {
        if (xhr.responseJSON.length <= 0) {
          return false;
        }
        return xhr.responseJSON[0].selector;
      }

      /**
       * Get cancellation url.
       *
       * @param data
       * @returns {*|jQuery}
       */
      function getCancelUrl(data) {
        return $('a', data).attr('href');
      }
    },
  };
})(jQuery, Drupal, this);
