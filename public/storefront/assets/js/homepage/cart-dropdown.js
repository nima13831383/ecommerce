(function ($) {
  'use strict';
  if (!$ || !$('#cart-preview').length) return;

  const $preview = $('#cart-preview');
  const $buttons = $('.header-actions .icon-button:nth-child(3), .mobile-header__row .icon-button:last-child');
  $buttons.wrap('<span class="cart-trigger-wrapper"></span>');

  function activeButton() {
    return $buttons.filter(function () {
      const box = this.getBoundingClientRect();
      return box.width > 0 && box.height > 0;
    }).first();
  }

  function mountPreview() {
    const $button = activeButton();
    if ($button.length) $preview.appendTo($button.closest('.cart-trigger-wrapper'));
  }

  $buttons.attr({ 'data-cart-toggle': 'true', 'aria-controls': 'cart-preview', 'aria-expanded': 'false' });

  function closeCart(restoreFocus) {
    $preview.removeClass('is-open').attr('aria-hidden', 'true');
    $buttons.attr('aria-expanded', 'false');
    if (restoreFocus !== false) $buttons.filter(':visible').first().trigger('focus');
  }

  function openCart() {
    $(document).trigger('cart:opened');
    mountPreview();
    $preview.addClass('is-open').attr('aria-hidden', 'false');
    $buttons.attr('aria-expanded', 'true');
  }

  $buttons.on('click.cartDropdown', function (event) {
    event.stopPropagation();
    event.preventDefault();
    if ($preview.hasClass('is-open')) closeCart();
    else openCart();
  });
  $(document).on('click.cartDropdown', function (event) {
    if ($preview.hasClass('is-open') && !$(event.target).closest('#cart-preview, [data-cart-toggle="true"]').length) closeCart(false);
  });
  $(document).on('keydown.cartDropdown', function (event) {
    if (event.key === 'Escape' && $preview.hasClass('is-open')) closeCart();
  });
  $(document).on('cart:close.cartDropdown', function () { closeCart(false); });
  closeCart(false);
}(window.jQuery));
