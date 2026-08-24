# AGENTS.md

## Project Goal

هدف این تسک پیاده‌سازی مستقل سیستم **محاسبه هزینه ارسال مرسوله پستی** در پروژه Laravel موجود است.

منبع اصلی برای استخراج منطق، داده‌ها و فرمول‌های محاسبه:

1. یک افزونه WordPress که بعداً مسیر آن مشخص خواهد شد.
2. فایل PDF نرخ‌نامه پستی که بعداً مسیر آن مشخص خواهد شد.

پیاده‌سازی Laravel باید با مهندسی معکوس این دو منبع انجام شود.

در این مرحله سیستم فقط برای **تست و بررسی صحت محاسبات** ساخته می‌شود و نباید به Checkout، Cart، Order، Product یا سایر بخش‌های عملیاتی فروشگاه متصل شود.

---

# Scope

فقط دو سرویس پستی زیر باید پشتیبانی شوند:

* پست پیشتاز
* پست ویژه

سایر روش‌های ارسال موجود در افزونه WordPress یا نرخ‌نامه نباید پیاده‌سازی شوند، مگر اینکه صراحتاً در درخواست بعدی گفته شود.

---

# Critical Rule: Do Not Guess Shipping Logic

هیچ فرمول، نرخ، ضریب، محدوده وزنی، هزینه اضافی، مالیات، بیمه، بسته‌بندی یا قانون ارسال نباید بر اساس حدس یا دانش عمومی پیاده‌سازی شود.

Source of Truth فقط این موارد هستند:

* WordPress plugin
* PDF tariff/rate document

اگر بین PDF و افزونه تفاوت وجود داشت:

1. تفاوت را شناسایی کن.
2. مشخص کن افزونه دقیقاً چه کاری انجام می‌دهد.
3. مشخص کن PDF چه قانونی تعیین کرده است.
4. قبل از تغییر رفتار نسبت به افزونه، اختلاف را گزارش کن.
5. در صورت نبود دستور دیگر، رفتار اجرایی افزونه را برای محاسبه نهایی بازسازی کن، اما نرخ‌ها و قوانین PDF را نیز بررسی کن.

---

# Initial Workflow

زمانی که مسیر افزونه WordPress و PDF نرخ‌نامه ارائه شد، مستقیماً شروع به کدنویسی نکن.

ابتدا سیستم موجود را مهندسی معکوس کن.

ترتیب بررسی:

1. ساختار افزونه WordPress را بررسی کن.
2. فایل‌های مرتبط با shipping، pricing، tariff، rate، post، pishtaz، vijeh و موارد مشابه را پیدا کن.
3. مسیر اجرای محاسبه قیمت را پیدا کن.
4. ورودی‌های محاسبه را شناسایی کن.
5. فایل‌های data افزونه را پیدا کن.
6. وابستگی بین استان، شهر، وزن، ارزش کالا، نوع مرسوله، روش پرداخت، بسته‌بندی و نوع سرویس را مشخص کن.
7. تمام surchargeها و هزینه‌های جانبی را استخراج کن.
8. PDF نرخ‌نامه را بررسی کن.
9. جدول‌ها، ضرایب و بازه‌های وزنی PDF را با افزونه مقایسه کن.
10. سپس معماری Laravel را طراحی و پیاده‌سازی کن.

---

# WordPress Plugin Data

یکی از مهم‌ترین الزامات پروژه این است که داده‌هایی که در افزونه WordPress داخل فایل‌های `data` یا فایل‌های مشابه قرار دارند، دوباره داخل کد Laravel به صورت دستی hard-code نشوند.

به عنوان مثال اگر افزونه دارای فایل‌هایی برای موارد زیر باشد:

* استان‌ها
* شهرها
* کد شهر
* کد استان
* مناطق پستی
* نرخ‌ها
* ضرایب
* mappingها
* package types
* service codes

نباید اطلاعات آن فایل‌ها را داخل Service یا Controller کپی کنی.

## Required Behavior

