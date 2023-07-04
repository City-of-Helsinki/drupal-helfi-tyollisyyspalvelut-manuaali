(function ($, Drupal) {

  Drupal.behaviors.serviceConfirmPopup = {
    attach: function (context, settings) {

      // call to function to open popup
      $("#edit-submit").click(function (e) {
        e.preventDefault();
        let message = confirmMessage(context);
        if ($('#edit-title-0-value').val() != '') {
          title = $('#edit-title-0-value').val();
        }
        confirmPopup(title);
      });

      function confirmMessage(context) {
        console.log(context);
      }

      function confirmPopup(title = "") {
        var content = '<div>Are you sure you want save <b>' + title + '</b>?</div>';
        confirmationDialog = Drupal.dialog(content, {
          dialogClass: 'confirm-dialog',
          resizable: true,
          closeOnEscape: false,
          width: 600,
          title: "Saving Confirmation",
          buttons: [
            {
              text: 'Confirm',
              class: 'button--primary button',
              click: function () {
                $('#custom-form-submit-after-check').click();
              }
            },
            {
              text: 'Close',
              click: function () {
                $(this).dialog('close');
              }
            }
          ],
        });
        confirmationDialog.showModal();
      }
    }
  }
})(jQuery, Drupal);
