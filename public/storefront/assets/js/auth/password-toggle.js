(function ($) {
  'use strict';
  $('[data-password-toggle]').each(function () {
    const $button = $(this);
    const $input = $(`#${$button.attr('aria-controls')}`);
    if (!$input.length) return;
    $button.closest('.password-wrap').children('svg').remove();
    // Password fields keep the wrapper's permanent eye-side padding in both states.
    const label = $input.attr('name') === 'password-confirm' ? ' تکرار رمز عبور' : ' رمز عبور';
    $button.attr('aria-label', 'نمایش' + label);
    $button.on('click.auth-label', function () {
      $button.attr('aria-label', $input.attr('type') === 'text' ? 'مخفی کردن' + label : 'نمایش' + label);
    });
    $button.on('click.auth', function () {
      const visible = $input.attr('type') === 'text';
      $input.attr('type', visible ? 'password' : 'text');
      $button.attr('aria-label', visible ? 'نمایش رمز عبور' : 'مخفی کردن رمز عبور');
    });
  });
}(window.jQuery));
$(function () {
  const symbols = document.querySelector('.auth-symbols defs');
  if (symbols && !document.getElementById('i-eye-off')) {
    const symbol = document.createElementNS('http://www.w3.org/2000/svg', 'symbol');
    symbol.id = 'i-eye-off'; symbol.setAttribute('viewBox', '0 0 24 24');
    symbol.innerHTML = '<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c6.3 0 9.5 8 9.5 8a15.7 15.7 0 0 1-3.2 4.3M6.1 6.1C3.7 7.8 2.5 12 2.5 12s3.2 8 9.5 8a10.6 10.6 0 0 0 2.1-.2"/>';
    symbols.appendChild(symbol);
  }
  $('[data-password-toggle]').each(function () {
    const button = this, input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    const confirmation = input.name === 'password-confirm';
    const label = confirmation ? ' تکرار رمز عبور' : ' رمز عبور';
    const use = button.querySelector('use');
    const update = function () {
      const visible = input.type === 'text';
      if (use) use.setAttribute('href', visible ? '#i-eye-off' : '#i-eye');
      button.setAttribute('aria-label', (visible ? 'مخفی کردن' : 'نمایش') + label);
    };
    update();
    button.addEventListener('click', update);
  });
});
document.addEventListener('DOMContentLoaded', function () {
  const title = document.querySelector('#forgot-title');
  if (title) title.textContent = 'بازیابی رمز عبور';
  document.querySelectorAll('.sr-only').forEach(function (el) { el.textContent = el.matches('label') ? 'جستجو در محصولات' : 'پرش به محتوای اصلی'; });
  document.querySelectorAll('.auth-field').forEach(function (el) { const input = el.querySelector('input'); if (!input) return; const labels = { identifier: 'موبایل یا ایمیل', password: 'رمز عبور', 'first-name': 'نام و نام خانوادگی', phone: 'شماره موبایل', email: 'ایمیل', 'password-confirm': 'تکرار رمز عبور' }; if (labels[input.name] && el.firstChild) el.firstChild.textContent = labels[input.name]; });
  document.querySelectorAll('.password-help').forEach(function (el) { el.textContent = 'حداقل ۸ کاراکتر، شامل یک حرف و یک عدد '; });
});
(function ($) {
  'use strict';
  const page = location.pathname.split('/').pop();
  const first = (selector, value) => { const el = document.querySelector(selector); if (el && el.firstChild) el.firstChild.textContent = value; };
  $(function () {
    $('.announcement__inner span').eq(0).text('ارسال رایگان برای سفارش‌های بالای ۵۰۰ هزار تومان').end().eq(1).text('پشتیبانی همه‌روزه / تضمین اصالت کالا');
    $('input[type="search"]').attr('placeholder', 'جستجو در محصولات...'); $('.brand-mark').attr('aria-label', 'لوگوی لوکسیرَا'); $('[data-action="menu"]').attr('aria-label', 'باز کردن منو');
    $('.desktop-nav li a, .mobile-nav a').each(function (i) { this.textContent = ['خانه','عطر','لوازم آرایشی','پوست و مو','اکسسوری','برندها','تماس با ما'][i % 7]; });
    $('.auth-trust__item').each(function (i) { const t = [['تضمین اصالت کالا','محصولات اورجینال'],['پرداخت امن','با درگاه‌های معتبر بانکی'],['ارسال سریع','به سراسر ایران'],['پشتیبانی ۲۴/۷','پاسخگوی شما هستیم']][i]; if (t) { $(this).find('strong').text(t[0]); $(this).find('span').text(t[1]); } });
    if (page === 'login.html') { $('#login-title').text('ورود به حساب کاربری'); $('.auth-card__support').text('برای دسترسی به حساب کاربری خود وارد شوید.'); first('#login-identifier','موبایل یا ایمیل'); first('#login-password','رمز عبور'); $('#login-identifier').attr('placeholder','شماره موبایل یا name@email.com'); $('#login-password').attr('placeholder','رمز عبور خود را وارد کنید'); $('.auth-check').contents().last()[0].textContent='مرا به خاطر بسپار'; $('.auth-link').text('رمز عبور را فراموش کرده‌اید؟'); first('.auth-submit','ورود'); $('.auth-separator').text('یا ورود با'); first('.auth-switch','حساب ندارید؟ '); $('.auth-switch a').text('ثبت‌نام کنید'); }
    if (page === 'register.html') { $('#register-title').text('ایجاد حساب کاربری'); $('.auth-card__support').text('اطلاعات خود را وارد کنید تا حساب کاربری شما ایجاد شود.'); [['#reg-name','نام و نام خانوادگی','مثال: سارا محمدی'],['#reg-phone','شماره موبایل','۰۹۱۲۳۴۵۶۷۸۹'],['#reg-email','ایمیل','name@email.com'],['#reg-password','رمز عبور','حداقل ۸ کاراکتر'],['#reg-confirm','تکرار رمز عبور','رمز عبور را دوباره وارد کنید']].forEach(([s,l,p])=>{const e=$(s); first(s,l); e.attr('placeholder',p);}); $('.terms span').html('قوانین و <a class="auth-link" href="#">شرایط استفاده و حریم خصوصی</a> را می‌پذیرم.'); first('.auth-submit','ثبت‌نام'); first('.auth-switch','قبلاً حساب دارید؟ '); $('.auth-switch a').text('وارد شوید'); }
    if (page === 'forgot-password.html') { $('#forgot-title').text('بازیابی رمز عبور'); $('.auth-card__support').text('لطفاً شماره موبایل یا ایمیل خود را وارد کنید تا لینک بازیابی رمز عبور برای شما ارسال شود.'); first('#forgot-identifier','موبایل یا ایمیل'); $('#forgot-identifier').attr('placeholder','شماره موبایل یا name@email.com'); first('.auth-submit','ارسال لینک بازیابی'); $('.auth-separator').text('یا'); $('.auth-info-panel span').text('لینک بازیابی به ایمیل یا پیامک ارسال می‌شود. لطفاً پوشه اسپم خود را نیز بررسی کنید.'); $('.auth-switch a').text('بازگشت به ورود'); }
  });
}(window.jQuery));
