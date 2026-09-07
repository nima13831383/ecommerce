# گزارش ارزیابی مقیاس‌پذیری، ظرفیت و ایمنی همزمانی فروشگاه

## خلاصه مدیریتی

این گزارش بر پایه کد و تست‌های موجود در مخزن تهیه شده است. هسته تجارت الکترونیک از نظر صحت همزمانی وضعیت خوبی دارد: موجودی، سفارش، پرداخت، کوپن، ارسال، اعلان، idempotency و کش فروشگاه با تراکنش، قفل ردیف، محدودیت یکتا، قفل توزیع‌شده یا کلید idempotency محافظت می‌شوند. آزمون‌های واقعی MySQL برای مسیرهای اصلی به‌صورت ترتیبی موفق شدند.

با این حال، محیط فعلی محیط سنجش ظرفیت Production نیست: سیستم محلی Windows، PHP 8.2.12، کش و صف پیش‌فرض database است و Redis، Horizon اجرایی، Linux، Nginx/PHP-FPM و داده‌ مقیاس Production در دسترس نیست. بنابراین هیچ عدد معتبر و قابل تعهدی برای RPS یا تعداد کاربر همزمان اعلام نمی‌شود.

نتیجه: معماری برای مرحله تست Production-like آماده است، اما گلوگاه‌های شناخته‌شده شامل جست‌وجوی `%LIKE%`، زیرپرس‌وجوهای قیمت/موجودی زنده، بودجه اتصال دیتابیس، تأخیر سرویس‌های پرداخت و پیامک و نبود تست ظرفیت واقعی هستند.

`SCALABILITY READINESS: GOOD WITH IDENTIFIED BOTTLENECKS`

`RACE CONDITION SAFETY: STRONG FOR VERIFIED CORE FLOWS; PARTIAL COVERAGE FOR CART, OTP, SLUG, AND REDIS RUNTIME`

`PRODUCTION CAPACITY CERTIFICATION: PENDING PRODUCTION-LIKE LOAD TEST`

## تعریف ظرفیت کاربر

کاربر ذخیره‌شده، کاربر فعال، کاربر همزمان، درخواست همزمان، RPS و throughput ثبت سفارش معیارهای متفاوت‌اند. برای برنامه‌ریزی، اگر ۲۰٬۰۰۰ کاربر فعال در هر دقیقه سه درخواست ایجاد کنند: `۲۰٬۰۰۰ × ۳ ÷ ۶۰ = ۱٬۰۰۰ RPS`؛ با ضریب اوج ۲، مقدار برنامه‌ریزی‌شده ۲٬۰۰۰ RPS است. این فقط مثال فرمول است و نتیجه اندازه‌گیری پروژه نیست.

## معماری فعلی

Laravel 12 و PHP 8.2 با Blade SSR، Filament، MySQL، Eloquent، تراکنش‌های Laravel، session/cache/queue مبتنی بر database در محیط محلی و Redis اختیاری در Production استفاده می‌شود. سرویس‌های اصلی عبارت‌اند از ProductCatalogQuery، ProductPriceResolver، CartService، CouponService، InventoryService، CheckoutService، OrderService، PaymentService، ShipmentService و CustomerOtpService.

مسیر عمومی معمولاً چنین است: Controller و Request → سرویس دامنه یا Query → Eloquent و دیتابیس → Blade یا API Resource. کش StorefrontQueryCache کلیدهای canonical، نسل‌های build-before-swap، stale-while-revalidate، قفل توزیع‌شده و fallback نسل قبلی دارد.

## پیش‌فرض‌های استقرار

`.env.example` محیط محلی را با MySQL، session database، cache database و queue database تعریف می‌کند. `DEPLOYMENT.md` برای Production استفاده از Redis، Horizon روی Linux، scheduler با `withoutOverlapping` و `onOneServer`، رسانه اشتراکی و workerهای supervised را توصیه می‌کند. توپولوژی واقعی Production در مخزن موجود نیست.

## ظرفیت کاربران و درخواست‌ها

شناسه‌های کاربران و روابط از نوع bigint هستند و unique email/mobile و indexهای لازم دارند؛ این موضوع امکان نگهداری ۱۰۰ هزار تا میلیون‌ها ردیف را فراهم می‌کند، ولی ظرفیت واقعی به RAM، اندازه ایندکس، cleanup نشست، backup و latency وابسته است.

