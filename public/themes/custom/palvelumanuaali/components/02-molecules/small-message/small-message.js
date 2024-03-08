(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.smallMessage = {
    attach(context) {
    const articleFlag = $(".action-flag .use-ajax");
    const articleUnflag = $(".action-unflag .use-ajax");

      $(articleFlag).click(function() {
        const nearestMessage = $(this).closest(".small-message-wrapper").children(".pill--small-message--add");
        nearestMessage.fadeIn('2000ms');
        setTimeout(function() {
          nearestMessage.fadeOut('2000ms');
        }, 3000);
      });

      $(articleUnflag).click(function() {
        const nearestMessage = $(this).closest(".small-message-wrapper").children(".pill--small-message--remove");
        nearestMessage.fadeIn('2000ms');
        setTimeout(function() {
          nearestMessage.fadeOut('2000ms');
        }, 3000);
      });

    },
  };
})(jQuery, Drupal, drupalSettings);
