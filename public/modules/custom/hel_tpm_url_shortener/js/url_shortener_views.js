(function ($, Drupal, drupalSettings) {

  /**
   * Displays a popup with a provided message, attaching it to the given form.
   *
   * @param {string} message - The message to display in the popup.
   * @param {jQuery} form - The form element associated with the popup.
   * @return {void} This function does not return a value.
   */
  function showPopup (message, form) {
    let clipBoardStatus = $(form).find('.clipboard-status');
    let popupTitle = $('.popup-title', clipBoardStatus);
    clipBoardStatus.css('display', 'block');
    popupTitle.html(Drupal.t(message));
    clipBoardStatus.fadeTo(300, 1, function() {});
    clipBoardStatus.delay(3000).fadeOut(300, function() {});
  }

  /**
   * Displays a short URL in the given form and toggles its visibility.
   *
   * @param {string} shortUrl The short URL to be displayed.
   * @param {HTMLElement} form The form element in which the short URL should be shown.
   * @return {void}
   */
  function showShortUrl(shortUrl, form) {
    let shortUrlWrapper = $(form).find('.short-link-result');
    let shortUrlElement = $(form).find('.short-link');
    let hideShortLink = $(form).find('.hide-short-link');
    let createLinkButton = $(form).find('.create-link');
    shortUrlElement.text(shortUrl);
    shortUrlWrapper.toggleClass('visually-hidden');
    hideShortLink.removeClass('visually-hidden');
    Drupal.behaviors.url_shortener.hideCreateLink(createLinkButton);
  }

  /**
   * Drupal behavior for URL shortener functionality.
   *
   * This behavior is responsible for attaching functionality to shorten URLs
   * on specific elements or pages within a Drupal site. It ensures that the
   * defined behavior executes when the page loads or when new content is
   * loaded via AJAX.
   *
   * @namespace
   * @property {Object} attach - Method executed when this behavior is applied. This is typically invoked on page load or when content is added.
   * @property {HTMLElement} attach.context - The current context in which the behavior is executed, provided by Drupal's framework.
   * @property {Object} attach.settings - An object containing any settings or configuration needed for the behavior.
   */
  Drupal.behaviors.url_shortener_views = {
    attach: function (context, settings) {
      let view = $('.views-exposed-form.bef-exposed-form', context);
      let shortenLink = $('.shorten-link .label', view);
      let createLink = $('.create-link', view);
      let hideShortLink = $('.hide-short-link', view);
      let shortLinkResult = $('.short-link-result', view);
      // Update current path value.
      $('.current-path', view).val(window.location.pathname + window.location.search);
      // When current path is updated show create link
      // When current path is updated remove previous short link.
      this.showCreateLink(createLink);
      this.hideShortLinkResult(shortLinkResult);

      let current_path = $('.current-path').val();
      shortenLink.click(function(event){
        event.preventDefault();
        createLink.click();
      });

      createLink.click(function (event) {
        event.preventDefault();
        let form = $(this, context).closest('.shorten-link');
         $.ajax({
          'url': $(this).attr('data-ajax-url'),
          'data': {
            current_path: current_path
          },
          'success': function (data) {
            let link = data.data;
            navigator.clipboard
              .writeText(link)
              .then(() => {
                // On success hide copy clipboard to button and create message.
                $('.create-link', form).addClass('active');
                $('.create-link', form).prop("disabled",true);
                showPopup(Drupal.t('Link copied.'), form);
              })
              .catch((e) => {
                showShortUrl(link, form);
                showPopup(Drupal.t('Copying link failed'), form);
              });
          }
        });
      });

      hideShortLink.click(function () {
        Drupal.behaviors.url_shortener.hideShortLinkResult(shortLinkResult);
        Drupal.behaviors.url_shortener.showCreateLink(createLink);
        $(this).addClass('visually-hidden');
      });


    },

    showCreateLink: function (createLink) {
      createLink.removeClass('visually-hidden');
      createLink.removeClass('active');
      createLink.attr('disabled', false);
    },

    hideCreateLink: function (createLink) {
      createLink.removeClass('active');
      createLink.addClass('visually-hidden');
    },

    hideShortLinkResult: function (shortLinkResult) {
      shortLinkResult.addClass('visually-hidden');
      $('.short-link', shortLinkResult).text('');

    }
  }
  // Argument passed from InvokeCommand.
})(jQuery, Drupal, drupalSettings);
