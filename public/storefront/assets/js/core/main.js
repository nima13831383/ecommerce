(function () {
  'use strict';
  function init() {
    if (!window.jQuery) return;
    window.jQuery(function () {
      const $ = window.jQuery;
      $('.hero-slide:not(:first-child) h1').each(function () { $(this).replaceWith($('<h2/>', { html: $(this).html() })); });
      $('.favorite').each(function () {
        $(this).attr('aria-pressed', 'false');
      }).on('click', function () {
        const $button = $(this);
        const active = $button.attr('aria-pressed') === 'true';
        const label = $button.attr('aria-label') || '';
        $button.attr('aria-pressed', String(!active)).toggleClass('is-active', !active);
        $button.attr('aria-label', !active ? label.replace('افزودن', 'حذف') : label.replace('حذف', 'افزودن'));
      });
    });
  }
  init();
}());
