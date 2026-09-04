(function ($) {
  'use strict';
  if (!$ || !$('.not-found-page').length) return;

  const $form = $('[data-recovery-search]');
  $form.on('submit', function (event) {
    event.preventDefault();
    const query = $.trim($(this).find('input').val());
    window.location.href = 'search.html' + (query ? '?q=' + encodeURIComponent(query) : '');
  });
}(window.jQuery));
