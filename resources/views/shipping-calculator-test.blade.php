<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>آزمون محاسبه هزینه پست</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
<a href="#calculator-title" class="sr-only rounded-md bg-white px-4 py-2 text-slate-950 focus:not-sr-only focus:fixed focus:right-4 focus:top-4 focus:z-50 focus:ring-2 focus:ring-emerald-600">پرش به فرم محاسبه</a>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6">
        <p class="mb-2 text-sm font-semibold text-emerald-700">ابزار مستقل بررسی نرخ</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">محاسبه آزمایشی هزینه ارسال پستی</h1>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600">
            این صفحه فقط برای مقایسه و اشکال‌زدایی محاسبات پیشتاز و ویژه است و به سبد خرید، سفارش یا پرداخت فروشگاه متصل نیست.
        </p>
    </header>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(22rem,2fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="calculator-title">
            <h2 id="calculator-title" class="mb-5 text-lg font-bold text-slate-900">ورودی‌های محاسبه</h2>

            @if ($errors->any())
                <div id="validation-summary" role="alert" tabindex="-1" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <h3 class="font-bold">لطفاً خطاهای فرم را برطرف کنید:</h3>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->keys() as $field)
                            <li><a class="underline underline-offset-2" href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="mb-5 text-xs text-slate-500">تمام فیلدهای فرم الزامی هستند.</p>

            <form method="POST" action="{{ route('shipping-calculator-test.calculate') }}" class="space-y-6" novalidate>
                @csrf

                <fieldset>
                    <legend class="mb-3 text-sm font-bold text-slate-700">مبدأ و مقصد</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @php
                            $originProvince = old('origin_province', $formData['origin_province'] ?? '');
                            $originCity = old('origin_city', $formData['origin_city'] ?? '');
                            $destinationProvince = old('destination_province', $formData['destination_province'] ?? '');
                            $destinationCity = old('destination_city', $formData['destination_city'] ?? '');
                        @endphp

                        <div>
                            <label for="origin_province" class="mb-1.5 block text-sm font-medium">استان مبدأ</label>
                            <select id="origin_province" name="origin_province" required aria-required="true" aria-describedby="origin_province_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب استان</option>
                                @foreach ($provinces as $id => $label)
                                    <option value="{{ $id }}" @selected((string) $originProvince === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('origin_province')<p id="origin_province_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="origin_city" class="mb-1.5 block text-sm font-medium">شهر مبدأ</label>
                            <select id="origin_city" name="origin_city" required aria-required="true" data-selected="{{ $originCity }}" aria-describedby="origin_city_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">ابتدا استان را انتخاب کنید</option>
                            </select>
                            @error('origin_city')<p id="origin_city_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="destination_province" class="mb-1.5 block text-sm font-medium">استان مقصد</label>
                            <select id="destination_province" name="destination_province" required aria-required="true" aria-describedby="destination_province_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب استان</option>
                                @foreach ($provinces as $id => $label)
                                    <option value="{{ $id }}" @selected((string) $destinationProvince === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('destination_province')<p id="destination_province_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="destination_city" class="mb-1.5 block text-sm font-medium">شهر مقصد</label>
                            <select id="destination_city" name="destination_city" required aria-required="true" data-selected="{{ $destinationCity }}" aria-describedby="destination_city_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">ابتدا استان را انتخاب کنید</option>
                            </select>
                            @error('destination_city')<p id="destination_city_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 text-sm font-bold text-slate-700">مشخصات مرسوله</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="weight" class="mb-1.5 block text-sm font-medium">وزن مرسوله (گرم)</label>
                            <input id="weight" name="weight" type="number" required aria-required="true" inputmode="decimal" step="1" min="1" max="30000" value="{{ old('weight', $formData['weight'] ?? '') }}" aria-describedby="weight_help weight_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-left text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm" dir="ltr">
                            <p id="weight_help" class="mt-1.5 text-xs text-slate-500">حداکثر ۳۰٬۰۰۰ گرم؛ وزن به پله هزار گرم بعدی گرد می‌شود.</p>
                            @error('weight')<p id="weight_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="declared_value" class="mb-1.5 block text-sm font-medium">ارزش مرسوله (ریال)</label>
                            <input id="declared_value" name="declared_value" type="number" required aria-required="true" inputmode="numeric" step="1" min="0" value="{{ old('declared_value', $formData['declared_value'] ?? '') }}" aria-describedby="declared_value_help declared_value_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-left text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm" dir="ltr">
                            <p id="declared_value_help" class="mt-1.5 text-xs text-slate-500">واحد ورودی ریال است؛ افزونه در محاسبه حداکثر یک میلیارد ریال را لحاظ می‌کند.</p>
                            @error('declared_value')<p id="declared_value_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="parcel_type" class="mb-1.5 block text-sm font-medium">نوع مرسوله</label>
                            <select id="parcel_type" name="parcel_type" required aria-required="true" aria-describedby="parcel_type_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب نوع مرسوله</option>
                                @foreach ($parcelTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('parcel_type', $formData['parcel_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('parcel_type')<p id="parcel_type_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="payment_type" class="mb-1.5 block text-sm font-medium">نوع پرداخت</label>
                            <select id="payment_type" name="payment_type" required aria-required="true" aria-describedby="payment_type_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب نوع پرداخت</option>
                                @foreach ($paymentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_type', $formData['payment_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment_type')<p id="payment_type_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="package_size" class="mb-1.5 block text-sm font-medium">اندازه بسته</label>
                            <select id="package_size" name="package_size" required aria-required="true" aria-describedby="package_size_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب اندازه بسته</option>
                                @foreach ($packageSizes as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old('package_size', $formData['package_size'] ?? '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('package_size')<p id="package_size_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="service" class="mb-1.5 block text-sm font-medium">نوع سفارش</label>
                            <select id="service" name="service" required aria-required="true" aria-describedby="service_error" class="block min-h-11 w-full rounded-lg border-slate-300 text-base shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:text-sm">
                                <option value="">انتخاب سرویس پستی</option>
                                @foreach ($services as $value => $label)
                                    <option value="{{ $value }}" @selected(old('service', $formData['service'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('service')<p id="service_error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                    <button type="submit" class="min-h-11 cursor-pointer rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                        محاسبه هزینه
                    </button>
                    <a href="{{ route('shipping-calculator-test.show') }}" class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                        پاک کردن فرم
                    </a>
                </div>
            </form>
        </section>

        <aside class="space-y-6 xl:sticky xl:top-6" aria-live="polite">
            @if ($quote)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="result-title">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="result-title" class="text-lg font-bold">نتیجه محاسبه</h2>
                            @if ($quote->available === true)
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">قابل محاسبه</span>
                            @elseif ($quote->available === false)
                                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">غیرقابل ارسال</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">نیازمند تأیید دسترسی</span>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-5 py-5 text-sm">
                        <dt class="text-slate-500">سرویس انتخابی</dt>
                        <dd class="font-bold text-slate-900">{{ $services[$quote->service] }}</dd>
                        <dt class="text-slate-500">دسترسی</dt>
                        <dd class="font-bold text-slate-900">
                            {{ $quote->available === true ? 'تأییدشده در داده محلی' : ($quote->available === false ? 'غیرفعال' : 'نامشخص در داده محلی') }}
                        </dd>
                        <dt class="text-slate-500">قیمت نهایی</dt>
                        <dd class="text-lg font-black text-emerald-800" dir="ltr">{{ number_format($quote->total) }}</dd>
                        <dt class="text-slate-500">واحد پول</dt>
                        <dd class="font-bold text-slate-900">ریال (IRR)</dd>
                    </dl>
                </section>

                @if ($quote->warnings)
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="warnings-title">
                        <h2 id="warnings-title" class="text-sm font-bold text-amber-950">ملاحظات منبع</h2>
                        <ul class="mt-3 list-inside list-disc space-y-2 text-sm leading-6 text-amber-950">
                            @foreach ($quote->warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="breakdown-title">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 id="breakdown-title" class="text-base font-bold">ریز محاسبه</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">جزء هزینه</th>
                                <th scope="col" class="px-5 py-3 text-left font-semibold">مبلغ (ریال)</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach ($quote->breakdown as $item)
                                <tr>
                                    <td class="px-5 py-3 leading-6 text-slate-700">{{ $item['label'] }}</td>
                                    <td class="px-5 py-3 text-left font-mono font-semibold text-slate-900" dir="ltr">{{ number_format($item['amount'], 0) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-300 bg-slate-50">
                            <tr>
                                <th scope="row" class="px-5 py-4 text-right font-black">جمع نهایی</th>
                                <td class="px-5 py-4 text-left font-mono font-black text-emerald-800" dir="ltr">{{ number_format($quote->total) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer text-sm font-bold text-slate-800">اطلاعات فنی محاسبه</summary>
                    <dl class="mt-4 grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] gap-x-3 gap-y-2 break-words text-xs leading-6">
                        <dt class="text-slate-500">منبع نرخ</dt><dd class="text-left" dir="ltr">{{ $quote->metadata['rate_source'] ?? '—' }}</dd>
                        <dt class="text-slate-500">مرجع قدیمی افزونه</dt><dd class="font-mono text-left" dir="ltr">{{ $quote->metadata['plugin_rate_reference'] ?? '—' }}</dd>
                        <dt class="text-slate-500">پله وزن</dt><dd>{{ isset($quote->metadata['weight_bracket_grams']) ? number_format($quote->metadata['weight_bracket_grams']).' گرم' : '—' }}</dd>
                        <dt class="text-slate-500">ناحیه مقصد</dt><dd class="font-mono">{{ $quote->metadata['destination_zone'] ?? '—' }}</dd>
                        <dt class="text-slate-500">اندازه انتخابی / مؤثر</dt><dd>{{ ($quote->metadata['package_size_selected'] ?? '—').' / '.($quote->metadata['package_size_effective'] ?? '—') }}</dd>
                        <dt class="text-slate-500">ارزش ورودی / مؤثر</dt><dd>{{ isset($quote->metadata['declared_value_used_rials']) ? number_format($quote->metadata['declared_value_input_rials']).' / '.number_format($quote->metadata['declared_value_used_rials']).' ریال' : '—' }}</dd>
                    </dl>
                </details>
            @else
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-7 text-center shadow-sm">
                    <h2 class="font-bold text-slate-900">هنوز محاسبه‌ای انجام نشده است</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">پس از تکمیل فرم، قیمت نهایی و ریز اجزای واقعی منطق افزونه در این قسمت نمایش داده می‌شود.</p>
                </section>
            @endif
        </aside>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cities = {{ Illuminate\Support\Js::from($citiesByProvince) }};

        const bindCitySelect = (provinceId, cityId) => {
            const province = document.getElementById(provinceId);
            const city = document.getElementById(cityId);

            const render = (selected = '') => {
                const provinceCities = cities[province.value] ?? {};
                city.innerHTML = '';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = province.value ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید';
                city.appendChild(placeholder);

                Object.entries(provinceCities).forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    option.selected = String(value) === String(selected);
                    city.appendChild(option);
                });
            };

            render(city.dataset.selected ?? '');
            province.addEventListener('change', () => render(''));
        };

        bindCitySelect('origin_province', 'origin_city');
        bindCitySelect('destination_province', 'destination_city');

        const summary = document.getElementById('validation-summary');
        if (summary) {
            summary.focus();
        }
    });
</script>
</body>
</html>
