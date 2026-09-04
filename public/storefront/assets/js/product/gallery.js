(function ($) {
  'use strict';
  if (!$ || !$('.product-gallery').length) return;
  const $gallery = $('.product-gallery').first();
  const $main = $gallery.find('[data-gallery-main]');
  const $thumbs = $gallery.find('[data-gallery-thumb]');
  function activate($thumb) {
    const state = $thumb.data('gallery-thumb');
    $main.attr('data-gallery-state', state);
    $thumbs.removeClass('is-active').attr('aria-pressed', 'false');
    $thumb.addClass('is-active').attr('aria-pressed', 'true');
  }
  $thumbs.on('click.productGallery', function () { activate($(this)); });
  $thumbs.on('keydown.productGallery', function (event) {
    const index = $(this).index();
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); $thumbs.eq((index + (event.key === 'ArrowLeft' ? 1 : -1) + $thumbs.length) % $thumbs.length).trigger('focus').trigger('click'); }
  });
}(window.jQuery));
