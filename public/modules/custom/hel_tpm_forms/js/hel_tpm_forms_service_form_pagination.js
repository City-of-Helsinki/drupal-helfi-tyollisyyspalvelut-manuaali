(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms_service_form_pagination = {
    attach: function (context, settings) {
      let tabs = document.getElementsByClassName("tab");
      defaultTab();
      nextPrevNav();
      stepNav();

      /**
       * Default tab logic.
       */
      function defaultTab() {
        let currentTab = getCurrentTab();
        showTab(currentTab); // Display the current tab
      }

      /**
       * Provides for next and previous navigation events.
       */
      function nextPrevNav() {
        $('.btn-prev').once().click(function () {
          nextPrev(-1);
        });

        $('.btn-next').once().click(function () {
          nextPrev(1);
        });
      }

      /**
       * Pager navigation.
       */
      function stepNav() {
        $('.step').once().click(function () {
          let step = $(this).attr('data-step');
          switchTab(step);
        });
      }

      /**
       * Update url step parameter.
       *
       * @param step
       */
      function updateStepParam(step) {
        let urlParams = new URLSearchParams(window.location.search);
        urlParams.set('step', step);
        history.replaceState(null, null, "?"+urlParams.toString());
      }

      /**
       * Switch tab.
       *
       * @param n
       */
      function switchTab(n) {
        let currentTab = getCurrentTab();
        $(tabs).each(function () {
          this.style.display = "none";
        });
        currentTab = Number(n);
        showTab(currentTab);
      }

      /**
       * Show selected tab function.
       *
       * @param n
       */
      function showTab(n) {
        n = Number(n);

        // This function will display the specified tab of the form ...
        tabs[n].style.display = "block";
        let lastTab = tabs.length - 1;
        let nextBtn = document.getElementById('nextBtn');
        let prevBtn = document.getElementById('prevBtn');
        // ... and fix the Previous/Next buttons:
        if (n > 0) {
          prevBtn.hidden = false;
          nextBtn.hidden = false;
        }
        else {
          prevBtn.hidden = true
        }

        if (n === lastTab) {
          nextBtn.hidden = true;
        }

        updateStepParam(n);
        // ... and run a function that displays the correct step indicator:
        fixStepIndicator(n);
      }

      /**
       * Helper function to get current tab from url parameter.
       *
       * @returns {number}
       */
      function getCurrentTab() {
        let currentTab = 0;
        let urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('step')) {
          currentTab = urlParams.get('step');
          currentTab = Number(currentTab);
        }
        return Number(currentTab);
      }

      function nextPrev(n) {
        let currentTab = getCurrentTab();
        currentTab = currentTab + Number(n);
        // Otherwise, display the correct tab:
        switchTab(currentTab)
      }

      function fixStepIndicator(n) {
        // This function removes the "active" class of all steps...
        let i, x = document.getElementsByClassName("step");
        for (i = 0; i < x.length; i++) {
          x[i].className = x[i].className.replace(" active", "");
        }
        //... and adds the "active" class to the current step:
        x[n].className += " active";
      }
    }
  }
})(jQuery, Drupal, drupalSettings);
