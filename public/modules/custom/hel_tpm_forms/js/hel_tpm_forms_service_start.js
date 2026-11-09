(function (Drupal, drupalSettings, once) {
  /**
   * Gets the code of the selected language on the start page.
   *
   * @param {HTMLElement} startPage
   *   The start page element.
   *
   * @return {string|null}
   *   The selected language code or null if not found.
   */
  function getSelectedLangcode(startPage) {
    const input = startPage.querySelector('select[name="langcode[0][value]"], input[name="langcode[0][value]"]:checked');
    return input ? input.value : null;
  }

  /**
   * Leaves the form page without the browser's unsaved changes prompt.
   *
   * The loss of entered information is confirmed with confirmLeave() instead.
   *
   * @param {string} url
   *   The URL to go to.
   */
  function leavePage(url) {
    // Set by the node_edit_protection module.
    window.onbeforeunload = null;
    window.location.href = url;
  }

  /**
   * Confirms leaving the form with a dialog like other service form dialogs.
   *
   * @param {string} message
   *   The confirmation message.
   * @param {function} onApprove
   *   Called when the user approves.
   */
  function confirmLeave(message, onApprove) {
    const content = document.createElement('div');
    content.className = 'desc';
    content.textContent = message;
    const dialog = Drupal.dialog(content, {
      dialogClass: 'confirm-dialog',
      resizable: true,
      closeOnEscape: true,
      width: 600,
      buttons: [
        {
          text: Drupal.t('Cancel'),
          click: function () {
            dialog.close();
          }
        },
        {
          text: Drupal.t('Approve'),
          class: 'button--primary button',
          click: function () {
            dialog.close();
            onApprove();
          }
        }
      ]
    });
    dialog.showModal();
  }

  /**
   * Marks in the URL whether the language has been selected.
   *
   * When the form is loaded again, e.g. by refreshing the page, the start page
   * is skipped only if the language has been selected.
   *
   * @param {boolean} selected
   *   Whether the language has been selected.
   */
  function setLanguageSelected(selected) {
    const settings = drupalSettings.hel_tpm_forms && drupalSettings.hel_tpm_forms.service_start;
    if (!settings) {
      return;
    }
    const url = new URL(window.location.href);
    if (selected) {
      url.searchParams.set(settings.languageSelectedParameter, '1');
    }
    else {
      url.searchParams.delete(settings.languageSelectedParameter);
    }
    history.replaceState(history.state, '', url);
  }

  /**
   * Gets the current step of the form from the URL.
   *
   * @return {number}
   *   The current step.
   */
  function getCurrentStep() {
    return Number(new URLSearchParams(window.location.search).get('step') || 0);
  }

  /**
   * Start page of the service creation form.
   *
   * While the form has the "service-start-active" class, only the start page
   * is shown (see hel_tpm_forms_service_start.css). The class is already set
   * by the server when the form is opened on the start page.
   *
   * The "Start" button continues to the first step of the form. If a language
   * other than the language of the page is selected, the form is loaded again
   * in the selected language instead. The start page can be opened again with
   * the back button of the first step.
   *
   * @see \Drupal\hel_tpm_forms\Hook\ServiceStartPageHooks
   */
  Drupal.behaviors.hel_tpm_forms_service_start = {
    attach: function (context) {
      once('service-start', 'form[data-service-start]', context).forEach(function (form) {
        const startPage = form.querySelector('.service-start');
        const startButton = startPage ? startPage.querySelector('.service-start__start') : null;
        if (!startButton) {
          return;
        }
        const backButton = form.querySelector('.btn-start-page');
        // Marks the place of the start page fields in the form while the start
        // page is shown at the top of the form.
        const startPagePlaceholder = document.createComment('service-start');
        // Whether the start page was opened again from the form.
        let returned = false;

        /**
         * Shows the start page alone at the top of the form.
         */
        function openStartPage() {
          startPage.before(startPagePlaceholder);
          form.prepend(startPage);
          startPage.classList.remove('service-start-hidden');
          form.classList.add('service-start-active');
          setLanguageSelected(false);
        }

        /**
         * Hides the start page and shows the rest of the form.
         */
        function closeStartPage() {
          form.classList.remove('service-start-active');
          // The start page fields stay in the form so that their values are
          // submitted.
          startPagePlaceholder.replaceWith(startPage);
          startPage.classList.add('service-start-hidden');
        }

        if (form.dataset.serviceStart === 'start') {
          openStartPage();
        }

        startButton.addEventListener('click', function () {
          const settings = drupalSettings.hel_tpm_forms && drupalSettings.hel_tpm_forms.service_start;
          const langcode = getSelectedLangcode(startPage);
          if (settings && langcode && langcode !== settings.currentLanguage && settings.languageUrls[langcode]) {
            const url = settings.languageUrls[langcode];
            // Changing the language loads the form again. Information already
            // entered to the form is lost, so confirm it from the user.
            if (returned) {
              confirmLeave(Drupal.t('Changing the language loads the form again and the information you have entered will be lost. Do you want to continue?'), function () {
                leavePage(url);
              });
            }
            else {
              leavePage(url);
            }
            return;
          }

          // The selected language is the language of the page, which is
          // already shown in the read-only language information.
          closeStartPage();
          setLanguageSelected(true);

          // Open the first step using the existing step navigation. This also
          // moves the focus to the step indicator.
          const firstStep = form.querySelector('.multistep-nav[data-step="0"]');
          if (firstStep) {
            firstStep.click();
          }
        });

        const exitLink = startPage.querySelector('.service-start__exit');
        if (exitLink) {
          exitLink.addEventListener('click', function (event) {
            event.preventDefault();
            const url = exitLink.getAttribute('href');
            if (returned) {
              confirmLeave(Drupal.t('The information you have entered will be lost when you leave the form. Do you want to continue?'), function () {
                leavePage(url);
              });
            }
            else {
              leavePage(url);
            }
          });
        }

        if (!backButton) {
          return;
        }
        // The back button is shown only on the first step.
        backButton.hidden = getCurrentStep() !== 0;
        document.addEventListener('hel_tpm_forms:step-change', function (event) {
          backButton.hidden = event.detail.step !== 0;
        });
        backButton.addEventListener('click', function () {
          returned = true;
          openStartPage();
          window.scrollTo(0, 0);
          // Move the focus to the selected option of the start page, or to its
          // first input.
          const focusTarget = startPage.querySelector('input[type="radio"]:checked')
            || startPage.querySelector('select, input:not([type="hidden"])');
          if (focusTarget) {
            focusTarget.focus();
          }
        });
      });
    }
  };
})(Drupal, drupalSettings, once);
