<?php

test('static storefront pages render the raw design content and real navigation', function (): void {
    $this->get(route('storefront.about'))->assertOk()->assertSee('داستان ما')->assertSee(route('storefront.blog.index'));
    $this->get(route('storefront.contact'))->assertOk()->assertSee('تماس با ما')->assertSee('فرم پیام در حال حاضر');
    $this->get(route('storefront.faq'))->assertOk()->assertSee('سوالات متداول')->assertSee('چگونه سفارش خود را پیگیری کنم؟');
});

test('web not found uses storefront html while api not found remains json', function (): void {
    $this->get('/route-that-does-not-exist')->assertNotFound()->assertSee('صفحه مورد نظر پیدا نشد')->assertSee('lang="fa" dir="rtl"', false);
    $this->get('/api/v1/route-that-does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found');
});

test('shared newsletter presentation is used by the homepage and not-found page', function (): void {
    $home = $this->get('/')->assertOk();
    $notFound = $this->get('/route-that-does-not-exist')->assertNotFound();

    $home->assertSee('در خبرنامه لوکسیرا عضو شوید')->assertSee('not-found-newsletter', false);
    $notFound->assertSee('در خبرنامه لوکسیرا عضو شوید')->assertSee('not-found-newsletter', false);
    expect(file_get_contents(base_path('public/storefront/assets/js/category/filter-drawer.js')))
        ->not->toContain('data-filter-apply')
        ->toContain('data-filter-reset');
});
