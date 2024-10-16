(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.url_shortener = {
      attach: function (context, settings) {
        let parent = '#shorten-link';
        this.updateCurrentPath(parent);
        this.copyToClipboard(parent);
      },
      updateCurrentPath : function (parent) {
        let currentPath = $('.current-path', parent).val();
        if (currentPath.length > 0 && currentPath === window.location.pathname + window.location.search) {
          return;
        }

        // Update current path value.
        $('.current-path', parent).val(window.location.pathname + window.location.search);
        // When current path is updated show create link
        $('.create-link', parent).removeClass('visually-hidden');
        // When current path is updated hide clipboard button.
        $('.clipboard-button', parent).addClass('visually-hidden');
        // When current path is updated remove previous short link.
        $('.short-link-result', parent).remove();
      },

      /**
       * Copy to clipboard function.
       *
       * @param parent
       */
      copyToClipboard: function (parent) {
        let context = this;
        let createLinkButton = $('.create-link', parent);
        let clipboardButton = $('.clipboard-button', parent);
        let shortLinkResult = $('.short-link-result', parent);
        let shortLink = $('.short-link', parent);

        $(once('clipboard-button', clipboardButton)).click(function (event) {
          event.preventDefault();
          let shortLink = $('.short-link', shortLinkResult);
          navigator.clipboard
            .writeText(shortLink.text())
            .then(() => {
              // On success hide copy clipboard to button and create message.
              clipboardButton.addClass('visually-hidden');
              createLinkButton.removeClass('visually-hidden');
              shortLinkResult.addClass('visually-hidden');

              context.showPopup(Drupal.t('Copied to clipboard.'));
            })
            .catch((e) => {
              context.showPopup(Drupal.t('Copy to clipboard failed.'));
            });
        });
      },

      /**
       * Show popup function.
       *
       * @param message
       *  Message text.
       */
      showPopup: function(message) {
        let clipBoardStatus = $('.clipboard-status');
        let popupTitle = $('.popup-title', clipBoardStatus);
        popupTitle.html(Drupal.t(message));
        clipBoardStatus.fadeTo(300, 1, function() {});
        clipBoardStatus.delay(3000).fadeOut(300, function() {});
      }
    }
  // Argument passed from InvokeCommand.
})(jQuery, Drupal, drupalSettings);
