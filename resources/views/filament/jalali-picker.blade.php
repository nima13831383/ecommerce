<style>
    .app-jalali-picker{position:absolute;z-index:80;display:none;padding:.75rem;background:#fff;border:1px solid #d1d5db;border-radius:.75rem;box-shadow:0 10px 25px #0002;direction:rtl;width:18rem}.app-jalali-picker.is-open{display:block}.app-jalali-picker header{display:flex;gap:.35rem;margin-bottom:.5rem}.app-jalali-picker input,.app-jalali-picker select{min-width:0;flex:1;border:1px solid #d1d5db;border-radius:.35rem;padding:.25rem}.app-jalali-picker .days{display:grid;grid-template-columns:repeat(7,1fr);gap:.2rem}.app-jalali-picker button{border:0;border-radius:.35rem;padding:.3rem;cursor:pointer;background:#f3f4f6}.app-jalali-picker button:hover{background:#f59e0b;color:#fff}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    const digits = (value) => String(value).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    const create = (input) => {
        if (input.dataset.jalaliReady) return;
        input.dataset.jalaliReady = '1';
        const panel = document.createElement('div'); panel.className = 'app-jalali-picker';
        const header = document.createElement('header'); const year = document.createElement('input'); year.inputMode = 'numeric'; year.placeholder = 'سال';
        const month = document.createElement('select'); months.forEach((name, i) => { const option = new Option(name, i + 1); month.add(option); }); header.append(year, month); panel.append(header);
        const days = document.createElement('div'); days.className = 'days'; panel.append(days); document.body.append(panel);
        const render = () => { days.replaceChildren(); const max = Number(year.value) % 4 === 3 && Number(month.value) === 12 ? 30 : (Number(month.value) <= 6 ? 31 : (Number(month.value) <= 11 ? 30 : 29)); for (let i = 1; i <= max; i++) { const button = document.createElement('button'); button.type = 'button'; button.textContent = i; button.addEventListener('click', () => { const time = input.value.match(/\s+(.+)$/)?.[1] || (input.dataset.jalaliPicker === 'datetime' ? '00:00' : ''); input.value = `${year.value}/${String(month.value).padStart(2,'0')}/${String(i).padStart(2,'0')}${time ? ` ${time}` : ''}`; input.dispatchEvent(new Event('input', {bubbles:true})); input.dispatchEvent(new Event('change', {bubbles:true})); panel.classList.remove('is-open'); }); days.append(button); } };
        const sync = () => { const match = digits(input.value).match(/(\d{3,4})[\/-](\d{1,2})[\/-](\d{1,2})/); year.value = match?.[1] || '1405'; month.value = match?.[2] || '1'; render(); };
        year.addEventListener('input', render); month.addEventListener('change', render); input.addEventListener('focus', () => { sync(); const rect = input.getBoundingClientRect(); panel.style.top = `${rect.bottom + window.scrollY + 4}px`; panel.style.left = `${Math.max(8, rect.left + window.scrollX - 80)}px`; panel.classList.add('is-open'); });
        document.addEventListener('click', event => { if (event.target !== input && !panel.contains(event.target)) panel.classList.remove('is-open'); });
    };
    document.querySelectorAll('[data-jalali-picker]').forEach(create);
    new MutationObserver(() => document.querySelectorAll('[data-jalali-picker]').forEach(create)).observe(document.body, {childList:true, subtree:true});
});
</script>
