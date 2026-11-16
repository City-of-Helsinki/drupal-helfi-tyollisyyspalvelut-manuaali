/**
 * @file
 * Keeps the country code out of telephone number inputs.
 *
 * The country code (e.g. +358) has its own input in front of the number. A
 * number typed or pasted as "040 123 4567" or "+358 40 123 4567" becomes
 * "40 123 4567". A different country code followed by a space, e.g.
 * "+46 8 123 456", is moved to the country code input. The same is done on the
 * server, see PhoneNumberHandler::splitNumber().
 */
((Drupal, once) => {
  /**
   * Escapes a string for use in a regular expression.
   *
   * @param {string} string
   *   The string.
   *
   * @return {string}
   *   The escaped string.
   */
  const escapeRegExp = (string) =>
    string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  Drupal.behaviors.helTpmTelephoneCountryCode = {
    attach(context) {
      once(
        'hel-tpm-telephone-country-code',
        'input[data-telephone-country-code="number"]',
        context,
      ).forEach((input) => {
        // The country code input of the same item:
        // "...[0][value]" -> "...[0][country_code]".
        const codeInput = input.form?.querySelector(
          `input[name="${CSS.escape(
            input.name.replace(/\[value\]$/, '[country_code]'),
          )}"]`,
        );
        if (!codeInput) {
          return;
        }

        const crop = () => {
          let { value } = input;
          const original = value;

          // Another country code followed by a space or a dash.
          const other = value.match(/^\s*(?:\+|00)([0-9]{1,4})[\s-]+/);
          if (other) {
            codeInput.value = `+${other[1]}`;
            value = value.slice(other[0].length);
          } else {
            // The current country code, with or without a space.
            const digits = escapeRegExp(codeInput.value.replace(/^\+/, ''));
            if (digits) {
              value = value.replace(
                new RegExp(`^\\s*(?:\\+|00)${digits}\\s*`),
                '',
              );
            }
          }
          // The trunk prefix: "(0)" or a leading zero.
          value = value.replace(/^\s*(?:\(0\)|0)\s*/, '');

          if (value === original) {
            return;
          }
          const removed = original.length - value.length;
          const caret = input.selectionStart;
          input.value = value;
          // Keep the caret where it was relative to the remaining text.
          if (caret !== null && document.activeElement === input) {
            const position = Math.max(0, caret - removed);
            input.setSelectionRange(position, position);
          }
        };

        input.addEventListener('input', crop);
        crop();
      });
    },
  };
})(Drupal, once);