تا جای ممکن Laravel باید **مستقیماً همان فایل‌های داده افزونه را include/load کند**.

هدف این است که اگر در آینده فایل‌های data افزونه به‌روزرسانی شدند، بتوان با جایگزین کردن فایل داده، اطلاعات جدید را بدون بازنویسی Service استفاده کرد.

بنابراین این کار ممنوع است:

```php
$provinces = [
    '1' => 'آذربایجان شرقی',
    '2' => 'آذربایجان غربی',
    // ...
];
```

اگر همین اطلاعات در فایل data افزونه موجود باشد.

به جای آن باید Adapter/Loader مناسبی ایجاد شود.

مثلاً معماری می‌تواند چیزی شبیه این باشد:

```text
app/
    Services/
        Shipping/
            PostShippingCalculator.php

            Data/
                WordpressShippingDataLoader.php

            DTO/
                ShippingQuoteRequest.php
                ShippingQuoteResult.php
```

این فقط یک مثال است.

ساختار نهایی باید بر اساس ساختار واقعی پروژه و افزونه انتخاب شود.

---

# Separation Between Data and Logic

داده و منطق محاسبه باید از یکدیگر جدا باشند.

به طور کلی:

```text
WordPress data files
        ↓
Data Loader / Adapter
        ↓
Normalized Data
        ↓
Laravel Shipping Calculator
        ↓
Quote Result
```

Service محاسبه نباید تبدیل به مجموعه بزرگی از arrayهای hard-coded شود.

---

# Reverse Engineering Requirement

هدف فقط رسیدن به یک عدد مشابه افزونه نیست.

باید منطق واقعی افزونه استخراج شود.

برای هر بخش محاسبه مشخص کن:

* ورودی چیست؟
* داده از کجا می‌آید؟
* فرمول چیست؟
* چه شرط‌هایی وجود دارد؟
* چه surchargeهایی اضافه می‌شوند؟
* ترتیب اعمال هزینه‌ها چیست؟
* rounding چگونه انجام می‌شود؟
* واحد پول چیست؟
* چه زمانی سرویس قابل ارسال نیست؟
* چه validationهایی وجود دارد؟

---

# Laravel Implementation

پیاده‌سازی باید متناسب با معماری فعلی Laravel پروژه باشد.

قبل از ایجاد ساختار جدید:

* نسخه Laravel را بررسی کن.
* conventionهای فعلی پروژه را بررسی کن.
* ساختار Controllerها را بررسی کن.
* Serviceهای موجود را بررسی کن.
* Route structure را بررسی کن.
* Blade/component conventions را بررسی کن.

ساختار جدید را با معماری موجود پروژه هماهنگ نگه دار.

از refactor غیرمرتبط خودداری کن.

---

# Isolation Requirement

این قابلیت فعلاً کاملاً مستقل است.

نباید به موارد زیر متصل شود:

* Cart
* Checkout
* Order
* Product
* Customer shipping address
* Payment gateway
* Existing shipping system

نباید behavior موجود سایت تغییر کند.

فقط یک صفحه تست مستقل ایجاد شود.

مثلاً یک route مستقل شبیه:

```text
/shipping-calculator-test
```

نام دقیق route باید با convention پروژه هماهنگ شود.

---

# Test View

یک Blade View مستقل برای تست محاسبه هزینه ارسال ایجاد کن.

صفحه باید RTL و مناسب زبان فارسی باشد.

ظاهر پیچیده لازم نیست.

هدف اصلی:

* وارد کردن ورودی
* اجرای محاسبه
* مشاهده نتیجه
* Debug کردن فرمول

---

# Test Form Inputs

فرم تست باید حداقل ورودی‌های زیر را داشته باشد.

## Origin Province

استان مبدأ.

لیست استان‌ها باید از data موجود در افزونه خوانده شود و نباید دوباره hard-code شود، اگر این داده در افزونه وجود دارد.

---

## Origin City

شهر مبدأ.

شهرها باید بر اساس استان انتخاب‌شده نمایش داده شوند.

