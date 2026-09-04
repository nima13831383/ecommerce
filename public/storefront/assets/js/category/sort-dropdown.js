(function ($) {
  'use strict';
  if (!$ || !$('.category-page').length) return;

  const sortControls = [];
  const values = ['popular', 'newest', 'bestselling', 'price-low', 'price-high', 'discount'];

  $('.category-sort select').each(function (index) {
    const $select = $(this);
    const $sort = $select.closest('.category-sort');
    const selectId = $select.attr('id');
    const menuId = `${selectId}-listbox`;
    const label = $sort.find('label').first().text().replace(':', '').trim() || 'مرتب‌سازی';
    const options = this.options;
    const $control = $('<div class="category-sort__control"></div>');
    const $trigger = $(`<button type="button" class="category-sort__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="${menuId}"></button>`);
    const $menu = $(`<ul id="${menuId}" class="category-sort__menu" role="listbox" aria-hidden="true"></ul>`);

    $select.addClass('category-sort__native');
    $trigger.attr('aria-label', label);
    [...options].forEach((option, optionIndex) => {
      const value = option.getAttribute('value') || values[optionIndex] || `sort-${optionIndex}`;
      if (!option.getAttribute('value')) option.value = value;
      $menu.append($(`<li class="category-sort__option" role="option" tabindex="-1" data-value="${value}"></li>`).text(option.text).attr('aria-selected', option.selected ? 'true' : 'false'));
    });
    $trigger.append('<span class="category-sort__label"></span><span class="category-sort__chevron" aria-hidden="true"></span>');
    $control.append($trigger, $menu);
    $select.after($control);

    const state = { select: this, $sort, $trigger, $menu, $options: $menu.children(), open: false };
    sortControls.push(state);

    function selectedIndex() {
      return Math.max(0, [...state.select.options].findIndex((option) => option.value === state.select.value));
    }
    function render() {
      const index = selectedIndex();
      state.$trigger.find('.category-sort__label').text(state.select.options[index]?.text || '');
      state.$options.each(function (optionIndex) {
        $(this).attr('aria-selected', optionIndex === index ? 'true' : 'false').toggleClass('is-selected', optionIndex === index);
      });
    }
    function focusOption(index) {
      const bounded = Math.max(0, Math.min(index, state.$options.length - 1));
      state.$options.removeClass('is-active').eq(bounded).addClass('is-active').trigger('focus');
    }
    function close(restoreFocus) {
      state.open = false;
      state.$trigger.attr('aria-expanded', 'false');
      state.$menu.removeClass('is-open').attr('aria-hidden', 'true');
      if (restoreFocus) state.$trigger.trigger('focus');
    }
    function open() {
      sortControls.forEach((control) => { if (control !== state && control.open) control.close(false); });
      state.open = true;
      state.$trigger.attr('aria-expanded', 'true');
      state.$menu.addClass('is-open').attr('aria-hidden', 'false');
      focusOption(selectedIndex());
    }
    function selectOption(index) {
      const option = state.select.options[index];
      if (!option) return;
      state.select.value = option.value;
      $(state.select).trigger('change');
      render();
      close(true);
    }
    state.openMenu = open;
    state.close = close;
    state.selectOption = selectOption;
    state.selectedIndex = selectedIndex;
    $trigger.on('click.categorySort', () => (state.open ? close(false) : open()));
    state.$options.on('click.categorySort', function () { selectOption($(this).index()); });
    state.$options.on('keydown.categorySort', function (event) {
      const current = $(this).index();
      if (event.key === 'ArrowDown') { event.preventDefault(); focusOption(current + 1); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); focusOption(current - 1); }
      else if (event.key === 'Home') { event.preventDefault(); focusOption(0); }
      else if (event.key === 'End') { event.preventDefault(); focusOption(state.$options.length - 1); }
      else if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectOption(current); }
      else if (event.key === 'Escape') { event.preventDefault(); close(true); }
    });
    $select.on('change.categorySort', render);
    render();
  });

  $(document).on('click.categorySort', function (event) {
    sortControls.forEach((control) => {
      if (control.open && !$(event.target).closest('.category-sort__control').is(control.$sort.find('.category-sort__control'))) control.close(false);
    });
  });
  $('.category-sort__trigger').on('keydown.categorySort', function (event) {
    const state = sortControls.find((control) => control.$trigger[0] === this);
    if (!state) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); if (!state.open) state.openMenu(); else state.$options.eq(state.selectedIndex() + (event.key === 'ArrowDown' ? 1 : -1)).trigger('focus'); }
    else if (event.key === 'Escape' && state.open) { event.preventDefault(); state.close(true); }
  });
}(window.jQuery));
