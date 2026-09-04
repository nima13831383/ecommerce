(function ($) {
  'use strict';
  if (!$ || !$('#mobile-nav').length) return;

  const $button = $('[data-action="menu"]');
  const $nav = $('#mobile-nav');
  const mobileQuery = window.matchMedia('(max-width: 900px)');
  const $backdrop = $('<div class="mobile-menu-backdrop" data-mobile-backdrop aria-hidden="true"></div>').appendTo(document.body);
  const $close = $('<button type="button" class="mobile-menu__close" data-action="close-menu" aria-label="بستن منو"><span aria-hidden="true">×</span></button>');
  $nav.detach().appendTo(document.body);
  $nav.prepend($close);
  $nav.removeAttr('hidden').attr({ role: 'dialog', 'aria-modal': 'true', 'aria-hidden': 'true' }).prop('inert', true);

  function closeMenu(restoreFocus) {
    $button.attr('aria-expanded', 'false').attr('aria-label', 'باز کردن منو');
    $nav.removeClass('is-open').attr('aria-hidden', 'true').prop('inert', true);
    $backdrop.removeClass('is-open');
    $('body').removeClass('mobile-drawer-open');
    if (restoreFocus !== false) $button.trigger('focus');
  }

  function openMenu() {
    $(document).trigger('cart:close');
    $button.attr('aria-expanded', 'true').attr('aria-label', 'بستن منو');
    $nav.prop('inert', false).attr('aria-hidden', 'false').addClass('is-open');
    $backdrop.addClass('is-open');
    $('body').addClass('mobile-drawer-open');
    $close.trigger('focus');
  }

  $button.on('click.mobileMenu', function () {
    if ($button.attr('aria-expanded') === 'true') closeMenu();
    else openMenu();
  });
  $close.on('click.mobileMenu', function () { closeMenu(); });
  $backdrop.on('click.mobileMenu', function () { closeMenu(); });
  $nav.on('click.mobileMenu', 'a', function () { closeMenu(false); });
  $(document).on('keydown.mobileMenu', function (event) {
    if (event.key === 'Escape' && $button.attr('aria-expanded') === 'true') closeMenu();
  });
  $(document).on('cart:opened.mobileMenu', function () { if ($button.attr('aria-expanded') === 'true') closeMenu(false); });
  mobileQuery.addEventListener('change', function (event) { if (!event.matches) closeMenu(false); });
  closeMenu(false);
}(window.jQuery));
