@extends('storefront.layouts.account')

@section('account-content')
    @include('storefront.partials.account-sidebar')
    <section class="account-content">
        <div class="account-heading"><div><h1>آدرس‌های من</h1><p>آدرس‌های ذخیره‌شده برای ارسال سفارش‌ها.</p></div><button class="account-button account-button--pink" type="button" data-address-add>افزودن آدرس جدید</button></div>
        @if (session('status')) <p class="form-feedback" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <div class="form-error" role="alert">{{ $errors->first() }}</div> @endif
        <div class="address-list">
            @forelse ($addresses as $address)
                <article class="address-card {{ $address->is_default ? 'is-default' : '' }}">
                    <div class="address-card__head"><h2>{{ $address->first_name }} {{ $address->last_name }}</h2>@if($address->is_default)<span class="address-card__default">پیش‌فرض</span>@endif</div>
                    <p>{{ $address->mobile }}</p>
                    @if($addressLocations[$address->id])<p>{{ $addressLocations[$address->id]['province_name'] }}، {{ $addressLocations[$address->id]['city_name'] }}</p>@endif
                    <p>{{ $address->address_line }}</p>
                    @if($address->postal_code)<p>کد پستی: {{ $address->postal_code }}</p>@endif
                    <div class="address-card__actions">
                        <a class="text-button" href="{{ route('storefront.account.addresses', ['edit' => $address->id]) }}">ویرایش</a>
                        <form method="POST" action="{{ route('storefront.account.addresses.destroy', $address) }}">@csrf @method('DELETE')<button class="text-button text-button--danger" type="submit">حذف</button></form>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h2>هنوز آدرسی ثبت نکرده‌اید.</h2></div>
            @endforelse
        </div>
        <section class="account-card address-form-panel {{ $editing || $errors->any() ? 'is-open' : '' }}" data-address-form-panel {{ $editing || $errors->any() ? '' : 'hidden' }}>
            <h2>{{ $editing ? 'ویرایش آدرس' : 'افزودن آدرس جدید' }}</h2>
            <form class="account-form" method="POST" action="{{ $editing ? route('storefront.account.addresses.update', $editing) : route('storefront.account.addresses.store') }}">
                @csrf @if($editing) @method('PATCH') @endif
                <div class="account-form__grid">
                    <label><span>نوع آدرس</span><select name="type"><option value="both">هر دو</option><option value="shipping">ارسال</option><option value="billing">صورتحساب</option></select></label>
                    <label><span>نام *</span><input name="first_name" value="{{ old('first_name', $editing?->first_name) }}" required></label>
                    <label><span>نام خانوادگی *</span><input name="last_name" value="{{ old('last_name', $editing?->last_name) }}" required></label>
                    <label><span>موبایل *</span><input name="mobile" value="{{ old('mobile', $editing?->mobile) }}" inputmode="numeric" required></label>
                    <label><span>استان</span><select name="province_id" data-province-select><option value="">انتخاب استان</option>@foreach($provinces as $id => $name)<option value="{{ $id }}" @selected(old('province_id', $editing?->province_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label><span>شهر</span><select name="city_id" data-city-select><option value="">انتخاب شهر</option>@foreach($cities as $id => $name)<option value="{{ $id }}" @selected(old('city_id', $editing?->city_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="field-wide"><span>آدرس کامل *</span><textarea name="address_line" rows="3" required>{{ old('address_line', $editing?->address_line) }}</textarea></label>
                    <label><span>کد پستی</span><input name="postal_code" value="{{ old('postal_code', $editing?->postal_code) }}" inputmode="numeric"></label>
                    <label><span>پلاک</span><input name="plaque" value="{{ old('plaque', $editing?->plaque) }}"></label>
                    <label><span>واحد</span><input name="unit" value="{{ old('unit', $editing?->unit) }}"></label>
                    <label><span><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $editing?->is_default))> آدرس پیش‌فرض</span></label>
                </div>
                <button class="account-button account-button--pink" type="submit">{{ $editing ? 'ذخیره تغییرات' : 'افزودن آدرس' }}</button><a class="account-button account-button--light" href="{{ route('storefront.account.addresses') }}" data-address-cancel>انصراف</a>
            </form>
        </section>
    </section>
@endsection

@push('scripts')
<script>
document.querySelector('[data-address-add]')?.addEventListener('click', function () { const panel = document.querySelector('[data-address-form-panel]'); panel.hidden = false; panel.classList.add('is-open'); });
document.querySelector('[data-province-select]')?.addEventListener('change', async function () {
    const city = document.querySelector('[data-city-select]');
    city.innerHTML = '<option value="">در حال بارگذاری...</option>';
    if (!this.value) { city.innerHTML = '<option value="">انتخاب شهر</option>'; return; }
    try {
        const response = await fetch('{{ url('/locations/provinces') }}/' + this.value + '/cities', {headers: {'Accept': 'application/json'}});
        const payload = await response.json();
        city.innerHTML = '<option value="">انتخاب شهر</option>' + payload.data.map(item => '<option value="' + item.id + '">' + item.name + '</option>').join('');
    } catch (_) { city.innerHTML = '<option value="">خطا؛ دوباره تلاش کنید</option>'; }
});
</script>
@endpush