اگر mapping استان → شهر در افزونه وجود دارد، دقیقاً از همان داده استفاده شود.

---

## Destination Province

استان مقصد.

---

## Destination City

شهر مقصد.

شهرها باید وابسته به استان مقصد باشند.

---

## Parcel Weight

وزن مرسوله بر حسب گرم.

حداکثر فعلی فرم:

```text
30,000 grams
```

Validation واقعی باید با محدودیت‌های افزونه و نرخ‌نامه نیز بررسی شود.

---

## Parcel Value

ارزش مرسوله بر حسب:

```text
ریال
```

واحد پول را بدون بررسی افزونه تغییر نده.

اگر افزونه در بخشی تومان و در بخشی ریال استفاده می‌کند، تبدیل واحد را دقیقاً شناسایی و مستند کن.

---

## Parcel Type

حداقل گزینه‌ها:

```text
عادی
شکستنی یا مایعات
```

بررسی کن افزونه برای مرسوله شکستنی/مایعات چه surcharge یا قانون خاصی اعمال می‌کند.

---

## Payment Type

گزینه‌ها:

```text
پرداخت آنلاین
پرداخت در محل
پس‌کرایه
ارسال رایگان
```

رفتار هر گزینه باید از افزونه استخراج شود.

صرفاً به دلیل نام گزینه درباره نحوه محاسبه آن حدس نزن.

---

# Package Size

گزینه‌های فرم:

```text
کارتن پستی سایز 1
کارتن پستی سایز 2
کارتن پستی سایز 3
کارتن پستی سایز 4
کارتن پستی سایز 5
کارتن پستی سایز 6
کارتن پستی سایز 7
کارتن پستی سایز 8
کارتن پستی سایز 9
بزرگتر از کارتن پستی سایز 9

پاکت جوف A5
پاکت جوف A4
پاکت جوف A3
پاکت جوف B5
پاکت جوف B4
```

اگر افزونه برای package type شناسه، code، weight، price یا mapping خاصی دارد از همان استفاده کن.

---

# Shipping Service

فرم فقط این دو گزینه را نمایش دهد:

```text
پست پیشتاز
پست ویژه
```

حتی اگر افزونه روش‌های ارسال بیشتری داشته باشد، در این implementation آن‌ها را وارد نکن.

---

# Clear Form

یک دکمه برای پاک کردن/reset فرم وجود داشته باشد.

---

# Province and City Data

لیست کامل استان‌ها و شهرها را از متن این فایل hard-code نکن.

نمونه‌هایی که در specification ارائه شده‌اند فقط برای توضیح UI هستند.

Source of Truth باید داده‌های خود افزونه باشد.

به خصوص برای شهرها، spelling، شناسه‌ها و mappingها باید از فایل افزونه استخراج شوند.

---

# Calculation Result

نتیجه محاسبه در صفحه تست باید حداقل شامل این موارد باشد:

```text
service
final_price
currency
available
```

در صورت امکان برای Debug breakdown هزینه نیز نمایش داده شود.

مثلاً:

```text
Base postage
Weight surcharge
Destination/zone surcharge
Declared value / insurance
Packaging
Fragile/liquid surcharge
Payment-related cost
Tax/VAT
Other charges
Final price
```

اما فقط آیتم‌هایی را نمایش بده که واقعاً در منطق افزونه یا PDF وجود دارند.

آیتم ساختگی ایجاد نکن.

---

# Debug Mode

در صفحه تست بهتر است breakdown محاسبه قابل مشاهده باشد تا بتوان نتیجه Laravel را با افزونه WordPress مقایسه کرد.

برای مثال result object می‌تواند مفهومی مشابه این داشته باشد:

```php
[
    'service' => 'pishtaz',
    'available' => true,
    'currency' => 'IRR',

    'breakdown' => [
        // calculated components
    ],

    'total' => 0,
]
```

این structure فقط پیشنهاد است.

