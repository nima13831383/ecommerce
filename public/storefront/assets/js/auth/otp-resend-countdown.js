(() => {
  const digits = { 0: '۰', 1: '۱', 2: '۲', 3: '۳', 4: '۴', 5: '۵', 6: '۶', 7: '۷', 8: '۸', 9: '۹' };
  const persianDigits = (value) => String(value).replace(/\d/g, (digit) => digits[digit]);
  const format = (seconds) => {
    const safe = Math.max(0, seconds);

    return persianDigits(String(Math.floor(safe / 60)).padStart(2, '0') + ':' + String(safe % 60).padStart(2, '0'));
  };

  document.querySelectorAll('[data-otp-resend]').forEach((root) => {
    const availableAt = Number(root.dataset.resendAvailableAt) * 1000;
    const button = root.querySelector('button');
    const label = root.querySelector('[data-otp-resend-label]');

    if (!button || !label || !Number.isFinite(availableAt)) {
      return;
    }

    const refresh = () => {
      const remaining = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000));
      const disabled = remaining > 0;

      button.disabled = disabled;
      button.setAttribute('aria-disabled', String(disabled));
      label.textContent = disabled ? 'ارسال مجدد کد تا ' + format(remaining) : 'ارسال مجدد کد';

      if (!disabled) {
        window.clearInterval(interval);
      }
    };

    const interval = window.setInterval(refresh, 1000);

    refresh();
  });
})();