هیچ سقف معتبر برای کاربر همزمان یا RPS وجود ندارد. `php artisan serve` و Windows فقط برای توسعه‌اند. مقدار زیر عمداً تأییدنشده است:

`PRODUCTION RPS CAPACITY: NOT YET CERTIFIABLE`

### جدول مسیرها

| مسیر | وضعیت معماری | RPS اندازه‌گیری‌شده |
|---|---|---:|
| صفحه اصلی و آرشیو cached | کش SWR و lock | اندازه‌گیری نشده |
| جزئیات محصول و مقاله | cache/detail refresh | اندازه‌گیری نشده |
| جست‌وجو و فیلتر | query زنده برای مسیرهای حساس | اندازه‌گیری نشده |
| سبد و mutation | تراکنش و قفل سبد | اندازه‌گیری نشده |
| checkout و order | تراکنش، رزرو و idempotency | اندازه‌گیری نشده |
| initiate/verify پرداخت | قفل DB و provider همزمان | اندازه‌گیری نشده |

## محیط محلی و نتایج

PHP نسخه 8.2.12 و افزونه pdo_mysql مشاهده شد. اتصال MySQL روی `127.0.0.1:3306` برقرار بود. افزونه‌های pcntl، posix و redis در خروجی PHP موجود نبودند؛ جزئیات CPU/RAM به علت محدودیت دسترسی WMI قابل خواندن نبود. دیتابیس آزمون ایزوله `ecommerce_testing` استفاده شد.

هیچ HTTP load benchmark اجرا نشد؛ p50/p95/p99، throughput پایدار و error rate برای Production نامشخص‌اند. زمان تست‌های همزمانی صرفاً evidence صحت است، نه benchmark ظرفیت.

## کش و مقیاس‌پذیری

کلیدها با SHA-256 و پارامترهای normalize‌شده ساخته می‌شوند. hit تازه مستقیم پاسخ می‌گیرد؛ در stale فقط یک lock بازسازی می‌کند و سایر درخواست‌ها stale را می‌گیرند؛ hard miss با reread محدود و fallback نسل قبلی به 503 کنترل‌شده می‌رسد. قبل از write مالکیت lock بررسی می‌شود.

کلیدهای search/filter/page پرشمار هستند و باید TTL، حافظه و eviction پایش شود. مسیرهای قیمت و موجودی زنده cache نمی‌شوند و وضعیت جاری را دوباره می‌خوانند. داده تراکنشی و مشتری نباید stale-cache truth باشد.

## Redis و Horizon

Redis برای cache، lock، queue و session در تنظیمات پشتیبانی می‌شود و connectionهای cache و queue جدا هستند. runtime Redis محلی در دسترس نبود؛ latency، failover، eviction و lockهای Redis هنوز runtime-verified نیستند.

Horizon سه supervisor دارد: commerce، cache-rebuild و cache-refresh. Production پیش‌فرض ۱۰ process برای commerce، دو process برای rebuild و یک process برای refresh دارد. jobها retry، timeout، backoff و idempotency دارند و `retry_after=900` از timeout بازسازی ۶۰۰ ثانیه بیشتر است. این اعداد نقطه شروع‌اند، نه ظرفیت اثبات‌شده.

## دیتابیس و EXPLAIN

محصولات unique slug/SKU و indexهای status/feature، type/stock، price و published time دارند. سفارش‌ها order number و idempotency key یکتا، index کاربر/وضعیت و زمان ایجاد دارند. cart line، coupon usage، shipment و notification نیز محدودیت‌های composite/unique دارند.

ریسک اصلی: جست‌وجوی `%term%` روی نام و توضیح، ایندکس B-tree معمولی را استفاده نمی‌کند. محاسبه effective price و in-stock با correlated subquery روی variation و reservation انجام می‌شود. باید روی دیتای واقعی EXPLAIN برای newest، taxonomy، search، price sort/filter و stock اجرا شود.

## رشد کاتالوگ و کاربران

در ۱۰ هزار محصول، query و کش فعلی قابل مدیریت به نظر می‌رسد. در ۱۰۰ هزار محصول search، قیمت/موجودی زنده و cardinality کش به bottleneck محتمل تبدیل می‌شوند. در یک میلیون محصول، read model یا search index و rebuild batch لازم خواهد بود؛ هیچ benchmark برای این اندازه انجام نشده است.

