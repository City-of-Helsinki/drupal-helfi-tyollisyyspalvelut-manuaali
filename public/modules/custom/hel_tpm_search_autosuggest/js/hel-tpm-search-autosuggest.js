(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.hel_tpm_search_autocomplete = {
    addTabIndex: function(formId) {
      var tab = 0;
      jQuery('.suggestion-item', formId).each(function () {
        $(this).attr('tabIndex', tab);
        tab++;
      });
    },
    submitSelection: function(searchVal, formId) {
      console.log(searchVal);
      $('input[name="search"]', formId).val(searchVal);
      $(formId).submit();
    },
    handleSelectionEvents: function(formId, searchField) {
      jQuery('.suggestion')
        .keypress(function (ev) {
          var keycode = (ev.keyCode ? ev.keyCode : ev.which);
          if (keycode === '13') {
            fnc.call(this, ev);
          }
          Drupal.behaviors.hel_tpm_search_autocomplete.submitSelection($(this).attr('value'), formId);
        })
        .click(function(){
          Drupal.behaviors.hel_tpm_search_autocomplete.submitSelection($(this).attr('value'), formId);
        });
    },
    buildSuggestions: function(form, data, term) {
      var searchList = '#search-service-list .item-list';
      var suggestionList = '#search-suggestions .item-list';
      if (term.length <= 0) {
        jQuery(searchList, form).html('');
        jQuery(suggestionList, form).html('');
        return;
      }
      var services = '';
      var suggestions = '';
      var i = 0;
      for(;data[i];) {
        if (data[i]['url']) {
          services += '<span class="suggestion-item"><a href="' + data[i]['url'] + '">' + data[i]['value'] + "</a></span>";
        }
        else {
          suggestions += '<span class="suggestion-item" value="' + data[i]['value'] + '">' + data[i]['label'] + '</span>';
        }
        i++;
      }

      jQuery(searchList, form).html(services, form);
      jQuery(suggestionList, form).html(suggestions, form);
    },
    attach: function (context, settings) {
      var formId = '#hel-tpm-search-autosuggest-form';
      var form = $(formId);
      var searchField = '#hel_tpm_search_form'
      var autocompleteWrapper = '#search-autocomplete';
      jQuery(searchField, form).keyup(function() {

        var term = jQuery(this).val();
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
            Drupal.behaviors.hel_tpm_search_autocomplete.buildSuggestions(form, data, term);
            Drupal.behaviors.hel_tpm_search_autocomplete.addTabIndex(formId);
            Drupal.behaviors.hel_tpm_search_autocomplete.handleSelectionEvents(formId, searchField);
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);