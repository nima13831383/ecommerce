# Filament Jalali Input Closure

## 1. Date Field Inventory

Editable date fields were inventoried across Product sale/publish dates, Coupon start/expiry, Post scheduling, and all table date-range filters (Users, Orders, Payments, Shipments, Inventory Transactions, and Inventory Reservations). Date displays were also audited in Users, Products, Coupons, Orders, Payments, Shipments, Inventory, Notifications, Settings, and Coupon usage views.

## 2. Jalali Picker Architecture

`App\Filament\Forms\Components\JalaliDatePicker` and `JalaliDateTimePicker` are centralized TextInput-based fields. They expose `data-jalali-picker` hooks and a shared Filament panel render hook (`resources/views/filament/jalali-picker.blade.php`) with a lightweight Persian calendar picker. Resources do not contain duplicated conversion callbacks.

## 3. Date Conversion

`App\Support\JalaliDate` remains the single conversion/presentation authority. Jalali picker state hydrates from canonical Carbon/Gregorian values and dehydrates to `Y-m-d H:i:s`; existing canonical database columns, sorting, scopes, and API timestamps remain unchanged. Already-canonical ISO input remains backward compatible for existing Livewire actions.

## 4. DateTime / Tehran

DateTime conversion uses `config('app.timezone')`, defaulting to `Asia/Tehran`, without a second timezone shift. Known date-only and Jalali datetime round trips pass, including Persian digits and nullable values.

## 5. Blog Scheduling

Post scheduling now uses the centralized Jalali DateTime field. Existing PostService future-time validation and `Post::published()` semantics are unchanged and remain green through real Filament action tests.

## 6. Coupon Dates

Coupon start/expiry fields use the same Jalali DateTime field. CouponService validity calculations remain canonical and unchanged.

## 7. Other Filament Resources

Date filters in Users, Orders, Payments, Shipments, Inventory Transactions, and Inventory Reservations use Jalali date fields. Shipment, payment, order, inventory, notification, user, product, coupon, settings, and taxonomy date displays use the centralized Jalali formatter.

## 8. Tables / Infolists

All remaining Filament `dateTime()` display calls were replaced with Jalali formatter callbacks. No raw Gregorian human-facing date display remains in `app/Filament`.

## 9. Filters

Jalali filter values dehydrate to canonical Gregorian values before existing `whereDate` queries execute; no database schema or query semantics changed.

## 10. Browser QA

An authenticated Playwright run was completed against a disposable isolated SQLite browser database (not `ecommerce`). All 14 scripted checks passed: it logged into Filament, opened the Post scheduling action, displayed the Persian/Jalali calendar, selected `1405/06/20 12:30`, saved, and reloaded the edit page. The persisted value was canonical `2026-09-11T12:30:00+03:30`. A Coupon start date was selected as `1405/07/01 10:15`, reloaded identically, and persisted canonically as `2026-09-23T10:15:00+03:30`. A real Users created-date table filter accepted a Jalali range and returned the seeded admin row. Browser diagnostics recorded zero console errors, page errors, failed requests, Livewire exceptions, or picker initialization errors.

## 11. Tests

* `tests/Feature/Filament/JalaliDateInputTest.php`: **3 passed, 7 assertions**.
* `tests/Feature/Filament/Blog/PostLifecycleRuntimeTest.php`: **3 passed, 48 assertions**.
* Focused Filament suite: **86 passed, 819 assertions, 0 failures, 0 skipped**.
* Full isolated suite: **346 passed, 2,146 assertions, 0 failures, 0 skipped**.

## 12. API Compatibility

Product/API tests remain green; public timestamps remain machine-oriented canonical values. No API contract changed.

## 13. Files Changed

Central components/validation, Filament form/table/infolist wiring, AdminPanelProvider hook, Jalali conversion helper, and focused tests. No raw frontend files changed.

## 14. Limitations

The browser proof used a disposable SQLite fixture and did not touch development data. No remaining Filament Jalali runtime limitation was observed in this closure run.

**FILAMENT JALALI: VERIFIED PASS**
