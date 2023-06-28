(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      addError();
      togglePrice();
      toggleTime();
      toggleAgeRange();
      handleSelectedStatement();
      handleSelectedObligatoryness();

      addMoreSubmitBackgroud();

      /**
       * Add custom background position css for add more buttons.
       */
      function addMoreSubmitBackgroud() {
        let widgets = [
          '.field--widget-hel-tpm-editorial-paragraphs-custom',
          '.field--widget-hel-tpm-service-dates-service-time-and-place-widget'
        ];
        $(widgets).each(function() {
          if($(this + ' .field-add-more-submit').length){
            let backgroundPos = $(this + ' .field-add-more-submit').val().length;
            backgroundPos = backgroundPos/2;
            backgroundPos = backgroundPos.toString();
            backgroundPos = backgroundPos + "ch";
            backgroundPos = "calc(50% + " + backgroundPos + " - 0.5rem)";
            $(this + ' .field-add-more-submit').css('background-position-x',backgroundPos);
          }
        })
      }

      /**
       * Toggle price fields.
       */
      function togglePrice() {
        let freeServiceCheckbox = '.field--name-field-free-service .form-checkbox';
        togglePriceElements(freeServiceCheckbox);

        //handle show/hide logic of service price
        $(freeServiceCheckbox).once().click(function () {
          togglePriceElements(this);
        });
      }

      /**
       * Toggle price elements.
       */
      function togglePriceElements(priceElement) {
        let servicePrice = '.field--name-field-service-price .field--name-field-price';
        let servicePriceDescription = '.field--name-field-service-price .field--name-field-description';
        if ($(priceElement).is(":checked") === false) {
          $(servicePrice).hide();
          $(servicePriceDescription).hide();
        }
        else {
          $(servicePrice).show();
          $(servicePriceDescription).show();
        }
      }

      // hide age range on the first a page of service entity form.
      function toggleAgeRange() {
        let ageGroupRadio = '.field--name-field-age-groups .form-item .form-checkbox';
        toggleAgeField(ageGroupRadio)

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


      // hide time element on the second page of service entity form.
      function toggleTime() {
        let separateTimeCheckbox = '.field--name-field-separate-time .form-checkbox';
        let hideableTimes = '.event-times';
        $(separateTimeCheckbox).each(function() {
          if ($(this).is(":checked")) {
            $(this).parent().parent().siblings(hideableTimes).hide();
          }
        });
        //handle show/hide logic of service time
        $(separateTimeCheckbox).click(function () {
          let thisTimeCheckbox = $(this).parent().parent().siblings(hideableTimes);
          if ($(this).is(":checked") === true) {
            $(thisTimeCheckbox).hide();
          }
          else {
            $(thisTimeCheckbox).show();
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
})(jQuery, Drupal, drupalSettings);
