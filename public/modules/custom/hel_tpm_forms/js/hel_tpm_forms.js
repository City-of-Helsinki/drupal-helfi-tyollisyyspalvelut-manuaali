(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      var currentTab = 0;
      var urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('step')) {
        currentTab = urlParams.get('step');
      } else {
        currentTab = 0;
      }
      showTab(currentTab); // Display the current tab

      $('#prevBtn').click(function () {
        nextPrev(-1);
      });

      $('#nextBtn').click(function () {
        nextPrev(1);
      });

      $('.step').click(function () {
        let step = $(this).attr('data-step');
        switchTab(step);
        urlParams.set('step', step);
        history.replaceState(null, null, "?"+urlParams.toString());
      });

      /**
       * Fetch required fields for current step.
       *
       * @param n
       */
      function stepRequiredFields(n) {
        let tab = '.tab-' + n;
        let requiredFields = $('input, textarea, select, fieldset', tab).filter('[required]');
        let emptyRequired = [];
        console.log(emptyRequired);
        requiredFields.each(function () {
          console.log($(this).val());
          if ($(this).val().length > 0) {
            console.log('asfd');
            return;
          }
          emptyRequired.push($(this).attr('name'));
        });
        if (emptyRequired.length > 0) {
          $(emptyRequired).each(function() {
            alert(this);
          });
        }
      }

      function switchTab(n) {
        var x = document.getElementsByClassName("tab");
        x[currentTab].style.display = "none";
        currentTab = n;
        showTab(currentTab);
      }

      function showTab(n) {
        hidePrice();
        hideTime();
        hideAgeRange();
        hideConsent();
        stepRequiredFields(n);
        // This function will display the specified tab of the form ...
        var x = document.getElementsByClassName("tab");
        x[n].style.display = "block";
        // ... and fix the Previous/Next buttons:
        if (n === 0) {
          document.getElementById("prevBtn").style.display = "none";
          hidePrice();
        } else if (n === 1){
          document.getElementById("prevBtn").style.display = "inline";
          hideTime();
        } else {
          document.getElementById("prevBtn").style.display = "inline";
        }


        if (n === (x.length)) {
          document.getElementById('nextBtn').hidden = true;
        } else {
          document.getElementById('nextBtn').hidden = false;
          document.getElementById("nextBtnText").innerHTML = "Next";
        }
        // ... and run a function that displays the correct step indicator:
        fixStepIndicator(n)
      }

      function nextPrev(n) {
        // This function will figure out which tab to display
        var x = document.getElementsByClassName("tab");
        // Exit the function if any field in the current tab is invalid:
        // if (n == 1 && !validateForm()) return false;
        // validateForm has been removed for now.
        // Hide the current tab:
        x[currentTab].style.display = "none";
        // Increase or decrease the current tab by 1:
        currentTab = currentTab + n;
        // if you have reached the end of the form... :
        if (currentTab >= x.length) {
          //...the form gets submitted:
          document.getElementById("regForm").submit();
          return false;
        }
        // Otherwise, display the correct tab:
        showTab(currentTab);
      }

      function fixStepIndicator(n) {
        // This function removes the "active" class of all steps...
        var i, x = document.getElementsByClassName("step");
        for (i = 0; i < x.length; i++) {
          x[i].className = x[i].className.replace(" active", "");
        }
        //... and adds the "active" class to the current step:
        x[n].className += " active";
      }

      // hide price elements in the first service entity form page if the checkbox is not checked.
      function hidePrice() {
        let freeServiceCheckbox = '.field--name-field-free-service .form-checkbox';
        let servicePrice = '.field--name-field-service-price .field--name-field-price';
        let servicePriceDescription = '.field--name-field-service-price .field--name-field-description';

        if ($(freeServiceCheckbox).is(":checked") === false) {
          $(servicePrice).hide();
          $(servicePriceDescription).hide();
        }
        else  {
          $(servicePrice).show();
          $(servicePriceDescription).show();

        }

        //handle show/hide logic of service price
        $(freeServiceCheckbox).click(function () {
          if ($(this).is(":checked") === false) {
            $(servicePrice).hide();
            $(servicePriceDescription).hide();
          }
          else {
            $(servicePrice).show();
            $(servicePriceDescription).show();
          }
        });
      }

      // hide time element on the second page of service entity form.
      function hideTime() {
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
          else  {
            $(thisTimeCheckbox).show();
          }
        });
      }


      // hide age range on the first a page of service entity form.
      function hideAgeRange() {
        let ageGroups = '.field--name-field-age-groups .form-item';
        let ageField = '.field--name-field-age';
        $(ageGroups).siblings().each(function () {
          if ($(this).children('.form-radio').val() === "no_age_restriction" && $(this).children('.form-radio').is(":checked") === true ) {
            $(ageField).hide();
          }
          else if (($(this).children('.form-radio').val() !== "no_age_restriction" && $(this).children('.form-radio').is(":checked") === true )) {
            $(ageField).show();
          }
        });
        //handle age accordion
        let ageGroupRadio = '.field--name-field-age-groups .form-item .form-radio';
          $(ageGroupRadio).click(function() {
          if ($(this).val() === "no_age_restriction" && $(this).is(":checked") === true ) {
            $(ageField).hide();
          }
          else if (($(this).val() != "no_age_restriction" && $(this).is(":checked") === true )) {
            $(ageField).show();
          }
        });
      }

      // hide consent description on the third a page of service entity form.
      function hideConsent() {
        let consentCheckbox = '.field--name-field-client-consent .form-item .form-checkbox';
        let fieldDescription = $(consentCheckbox).parent().parent().siblings('.field--name-field-field-client-consent-descr');

        if ($(consentCheckbox).is(":checked") === false) {
          $(fieldDescription).hide();
        }
        else  {
          $(fieldDescription).show();
        }
        //Handle show/hide logic of service consent description
        let consentDescription = '.field--name-field-field-client-consent-descr';

        $(consentCheckbox).click(function () {
          if ($(this).is(":checked") === false) {
            $(this).parent().parent().siblings(consentDescription).hide();
          }
          else  {
            $(this).parent().parent().siblings(consentDescription).show();
          }
        });
      }
    }


  }
})(jQuery, Drupal, drupalSettings);
