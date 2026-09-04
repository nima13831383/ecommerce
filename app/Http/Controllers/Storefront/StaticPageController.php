<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function about(): View
    {
        return view('storefront.static.about', ['title' => 'درباره ما | لوکسیر']);
    }

    public function contact(): View
    {
        return view('storefront.static.contact', ['title' => 'تماس با ما | لوکسیر']);
    }

    public function faq(): View
    {
        return view('storefront.static.faq', ['title' => 'پرسش‌های متداول | لوکسیر']);
    }
}
