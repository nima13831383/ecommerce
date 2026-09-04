(function ($) {
  'use strict';
  if (!$ || !$('#cart-preview').length) return;

  const $preview = $('#cart-preview');
  const $buttons = $('[data-cart-toggle="true"]');

  function activeButton() {
    return $buttons.filter(function () {
      const box = this.getBoundingClientRect();
      return box.width > 0 && box.height > 0;
    }).first();
  }

  function mountPreview() {
    const $button = activeButton();
    if ($button.length) $preview.appendTo($button.closest('.header-actions, .mobile-header__row'));
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
  $(document).on('cart:updated.cartDropdown', function (_event, cart) {
    const lines = (cart && cart.lines) || [];
    const html = lines.slice(0, 3).map(function (line) {
      const image = line.image ? '<img src="' + line.image.url + '" alt="">' : '';
      return '<article class="cart-item"><div class="cart-item__media ' + (line.image ? '' : 'media-placeholder') + '">' + image + '</div><div><strong class="cart-item__title"></strong><span class="cart-item__meta"></span></div><span class="cart-item__price"></span></article>';
    }).join('');
    $preview.find('.cart-item, .cart-preview__empty').remove();
    if (!html) $preview.find('.cart-preview__total').before('<p class="cart-preview__empty">سبد خرید شما خالی است.</p>'); else $preview.find('.cart-preview__total').before(html);
    lines.slice(0, 3).forEach(function (line, index) { const $item = $preview.find('.cart-item').eq(index); $item.find('.cart-item__title').text(line.name); $item.find('.cart-item__meta').text(line.quantity + ' × ' + Number(line.unit_price || 0).toLocaleString('fa-IR') + ' ریال'); $item.find('.cart-item__price').text(Number(line.line_total || 0).toLocaleString('fa-IR')); });
    $preview.find('.cart-preview__header span').text((cart.item_count || 0) + ' کالا'); $preview.find('.cart-preview__total strong').text(Number(cart.grand_total || 0).toLocaleString('fa-IR') + ' ریال');
  });
  closeCart(false);
}(window.jQuery));
