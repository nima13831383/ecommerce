(function ($) {
  'use strict';
  if (!$ || !$('.not-found-page').length) return;

  const $form = $('[data-recovery-search]');
  $form.on('submit', function (event) {
    event.preventDefault();
    const query = $.trim($(this).find('input').val());
    const action = $form.attr('action') || '/products';
    window.location.href = action + (query ? '?search=' + encodeURIComponent(query) : '');
  });
}(window.jQuery));
