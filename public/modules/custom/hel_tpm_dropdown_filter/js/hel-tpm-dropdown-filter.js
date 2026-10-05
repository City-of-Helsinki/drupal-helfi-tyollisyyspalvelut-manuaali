/**
 * @file
 * Enhances the dropdown filters rendered by the DropdownFilter widget.
 *
 * The dropdowns are native details elements with checkboxes. This closes the
 * dropdown after each selection, so that further selections can't be made
 * while the auto-submit AJAX request is running, and handles closing on
 * outside click, single selection and selecting whole option groups.
 */
(function (Drupal, once) {
  'use strict';

  const openClass = 'multi-select-container--open';

  // The filter changed last, so focus can be moved back to it after the AJAX
  // request has replaced the form.
  let lastChangedFilter = null;

  function optionCheckboxes(element) {
    return Array.from(element.querySelectorAll('input[type="checkbox"]:not([data-dropdown-filter-group])'));
  }

  function updateGroups(filter) {
    filter.querySelectorAll('.select-group').forEach((group) => {
      const checkboxes = optionCheckboxes(group);
      const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
      const toggle = group.querySelector('[data-dropdown-filter-group]');
      toggle.checked = checkedCount === checkboxes.length;
      toggle.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    });
  }

  function updateSummary(filter) {
    const details = filter.querySelector('[data-dropdown-filter-details]');
    const summary = details.querySelector('summary');
    const selected = optionCheckboxes(filter)
      .filter((checkbox) => checkbox.checked)
      .map((checkbox) => checkbox.closest('label').textContent.trim());

    summary.textContent = '';
    if (selected.length) {
      const first = document.createElement('span');
      first.className = 'trim-label';
      first.textContent = selected[0];
      summary.append(first);
      if (selected.length > 1) {
        summary.append(' +' + (selected.length - 1));
      }
    }
    else {
      summary.textContent = summary.dataset.placeholder;
    }
    details.classList.toggle('active', selected.length > 0);
  }

  function close(details) {
    details.open = false;
  }

  function init(filter) {
    const details = filter.querySelector('[data-dropdown-filter-details]');
    const summary = details.querySelector('summary');
    const single = filter.hasAttribute('data-dropdown-filter-single');

    updateGroups(filter);

    details.addEventListener('toggle', () => {
      details.classList.toggle(openClass, details.open);
      if (details.open) {
        document.querySelectorAll('[data-dropdown-filter-details][open]').forEach((other) => {
          if (other !== details) {
            close(other);
          }
        });
      }
    });

    // Clicking the filter label opens the dropdown, like a select label does.
    filter.querySelector('.form-item__label').addEventListener('click', () => {
      details.open = !details.open;
    });

    filter.addEventListener('change', (event) => {
      const checkbox = event.target;

      if (checkbox.hasAttribute('data-dropdown-filter-group')) {
        const groupCheckboxes = optionCheckboxes(checkbox.closest('.select-group'));
        groupCheckboxes.forEach((option) => {
          option.checked = checkbox.checked;
        });
        // Let the form auto-submit once for the whole group.
        groupCheckboxes[0].dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }

      if (single && checkbox.checked) {
        optionCheckboxes(filter).forEach((option) => {
          option.checked = option === checkbox;
        });
      }

      updateGroups(filter);
      updateSummary(filter);
      close(details);
      summary.focus();
      lastChangedFilter = filter.dataset.dropdownFilter;
    });

    filter.addEventListener('keydown', (event) => {
      // Checkboxes only toggle with Space natively, also allow Enter.
      if (event.key === 'Enter' && event.target.type === 'checkbox') {
        event.preventDefault();
        event.target.click();
      }
      else if (event.key === 'Escape' && details.open) {
        close(details);
        summary.focus();
      }
    });

    filter.addEventListener('focusout', (event) => {
      if (event.relatedTarget && !details.contains(event.relatedTarget)) {
        close(details);
      }
    });

    if (lastChangedFilter === filter.dataset.dropdownFilter) {
      lastChangedFilter = null;
      summary.focus();
    }
  }

  // Close open dropdowns when clicking outside of them.
  document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-dropdown-filter-details][open]').forEach((details) => {
      if (!details.closest('[data-dropdown-filter]').contains(event.target)) {
        close(details);
      }
    });
  });

  Drupal.behaviors.helTpmDropdownFilter = {
    attach(context) {
      once('hel-tpm-dropdown-filter', '[data-dropdown-filter]', context).forEach(init);
    },
  };
})(Drupal, once);
