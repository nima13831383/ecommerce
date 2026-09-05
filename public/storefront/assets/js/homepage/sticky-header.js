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
  let frame = null;
  let collapsed = false;
  const collapseAt = 72;
  const expandAt = 24;

  function showAll() {
    $announcement.removeClass('is-hidden');
    $lowerDesktopNav.removeClass('is-hidden');
    $mobileSearch.removeClass('is-hidden');
  }

  function update() {
    frame = null;
    const current = getScrollTop();
    $header.toggleClass('is-stuck', current > 4);
    if (!collapsed && current >= collapseAt) collapsed = true;
    if (collapsed && current <= expandAt) collapsed = false;

    if (!collapsed) {
      showAll();
    } else if (mediaQuery.matches) {
      $lowerDesktopNav.addClass('is-hidden');
    } else if ($mobileMenuButton.attr('aria-expanded') !== 'true') {
      $announcement.addClass('is-hidden');
      $mobileSearch.addClass('is-hidden');
    }
  }

  function requestUpdate() {
    if (frame === null) frame = window.requestAnimationFrame(update);
  }
  window.addEventListener('scroll', requestUpdate, { passive: true });
  mediaQuery.addEventListener('change', function () { showAll(); update(); });
  update();
}(window.jQuery));
