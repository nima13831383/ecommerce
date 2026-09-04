(function ($) {
  'use strict';
  if (!$ || !$('.products-wrap [data-product-grid]').length) return;

  $('.products-wrap').each(function () {
    const $component = $(this);
    const $track = $component.find('[data-product-grid]');
    const $cards = $track.find('[data-product-card]');
    const $previous = $component.find('[data-product-action="prev"]');
    const $next = $component.find('[data-product-action="next"]');
    let current = 0;

    function visibleCount() {
      const card = $cards.get(0);
      if (!card) return 1;
      const gap = parseFloat(window.getComputedStyle($track.get(0)).columnGap || window.getComputedStyle($track.get(0)).gap) || 0;
      return Math.max(1, Math.floor(($track.innerWidth() + gap) / (card.getBoundingClientRect().width + gap)));
    }

    function updateControls() {
      const last = Math.max(0, $cards.length - visibleCount());
      current = Math.min(current, last);
      $previous.prop('disabled', current === 0);
      $next.prop('disabled', current >= last);
    }

    function moveTo(index) {
      const last = Math.max(0, $cards.length - visibleCount());
      current = Math.max(0, Math.min(index, last));
      const card = $cards.get(current);
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
      updateControls();
    }

    $previous.on('click', function () { moveTo(current - 1); });
    $next.on('click', function () { moveTo(current + 1); });
    $(window).on('resize', updateControls);
    updateControls();
  });
}(window.jQuery));
