(function ($) {
  'use strict';
  if (!$ || !$('.category-page').length) return;

  const $form = $('.category-filters').first();
  const $main = $('.category-main');
  const $open = $('[data-filter-open]');
  const $drawer = $('<aside id="category-filter-drawer" class="category-filter-drawer" role="dialog" aria-modal="true" aria-labelledby="category-filter-title" aria-hidden="true"></aside>');
  const $backdrop = $('<div class="category-filter-backdrop" aria-hidden="true"></div>');
  let request = null;
  let sequence = 0;

  $drawer.append('<div class="category-filter-drawer__header"><h2 id="category-filter-title">فیلتر محصولات</h2><button type="button" class="category-filter-drawer__close" data-filter-close aria-label="بستن فیلترها">×</button></div>');
  $drawer.append($form.find('.filter-group').clone());
  $drawer.append('<div class="category-filter-drawer__footer"><button type="button" class="filter-actions__reset" data-filter-reset>پاک کردن</button><button type="button" class="filter-actions__apply" data-filter-apply>اعمال فیلترها</button></div>');
  $('body').append($backdrop, $drawer);

  function localized(value) {
    return String(value).replace(/[0-9]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[digit]; });
  }

  function browserUrlFrom(url) {
    return url.replace(/([?&])ajax=1(&|$)/, '$1').replace(/[?&]$/, '');
  }

  function refresh(push, pageUrl) {
    const params = pageUrl ? new URL(pageUrl, window.location.origin).searchParams : new URLSearchParams(new FormData($form[0]));
    const sort = $('#desktop-sort').val() || $('#mobile-sort').val() || 'newest';
    params.set('sort', sort);
    params.set('ajax', '1');
    const url = $form.attr('action') + '?' + params.toString();
    const browserUrl = browserUrlFrom(url);
    const current = ++sequence;
    if (request) request.abort();
    request = $.getJSON(url).done(function (response) {
      if (current !== sequence) return;
      $main.find('#products, .storefront-empty, .category-pagination').remove();
      $main.find('.category-toolbar').after(response.html);
      $('#products-title').text(localized(response.count || 0) + ' محصول');
      if (push) window.history.pushState({}, '', browserUrl);
    }).fail(function (_xhr, status) {
      if (status !== 'abort') window.location.assign(browserUrl);
    });
  }

  function syncInput(source, target) {
    const name = source.attr('name');
    if (!name) return;
    const selector = '[name="' + name.replace(/"/g, '\\"') + '"]' + (source.attr('type') === 'checkbox' ? '[value="' + source.val() + '"]' : '');
    target.find(selector).prop('checked', source.prop('checked')).val(source.val());
  }

  function closeDrawer(restoreFocus) {
    $drawer.removeClass('is-open').attr('aria-hidden', 'true').prop('inert', true);
    $backdrop.removeClass('is-open');
    $('body').removeClass('category-filter-open');
    $open.attr('aria-expanded', 'false');
    if (restoreFocus !== false) $open.trigger('focus');
  }

  $open.on('click.categoryFilters', function () {
    $drawer.prop('inert', false).addClass('is-open').attr('aria-hidden', 'false');
    $backdrop.addClass('is-open');
    $('body').addClass('category-filter-open');
    $open.attr('aria-expanded', 'true');
    $drawer.find('[data-filter-close]').trigger('focus');
  });
  $drawer.on('click.categoryFilters', '[data-filter-close], [data-filter-apply]', function () { closeDrawer(); });
  $backdrop.on('click.categoryFilters', function () { closeDrawer(); });
  $(document).on('keydown.categoryFilters', function (event) { if (event.key === 'Escape' && $drawer.hasClass('is-open')) closeDrawer(); });
  $(window).on('resize.categoryFilters', function () { if (window.innerWidth > 900 && $drawer.hasClass('is-open')) closeDrawer(false); });

  $form.on('change', 'input, select', function () { syncInput($(this), $drawer); refresh(true); });
  $drawer.on('change', 'input, select', function () { syncInput($(this), $form); refresh(true); });
  $('.category-sort select').on('change', function () { $('#desktop-sort, #mobile-sort').val($(this).val()); refresh(true); });
  $form.on('reset', function () { setTimeout(function () { $form.find('input, select').each(function () { syncInput($(this), $drawer); }); refresh(true); }, 0); });
  $(document).on('click', '[data-filter-reset]', function () { $form[0].reset(); });
  $(document).on('click', '.category-pagination a', function (event) { event.preventDefault(); const href = $(this).attr('href'); if (href) refresh(true, href); });
  window.addEventListener('popstate', function () { window.location.reload(); });
  $drawer.prop('inert', true);
}(window.jQuery));
