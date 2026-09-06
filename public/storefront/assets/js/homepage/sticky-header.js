(function ($) {
  'use strict';
  if (!$ || !$('header').length) return;

  const $header = $('header');
  const scrollRoot = document.scrollingElement || document.documentElement;
  let frame = null;

  function update() {
    frame = null;
    $header.toggleClass('is-stuck', Math.max(window.scrollY, scrollRoot.scrollTop, document.body?.scrollTop || 0) > 4);
  }

  window.addEventListener('scroll', function () {
    if (frame === null) frame = window.requestAnimationFrame(update);
  }, { passive: true });
  update();
}(window.jQuery));
