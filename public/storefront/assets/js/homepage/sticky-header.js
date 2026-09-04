(function ($) {
  'use strict';
  if (!$ || !$('header').length) return;
  const $header = $('header');
  const $announcement = $('.header-row--announcement');
  const $lowerDesktopNav = $('.desktop-nav');
  const $mobileSearch = $('.mobile-header__search-row');
  const $mobileMenuButton = $('[data-action="menu"]');
  const mediaQuery = window.matchMedia('(min-width: 901px)');
  const scrollRoot = document.scrollingElement || document.documentElement;
  const getScrollTop = () => Math.max(window.scrollY, scrollRoot.scrollTop, document.body?.scrollTop || 0);
  let lastScroll = getScrollTop();
  let frame = null;

  function showAll() {
    $announcement.removeClass('is-hidden');
    $lowerDesktopNav.removeClass('is-hidden');
    $mobileSearch.removeClass('is-hidden');
  }

  function update() {
    frame = null;
    const current = getScrollTop();
    const delta = current - lastScroll;
    $header.toggleClass('is-stuck', current > 4);
    if (current <= 12) {
      showAll();
    } else if (mediaQuery.matches) {
      if (delta < -10 && $lowerDesktopNav.hasClass('is-hidden')) showAll();
      else if (!$lowerDesktopNav.hasClass('is-hidden')) {
        $lowerDesktopNav.addClass('is-hidden');
      }
    } else if (!$mobileMenuButton.attr('aria-expanded') || $mobileMenuButton.attr('aria-expanded') === 'false') {
      if (delta < -10 && $announcement.hasClass('is-hidden') && $mobileSearch.hasClass('is-hidden')) showAll();
      else if (!$announcement.hasClass('is-hidden') || !$mobileSearch.hasClass('is-hidden')) {
        $announcement.addClass('is-hidden');
        $mobileSearch.addClass('is-hidden');
      }
    }
    lastScroll = current;
  }

  function requestUpdate() {
    if (frame === null) frame = window.requestAnimationFrame(update);
  }
  window.addEventListener('scroll', requestUpdate, { passive: true });
  document.addEventListener('scroll', requestUpdate, { passive: true });
  mediaQuery.addEventListener('change', function () { showAll(); update(); });
  update();
}(window.jQuery));
