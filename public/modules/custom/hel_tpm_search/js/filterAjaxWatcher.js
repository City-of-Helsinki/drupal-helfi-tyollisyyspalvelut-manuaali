/**
 * Based on expiremantal module https://www.drupal.org/sandbox/holist/3156043
 */
(function ($, window, Drupal) {
  "use strict";
  Drupal.behaviors.filterAjaxWatcher = {
    attach: function () {
      const elements = once('wrapped', '.bef-exposed-form');
      elements.forEach(function (element) {
        if (Drupal.ajax) {
          Drupal.Ajax.prototype.eventResponse = function (element, event) {
            var ajax = this;
            var inFilters = $(element).parents('.views-exposed-form').length > 0;
            event.preventDefault();
            event.stopPropagation();

            // If we have ongoing AJAX request and we are not dealing with filters, default to normal behaviour.
            if (!inFilters && ajax.ajaxing) return;

            try {
              if (inFilters && Drupal.filterAjaxWatcher && Drupal.filterAjaxWatcher.status !== "200") {
                // Do this only for AJAX requests inside Views exposed forms.
                Drupal.filterAjaxWatcher.abort();
                delete Drupal.filterAjaxWatcher;
              }
              if (ajax.$form) {
                if (ajax.setClick) {
                  element.form.clk = element;
                }

                var form = ajax.$form.ajaxSubmit(ajax.options);
                Drupal.filterAjaxWatcher = form.data("jqxhr");
              } else {
                ajax.beforeSerialize(ajax.element, ajax.options);
                Drupal.filterAjaxWatcher = $.ajax(ajax.options);
              }
            } catch (e) {
              ajax.ajaxing = false;
              window.alert(
                Drupal.t("There was an error while requesting the results. Please reload the page and try again.")
                + "\n\n" +
                Drupal.t("Error details") + ": " + ajax.options.url + ": " + e.message
              );
            }
          };

          Drupal.Ajax.prototype.setProgressIndicatorFullscreen = function () {
            // Don't append throbber if it already exists.
            if ($("html > .ajax-progress-fullscreen").length > 0) {
              return;
            }
            this.progress.element = $(
              Drupal.theme("ajaxProgressIndicatorFullscreen")
            );
            $("body").after(this.progress.element);
          };
        }
      });
    },
  };
})(jQuery, window, Drupal);
