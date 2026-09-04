(function () {
  'use strict';
  const list = document.querySelector('[data-orders-list]');
  if (!list) return;
  const cards = [...list.querySelectorAll('[data-order-card]')];
  const filterButtons = [...document.querySelectorAll('[data-order-filter]')];
  const search = document.querySelector('[data-order-search]');
  let status = 'all';
  function render() {
    const query = (search?.value || '').trim().toLowerCase();
    cards.forEach((card) => {
      const matchesStatus = status === 'all' || card.dataset.status === status;
      const matchesQuery = !query || card.dataset.order.toLowerCase().includes(query);
      card.hidden = !(matchesStatus && matchesQuery);
    });
  }
  filterButtons.forEach((button) => button.addEventListener('click', function () { status = button.dataset.orderFilter; filterButtons.forEach((item) => item.classList.toggle('is-active', item === button)); render(); }));
  search?.addEventListener('input', render);
}());
