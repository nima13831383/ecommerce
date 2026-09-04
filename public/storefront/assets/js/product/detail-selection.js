(function ($) {
  'use strict';

  if (!$ || !$('[data-product-detail]').length) return;

  $('[data-product-detail]').each(function () {
    const $page = $(this);
    const isVariable = $page.data('product-type') === 'variable';
    const resolveUrl = $page.data('resolve-url');
    const $price = $page.find('[data-price]');
    const $regular = $page.find('[data-regular-price]');
    const $discount = $page.find('[data-discount]');
    const $stock = $page.find('[data-stock]');
    const $status = $page.find('[data-selection-status]');
    const $selectedVariation = $page.find('[data-selected-variation]');
    const $addButton = $page.find('[data-add-cart]');
    const $quantityInput = $page.find('[data-quantity-input]');
    const initialPrice = $price.text().trim();
    let requestId = 0;
    let activeRequest = null;

    const formatRial = (value) => Number(value).toLocaleString('fa-IR') + ' ریال';

    function resetSelection(message) {
      $selectedVariation.val('');
      if (isVariable) {
        $addButton.prop('disabled', true).attr('aria-disabled', 'true');
      }
      $price.text(initialPrice);
      $regular.text('').prop('hidden', true);
      $discount.prop('hidden', true);
      $stock.text('وضعیت پس از انتخاب گزینه‌ها مشخص می‌شود').removeClass('is-in-stock is-out-of-stock');
      if ($status.length) $status.text(message);
    }

    function selectedOptions() {
      const options = [];
      $page.find('[data-attribute-id]').each(function () {
        const $axis = $(this);
        if (!$axis.is('fieldset')) return;
        const $active = $axis.find('[data-variant-value].is-active').first();
        if ($active.length) {
          options.push({
            attribute_id: Number($active.data('attribute-id')),
            value_id: Number($active.data('value-id')),
          });
        }
      });
      return options;
    }

    function resolve() {
      if (!isVariable) return;
      const options = selectedOptions();
      const required = $page.data('required-attributes') || [];

      if (options.length < required.length) {
        requestId += 1;
        if (activeRequest) activeRequest.abort();
        activeRequest = null;
        resetSelection('برای مشاهده قیمت و موجودی، گزینه‌ها را انتخاب کنید.');
        return;
      }

      const currentId = ++requestId;
      if (activeRequest) activeRequest.abort();
      activeRequest = $.ajax({
        url: resolveUrl,
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ options }),
      }).done(function (response) {
        if (currentId !== requestId) return;
        const data = response.data || {};
        const pricing = data.pricing || {};
        $selectedVariation.val(data.id || '');
        $price.text(formatRial(pricing.effective_price || 0));
        if (pricing.is_discounted && pricing.regular_price !== null) {
          $regular.text(formatRial(pricing.regular_price)).prop('hidden', false);
          $discount.prop('hidden', false);
        } else {
          $regular.text('').prop('hidden', true);
          $discount.prop('hidden', true);
        }
        const inStock = !!(data.availability && data.availability.in_stock);
        $addButton.prop('disabled', !inStock).attr('aria-disabled', inStock ? 'false' : 'true');
        $stock.text(inStock ? 'موجود در انبار' : 'ناموجود')
          .toggleClass('is-in-stock', inStock)
          .toggleClass('is-out-of-stock', !inStock);
        if ($status.length) $status.text(inStock ? 'این ترکیب انتخاب شد.' : 'این ترکیب در حال حاضر موجود نیست.');
        if (data.image) {
          $page.find('[data-gallery-image]').attr('src', data.image);
        }
      }).fail(function (_xhr, textStatus) {
        if (currentId !== requestId || textStatus === 'abort') return;
        $selectedVariation.val('');
        if ($status.length) $status.text('انتخاب فعلی قابل دسترس نیست. لطفاً دوباره تلاش کنید.');
      });
    }

    $page.on('click.productVariationSelection', '[data-variant-value]', function () {
      const $option = $(this);
      const group = $option.data('variant-group');
      $page.find('[data-variant-group="' + group + '"]').removeClass('is-active').attr('aria-checked', 'false');
      $option.addClass('is-active').attr('aria-checked', 'true');
      resolve();
    });

    $page.on('click.productGallerySelection', '[data-gallery-thumb]', function () {
      const $thumb = $(this);
      const url = $thumb.data('image-url');
      const alt = $thumb.data('image-alt');
      if (url) $page.find('[data-gallery-image]').attr({ src: url, alt: alt || '' });
    });

    $page.on('click.productQuantity', '[data-quantity-decrease], [data-quantity-increase]', function () {
      const increase = $(this).is('[data-quantity-increase]');
      const current = Math.max(1, Number($quantityInput.val() || 1) + (increase ? 1 : -1));
      $quantityInput.val(current);
      $page.find('[data-quantity-value]').text(current.toLocaleString('fa-IR')).attr('aria-label', 'تعداد ' + current);
    });
  });
}(window.jQuery));
