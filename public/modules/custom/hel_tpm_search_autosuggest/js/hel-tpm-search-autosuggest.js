(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.hel_tpm_search_autocomplete = {
    attach: function (context, settings) {
      var formId = '#hel_tpm_search_form';

      var autocompleteWrapper = '#search-autocomplete';
      var searchList = '#search-service-list .item-list';
      var suggestionList = '#search-suggestions .item-list';
      jQuery(formId).keyup(function() {
        var term = jQuery(formId).val();
          if (term.length > 0) {
          $(autocompleteWrapper).show();
        }
        else {
          $(autocompleteWrapper).hide();
        }
        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "search_api_autocomplete/service_search",
          data: { q: term },
          success: function (data) {
            if (term.length <= 0) {
              jQuery(searchList).html('');
              jQuery(suggestionList).html('');
              return;
            }
            var services = '';
            var suggestions = '';
            var i = 0;
            for(;data[i];) {
              if (data[i]['url']) {
                services += '<span><a href="' + data[i]['url'] + '">' + data[i]['value'] + "</a></span>";
              }
              else {
                suggestions += "<span class='suggestion' value='" + data[i]['value'] + "'>" + data[i]['label'] + "</span>";
              }
              i++;
            }

            jQuery(searchList).html(services);
            jQuery(suggestionList).html(suggestions);
            jQuery('.suggestion').click(function () {
              var searchVal = $(this).attr('value');
              $('input[name="search"]').val(searchVal);
            });
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);