<?php

beforeEach(function () {
    $this->withoutVite();
});

it('shows the isolated persian rtl calculator page', function () {
    $this->get(route('shipping-calculator-test.show'))
        ->assertOk()
        ->assertSee('محاسبه آزمایشی هزینه ارسال پستی')
        ->assertSee('پست پیشتاز')
        ->assertSee('پست ویژه')
        ->assertSee('dir="rtl"', false);
});

it('validates province and city relationships with persian feedback', function () {
    $this->post(route('shipping-calculator-test.calculate'), [
        'origin_province' => 1,
        'origin_city' => 31,
        'destination_province' => 1,
        'destination_city' => 1,
        'weight' => 500,
        'declared_value' => 50_000_000,
        'parcel_type' => 'normal',
        'payment_type' => 'online',
        'package_size' => 1,
        'service' => 'pishtaz',
    ])->assertSessionHasErrors([
        'origin_city' => 'شهر مبدأ با استان مبدأ انتخاب‌شده همخوانی ندارد.',
    ]);
});

it('renders the supplied Tapin reference quote and source breakdown', function () {
    $this->post(route('shipping-calculator-test.calculate'), [
        'origin_province' => 2,
        'origin_city' => 4391,
        'destination_province' => 27,
        'destination_city' => 6971,
        'weight' => 5000,
        'declared_value' => 50_000_000,
        'parcel_type' => 'normal',
        'payment_type' => 'online',
        'package_size' => 6,
        'service' => 'pishtaz',
    ])->assertOk()
        ->assertSee('2,326,500', false)
        ->assertSee('نرخ پایه تعرفه پست')
        ->assertSee('مجموع هزینه خدمات')
        ->assertSee('تعدیل جمع کل مرجع تاپین')
        ->assertSee('ریال (IRR)');
});