جدول users از نظر نوع کلید برای ۱۰۰ هزار، یک میلیون و ده میلیون ردیف مناسب است، اما session retention، admin counts، backup و archival باید اندازه‌گیری و برنامه‌ریزی شود.

## نشست، فایل و Load Balancer

session database با دیتابیس مشترک برای چند node سازگار است و Redis session نیز قابل تنظیم است. فایل local روی چند node کافی نیست؛ Production به object/shared storage و در صورت نیاز CDN نیاز دارد. `storage:link` باید در استقرار اجرا شود. طراحی بدون state محلی و با DB/Redis/queue مرکزی برای load balancer مناسب است، ولی runtime چندنودی تست نشده است.

## بودجه اتصال دیتابیس

pool یا سقف ثابت اتصال در PHP تعریف نشده است. بودجه باید برای `web workers + queue workers + scheduler + admin/maintenance` جمع شود و از `max_connections` با headroom کمتر باشد. افزایش worker بدون سنجش wait time و DB CPU می‌تواند throughput را کاهش دهد.

## ماتریس Race Condition

| حوزه | وضعیت | شاهد |
|---|---|---|
| Inventory | PROTECTED | transaction، lock مالک و reservation، آزمون MySQL |
| Cart | PARTIALLY PROTECTED | lock سبد/line و unique line؛ آزمون چندپردازه اختصاصی یافت نشد |
| Checkout/Order | PROTECTED | idempotency، fingerprint، unique key، reservation، آزمون MySQL |
| Coupon | PROTECTED | lock coupon/usage و unique coupon-order، آزمون contention |
| Payment | PROTECTED | lock payment/order و idempotent initiation/verify |
| Shipment | PROTECTED | lock order/shipment و one-shipment unique |
| Notification | PROTECTED | unique intent و transition lock |
| OTP | PARTIALLY PROTECTED | hash، expiry، attempt/resend limit؛ آزمون race چندپردازه ندارد |
| Slug | PARTIALLY PROTECTED | unique DB و suffix service؛ آزمون همزمانی ندارد |
| Storefront cache | PROTECTED WITH DEPLOYMENT LIMIT | lock/SWR/generation؛ Redis runtime تست نشده |

## شواهد واقعی MySQL

تست‌ها به‌صورت ترتیبی روی `ecommerce_testing` اجرا شدند و همگی موفق بودند: `ConcurrencyHarnessTest` (۱ تست، ۷ assertion)، `InventoryConcurrencyTest` (۱، ۷)، `CouponRedemptionConcurrencyTest` (۴، ۵۱)، `CheckoutIdempotencyConcurrencyTest` (۱، ۲۴)، `CheckoutIdempotencyConflictConcurrencyTest` (۵، ۳۵)، `CancellationPaymentConcurrencyTest` (۱، ۳۸)، `PaymentVerificationConcurrencyTest` (۲، ۴۳)، `ShipmentEnsureConcurrencyTest` (۲، ۳۲)، `NotificationIntentConcurrencyTest` (۲، ۲۴)، `StorefrontQueryCacheConcurrencyTest` (۱، ۴) و `MySqlSchemaTest` (۱، ۸). جمع: ۲۱ تست و ۲۷۳ assertion.

هشدار permission مربوط به Pest result cache غیر بحرانی بود و نتیجه تست را تغییر نداد.

## Idempotency و ریسک تراکنش

Checkout، payment، coupon redemption، shipment ensure، notification intent و cache rebuild مسیرهای idempotent یا unique دارند. Cart line نیز composite uniqueness دارد، ولی آزمون race اختصاصی Cart موجود نیست. تماس شبکه پرداخت بیرون از تراکنش state transition قرار گرفته است؛ این نکته از نگه‌داشتن قفل هنگام network call جلوگیری می‌کند. رزرو موجودی در تراکنش order انجام می‌شود و روی کالاهای پرترافیک contention ایجاد می‌کند؛ deadlock/timeout باید در Production پایش شود.

## Provider و Abuse

ZarinPal و SMS.ir از adapterهای synchronous استفاده می‌کنند. quota، timeout، circuit breaker و latency واقعی provider بررسی نشده است و هیچ traffic واقعی برای این گزارش ارسال نشد. login و OTP محدودیت تلاش/نرخ دارند؛ کاتالوگ عمومی throttle اختصاصی در routeها ندارد و می‌تواند در edge/WAF محدود شود.

## Observability و Recovery

