(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      addError();
      toggleAgeRange();
      handleSelectedStatement();
      handleSelectedObligatoryness();

      // hide age range on the first a page of service entity form.
      function toggleAgeRange() {
        let ageGroupRadio = '.field--name-field-age-groups .form-item .form-checkbox';
        toggleAgeField(ageGroupRadio);

        //handle age accordion
        $(ageGroupRadio).click(function() {
          toggleAgeField(this);
        });
      }

      /**
       * Toggle field age element.
       *
       * @param elem
       */
      function toggleAgeField(elem) {
        let ageField = '.field--name-field-age';
        if ($(elem).is(':checked')) {
          $(ageField).hide();
        }
        else {
          $(ageField).show();  return ajax.keypressResponse(this, event);

        }
      }

      function addError() {
        let x = $(".tab.field-group-html-element");
        x.each(function(index) {
          if ($(this).find('.error').length !== 0) {
            let errorStep ='.nav-step-' + index;
            $(errorStep).addClass('highlight-error');
          }
        });
      }


      // handle checkbox select color changed
      // input selected -> parent gets "selected" class
      // when unselected -> "selected" class removed
      function handleSelectedStatement() {
        let statementRadio = '.field--name-field-statements .form-item--radio-button .form-radio';
        let statementItem = $(statementRadio).parent();

        if ($(statementRadio).is(":checked") === true) {
          $(statementItem).addClass('selected');
        }

        $(statementRadio).parent().click(function () {
          if ($(this).children('.form-radio').is(":checked") === true) {
            $(this).addClass('selected');
            $(this).siblings('.form-item--radio-button').removeClass('selected');
          }
        });
      }

      // handle checkbox select color changed
      // input selected -> parent gets "selected" class
      // when unselected -> "selected" class removed
      function handleSelectedObligatoryness() {
        let obligatorynessRadio = '.field--name-field-obligatoryness .form-item--radio-button .form-radio';
        let obligatorynessItem = $(obligatorynessRadio).parent();

        if ($(obligatorynessRadio).is(":checked") === true) {
          $(obligatorynessItem).addClass('selected');
        }

        $(obligatorynessRadio).parent().click(function () {
          if ($(this).children('.form-radio').is(":checked") === true) {
            $(this).addClass('selected');
            $(this).siblings('.form-item--radio-button').removeClass('selected');
          }
        });
      }

      // hide age range on the first a page of service entity form.
      function handleFocus() {

      }

    }
  }

  let addParagraphClicked = false;

    Drupal.behaviors.serviceTimeParagraphScroll = {
      attach(context) {

        once(
          'service-time-add-button',
          '.field--widget-hel-tpm-service-dates-service-time-and-place-widget .paragraphs-dropbutton-wrapper input',
          context
        ).forEach(function (button) {

          $(button).on('click', function () {
            addParagraphClicked = true;
          });

        });

        $(document).ajaxComplete(function () {



          addParagraphClicked = false;

          setTimeout(function () {

            const $newRow = $('.field-service-time-and-location-values > tbody > .table__row').last();

            if ($newRow.length) {
              $newRow[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
              });
            }

          }, 5000);

        });

      }
    };


})(jQuery, Drupal, drupalSettings, once );
