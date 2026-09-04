(function () {
  'use strict';
  const grid = document.querySelector('[data-wishlist-grid]');
  if (!grid) return;
  const count = document.querySelector('[data-wishlist-count]');
  const empty = document.querySelector('[data-wishlist-empty]');
  grid.addEventListener('click', function (event) {
    const button = event.target.closest('[data-wishlist-remove]');
    if (!button) return;
    const item = button.closest('[data-wishlist-item]');
    if (!item) return;
    item.classList.add('is-removing');
    window.setTimeout(() => { item.remove(); const total = grid.querySelectorAll('[data-wishlist-item]').length; if (count) count.textContent = String(total); if (!total && empty) empty.hidden = false; }, 210);
  });
}());
