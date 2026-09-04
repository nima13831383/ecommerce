(function () {
  'use strict';
  const panel = document.querySelector('[data-address-form-panel]');
  const form = document.querySelector('[data-address-form]');
  const title = panel?.querySelector('[data-address-form-title]');
  const submit = panel?.querySelector('[data-address-submit]');
  let editing = null;
  function openForm(card) {
    editing = card || null;
    if (title) title.textContent = card ? 'ویرایش آدرس' : 'افزودن آدرس جدید';
    if (submit) submit.textContent = card ? 'ذخیره آدرس' : 'افزودن آدرس';
    if (card) form.querySelectorAll('[data-address-field]').forEach((input) => { input.value = card.dataset[input.name] || ''; });
    panel.classList.add('is-open');
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    form.querySelector('[data-address-field]').focus();
  }
  document.querySelector('[data-address-add]')?.addEventListener('click', () => openForm());
  document.querySelector('[data-address-cancel]')?.addEventListener('click', () => { panel.classList.remove('is-open'); editing = null; form.reset(); });
  document.querySelectorAll('[data-address-edit]').forEach((button) => button.addEventListener('click', () => openForm(button.closest('[data-address-card]'))));
  form?.addEventListener('submit', function (event) { event.preventDefault(); const name = form.querySelector('[name="recipient"]')?.value.trim(); if (!name) return; if (editing) editing.querySelector('[data-address-recipient]').textContent = name; panel.classList.remove('is-open'); form.reset(); editing = null; });
  let pending = null;
  const dialog = document.querySelector('[data-delete-dialog]');
  document.querySelectorAll('[data-address-delete]').forEach((button) => button.addEventListener('click', () => { pending = button.closest('[data-address-card]'); dialog.hidden = false; dialog.querySelector('[data-delete-cancel]').focus(); }));
  dialog?.querySelector('[data-delete-cancel]')?.addEventListener('click', () => { dialog.hidden = true; pending = null; });
  dialog?.querySelector('[data-delete-confirm]')?.addEventListener('click', () => { pending?.remove(); dialog.hidden = true; pending = null; if (!document.querySelector('[data-address-card]')) document.querySelector('[data-address-empty]').hidden = false; });
  dialog?.addEventListener('click', (event) => { if (event.target === dialog) { dialog.hidden = true; pending = null; } });
}());
