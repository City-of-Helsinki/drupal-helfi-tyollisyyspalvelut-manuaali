(function ($, Drupal) {
  Drupal.behaviors.custom_module_forms_service_form_pagination = {
    attach: function (context, settings) {
      const tabs = document.getElementsByClassName('tab');
      const steps = Array.from(document.querySelectorAll('.step'));

      if (!tabs.length || !steps.length) {
        return;
      }

      defaultTab();
      nextPrevNav();
      stepNav();

      function isDisabled(step) {
        return step.getAttribute('aria-disabled') === 'true';
      }

      /**
       * A tab can be opened if it exists and its step is not disabled.
       */
      function isTabEnabled(n) {
        return n >= 0 && n < tabs.length && steps[n] && !isDisabled(steps[n]);
      }

      /**
       * Finds the nearest enabled tab from `start` in `direction` (1 or -1).
       *
       * @returns {number} Tab index, or -1 if there is none.
       */
      function findEnabledTab(start, direction) {
        for (let n = start; n >= 0 && n < tabs.length; n += direction) {
          if (isTabEnabled(n)) {
            return n;
          }
        }
        return -1;
      }

      /**
       * Turns any requested index into an enabled tab index.
       */
      function resolveTab(n) {
        n = Number(n);
        if (!Number.isInteger(n)) {
          n = 0;
        }
        n = Math.max(0, Math.min(n, tabs.length - 1));
        if (isTabEnabled(n)) {
          return n;
        }
        const forward = findEnabledTab(n + 1, 1);
        if (forward !== -1) {
          return forward;
        }
        const backward = findEnabledTab(n - 1, -1);
        return backward !== -1 ? backward : 0;
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
        // Bound by id, as the button classes are also used by other buttons.
        $(once('prev-click-event', '#prevBtn', context)).each(function() {
          $(this).click(function() {
            nextPrev(-1);
          })
        });

        $(once('next-click-event', '#nextBtn', context)).each(function() {
          $(this).click(function() {
            nextPrev(1);
          })
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
          if (isDisabled(this)) {
            return;
          }
          switchTab(steps.indexOf(this));
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
       * Show selected tab.
       */
      function showTab(n) {
        n = resolveTab(n);

        Array.from(tabs).forEach(function (tab) {
          tab.style.display = 'none';
        });
        tabs[n].style.display = 'block';

        //const lastTab = tabs.length - 1;
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        if (prevBtn) {
          prevBtn.hidden = findEnabledTab(n - 1, -1) === -1;
        }
        if (nextBtn) {
          nextBtn.hidden = findEnabledTab(n + 1, 1) === -1;
        }

        updateStepParam(n);
        fixStepIndicator(n);
        // Let other scripts react to the step change.
        document.dispatchEvent(new CustomEvent('hel_tpm_forms:step-change', {detail: {step: n}}));
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
        return resolveTab(urlParams.get('step') ?? 0);
      }

      function nextPrev(direction) {
        const target = findEnabledTab(getCurrentTab() + direction, direction);
        if (target !== -1) {
          switchTab(target);
        }
      }

      /**
       * Marks the button for tab n as active.
       */
      function fixStepIndicator(n) {
        steps.forEach(function (step, index) {
          const isActive = index === n;
          step.classList.toggle('active', isActive);
          if (isActive) {
            step.setAttribute('aria-current', 'step');
          }
          else {
            step.removeAttribute('aria-current');
          }
        });
      }
    }
  };
})(jQuery, Drupal);
