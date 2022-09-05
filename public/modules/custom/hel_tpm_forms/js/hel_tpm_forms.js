(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.hel_tpm_forms = {
    attach: function (context, settings) {
      var currentTab = 0;
      var urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('step')) {
        var stepValue = urlParams.get('step');
        stepValue = stepValue-1;
        currentTab = stepValue;
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

      $('#step-1').click(function () {
          switchTab(0);
          urlParams.set('step', '1');
          history.replaceState(null, null, "?"+urlParams.toString());
      });

      $('#step-2').click(function () {
          switchTab(1);
          urlParams.set('step', '2');
          history.replaceState(null, null, "?"+urlParams.toString());
      });

      $('#step-3').click(function () {
          switchTab(2);
          urlParams.set('step', '3');
          history.replaceState(null, null, "?"+urlParams.toString());

      });

      $('#step-4').click(function () {
          switchTab(3);
          urlParams.set('step', '4');
          history.replaceState(null, null, "?"+urlParams.toString());

      });

      function switchTab(n) {
        var x = document.getElementsByClassName("tab");
        x[currentTab].style.display = "none";
        currentTab = n;
        showTab(currentTab);
      }

      function showTab(n) {
        // This function will display the specified tab of the form ...
        var x = document.getElementsByClassName("tab");
        x[n].style.display = "block";
        // ... and fix the Previous/Next buttons:
        if (n == 0) {
          document.getElementById("prevBtn").style.display = "none";
        } else {
          document.getElementById("prevBtn").style.display = "inline";
        }
        if (n == (x.length - 1)) {
          document.getElementById("nextBtn").innerHTML = "Submit";
        } else {
          document.getElementById("nextBtn").innerHTML = "Next";
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


    }
  }
})(jQuery, Drupal, drupalSettings);
