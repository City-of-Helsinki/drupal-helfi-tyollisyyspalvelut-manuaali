(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      var currentTab = 0;
      var urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('step')) {
        currentTab = urlParams.get('step');
        currentTab = Number(currentTab);
      } else {
        currentTab = 0;
      }
      showTab(currentTab); // Display the current tab
      addError();
      handleSelectedStatement();
      handleSelectedObligatoryness();

      $('.btn-prev').click(function () {
        nextPrev(-1);
      });

      $('.btn-next').click(function () {
        nextPrev(1);
      });

      $('.step').click(function () {
        let step = $(this).attr('data-step');
        switchTab(step);
        urlParams.set('step', step);
        history.replaceState(null, null, "?"+urlParams.toString());
      });


      function addError() {
        let x = $(".tab.field-group-html-element");
        x.each(function(index) {
          if ($(this).find('.error').length !== 0) {
            let errorStep ='.nav-step-' + index;
            $(errorStep).addClass('highlight-error');
          }
        });
      }

      function switchTab(n) {
        var x = document.getElementsByClassName("tab");
        x[currentTab].style.display = "none";

        currentTab = Number(n);

        showTab(currentTab);
      }

      function showTab(n) {
        n = Number(n);
        hidePrice();
        hideTime();
        hideAgeRange();
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

        if (n === (x.length-1)) {
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
        currentTab = Number(currentTab);
        n = Number(n);
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
