(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.custom_module_forms_service_form_pagination = {
    attach: function (context, settings) {
      const tabs = document.getElementsByClassName('tab');
      const steps = Array.from(document.querySelectorAll('.step'));
      const lines = document.getElementsByClassName('step-line');

      if (!tabs.length || !steps.length) {
        return;
      }

      mapStepsToTabs();
      defaultTab();
      nextPrevNav();
      stepNav();

      /**
       * Gives each accessible step button the index of the tab it opens.
       *
       * Inaccessible field groups are not rendered as tabs, so button
       * positions and tab indexes differ after the first disabled step.
       */
      function mapStepsToTabs() {
        let tabIndex = 0;
        steps.forEach(function (step) {
          if (isDisabled(step)) {
            step.removeAttribute('data-tab');
            return;
          }
          step.dataset.tab = tabIndex++;
        });

        if (tabIndex !== tabs.length) {
          console.warn('Stepper: ' + tabIndex + ' accessible steps but ' + tabs.length + ' tabs.');
        }
      }

      function isDisabled(step) {
        return step.getAttribute('aria-disabled') === 'true';
      }

      /**
       * Default tab logic.
       */
      function defaultTab() {
        showTab(getCurrentTab());
      }

      /**
       * Provides for next and previous navigation events.
       */
      function nextPrevNav() {
        $(once('prev-click-event', '.btn-prev', context)).on('click', function (e) {
          e.preventDefault();
          nextPrev(-1);
        });

        $(once('next-click-event', '.btn-next', context)).on('click', function (e) {
          e.preventDefault();
          nextPrev(1);
        });
      }

      /**
       * Pager navigation.
       *
       * A <button> fires "click" on Enter and Space as well, so no separate
       * keyboard handler is needed.
       */
      function stepNav() {
        $(once('step-event', '.step', context)).on('click', function (e) {
          e.preventDefault();
          if (isDisabled(this) || this.dataset.tab === undefined) {
            return;
          }
          switchTab(Number(this.dataset.tab));
        });
      }

      /**
       * Update url step parameter.
       */
      function updateStepParam(n) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('step', n);
        history.replaceState(null, '', '?' + urlParams.toString());
      }

      /**
       * Switch tab.
       */
      function switchTab(n) {
        showTab(n);
        scrollTop();
      }

      /**
       * Keeps a tab index within the available tabs.
       */
      function clampTab(n) {
        n = Number(n);
        if (!Number.isInteger(n) || n < 0) {
          return 0;
        }
        return Math.min(n, tabs.length - 1);
      }

      /**
       * Show selected tab.
       */
      function showTab(n) {
        n = clampTab(n);

        Array.from(tabs).forEach(function (tab) {
          tab.style.display = 'none';
        });
        tabs[n].style.display = 'block';

        const lastTab = tabs.length - 1;
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        if (prevBtn) {
          prevBtn.hidden = n === 0;
        }
        if (nextBtn) {
          nextBtn.hidden = n === lastTab;
        }

        updateStepParam(n);
        fixStepIndicator(n);
      }

      /**
       * Scrolls to the active step and moves focus there.
       */
      function scrollTop() {
        const active = document.querySelector('.multistep-nav.active');
        if (active) {
          active.scrollIntoView();
          active.focus();
        }
      }

      /**
       * Get current tab index from url parameter.
       */
      function getCurrentTab() {
        const urlParams = new URLSearchParams(window.location.search);
        return clampTab(urlParams.get('step') ?? 0);
      }

      function nextPrev(n) {
        switchTab(getCurrentTab() + Number(n));
      }

      /**
       * Marks the button for tab n as active.
       */
      function fixStepIndicator(n) {
        let activeIndex = -1;

        steps.forEach(function (step, index) {
          step.classList.remove('active');
          step.removeAttribute('aria-current');
          if (step.dataset.tab !== undefined && Number(step.dataset.tab) === n) {
            activeIndex = index;
          }
        });

        if (activeIndex === -1) {
          return;
        }
        steps[activeIndex].classList.add('active');
        steps[activeIndex].setAttribute('aria-current', 'step');
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
