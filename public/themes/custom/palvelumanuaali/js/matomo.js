// Klaro blocks this script until the user has given consent to the "matomo"
// Klaro service, which matches this file by its name "matomo.js".
// eslint-disable-next-line func-names
(function (Drupal) {
  // Klaro does not block scripts on its disabled URLs, so do not track on
  // pages where Klaro is not in use. Also check the consent explicitly in case
  // the script was not blocked for some other reason.
  const manager = Drupal.behaviors.klaro?.manager;
  if (!manager || !manager.getConsent('matomo')) {
    return;
  }

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
})(Drupal);
