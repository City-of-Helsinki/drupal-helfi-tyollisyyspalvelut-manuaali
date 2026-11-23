(function ($, Drupal, once) {
  'use strict';

  let submenuCounter = 0;

  /**
   * Open or close a sub-menu and keep the ARIA state in sync.
   *
   * @param {HTMLElement} button The .expand-sub toggle button.
   * @param {boolean} open Whether the sub-menu should be open.
   */
  function setSubmenuState(button, open) {
    const subMenu = button.nextElementSibling;
    button.classList.toggle('expand-sub--open', open);
    button.setAttribute('aria-expanded', String(open));
    if (subMenu) {
      subMenu.classList.toggle('main-menu--sub-open', open);
    }
  }

  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  // State of the mobile menu focus trap.
  const trap = {
    container: null,
    inerted: [],
    onKeydown: null,
  };

  /**
   * Return the visible, focusable elements inside a container.
   *
   * @param {HTMLElement} container The element to search.
   * @return {HTMLElement[]} Focusable elements in DOM order.
   */
  function getFocusable(container) {
    return Array.from(container.querySelectorAll(FOCUSABLE)).filter(
      (el) => el.getClientRects().length > 0 && !el.closest('[inert]'),
    );
  }

  /**
   * Make everything outside the container inert and keep Tab inside it.
   *
   * @param {HTMLElement} container The open mobile menu panel.
   */
  function activateFocusTrap(container) {
    if (trap.container) {
      return;
    }
    trap.container = container;

    // Inert the siblings of the container and of each of its ancestors, so
    // only the container stays reachable by keyboard and screen readers.
    let node = container;
    while (node && node !== document.body) {
      const parent = node.parentElement;
      if (!parent) {
        break;
      }
      Array.from(parent.children).forEach((sibling) => {
        if (
          sibling !== node &&
          !sibling.hasAttribute('inert') &&
          !['SCRIPT', 'STYLE', 'LINK', 'TEMPLATE'].includes(sibling.tagName)
        ) {
          sibling.setAttribute('inert', '');
          trap.inerted.push(sibling);
        }
      });
      node = parent;
    }

    container.setAttribute('role', 'dialog');
    container.setAttribute('aria-modal', 'true');
    container.setAttribute('aria-label', Drupal.t('Main menu'));

    // Fallback wrapping for browsers without inert support.
    trap.onKeydown = (e) => {
      if (e.key !== 'Tab') {
        return;
      }
      const focusable = getFocusable(container);
      if (!focusable.length) {
        e.preventDefault();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
        e.preventDefault();
        last.focus();
      }
      else if (!e.shiftKey && (document.activeElement === last || !container.contains(document.activeElement))) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', trap.onKeydown);
  }

  /**
   * Undo activateFocusTrap().
   */
  function releaseFocusTrap() {
    if (!trap.container) {
      return;
    }
    trap.inerted.forEach((el) => el.removeAttribute('inert'));
    trap.inerted = [];
    document.removeEventListener('keydown', trap.onKeydown);
    trap.onKeydown = null;
    trap.container.removeAttribute('role');
    trap.container.removeAttribute('aria-modal');
    trap.container.removeAttribute('aria-label');
    trap.container = null;
  }

  Drupal.behaviors.mainMenu = {
    attach: function (context) {
      const toggleExpand = $('.header__inner >.toggle-expand');
      const toggleInnerExpand = $('.header-main-nav .toggle-expand');
      const menu = $('.header-main-nav .main-nav');
      const header = $('.header__primary');
      const additionalMenu = $('.header__primary .additional-links-menu-nav');
      const body = $('body');

      if (!menu.length) {
        return;
      }

      // Sub-menu disclosure buttons.
      once('main-menu-expand', '.expand-sub', menu[0]).forEach((button) => {
        const subMenu = button.nextElementSibling;

        // Link the button to the sub-menu it controls.
        if (subMenu) {
          if (!subMenu.id) {
            submenuCounter += 1;
            subMenu.id = `main-menu-sub-${submenuCounter}`;
          }
          button.setAttribute('aria-controls', subMenu.id);
        }

        // Open sub-menus in the active trail on page load.
        const menuItem = button.closest('.main-menu__item--with-sub');
        setSubmenuState(
          button,
          !!menuItem && menuItem.classList.contains('main-menu__item--active'),
        );

        // A native <button> fires click on Enter and Space too.
        button.addEventListener('click', () => {
          setSubmenuState(
            button,
            button.getAttribute('aria-expanded') !== 'true',
          );
        });

        // Escape closes the sub-menu and returns focus to its button.
        if (subMenu) {
          subMenu.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
              e.stopPropagation();
              setSubmenuState(button, false);
              button.focus();
            }
          });
        }
      });

      const isMenuOpen = () => menu[0].classList.contains('main-nav--open');

      // Mobile layout = the header hamburger button is visible.
      const isMobileLayout = () =>
        !!toggleExpand[0] && toggleExpand[0].getClientRects().length > 0;

      // On mobile the closed panel is only moved off screen with a transform,
      // which leaves its links focusable. Make it inert while closed so
      // keyboard and screen reader users can't reach the hidden items.
      const updatePanelInert = () => {
        if (!header[0]) {
          return;
        }
        if (isMobileLayout() && !isMenuOpen()) {
          header[0].setAttribute('inert', '');
        }
        else {
          header[0].removeAttribute('inert');
        }
      };

      // Keep aria-expanded on both hamburger buttons in sync.
      const syncToggleState = () => {
        const open = String(isMenuOpen());
        [toggleExpand[0], toggleInnerExpand[0]].forEach((toggle) => {
          if (toggle) {
            toggle.setAttribute('aria-expanded', open);
          }
        });
      };

      // Mobile Menu Show.
      const openMenu = () => {
        if (toggleInnerExpand[0]) {
          toggleInnerExpand[0].classList.add('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.add('additional-links-menu-nav--open');
        }
        body[0].classList.add('no-scroll');
        menu[0].classList.add('main-nav--open');
        header[0].classList.add('header__primary-mobile');
        header.removeClass('slide-out');
        header[0].classList.add('slide-in');
        syncToggleState();
        // Make the panel reachable again before moving focus into it.
        updatePanelInert();
        // Trap focus in the panel only on mobile, where the hamburger button
        // is visible and the open menu covers the page.
        if (isMobileLayout()) {
          activateFocusTrap(header[0]);
        }
        // Move focus into the opened menu, onto its close button.
        if (toggleInnerExpand[0]) {
          toggleInnerExpand[0].focus();
        }
      };

      // Mobile Menu Hide.
      // Pass desktop = true when the layout has switched to desktop: the menu
      // is then reset without the slide-out animation (which would hide the
      // desktop nav) and without moving focus to the hidden header button.
      const closeMenu = (desktop = false) => {
        if (toggleInnerExpand[0]) {
          toggleInnerExpand[0].classList.remove('toggle-expand--open');
        }
        if (additionalMenu[0]) {
          additionalMenu[0].classList.remove('additional-links-menu-nav--open');
        }
        menu[0].classList.remove('main-nav--open');
        header[0].classList.remove('slide-in');
        body[0].classList.remove('no-scroll');
        if (desktop) {
          header[0].classList.remove('slide-out', 'header__primary-mobile');
        }
        else {
          header[0].classList.add('slide-out');
          setTimeout(function () {
            header[0].classList.remove('header__primary-mobile');
          }, 1000);
        }
        syncToggleState();
        // Release before refocusing: the header button is inert while trapped.
        releaseFocusTrap();
        // Hide the closed panel from keyboard and screen readers (mobile only).
        updatePanelInert();
        // Return focus to the button that opened the menu.
        if (!desktop && toggleExpand[0]) {
          toggleExpand[0].focus();
        }
      };

      const toggleMenu = () => (isMenuOpen() ? closeMenu() : openMenu());

      if (toggleExpand[0]) {
        once('main-menu-toggle', toggleExpand[0]).forEach((toggle) => {
          toggle.addEventListener('click', toggleMenu);
        });
      }

      if (toggleInnerExpand[0]) {
        once('main-menu-inner-toggle', toggleInnerExpand[0]).forEach((toggle) => {
          toggle.addEventListener('click', toggleMenu);
        });
      }

      // Escape closes the open mobile menu.
      once('main-menu-escape', 'body', context).forEach((el) => {
        el.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && isMenuOpen()) {
            closeMenu();
          }
        });
      });

      // If the layout switches to desktop while the menu is open (browser zoom
      // out, rotation, window resize), close the menu fully so no-scroll,
      // aria-expanded and the focus trap are reset. Focus is left where it is:
      // the header button is hidden, and the panel is now the visible nav.
      once('main-menu-resize', 'body', context).forEach(() => {
        window.addEventListener('resize', () => {
          // Keep the panel's inert state right in both directions
          // (desktop to mobile and mobile to desktop).
          updatePanelInert();
          if (isMobileLayout()) {
            return;
          }
          if (isMenuOpen() || trap.container) {
            closeMenu(true);
          }
          else {
            // Menu was closed on mobile earlier: clear the leftover
            // slide-out state so it can't hide the desktop nav.
            header[0].classList.remove('slide-out');
          }
        });
      });

      syncToggleState();
      updatePanelInert();
    },
  };
})(jQuery, Drupal, once);
