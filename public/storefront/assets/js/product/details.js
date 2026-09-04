(function ($) {
  'use strict';
  if (!$ || !$('.product-details').length) return;
  $('.product-details__trigger').on('click.productDetails', function () {
    const $trigger = $(this);
    const $item = $trigger.closest('.product-details__item');
    const open = !$item.hasClass('is-open');
    $item.toggleClass('is-open', open);
    $trigger.attr('aria-expanded', String(open));
  });
}(window.jQuery));
