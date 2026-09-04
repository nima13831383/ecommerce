(function ($) {
  'use strict';
  if (!$ || !$('.category-page').length) return;
  const $form = $('.category-filters').first();
  const $main = $('.category-main');
  let request = null;
  let sequence = 0;
  function refresh(push) {
    const params = new URLSearchParams(new FormData($form[0]));
    const sort = $('#desktop-sort').val() || $('#mobile-sort').val() || 'newest';
    params.set('sort', sort); params.set('ajax', '1');
    const url = $form.attr('action') + '?' + params.toString();
    const browserUrl = url.replace(/([?&])ajax=1(&|$)/, '$1').replace(/[?&]$/, '');
    const current = ++sequence;
    if (request) request.abort();
    request = $.getJSON(url).done(function (response) {
      if (current !== sequence) return;
      $main.find('#products, .storefront-empty, .category-pagination').remove();
      $main.find('.category-toolbar').after(response.html);
      $('#products-title').text((response.count || 0) + ' محصول');
      if (push) window.history.pushState({}, '', browserUrl);
    }).fail(function (_xhr, status) { if (status !== 'abort') window.location.assign(browserUrl); });
  }
  $form.on('change', 'input, select', function () { refresh(true); });
  $('.category-sort select').on('change', function () { refresh(true); });
  $form.on('reset', function () { setTimeout(function () { refresh(true); }, 0); });
  $(document).on('click', '[data-filter-reset]', function () { $form[0].reset(); });
  $(document).on('click', '.category-pagination a', function (event) { event.preventDefault(); const href = $(this).attr('href'); if (href) { window.history.pushState({}, '', href); window.location.reload(); } });
  window.addEventListener('popstate', function () { window.location.reload(); });
  $('.category-filters details').prop('open', false);
}(window.jQuery));
