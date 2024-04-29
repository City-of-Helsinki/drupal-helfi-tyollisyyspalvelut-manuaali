// eslint-disable-next-line func-names
(function ($, Drupal) {
  function loadMatomoAnalytics() {
    if (typeof Drupal.eu_cookie_compliance === 'undefined') {
      return;
    }

    // Use tracking also with essential cookie selection as it is done without tracking cookies.
    if (Drupal.eu_cookie_compliance.hasAgreed('essential_cookies') || Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      const _paq = window._paq = window._paq || [];
      _paq.push(["setExcludedQueryParams", ["name", "pass-reset-token", "destination", "autologout_timeout", "step", "check_logged_in", "fbclid", "time", "complianz_scan_token", "complianz_id"]]);
      _paq.push(['disableCookies']);
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      const d = document;
      const g = d.createElement('script');
      const s = d.getElementsByTagName('script')[0];
      _paq.push(['setTrackerUrl', '//webanalytics.digiaiiris.com/js/tracker.php']);
      _paq.push(['setSiteId', '604']);
      g.type = 'text/javascript';
      g.async = true;
      g.src = '//webanalytics.digiaiiris.com/js/piwik.min.js';
      s.parentNode.insertBefore(g, s);
    }
  }

  // Load when cookie settings are changed.
  $(document).on('eu_cookie_compliance.changeStatus', loadMatomoAnalytics());

  // Load on page load.
  $(document).ready(loadMatomoAnalytics);
})(jQuery, Drupal);
