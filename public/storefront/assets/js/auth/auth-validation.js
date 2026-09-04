(function ($) {
  'use strict';
  $('[data-auth-form]').each(function () {
    const $form = $(this), type = $form.data('auth-form'), $message = $form.find('[data-auth-message]');
    const validIdentifier = (value) => /^09\d{9}$/.test(value) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    const error = (name, text) => { const $field = $form.find(`[name="${name}"]`); $field.addClass('is-invalid').attr({ 'aria-invalid': 'true', 'aria-describedby': `${name}-error` }); $form.find(`[data-field-error="${name}"]`).attr('id', `${name}-error`).text(text); };
    const clear = () => { $form.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid'); $form.find('[data-field-error]').empty().removeAttr('id'); $message.prop('hidden', true).empty(); };
    $form.on('input.auth', '[name="password"]', function () { const value = String($(this).val() || ''), meter = $form.find('[data-password-meter]'); const strength = value.length >= 8 && /[A-Za-z]/.test(value) && /\d/.test(value) ? 'strong' : value.length >= 6 ? 'medium' : 'weak'; meter.attr('data-strength', strength); $form.find('[data-strength-label]').text(strength === 'strong' ? 'قوی' : strength === 'medium' ? 'متوسط' : value ? 'ضعیف' : ''); });
    $form.on('submit.auth', function (event) {
      event.preventDefault(); clear(); let invalid = false, first = null;
      const addError = (name, text) => { error(name, text); if (!first) first = $form.find(`[name="${name}"]`); invalid = true; };
      if (type === 'login') { const identifier = String($form.find('[name="identifier"]').val() || '').trim(); if (!validIdentifier(identifier)) addError('identifier', 'لطفاً شماره موبایل یا ایمیل معتبر وارد کنید.'); if (!String($form.find('[name="password"]').val() || '')) addError('password', 'لطفاً رمز عبور را وارد کنید.'); }
      if (type === 'register') { const name = String($form.find('[name="last-name"]').val() || '').trim(); if (!name) addError('last-name', 'لطفاً نام و نام خانوادگی را وارد کنید.'); if (!/^09[0-9]{9}$/.test(String($form.find('[name="phone"]').val() || '').trim())) addError('phone', 'لطفاً شماره موبایل معتبر وارد کنید.'); const password = String($form.find('[name="password"]').val() || ''); if (!(password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password))) addError('password', 'رمز عبور باید حداقل ۸ کاراکتر، یک حرف و یک عدد داشته باشد.'); if (password !== String($form.find('[name="password-confirm"]').val() || '')) addError('password-confirm', 'تکرار رمز عبور با رمز عبور یکسان نیست.'); if (!$form.find('[name="terms"]').prop('checked')) { $message.text('لطفاً قوانین و شرایط استفاده را بپذیرید.').attr('class', 'auth-message auth-message--error').prop('hidden', false); invalid = true; } }
      if (type === 'forgot' && !validIdentifier(String($form.find('[name="identifier"]').val() || '').trim())) addError('identifier', 'لطفاً شماره موبایل یا ایمیل معتبر وارد کنید.');
      if (invalid) { if (first) first.trigger('focus'); if (!$message.text()) $message.text('لطفاً اطلاعات مشخص‌شده را اصلاح کنید.').attr('class', 'auth-message auth-message--error').prop('hidden', false); return; }
      if (type === 'forgot') { $form.closest('[data-forgot-fields]').prop('hidden', true); $form.closest('.auth-card').find('[data-forgot-confirm]').prop('hidden', false); $form.closest('.auth-card').find('[data-masked-identifier]').text(String($form.find('[name="identifier"]').val()).includes('@') ? 'm••••@example.com' : '09••• ••• ••21'); return; }
      $message.text(type === 'login' ? 'فرم ورود با موفقیت تکمیل شد. اتصال واقعی در مرحله بک‌اند انجام می‌شود.' : 'فرم ثبت‌نام با موفقیت تکمیل شد. ایجاد حساب واقعی در مرحله بک‌اند انجام می‌شود.').attr('class', 'auth-message auth-message--success').prop('hidden', false);
    });
  });
}(window.jQuery));
