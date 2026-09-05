(function ($) {
  'use strict';
  if (!$) return;
  $('[data-password-toggle]').each(function () {
    const $button = $(this);
    const $input = $('#' + $button.attr('aria-controls'));
    if (!$input.length) return;
    const label = $input.attr('name') === 'password_confirmation' ? ' تکرار رمز عبور' : ' رمز عبور';
    $button.on('click.passwordToggle', function () {
      const visible = $input.attr('type') === 'text';
      $input.attr('type', visible ? 'password' : 'text');
      $button.attr('aria-label', (visible ? 'نمایش' : 'مخفی کردن') + label);
    });
  });
}(window.jQuery));
