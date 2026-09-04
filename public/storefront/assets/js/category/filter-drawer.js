(function ($) {
  'use strict';
  if (!$ || !$('.category-page').length) return;

  const $open = $('[data-filter-open]');
  const $drawer = $('<aside id="category-filter-drawer" class="category-filter-drawer" role="dialog" aria-modal="true" aria-labelledby="category-filter-title" aria-hidden="true"></aside>');
  const $backdrop = $('<div class="category-filter-backdrop" aria-hidden="true"></div>');
  const $filters = $('.category-filters').first();
  $drawer.append('<div class="category-filter-drawer__header"><h2 id="category-filter-title">فیلتر محصولات</h2><button type="button" class="category-filter-drawer__close" data-filter-close aria-label="بستن فیلترها">×</button></div>');
  $drawer.append($filters.find('.filter-group').clone(true, true));
  $drawer.append('<div class="category-filter-drawer__footer"><button type="button" class="filter-actions__reset filter-actions__reset--drawer" data-filter-reset>پاک کردن</button><button type="button" class="filter-actions__apply" data-filter-apply>اعمال فیلترها</button></div>');
  $('body').append($backdrop, $drawer);
  const $allChecks = $('[name="category"], [name="brand"], [name="stock"], [name="discount"]');

  function updateCount() {
    const count = $allChecks.filter(':checked').length;
    $('[data-filter-count]').text(count).prop('hidden', count === 0);
  }
  function closeDrawer(restoreFocus) {
    $drawer.removeClass('is-open').attr('aria-hidden', 'true').prop('inert', true);
    $backdrop.removeClass('is-open');
    $('body').removeClass('category-filter-open');
    $open.attr('aria-expanded', 'false');
    if (restoreFocus !== false) $open.trigger('focus');
  }
  function openDrawer() {
    $drawer.prop('inert', false).attr('aria-hidden', 'false').addClass('is-open');
    $backdrop.addClass('is-open');
    $('body').addClass('category-filter-open');
    $open.attr('aria-expanded', 'true');
    $drawer.find('[data-filter-close]').trigger('focus');
  }
  $open.on('click.categoryFilters', function () { openDrawer(); });
  $drawer.on('click.categoryFilters', '[data-filter-close], [data-filter-apply]', function () { closeDrawer(); });
  $backdrop.on('click.categoryFilters', closeDrawer);
  $(document).on('keydown.categoryFilters', function (event) { if (event.key === 'Escape' && $drawer.hasClass('is-open')) closeDrawer(); });
  $(document).on('change.categoryFilters', '[name="category"], [name="brand"], [name="stock"], [name="discount"]', updateCount);
  $(document).on('click.categoryFilters', '[data-filter-reset]', function () { $allChecks.prop('checked', false); updateCount(); });
  $(window).on('resize.categoryFilters', function () { if (window.innerWidth > 900 && $drawer.hasClass('is-open')) closeDrawer(false); });
  closeDrawer(false);
}(window.jQuery));
