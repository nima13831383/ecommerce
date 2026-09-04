<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Services\Blog\PostService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateStorefrontDemoBlog extends Command
{
    protected $signature = 'demo:storefront-blog';

    protected $description = 'Create the idempotent local storefront demo blog.';

    private const DemoMarker = '<!-- storefront-demo-blog -->';

    /** @var array<string, int> */
    private array $counts = [
        'categories_created' => 0,
        'categories_reused' => 0,
        'tags_created' => 0,
        'tags_reused' => 0,
        'public_posts_created' => 0,
        'public_posts_reused' => 0,
        'draft_controls_created' => 0,
        'draft_controls_reused' => 0,
        'scheduled_controls_created' => 0,
        'scheduled_controls_reused' => 0,
        'images_created' => 0,
        'images_reused' => 0,
    ];

    public function handle(PostService $posts): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('This development-only command is allowed only in local, development, or testing environments.');

            return self::FAILURE;
        }

        $this->info('Reconciling storefront demo blog...');

        $categories = $this->categories();
        $tags = $this->tags();

        foreach ($this->articles() as $article) {
            $this->syncPost($article, $categories, $tags, $posts);
        }

        foreach ($this->controlPosts() as $control) {
            $this->syncPost($control, $categories, $tags, $posts);
        }

        $this->info('Storefront demo blog reconciliation complete.');
        foreach ($this->counts as $key => $count) {
            $this->line("{$key}: {$count}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, PostCategory> */
    private function categories(): array
    {
        $definitions = [
            'guide' => ['name' => 'راهنمای خرید', 'slug' => 'راهنمای-خرید'],
            'perfume' => ['name' => 'عطر و ادکلن', 'slug' => 'عطر-و-ادکلن'],
            'skincare' => ['name' => 'مراقبت پوست', 'slug' => 'مراقبت-پوست'],
            'beauty' => ['name' => 'زیبایی و آرایش', 'slug' => 'زیبایی-و-آرایش'],
            'style' => ['name' => 'استایل و اکسسوری', 'slug' => 'استایل-و-اکسسوری'],
        ];

        $categories = [];
        foreach ($definitions as $key => $definition) {
            $category = PostCategory::query()->where('slug', $definition['slug'])->first();
            if ($category && $category->name !== $definition['name']) {
                throw new DomainException("Blog category slug `{$definition['slug']}` belongs to another record.");
            }

            if (! $category) {
                $this->counts['categories_created']++;
                $category = PostCategory::query()->create($definition);
            } else {
                $this->counts['categories_reused']++;
            }

            $categories[$key] = $category;
        }

        return $categories;
    }

    /** @return array<string, PostTag> */
    private function tags(): array
    {
        $definitions = [
            'buying' => ['name' => 'راهنمای خرید', 'slug' => 'راهنمای-خرید'],
            'perfume' => ['name' => 'عطر', 'slug' => 'عطر'],
            'skin' => ['name' => 'پوست', 'slug' => 'پوست'],
            'makeup' => ['name' => 'آرایش', 'slug' => 'آرایش'],
            'accessories' => ['name' => 'اکسسوری', 'slug' => 'اکسسوری'],
            'selection' => ['name' => 'انتخاب محصول', 'slug' => 'انتخاب-محصول'],
            'care' => ['name' => 'نگهداری', 'slug' => 'نگهداری'],
            'lifestyle' => ['name' => 'سبک زندگی', 'slug' => 'سبک-زندگی'],
            'gift' => ['name' => 'هدیه', 'slug' => 'هدیه'],
            'popular' => ['name' => 'محبوب', 'slug' => 'محبوب'],
        ];

        $tags = [];
        foreach ($definitions as $key => $definition) {
            $tag = PostTag::query()->where('slug', $definition['slug'])->first();
            if ($tag && $tag->name !== $definition['name']) {
                throw new DomainException("Blog tag slug `{$definition['slug']}` belongs to another record.");
            }

            if (! $tag) {
                $this->counts['tags_created']++;
                $tag = PostTag::query()->create($definition);
            } else {
                $this->counts['tags_reused']++;
            }

            $tags[$key] = $tag;
        }

        return $tags;
    }

    /** @param array<string, mixed> $definition @param array<string, PostCategory> $categories @param array<string, PostTag> $tags */
    private function syncPost(array $definition, array $categories, array $tags, PostService $posts): void
    {
        $slug = Post::normalizeSlug($definition['slug']);
        $post = Post::withTrashed()->where('slug', $slug)->first();
        $isNew = $post === null;

        if ($post && ! str_contains((string) $post->content, self::DemoMarker)) {
            throw new DomainException("Post slug `{$slug}` belongs to existing non-demo content and will not be overwritten.");
        }

        $categoryIds = [$categories[$definition['category']]->id];
        $tagIds = array_map(fn (string $tag): int => $tags[$tag]->id, $definition['tags']);
        $data = [
            'title' => $definition['title'],
            'slug' => $slug,
            'excerpt' => $definition['excerpt'],
            'content' => self::DemoMarker.$definition['content'],
            'featured_image' => $this->syncImage($slug, $definition['image']),
            'categories' => $categoryIds,
            'postTags' => $tagIds,
        ];

        if ($isNew) {
            $post = $posts->create($data);
        } else {
            if ($post->trashed()) {
                $post->restore();
            }

            $post = $posts->update($post, $data);
        }

        $status = $definition['status'];
        $publishedAt = $definition['published_at'];
        $currentStatus = $post->status instanceof PostStatus
            ? $post->status
            : PostStatus::tryFrom((string) $post->status) ?? PostStatus::Draft;

        if ($status === PostStatus::Published) {
            if ($currentStatus !== PostStatus::Published) {
                $post = $posts->publish($post);
            }

            $post->forceFill(['published_at' => $publishedAt])->save();
            $this->counts[$isNew ? 'public_posts_created' : 'public_posts_reused']++;
        }

        if ($status === PostStatus::Scheduled) {
            if (
                $currentStatus !== PostStatus::Scheduled
                || $post->published_at === null
                || ! $post->published_at->equalTo($publishedAt)
            ) {
                $post = $posts->schedule($post, $publishedAt);
            }

            $this->counts[$isNew ? 'scheduled_controls_created' : 'scheduled_controls_reused']++;
        }

        if ($status === PostStatus::Draft) {
            if ($currentStatus !== PostStatus::Draft) {
                $post = $posts->unpublish($post);
            }

            $this->counts[$isNew ? 'draft_controls_created' : 'draft_controls_reused']++;
        }
    }

    /** @param array{theme: string, alt: string}|null $image */
    private function syncImage(string $slug, ?array $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = 'blog/demo/'.substr(hash('sha256', $slug), 0, 20).'.svg';
        $disk = Storage::disk((string) config('media.public_disk', 'public'));
        if ($disk->exists($path)) {
            $this->counts['images_reused']++;

            return $path;
        }

        $disk->put($path, $this->imageSvg($image['theme'], $image['alt']));
        $this->counts['images_created']++;

        return $path;
    }

    private function imageSvg(string $theme, string $alt): string
    {
        $themes = [
            'perfume' => ['#2f1b41', '#d6a85f'],
            'skincare' => ['#d8f3dc', '#40916c'],
            'lipstick' => ['#f8ad9d', '#9d0208'],
            'bracelet' => ['#1d3557', '#a8dadc'],
            'gifts' => ['#720026', '#f9c74f'],
            'cosmetics' => ['#3a0ca3', '#f72585'],
        ];
        [$start, $end] = $themes[$theme] ?? $themes['cosmetics'];
        $label = htmlspecialchars($alt, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 760" role="img" aria-label="{$label}">
    <defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="{$start}"/><stop offset="1" stop-color="{$end}"/></linearGradient></defs>
    <rect width="1200" height="760" fill="url(#g)"/>
    <circle cx="960" cy="150" r="180" fill="#ffffff" opacity=".16"/>
    <circle cx="220" cy="650" r="250" fill="#000000" opacity=".12"/>
    <text x="600" y="390" text-anchor="middle" fill="#ffffff" font-size="42" font-family="Tahoma, sans-serif">{$label}</text>
</svg>
SVG;
    }

    /** @return list<array<string, mixed>> */
    private function articles(): array
    {
        $definitions = [
            ['title' => 'چطور عطر مناسب خودمان را انتخاب کنیم؟', 'slug' => 'چطور-عطر-مناسب-خودمان-را-انتخاب-کنیم', 'category' => 'guide', 'tags' => ['buying', 'perfume', 'selection'], 'days' => 2, 'theme' => 'perfume', 'intro' => 'انتخاب عطر فقط به پسندیدن یک رایحه محدود نمی‌شود؛ فصل، سبک زندگی و ماندگاری هم باید با سلیقه ما هماهنگ باشند.', 'advice' => 'برای شروع، خانواده‌های بویایی را روی نوار تست امتحان کنید و چند دقیقه به عطر فرصت دهید تا نت‌های میانی و پایانی خود را نشان دهد.'],
            ['title' => 'راهنمای انتخاب هدیه برای مناسبت‌های مختلف', 'slug' => 'راهنمای-انتخاب-هدیه-برای-مناسبت‌های-مختلف', 'category' => 'guide', 'tags' => ['buying', 'gift', 'lifestyle'], 'days' => 5, 'theme' => 'gifts', 'intro' => 'یک هدیه خوب پیش از آن‌که گران باشد، باید با شخصیت و نیاز دریافت‌کننده هماهنگ باشد.', 'advice' => 'برای انتخاب مطمئن، به مناسبت، رنگ‌های مورد علاقه و عادت‌های روزمره فرد توجه کنید و بسته‌بندی ساده و باکیفیت را فراموش نکنید.'],
            ['title' => '۵ نکته مهم قبل از خرید محصولات مراقبت پوست', 'slug' => '۵-نکته-مهم-قبل-از-خرید-محصولات-مراقبت-پوست', 'category' => 'guide', 'tags' => ['buying', 'skin', 'selection'], 'days' => 9, 'theme' => 'skincare', 'intro' => 'خرید محصولات مراقبت پوست زمانی نتیجه‌بخش است که نوع پوست و هدف استفاده را از قبل بشناسیم.', 'advice' => 'ترکیبات، تاریخ مصرف و روش استفاده را بخوانید، از خرید هم‌زمان چند محصول جدید خودداری کنید و تست حساسیت را جدی بگیرید.'],
            ['title' => 'راهنمای انتخاب اکسسوری برای استایل روزمره', 'slug' => 'راهنمای-انتخاب-اکسسوری-برای-استایل-روزمره', 'category' => 'guide', 'tags' => ['buying', 'accessories', 'lifestyle'], 'days' => 13, 'theme' => 'bracelet', 'intro' => 'اکسسوری مناسب می‌تواند یک استایل ساده روزمره را منسجم و شخصی‌تر نشان دهد.', 'advice' => 'یک نقطه کانونی انتخاب کنید، رنگ فلزها را هماهنگ نگه دارید و اندازه اکسسوری را با فرم لباس و موقعیت استفاده بسنجید.'],
            ['title' => 'تفاوت ادو پرفیوم، ادو تویلت و پرفیوم چیست؟', 'slug' => 'تفاوت-ادو-پرفیوم-ادو-تویلت-و-پرفیوم-چیست', 'category' => 'perfume', 'tags' => ['perfume', 'selection'], 'days' => 17, 'theme' => 'perfume', 'intro' => 'تفاوت اصلی این سه عنوان در غلظت اسانس و در نتیجه، شدت و ماندگاری رایحه است.', 'advice' => 'پرفیوم معمولاً غلیظ‌تر و ماندگارتر است، ادو پرفیوم تعادل خوبی برای استفاده روزانه دارد و ادو تویلت انتخابی سبک‌تر برای هوای گرم است.'],
            ['title' => 'چطور ماندگاری عطر را بیشتر کنیم؟', 'slug' => 'چطور-ماندگاری-عطر-را-بیشتر-کنیم', 'category' => 'perfume', 'tags' => ['perfume', 'care'], 'days' => 21, 'theme' => 'perfume', 'intro' => 'ماندگاری عطر به پوست، محل اسپری و حتی شیوه نگهداری شیشه بستگی دارد.', 'advice' => 'عطر را روی پوست مرطوب و نقاط نبض بزنید، آن را روی لباس نمالید و شیشه را دور از نور مستقیم و گرمای حمام نگهداری کنید.'],
            ['title' => 'راهنمای انتخاب رایحه مناسب فصل پاییز و زمستان', 'slug' => 'راهنمای-انتخاب-رایحه-مناسب-فصل-پاییز-و-زمستان', 'category' => 'perfume', 'tags' => ['perfume', 'lifestyle', 'selection'], 'days' => 26, 'theme' => 'perfume', 'intro' => 'هوای خنک پاییز و زمستان فرصت خوبی برای پوشیدن رایحه‌های گرم‌تر و عمیق‌تر فراهم می‌کند.', 'advice' => 'نت‌های چوبی، کهربایی، وانیلی و ادویه‌ای در این فصل جلوه بیشتری دارند؛ بااین‌حال مقدار استفاده را با فضای بسته هماهنگ کنید.'],
            ['title' => 'اشتباهات رایج در نگهداری عطر و ادکلن', 'slug' => 'اشتباهات-رایج-در-نگهداری-عطر-و-ادکلن', 'category' => 'perfume', 'tags' => ['perfume', 'care'], 'days' => 31, 'theme' => null, 'intro' => 'گرما، نور و تغییرات شدید دما می‌توانند تعادل رایحه عطر را زودتر از انتظار تغییر دهند.', 'advice' => 'شیشه را در جعبه اصلی و یک کمد خنک نگه دارید، درپوش را محکم ببندید و از نگهداری عطر در خودرو یا کنار پنجره پرهیز کنید.'],
            ['title' => 'روتین ساده مراقبت پوست برای شروع', 'slug' => 'روتین-ساده-مراقبت-پوست-برای-شروع', 'category' => 'skincare', 'tags' => ['skin', 'care', 'selection'], 'days' => 36, 'theme' => 'skincare', 'intro' => 'برای شروع مراقبت پوست به ده‌ها محصول نیاز ندارید؛ پاک‌سازی ملایم، آبرسانی و ضدآفتاب پایه‌های یک روتین خوب هستند.', 'advice' => 'محصولات را یکی‌یکی وارد روتین کنید تا واکنش پوست را بشناسید و نتیجه را با استمرار، نه تغییرات روزانه، ارزیابی کنید.'],
            ['title' => 'سرم آبرسان چیست و چه زمانی استفاده می‌شود؟', 'slug' => 'سرم-آبرسان-چیست-و-چه-زمانی-استفاده-می‌شود', 'category' => 'skincare', 'tags' => ['skin', 'care'], 'days' => 41, 'theme' => 'skincare', 'intro' => 'سرم آبرسان بافت سبکی دارد و برای رساندن ترکیبات رطوبت‌رسان به لایه‌های سطحی پوست طراحی شده است.', 'advice' => 'چند قطره سرم را روی پوست کمی نم‌دار بزنید و پس از آن مرطوب‌کننده استفاده کنید تا رطوبت در پوست حفظ شود.'],
            ['title' => 'چطور نوع پوست خود را بهتر بشناسیم؟', 'slug' => 'چطور-نوع-پوست-خود-را-بهتر-بشناسیم', 'category' => 'skincare', 'tags' => ['skin', 'selection'], 'days' => 46, 'theme' => 'skincare', 'intro' => 'شناخت نوع پوست کمک می‌کند انتخاب شوینده و مرطوب‌کننده دقیق‌تری داشته باشیم و کمتر تحت تأثیر تبلیغات قرار بگیریم.', 'advice' => 'رفتار پوست پس از شست‌وشو، میزان برق ناحیه T و احساس کشیدگی را بررسی کنید؛ برای تشخیص مشکلات پوستی پایدار از متخصص کمک بگیرید.'],
            ['title' => 'ترتیب استفاده از محصولات مراقبت پوست', 'slug' => 'ترتیب-استفاده-از-محصولات-مراقبت-پوست', 'category' => 'skincare', 'tags' => ['skin', 'care'], 'days' => 51, 'theme' => 'skincare', 'intro' => 'ترتیب درست استفاده باعث می‌شود محصولات با بافت سبک‌تر بهتر جذب شوند و لایه‌های سنگین‌تر رطوبت را حفظ کنند.', 'advice' => 'پاک‌کننده، تونر در صورت نیاز، سرم، مرطوب‌کننده و در روتین صبح ضدآفتاب ترتیب ساده و قابل‌اجرا برای بیشتر افراد است.'],
            ['title' => 'چطور رنگ رژ لب مناسب را انتخاب کنیم؟', 'slug' => 'چطور-رنگ-رژ-لب-مناسب-را-انتخاب-کنیم', 'category' => 'beauty', 'tags' => ['makeup', 'selection'], 'days' => 56, 'theme' => 'lipstick', 'intro' => 'رنگ رژ لب باید با تناژ پوست، رنگ لباس و حال‌وهوای موقعیت هماهنگ باشد، نه صرفاً با مد روز.', 'advice' => 'رنگ را در نور طبیعی امتحان کنید و برای استفاده روزانه سراغ تناژهایی بروید که بدون آرایش سنگین هم چهره را شاداب نشان می‌دهند.'],
            ['title' => 'نکات ساده برای ماندگاری بیشتر آرایش', 'slug' => 'نکات-ساده-برای-ماندگاری-بیشتر-آرایش', 'category' => 'beauty', 'tags' => ['makeup', 'care'], 'days' => 61, 'theme' => 'cosmetics', 'intro' => 'ماندگاری آرایش بیشتر از تعداد لایه‌ها به آماده‌سازی پوست و انتخاب بافت مناسب وابسته است.', 'advice' => 'پوست را تمیز و مرطوب کنید، هر لایه را با مقدار کم بسازید و در پایان از اسپری فیکس متناسب با نوع پوست استفاده کنید.'],
            ['title' => 'لوازم آرایشی ضروری برای یک کیف روزمره', 'slug' => 'لوازم-آرایشی-ضروری-برای-یک-کیف-روزمره', 'category' => 'beauty', 'tags' => ['makeup', 'lifestyle'], 'days' => 66, 'theme' => null, 'intro' => 'یک کیف آرایش روزمره لازم نیست سنگین باشد؛ چند محصول چندکاره می‌توانند نیازهای اصلی را پوشش دهند.', 'advice' => 'بالم لب رنگی، کانسیلر سبک، ریمل، رژ لب محبوب و آینه کوچک ترکیبی کاربردی برای تمدید آرایش در طول روز هستند.'],
            ['title' => 'راهنمای انتخاب دستبند متناسب با استایل', 'slug' => 'راهنمای-انتخاب-دستبند-متناسب-با-استایل', 'category' => 'style', 'tags' => ['accessories', 'selection'], 'days' => 72, 'theme' => 'bracelet', 'intro' => 'دستبند زمانی زیباتر دیده می‌شود که اندازه، رنگ و فرم آن با ساعت و لباس هماهنگ باشد.', 'advice' => 'برای استایل مینیمال یک قطعه ساده انتخاب کنید و در ترکیب چند دستبند، تفاوت بافت و ضخامت را حفظ کنید تا ظاهر شلوغ نشود.'],
            ['title' => 'چطور اکسسوری‌ها را با هم ست کنیم؟', 'slug' => 'چطور-اکسسوری‌ها-را-با-هم-ست-کنیم', 'category' => 'style', 'tags' => ['accessories', 'lifestyle'], 'days' => 78, 'theme' => 'bracelet', 'intro' => 'ست‌کردن اکسسوری‌ها به معنای یکسان‌بودن همه قطعات نیست؛ هماهنگی هوشمندانه جذاب‌تر از تکرار کامل است.', 'advice' => 'یک رنگ فلزی غالب داشته باشید، بین قطعات فاصله بصری ایجاد کنید و اکسسوری شاخص را کنار جزئیات ساده‌تر قرار دهید.'],
            ['title' => 'ایده‌های ساده برای کامل کردن استایل با اکسسوری', 'slug' => 'ایده‌های-ساده-برای-کامل-کردن-استایل-با-اکسسوری', 'category' => 'style', 'tags' => ['accessories', 'gift', 'lifestyle'], 'days' => 84, 'theme' => 'gifts', 'intro' => 'گاهی یک شال رنگی، کیف کوچک یا دستبند ظریف همان جزئی است که استایل روزمره را کامل می‌کند.', 'advice' => 'از رنگ‌های موجود در لباس الهام بگیرید و برای شروع یک اکسسوری کاربردی انتخاب کنید که بتوانید آن را با چند ترکیب مختلف بپوشید.'],
        ];

        return array_map(function (array $definition): array {
            return [
                'title' => $definition['title'],
                'slug' => $definition['slug'],
                'category' => $definition['category'],
                'tags' => $definition['tags'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays($definition['days'])->setTime(10, 0),
                'excerpt' => $definition['intro'],
                'content' => "<p>{$definition['intro']}</p><p>{$definition['advice']}</p>",
                'image' => $definition['theme'] === null ? null : ['theme' => $definition['theme'], 'alt' => $definition['title']],
            ];
        }, $definitions);
    }

    /** @return list<array<string, mixed>> */
    private function controlPosts(): array
    {
        return [
            [
                'title' => 'ترندهای زیبایی فصل آینده',
                'slug' => 'ترندهای-زیبایی-فصل-آینده',
                'category' => 'beauty',
                'tags' => ['makeup', 'lifestyle'],
                'status' => PostStatus::Scheduled,
                'published_at' => now()->addDays(20)->setTime(10, 0),
                'excerpt' => 'یادداشت زمان‌بندی‌شده درباره ترندهای زیبایی که هنوز برای انتشار عمومی آماده نشده است.',
                'content' => '<p>این مقاله برای انتشار در آینده آماده شده و تا زمان مقرر نباید در مجله نمایش داده شود.</p>',
                'image' => ['theme' => 'cosmetics', 'alt' => 'ترندهای زیبایی فصل آینده'],
            ],
            [
                'title' => 'راهنمای خرید هدیه‌های نوروزی',
                'slug' => 'راهنمای-خرید-هدیه‌های-نوروزی',
                'category' => 'guide',
                'tags' => ['gift', 'buying'],
                'status' => PostStatus::Scheduled,
                'published_at' => now()->addDays(35)->setTime(10, 0),
                'excerpt' => 'پیشنهادهای زمان‌بندی‌شده برای انتخاب هدیه نوروزی؛ انتشار این مطلب به آینده موکول شده است.',
                'content' => '<p>این محتوای کنترل زمان‌بندی است و تا زمان انتشار آینده خصوصی باقی می‌ماند.</p>',
                'image' => ['theme' => 'gifts', 'alt' => 'هدیه‌های نوروزی'],
            ],
            [
                'title' => 'پیش‌نویس داخلی وبلاگ',
                'slug' => 'پیش‌نویس-داخلی-وبلاگ',
                'category' => 'guide',
                'tags' => ['buying'],
                'status' => PostStatus::Draft,
                'published_at' => null,
                'excerpt' => 'محتوای داخلی برای کنترل دیده‌نشدن پیش‌نویس‌ها در ویترین عمومی.',
                'content' => '<p>این پیش‌نویس برای استفاده داخلی است و نباید در فهرست عمومی مجله نمایش داده شود.</p>',
                'image' => null,
            ],
        ];
    }
}