اگر معماری بهتر با پروژه وجود دارد از آن استفاده کن.

---

# Precision and Rounding

یکی از موارد مهم در مهندسی معکوس:

* integer/float usage
* rounding
* floor
* ceil
* weight steps
* currency conversion

را دقیقاً بررسی کن.

اختلاف چند ریال یا چند تومان نیز ممکن است نشانه تفاوت در ترتیب محاسبات باشد.

نتیجه را صرفاً با اضافه کردن correction number تطبیق نده.

دلیل اختلاف را پیدا کن.

---

# Weight Rules

بازه‌های وزن باید مستقیماً از افزونه و PDF استخراج شوند.

مواردی مانند:

```text
تا 500 گرم
500 تا 1000 گرم
هر 1000 گرم اضافه
...
```

نباید حدس زده شوند.

اگر افزونه برای وزن از `ceil` یا bracket خاص استفاده می‌کند، همان رفتار بازسازی شود.

---

# Destination Rules

بررسی کن قیمت به کدام موارد وابسته است:

* داخل شهری
* درون استانی
* استان همجوار
* استان غیرهمجوار
* منطقه پستی
* distance zone
* city code
* province code

این دسته‌بندی را از افزونه استخراج کن.

بر اساس نام استان‌ها خودت الگوریتم جغرافیایی نساز مگر افزونه دقیقاً چنین کاری انجام دهد.

---

# Validation

Request validation باید حداقل موارد زیر را پوشش دهد:

* origin province required
* origin city required
* destination province required
* destination city required
* weight required
* weight numeric
* weight > 0
* declared value numeric
* package type valid
* parcel type valid
* payment type valid
* service valid

مقادیر enum باید از domain واقعی استخراج شوند.

---

# Avoid Database Unless Required

در این مرحله برای این feature migration، table یا Model جدید ایجاد نکن مگر اینکه واقعاً برای بازسازی منطق افزونه ضروری باشد.

ترجیح فعلی:

```text
stateless calculation service
```

یعنی:

```text
Input → Calculate → Result
```

نه ذخیره دائمی quote.

---

# No Premature Integration

در این مرحله موارد زیر را انجام نده:

```text
Checkout integration
Cart integration
Order integration
Shipping Method integration
Admin settings
Caching system
Queue
API endpoint for external clients
Database persistence
```

مگر اینکه بعداً صراحتاً درخواست شوند.

---

# Tests

برای منطق محاسبه تست ایجاد کن.

تمرکز اصلی تست‌ها باید روی calculator/service باشد نه UI.

پس از استخراج الگوریتم، چند fixture واقعی از افزونه ایجاد کن.

برای نمونه:

```text
origin
destination
weight
value
parcel type
payment type
package
service
expected price
```

Expected price باید از اجرای منطق مرجع یا محاسبه معتبر افزونه/PDF به دست آمده باشد.

عدد ساختگی به عنوان expected result استفاده نکن.

---

# Comparison Tests

بعد از پیاده‌سازی، حداقل چند سناریو از این دسته‌ها بررسی شوند:

```text
low weight
high weight
same province
different province
different postal zone
normal parcel
fragile/liquid parcel
different package sizes
online payment
COD / payment variants
Pishtaz
Vijeh
```

فقط سناریوهایی را اجرا کن که واقعاً توسط افزونه پشتیبانی می‌شوند.

---

# Code Quality

کد باید:

* خوانا باشد
* مسئولیت‌ها را جدا کند
* تست‌پذیر باشد
* از magic number تا حد امکان جلوگیری کند
* داده را از logic جدا کند
* از duplicate logic جلوگیری کند

از overengineering خودداری کن.

برای این مرحله یک calculator مستقل کافی است.

---

# Comments

کامنت فقط برای توضیح منطق غیرواضح نرخ‌نامه یا رفتار خاص افزونه استفاده شود.

کامنت‌هایی شبیه:

```php
// calculate price
```

ارزش خاصی ندارند.

اما اگر رفتار عجیب افزونه وجود داشت، توضیح آن مفید است:

