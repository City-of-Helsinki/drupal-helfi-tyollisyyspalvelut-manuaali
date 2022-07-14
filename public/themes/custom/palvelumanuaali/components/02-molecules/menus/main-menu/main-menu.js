Drupal.behaviors.mainMenu = {
  attach(context) {

      let toggleExpand = document.getElementById('toggle-expand');


      let menu = document.getElementById('main-nav');

      let additionalMenu = document.getElementById('additional-links-menu-nav');



    if (typeof menu !== 'undefined' || menu == null) {
      const expandMenu = menu.getElementsByClassName('expand-sub');

      // Mobile Menu Show/Hide.
      toggleExpand.addEventListener('click', (e) => {
        if (toggleExpand) {
        toggleExpand.classList.toggle('toggle-expand--open');
        }
        if (additionalMenu) {
          additionalMenu.classList.toggle('additional-links-menu-nav--open');
        }
        menu.classList.toggle('main-nav--open');
        e.preventDefault();
      });

      // Expose mobile sub menu on click.
      Array.from(expandMenu).forEach((item) => {
        item.addEventListener('click', (e) => {
          const menuItem = e.currentTarget;
          const subMenu = menuItem.nextElementSibling;
          if (menuItem) {
              menuItem.classList.toggle('expand-sub--open');
          }
          if (subMenu) {
            subMenu.classList.toggle('main-menu--sub-open');
          }
        });
      });
    }
  },
};
