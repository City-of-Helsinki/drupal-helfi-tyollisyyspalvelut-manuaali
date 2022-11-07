(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.hel_tpm_search_autocomplete = {
    arrowNavigation: function(form) {
      $('input', form).keyup(function(e) {
        if (e.keyCode == 38) {
          $("#search-suggestions .suggestion-item:focus").next().focus();
        }
        if (e.keyCode == 40) {
          $("#search-suggestions .suggestion-item:focus").next().focus();
        }
      });
    },
    addTabIndex: function(form) {
      var tab = 0;
      jQuery('.suggestion-item', form).each(function () {
        $(this).attr('tabIndex', tab);
        tab++;
      });
    },
    submitSelection: function(searchVal, form) {
      $('input[name="search_api_fulltext"]', form).val(searchVal);
      $(form).submit();
    },
    getSearchHistory: function() {
      return JSON.parse(localStorage.getItem('hel_search_history'));
    },
    setSearchHistory: function(value) {
      localStorage.setItem('hel_search_history', JSON.stringify(value));
    },
    appendSearchHistory: function(form) {
      let value = $.trim($('input[name="search_api_fulltext"]', form).val());
      if (value.length <= 0) {
        return;
      }
      let searchHistory = Drupal.behaviors.hel_tpm_search_autocomplete.getSearchHistory();
      if (searchHistory == null) {
        searchHistory = [value];
      }
      else {
        if ($.inArray(value, searchHistory) > -1) {
          return;
        }
        if (searchHistory.length >= 10) {
          searchHistory.pop();
        }
        searchHistory.unshift(value);
      }
      Drupal.behaviors.hel_tpm_search_autocomplete.setSearchHistory(searchHistory);
    },
    buildSearchHistory: function() {
      let history = this.getSearchHistory();
      let content = '';
      let i = 0;
      if (history != null && history.length > 0) {
        $.each(history, function(key, value) {
          content +='<span class="suggestion-item" tabindex="' + i + '" value="' + value + '">' + value + '</span>';
          i++;
        });
      }
      return content;
    },
    handleSelectionEvents: function() {
      $('.suggestion-item')
        .keypress(function (ev) {
          var keycode = (ev.keyCode ? ev.keyCode : ev.which);
          if (keycode === '13') {
            fnc.call(this, ev);
          }
          let form = $(this).closest('form');
          Drupal.behaviors.hel_tpm_search_autocomplete.submitSelection($(this).attr('value'), form);
        })
        .on("mousedown", function (ev) {
          ev.stopPropagation();
        })
        .click(function (ev) {
          let form = $(this).closest('form');
          Drupal.behaviors.hel_tpm_search_autocomplete.submitSelection($(this).attr('value'), form);
        });
    },
    buildSuggestions: function(form, data, term) {
      var searchList = $('.service-list .item-list');
      var suggestionList = $('.suggestions .item-list');
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

      jQuery(searchList, form).html(services);
      jQuery(suggestionList, form).html(suggestions);
    },
    showHideAutocomplete: function(input, context) {
      let searchWrapper = '.search-history-wrapper';
      let autocompleteWrapper = '.hel-search-autocomplete';
      if ($(input).val().length <= 0) {
        $(searchWrapper, context).show();
        $(autocompleteWrapper, context).hide(searchWrapper);
      }
      else {
        $(searchWrapper, context).hide();
        $(autocompleteWrapper, context).show(searchWrapper);
      }

      // Handle click events outside of search element.
      $(document).click(function(event) {
        var $target = $(event.target);
        if(!$target.closest('.search-autocomplete-wrapper').length &&
          $('.search-autocomplete-wrapper').is(":visible")) {
          $('.search-wrapper').hide();
        }
      });
    },
    attach: function (context, settings) {
      var form = $('.search-autocomplete-wrapper');
      var searchField = 'input[name="search_api_fulltext"]'
      let history = Drupal.behaviors.hel_tpm_search_autocomplete.buildSearchHistory(form);

      $(document).ready(function() {
        $('#search-history .item-list', form).html(history);
        Drupal.behaviors.hel_tpm_search_autocomplete.handleSelectionEvents();
        $(searchField)
          .focus(function () {
            let context = $(this).closest(form);
            Drupal.behaviors.hel_tpm_search_autocomplete.showHideAutocomplete(this, context);
          });

        $(form).closest('form').on('submit', function(e) {
          Drupal.behaviors.hel_tpm_search_autocomplete.appendSearchHistory(form);
        });
      });

      jQuery(searchField, form).keyup(function() {
        var term = jQuery(this).val();
        var context = $(this).closest(form);

        Drupal.behaviors.hel_tpm_search_autocomplete.showHideAutocomplete(this, context);

        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "search_api_autocomplete/service_search",
          data: { q: term },
          success: function (data) {
            Drupal.behaviors.hel_tpm_search_autocomplete.buildSuggestions(context, data, term);
            Drupal.behaviors.hel_tpm_search_autocomplete.addTabIndex(context);
            Drupal.behaviors.hel_tpm_search_autocomplete.handleSelectionEvents();
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