```php
// The original WordPress plugin rounds the weight up to the next
// tariff bracket before applying the service coefficient.
```

---

# Preserve Traceability

تا جای ممکن مشخص باشد هر قانون از کجا آمده است.

برای قوانین مهم می‌توان کامنت کوتاهی گذاشت که به:

```text
WordPress plugin file
PDF tariff section/table
```

اشاره کند.

مثلاً:

```php
// Source: wordpress-plugin/.../calculator.php
```

یا:

```php
// Source: postal tariff PDF - Pishtaz weight table
```

از وابستگی به شماره صفحه PDF فقط در صورتی استفاده کن که شماره صفحات قابل اتکا باشد.

---

# Important Data Rule

اطلاعات موجود در فایل‌های `data` افزونه را داخل Service دوباره ننویس.

یعنی این:

```text
Plugin data
→ copied manually
→ Laravel PHP array
```

مطلوب نیست.

هدف این است:

```text
Plugin data file
→ Adapter / Loader
→ Laravel
```

تا بتوان فایل data افزونه را در آینده به‌روزرسانی کرد.

اگر فرمت فایل‌های data مستقیماً قابل استفاده در Laravel نیست، یک Adapter بنویس.

Adapter باید داده را normalize کند ولی dataset را duplicate نکند.

---

# Security

فایل‌های افزونه WordPress را execute نکن مگر اینکه مشخص شود safe و لازم است.

اگر فایل data حاوی PHP executable code است، ابتدا ساختار آن را بررسی کن.

اگر include مستقیم باعث اجرای bootstrap یا side effect افزونه WordPress می‌شود، آن را مستقیماً include نکن.

در چنین شرایطی یک loader امن طراحی کن که فقط data مورد نیاز را بخواند.

---

# WordPress Dependencies

Laravel نباید برای کار کردن به runtime وردپرس وابسته شود.

یعنی calculator نباید نیازمند مواردی مثل:

```php
add_action()
get_option()
WC()
wp_remote_get()
wpdb
WooCommerce classes
WordPress bootstrap
```

باشد.

اگر منطق افزونه به این APIها وابسته بود، فقط business logic مورد نیاز را استخراج و در Laravel بازسازی کن.

---

# External APIs

اگر افزونه برای محاسبه نرخ به API خارجی درخواست می‌زند:

قبل از پیاده‌سازی مشخص کن:

* API چیست؟
* endpoint چیست؟
* کدام بخش قیمت local است؟
* کدام بخش remote است؟
* authentication دارد یا خیر؟
* PDF چه نقشی دارد؟

بدون بررسی، API call جدید ایجاد نکن.

---

# Currency

واحدهای زیر ممکن است در منابع دیده شوند:

```text
IRR / ریال
تومان
```

واحد اصلی calculator باید بعد از بررسی افزونه مشخص شود.

تبدیل تومان ↔ ریال فقط در یک نقطه مشخص انجام شود.

تبدیل واحد را در چند Service یا View پراکنده نکن.

---

# Naming

برای نام‌های داخلی انگلیسی و ثابت استفاده کن.

مثلاً:

```text
pishtaz
vijeh
normal
fragile
online
cod
```

ولی mapping دقیق identifiers باید بر اساس افزونه تعیین شود.

UI می‌تواند فارسی باشد.

---

# Suggested Domain

در صورت سازگاری با پروژه، domain می‌تواند حول مفاهیم زیر طراحی شود:

```text
ShippingQuoteRequest
ShippingQuoteResult
PostShippingCalculator
WordpressShippingDataLoader
```

اما قبل از ساخت آن‌ها، ساختار پروژه موجود را بررسی کن.

این نام‌ها requirement قطعی نیستند.

---

# Test Page Behavior

Flow صفحه تست:

```text
Select origin province
        ↓
Select origin city
        ↓
Select destination province
        ↓
Select destination city
        ↓
Enter weight
        ↓
Enter declared value
        ↓
Select parcel type
        ↓
Select payment type
        ↓
Select package size
        ↓
Select Pishtaz or Vijeh
        ↓
Calculate
        ↓
Show result + breakdown
```

