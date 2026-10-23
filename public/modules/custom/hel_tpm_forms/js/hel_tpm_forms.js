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
          $(ageField).show();
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

        $(document).ajaxComplete(function (event, xhr, settings) {
          // Only react to a specific AJAX callback.
          if (!settings.extraData['_triggering_element_name']){
            return;
          }
          if (!settings.extraData['_triggering_element_name'].includes('field_service_time_and_location_service_time_and_place_add_more')){
            return;
          }
          setTimeout(function () {
            const $newRow = $('.field-service-time-and-location-values > tbody > .table__row').last();
            const $ajaxAdded = $newRow.find('.ajax-new-content');

            if ($ajaxAdded.length) {
              const $firstField = $newRow.find('.form-text').first();

              $firstField[0].focus({ preventScroll: true });
              const elementTop = $firstField.offset().top;
              const viewportHeight = window.innerHeight;
              const elementHeight = $firstField.outerHeight();

              window.scrollTo({
                top: elementTop - (viewportHeight / 2) + (elementHeight / 2),
                behavior: 'smooth'
              });
            }
          }, 1000);

        });

      }
    };


})(jQuery, Drupal, drupalSettings, once );
