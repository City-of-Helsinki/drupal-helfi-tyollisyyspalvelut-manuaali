(function ($, Drupal, once) {
  Drupal.behaviors.serviceFormPagination = {
    attach: function (context, settings) {
      const tabs = Array.from(document.getElementsByClassName('tab'));
      const steps = Array.from(document.querySelectorAll('.step'));

      if (!tabs.length || !steps.length) {
        return;
      }

      // stepTabs[i] is the .tab element for button i, or null if it has none.
      const stepTabs = mapStepsToTabs();

      defaultStep();
      nextPrevNav();
      stepNav();

      function isDisabled(step) {
        return step.getAttribute('aria-disabled') === 'true';
      }

      /**
       * Links each step button to its tab.
       *
       * Uses aria-controls (the group's HTML id) when available, otherwise
       * document order. Disabled groups normally render no tab, so they are
       * skipped in the order unless every step has a tab.
       */
      function mapStepsToTabs() {
        const allTabsRendered = tabs.length === steps.length;
        let next = 0;

        const map = steps.map(function (step) {
          const id = step.getAttribute('aria-controls');
          const byId = id ? document.getElementById(id) : null;
          if (byId && tabs.includes(byId)) {
            next = tabs.indexOf(byId) + 1;
            return byId;
          }
          if (isDisabled(step) && !allTabsRendered) {
            return null;
          }
          return tabs[next++] || null;
        });

        // A step without a tab can't be opened: disable it.
        map.forEach(function (tab, index) {
          if (!tab && !isDisabled(steps[index])) {
            steps[index].setAttribute('aria-disabled', 'true');
            steps[index].classList.add('is-disabled');
            console.warn('Stepper: no tab found for step ' + (index + 1) + '.');
          }
          if (tab) {
            tab.dataset.stepIndex = index;
          }
        });

        return map;
      }

      function isStepEnabled(n) {
        return n >= 0 && n < steps.length && !isDisabled(steps[n]) && stepTabs[n] !== null;
      }

      /**
       * Finds the nearest enabled step from `start` in `direction` (1 or -1).
       *
       * @returns {number} Step index, or -1 if there is none.
       */
      function findEnabledStep(start, direction) {
        for (let n = start; n >= 0 && n < steps.length; n += direction) {
          if (isStepEnabled(n)) {
            return n;
          }
        }
        return -1;
      }

      /**
       * Turns any requested index into an enabled step index (-1 if none).
       */
      function resolveStep(n) {
        n = Number(n);
        if (!Number.isInteger(n)) {
          n = 0;
        }
        n = Math.max(0, Math.min(n, steps.length - 1));
        if (isStepEnabled(n)) {
          return n;
        }
        const forward = findEnabledStep(n + 1, 1);
        return forward !== -1 ? forward : findEnabledStep(n - 1, -1);
      }

      function defaultStep() {
        showStep(getCurrentStep());
      }

      /**
       * Bound by id, as the button classes are also used by other buttons.
       */
      function nextPrevNav() {
        $(once('prev-click-event', '#prevBtn', context)).on('click', function (e) {
          e.preventDefault();
          nextPrev(-1);
        });

        $(once('next-click-event', '#nextBtn', context)).on('click', function (e) {
          e.preventDefault();
          nextPrev(1);
        });
      }

      /**
       * A <button> fires "click" on Enter and Space as well.
       */
      function stepNav() {
        $(once('step-event', '.step', context)).on('click', function (e) {
          e.preventDefault();
          const n = steps.indexOf(this);
          if (!isStepEnabled(n)) {
            return;
          }
          switchStep(n);
        });
      }

      function updateStepParam(n) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('step', n);
        history.replaceState(null, '', '?' + urlParams.toString());
      }

      function switchStep(n) {
        showStep(n);
        scrollTop();
      }

      function showStep(n) {
        n = resolveStep(n);

        tabs.forEach(function (tab) {
          tab.style.display = 'none';
        });

        // No enabled step at all: show the first tab as a fallback.
        if (n === -1) {
          tabs[0].style.display = 'block';
          return;
        }

        stepTabs[n].style.display = 'block';

        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        if (prevBtn) {
          prevBtn.hidden = findEnabledStep(n - 1, -1) === -1;
        }
        if (nextBtn) {
          nextBtn.hidden = findEnabledStep(n + 1, 1) === -1;
        }

        updateStepParam(n);
        fixStepIndicator(n);
        // Let other scripts react to the step change.
        document.dispatchEvent(new CustomEvent('hel_tpm_forms:step-change', {detail: {step: n}}));
      }

      function scrollTop() {
        const active = document.querySelector('.multistep-nav.active');
        if (active) {
          active.scrollIntoView();
          active.focus();
        }
      }

      function getCurrentStep() {
        const urlParams = new URLSearchParams(window.location.search);
        return resolveStep(urlParams.get('step') ?? 0);
      }

      function nextPrev(direction) {
        const target = findEnabledStep(getCurrentStep() + direction, direction);
        if (target !== -1) {
          switchStep(target);
        }
      }

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
})(jQuery, Drupal, once);
