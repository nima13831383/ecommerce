(function () {
  'use strict';
  const form = document.querySelector('[data-profile-form]');
  if (!form) return;
  const feedback = form.querySelector('[data-form-feedback]');
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    form.querySelectorAll('.form-error').forEach((error) => { error.textContent = ''; });
    let valid = true;
    form.querySelectorAll('[required]').forEach((input) => {
      if (!input.value.trim()) {
        const error = form.querySelector(`[data-error-for="${input.name}"]`);
        if (error) error.textContent = 'تکمیل این فیلد الزامی است.';
        valid = false;
      }
    });
    const phone = form.querySelector('[name="phone"]');
    if (phone && !/^09\d{9}$/.test(phone.value.replace(/\s/g, ''))) { const error = form.querySelector('[data-error-for="phone"]'); if (error) error.textContent = 'شماره موبایل را به شکل صحیح وارد کنید.'; valid = false; }
    const email = form.querySelector('[name="email"]');
    if (email && email.value && !email.checkValidity()) { const error = form.querySelector('[data-error-for="email"]'); if (error) error.textContent = 'ایمیل را به شکل صحیح وارد کنید.'; valid = false; }
    if (valid && feedback) { feedback.hidden = false; feedback.textContent = 'تغییرات اطلاعات حساب با موفقیت ثبت شد.'; window.setTimeout(() => { feedback.hidden = true; }, 3500); }
  });
  const passwordForm = document.querySelector('[data-password-form]');
  if (!passwordForm) return;
  passwordForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const first = passwordForm.querySelector('[name="password-new"]');
    const confirm = passwordForm.querySelector('[name="password-confirm"]');
    const error = passwordForm.querySelector('[data-password-error]');
    if (first.value.length < 8) { error.textContent = 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.'; return; }
    if (first.value !== confirm.value) { error.textContent = 'تکرار رمز عبور با رمز جدید یکسان نیست.'; return; }
    error.textContent = 'رمز عبور جدید برای این دموی استاتیک آماده شد.';
  });
}());