برای payment، SMS، notification، job، cache builder/lock و settings لاگ ساختاریافته وجود دارد و secrets ثبت نمی‌شوند. status command و failed-job handling وجود دارد. APM/trace backend کامل در repo تنظیم نشده است؛ Production باید latency درخواست/DB، cache hit ratio، lock contention، queue lag، provider latency و deadlock را اندازه‌گیری کند.

تراکنش‌ها rollback می‌کنند، replayهای idempotent وضعیت موجود را برمی‌گردانند، rebuild ناقص generation فعال نمی‌شود و jobها retry/backoff دارند. MySQL اصلی، Redis، object storage، load balancer و providerها بدون HA زیرساختی SPOF بالقوه‌اند.

## نقاط قوت و ضعف مهم

نقاط قوت: محدودیت‌های relational، تراکنش و row lock، snapshot سفارش، authority قیمت/موجودی، idempotency، cache generation، queue separation، scheduler coordination، secret-safe provider boundary و تست‌های واقعی MySQL.

ضعف‌های مهم: نبود benchmark Production-like، نبود runtime Redis/Horizon، search غیرقابل ایندکس با `%LIKE%`، correlated subqueryهای live، نبود connection budget، provider timeout نامعلوم و پوشش ناقص Cart/OTP/slug race.

## نقشه مقیاس‌پذیری

### P0

تست Production-like روی Linux/Nginx/PHP-FPM، Redis، MySQL واقعی و داده نماینده؛ backup/restore drill؛ APP_DEBUG خاموش؛ shared media؛ sizing اتصال و worker؛ بررسی timeout provider.

### P1

EXPLAIN و slow-query tuning، alert برای queue lag/cache miss/deadlock، تست چندپردازه Cart/OTP/slug و sizing PHP-FPM.

### P2

در صورت نیاز اندازه‌گیری‌شده، full-text/search service، public read model، CDN/object storage، replica برای readهای امن و archival.

### P3

فقط در صورت evidence: sharding، partitioning، multi-region یا Octane. فعلاً microservice، cache دوم، Redis-only tags و search cluster لازم نیست.

## مدل افقی و CDN

مدل پیشنهادی: چند Laravel web node stateless پشت load balancer، MySQL مرکزی، Redis مشترک برای cache/queue/lock/session، Horizon worker، یک scheduler هماهنگ، object storage و CDN. CDN نباید پاسخ شخصی‌سازی‌شده Cart/Account را cache کند.

## تست ظرفیت لازم

یک تست stepped با concurrency افزایشی در بازه‌های ۵، ۱۰، ۱۵ و ۳۰ دقیقه اجرا شود. ترکیب پیشنهادی: ۴۵٪ read cached، ۲۰٪ search/filter، ۱۰٪ account/order، ۱۰٪ cart mutation، ۸٪ checkout preview، ۵٪ order placement و ۲٪ payment verify با provider double. p50/p95/p99، throughput، 4xx/5xx، DB CPU/lock/connection، Redis latency/eviction، PHP-FPM saturation، queue lag و cache hard-miss ثبت شود. تست باید در اندازه‌های ۱۰k/۱۰۰k/۱M محصول تکرار شود.

## پاسخ ساده به ظرفیت

امروز نمی‌توان تعداد دقیق کاربر همزمان یا RPS را اعلام کرد. کد برای rollout کنترل‌شده و تست Production-like آماده است، نه برای ادعای ظرفیت قطعی. عدد قابل انتشار فقط پس از تعریف SLO و اجرای تست با topology و داده واقعی به دست می‌آید.

## شواهد اعتبارسنجی و ایمنی

`php artisan migrate` با پیام `Nothing to migrate` پایان یافت و `php artisan migrate:status` مهاجرت‌های موجود را اجراشده گزارش کرد. هیچ فرمان مخرب دیتابیس اجرا نشد، داده توسعه `ecommerce` بازنشانی یا تغییر نکرد، هیچ درخواست واقعی به سرویس خارجی ارسال نشد و مسیر `D:\uni-shop-project\front` بدون تغییر باقی ماند. این کار فقط فایل‌های گزارش و PDF ایجاد کرد.

## رأی نهایی

معماری از نظر correctness و race protection قوی است و برای مرحله بعدی آماده می‌باشد. ظرفیت Production باید با load test واقعی گواهی شود:

`گزارش فارسی PDF اسکیلینگ: VERIFIED PASS`
