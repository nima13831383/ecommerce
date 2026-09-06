<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">وضعیت کش</x-slot>

            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between"><dt>بک‌اند فعال</dt><dd>{{ $store }}</dd></div>
                <div class="flex justify-between"><dt>آمادگی Redis</dt><dd>{{ $redisReady ? 'آماده' : 'در دسترس نیست' }}</dd></div>
                <div class="flex justify-between"><dt>نسل فعال محصولات</dt><dd>{{ $productGeneration }}</dd></div>
                <div class="flex justify-between"><dt>نسل‌های نگهداری‌شده محصولات</dt><dd>{{ implode('، ', $productRetainedGenerations) }}</dd></div>
                <div class="flex justify-between"><dt>نسل فعال مقالات</dt><dd>{{ $blogGeneration }}</dd></div>
                <div class="flex justify-between"><dt>نسل‌های نگهداری‌شده مقالات</dt><dd>{{ implode('، ', $blogRetainedGenerations) }}</dd></div>
                <div class="flex justify-between"><dt>آخرین پاکسازی</dt><dd>{{ $lastPrune?->finished_at?->toIso8601String() ?? 'هنوز انجام نشده' }}</dd></div>
                <div class="flex justify-between"><dt>بازسازی پیشگیرانه</dt><dd>{{ $refreshAheadEnabled ? "فعال ({$refreshAheadPercent}٪ پایانی)" : 'غیرفعال' }}</dd></div>
            </dl>

            <p class="mt-4 text-sm text-gray-500">ویرایش محصول یا مقاله تا انقضای کش یا بازسازی دستی ممکن است در ویترین باقی بماند.</p>
            @if(($observability['builder_succeeded']['context']['duration_ms'] ?? null) !== null)
                <p class="mt-2 text-sm text-gray-500">آخرین زمان ساخت: {{ $observability['builder_succeeded']['context']['duration_ms'] }}ms</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">صف و Horizon</x-slot>

            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between"><dt>اتصال صف</dt><dd>{{ $queueConnection }}</dd></div>
                <div class="flex justify-between"><dt>Horizon</dt><dd>{{ $horizonAvailable ? 'فعال' : 'غیرفعال' }}</dd></div>
            </dl>

            @if(! $horizonAvailable)
                <p class="mt-4 text-sm text-warning-600">Horizon نیازمند Redis Queue است و در محیط فعلی فعال نیست.</p>
            @else
                <x-filament::button class="mt-4" tag="a" href="{{ url('/horizon') }}">باز کردن Horizon</x-filament::button>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">آخرین بازسازی‌ها</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead><tr><th class="p-2">شناسه</th><th class="p-2">دامنه</th><th class="p-2">وضعیت</th><th class="p-2">نسل</th><th class="p-2">درخواست</th></tr></thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr class="border-t"><td class="p-2">{{ $run->id }}</td><td class="p-2">{{ $run->domain->value }}</td><td class="p-2">{{ $run->status->value }}</td><td class="p-2">{{ $run->target_generation }}</td><td class="p-2">{{ $run->requested_at?->toIso8601String() }}</td></tr>
                    @empty
                        <tr><td class="p-3" colspan="5">هنوز بازسازی‌ای ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
