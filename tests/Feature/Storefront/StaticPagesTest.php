<?php

test('static storefront pages render the raw design content and real navigation', function (): void {
    $this->get(route('storefront.about'))->assertOk()->assertSee('داستان ما')->assertSee(route('storefront.blog.index'));
    $this->get(route('storefront.contact'))->assertOk()->assertSee('تماس با ما')->assertSee('فرم پیام در حال حاضر');
    $this->get(route('storefront.faq'))->assertOk()->assertSee('سوالات متداول')->assertSee('چگونه سفارش خود را پیگیری کنم؟');
});

test('web not found uses storefront html while api not found remains json', function (): void {
    $this->get('/route-that-does-not-exist')->assertNotFound()->assertSee('صفحه پیدا نشد')->assertSee('lang="fa" dir="rtl"', false);
    $this->get('/api/v1/route-that-does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found');
});