---

# UI Is Not The Priority

روی طراحی ظاهری بیش از حد زمان صرف نکن.

هدف این صفحه:

```text
Reverse engineering validation
Calculation testing
Comparison
Debugging
```

است.

از CSS/JavaScript framework جدید صرفاً برای این صفحه اضافه نکن.

از امکانات فعلی پروژه استفاده کن.

---

# Do Not Modify Unrelated Code

اصل مهم:

```text
minimum necessary changes
```

هیچ فایل نامرتبطی را refactor نکن.

هیچ package جدیدی نصب نکن مگر واقعاً ضروری باشد.

هیچ dependency را upgrade نکن.

---

# Before Implementation Report

پس از دریافت افزونه و PDF و قبل از تغییر جدی کد، ابتدا یک گزارش کوتاه از یافته‌ها ارائه کن.

گزارش باید شامل این موارد باشد:

```text
Relevant WordPress files
Relevant data files
Main calculation entry point
Pishtaz calculation flow
Vijeh calculation flow
Weight rules
Location/zone rules
Package rules
Declared value rules
Payment rules
Extra charges
Tax/VAT
Currency
Rounding
Relevant PDF tables
Differences between plugin and PDF
```

بعد از شناخت کافی، implementation را انجام بده.

---

# After Implementation Report

پس از اتمام کار، گزارش بده:

1. چه فایل‌هایی ایجاد شدند.
2. چه فایل‌هایی تغییر کردند.
3. calculator کجا قرار دارد.
4. data loader چگونه کار می‌کند.
5. route تست چیست.
6. هر سرویس چگونه محاسبه می‌شود.
7. چه قسمت‌هایی از افزونه مرجع بودند.
8. چه قسمت‌هایی از PDF مرجع بودند.
9. چه اختلاف‌هایی بین افزونه و PDF پیدا شد.
10. چه تست‌هایی اجرا شدند.
11. آیا نتایج Laravel با مرجع تطبیق دارند یا خیر.

---

# Definition of Done

این مرحله زمانی کامل است که:

* افزونه WordPress بررسی شده باشد.
* PDF نرخ‌نامه بررسی شده باشد.
* منطق Pishtaz استخراج شده باشد.
* منطق Vijeh استخراج شده باشد.
* داده‌های افزونه duplicate/hard-code نشده باشند.
* calculator مستقل Laravel ساخته شده باشد.
* صفحه تست مستقل وجود داشته باشد.
* مبدا و مقصد قابل انتخاب باشند.
* شهرها بر اساس استان قابل انتخاب باشند.
* وزن قابل وارد کردن باشد.
* ارزش مرسوله قابل وارد کردن باشد.
* نوع مرسوله قابل انتخاب باشد.
* نوع پرداخت قابل انتخاب باشد.
* اندازه بسته قابل انتخاب باشد.
* Pishtaz/Vijeh قابل انتخاب باشد.
* هزینه محاسبه شود.
* breakdown قابل بررسی باشد.
* validation وجود داشته باشد.
* تست‌های calculator وجود داشته باشند.
* هیچ اتصال جدیدی به Checkout/Cart/Order ایجاد نشده باشد.
* رفتار بخش‌های دیگر سایت تغییر نکرده باشد.

---

# Current Status

در حال حاضر مسیر منابع هنوز ارائه نشده است.

بنابراین تا زمانی که مسیرهای زیر ارائه نشده‌اند، درباره فرمول نهایی حمل‌ونقل حدس نزن:

```text
WORDPRESS_PLUGIN_PATH = D:\uni-shop-project\ecommerce-main\codex\plugin
POSTAL_TARIFF_PDF_PATH =D:\uni-shop-project\ecommerce-main\codex
```

پس از ارائه این دو مسیر، ابتدا منابع را بررسی و سپس implementation را شروع کن.
