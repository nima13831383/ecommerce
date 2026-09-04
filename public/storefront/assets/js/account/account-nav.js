(function () {
  'use strict';
  const toggle = document.querySelector('[data-account-nav-toggle]');
  const menu = document.querySelector('[data-account-nav-menu]');
  if (!toggle || !menu) return;
  toggle.addEventListener('click', function () {
    const open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-account-mobile-nav]')) {
      menu.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
}());
