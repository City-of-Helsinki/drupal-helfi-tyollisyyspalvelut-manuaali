(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      addError();
      toggleAgeRange();
      handleSelectedStatement();
      handleSelectedObligatoryness();
      handleFocus();

      // hide age range on the first a page of service entity form.
      function toggleAgeRange() {
        let ageGroupRadio = '.field--name-field-age-groups .form-item .form-checkbox';
        toggleAgeField(ageGroupRadio);

        //handle age accordion
        $(ageGroupRadio).click(function() {
          toggleAgeField(this);
        });
      }


        let addParagraphClicked = false;
            $(document).on('click', '.paragraphs-dropbutton-wrapper input', function () {
              addParagraphClicked = true;
            });

            $(document).ajaxComplete( function () {

              if (!addParagraphClicked) {
                return;
              }

              addParagraphClicked = false;
              setTimeout(function () {
              const $newParagraph = $('.field-service-time-and-location-values  > tbody').children('.table__row').last();
              console.log($newParagraph);
              if ($newParagraph.length) {
                $('html, body').animate({
                  scrollTop: $newParagraph.offset().top - 80
                }, 600);
              }
              }, 3000);
            });

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
})(jQuery, Drupal, drupalSettings);
