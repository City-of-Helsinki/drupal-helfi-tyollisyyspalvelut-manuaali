/**
 * @file
 * Opens the description field of an uploaded file in a dialog.
 */
(function ($, Drupal, once) {
  'use strict';

  /**
   * Builds the dialog container around the description field of a file row.
   *
   * The container stays inside the form, so the description is submitted
   * with the rest of the form.
   */
  function buildContainer(button, description) {
    const input = description.querySelector('input, textarea');
    const container = document.createElement('div');
    container.className = 'file-item__dialog';
    container.hidden = true;

    const nameId = input.id + '-file-name';
    const nameItem = document.createElement('div');
    nameItem.className = 'form-item';
    const nameLabel = document.createElement('label');
    nameLabel.className = 'form-item__label';
    nameLabel.htmlFor = nameId;
    nameLabel.textContent = button.dataset.fileNameLabel;
    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.id = nameId;
    nameInput.className = 'form-text';
    nameInput.readOnly = true;
    nameInput.value = button.dataset.fileName;
    nameItem.append(nameLabel, nameInput);

    description.before(container);
    container.append(nameItem, description);
    return {container, input};
  }

  /**
   * Opens the dialog for editing a file description.
   */
  function openDialog(button, container, input) {
    const originalValue = input.value;
    let closedByButton = false;
    let dialog;

    const close = (save) => {
      closedByButton = true;
      if (!save) {
        input.value = originalValue;
      }
      dialog.close();
    };

    const onKeydown = (event) => {
      // Enter would submit the whole node form.
      if (event.key === 'Enter') {
        event.preventDefault();
        close(true);
      }
    };
    input.addEventListener('keydown', onKeydown);
    // The dialog focuses the element with autofocus when it opens.
    input.autofocus = true;
    container.hidden = false;

    dialog = Drupal.dialog(container, {
      title: button.dataset.dialogTitle,
      width: Math.min(600, window.innerWidth - 32),
      appendTo: button.closest('form'),
      classes: {'ui-dialog': 'file-description-dialog'},
      draggable: false,
      resizable: false,
      buttons: [
        {
          text: button.dataset.cancelLabel,
          class: 'button file-description-dialog__cancel',
          click: () => close(false),
        },
        {
          text: button.dataset.saveLabel,
          class: 'button button--primary file-description-dialog__save',
          click: () => close(true),
        },
      ],
      close: () => {
        input.removeEventListener('keydown', onKeydown);
        // Closed with the close button or Escape: discard the changes and let
        // Drupal release the body scroll lock.
        if (!closedByButton) {
          input.value = originalValue;
          dialog.close();
        }
        // Destroying the dialog moves the container back into the file row.
        $(container).dialog('destroy');
        container.hidden = true;
        input.autofocus = false;
        // The row shows the description instead of the file name.
        const link = container.closest('.form-managed-file')?.querySelector('.file a');
        if (link) {
          link.textContent = input.value.trim() || button.dataset.fileName;
        }
      },
    });
    dialog.showModal();
  }

  Drupal.behaviors.helTpmFileDescriptionDialog = {
    attach: function (context) {
      once('file-description-dialog', '.file-item__edit', context).forEach((button) => {
        const description = button.closest('.form-managed-file')?.querySelector('.file-item__description');
        if (!description) {
          return;
        }
        const {container, input} = buildContainer(button, description);
        button.hidden = false;
        button.addEventListener('click', () => openDialog(button, container, input));
      });
    },
  };
})(jQuery, Drupal, once);
