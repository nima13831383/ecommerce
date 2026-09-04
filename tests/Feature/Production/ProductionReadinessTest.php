<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Storefront\StorefrontPaymentGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('media.public_disk', 'public');
    config()->set('media.legacy_disk', 'local');
    Storage::fake('public');
    Storage::fake('local');
});

function mediaProduct(string $slug = 'media-product'): Product
{
    return Product::query()->create([
        'name' => 'Media Product',
        'slug' => $slug,
        'type' => 'simple',
        'price' => 10000,
        'status' => 'published',
        'published_at' => now(),
    ]);
}

test('public product and blog media share the configured disk and storefront URL contract', function (): void {
    $product = mediaProduct();
    $productImage = ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'products/contract.svg',
        'alt' => 'Contract image',
        'is_primary' => true,
    ]);
    Storage::disk('public')->put($productImage->path, '<svg/>');

    $author = User::factory()->create();
    $post = Post::query()->create([
        'author_id' => $author->id,
        'title' => 'Media article',
        'slug' => 'media-article',
        'content' => '<p>Article</p>',
        'featured_image' => 'blog/contract.svg',
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    Storage::disk('public')->put($post->featured_image, '<svg/>');

    expect(ProductImage::storageDisk())->toBe('public')
        ->and(Storage::disk(ProductImage::storageDisk())->url($productImage->path))->toContain('/storage/products/contract.svg');

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee('/storage/products/contract.svg');
    $this->get('/blog/'.$post->slug)
        ->assertOk()
        ->assertSee('/storage/blog/contract.svg');
});

test('product media deletion uses the configured public disk', function (): void {
    $product = mediaProduct('deletable-media-product');
    $path = 'products/delete-contract.svg';
    Storage::disk('public')->put($path, '<svg/>');
    $image = ProductImage::query()->create(['product_id' => $product->id, 'path' => $path]);

    $image->delete();

    Storage::disk('public')->assertMissing($path);
});

test('public media reconciliation is dry-run safe, copies recognized records, and is idempotent', function (): void {
    $product = mediaProduct('reconcile-product');
    $productPath = 'products/reconcile.svg';
    ProductImage::query()->create(['product_id' => $product->id, 'path' => $productPath]);
    Storage::disk('local')->put($productPath, '<svg>product</svg>');

    $post = Post::query()->create([
        'author_id' => User::factory()->create()->id,
        'title' => 'Reconcile article',
        'slug' => 'reconcile-article',
        'content' => 'Article',
        'featured_image' => 'blog/reconcile.svg',
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    Storage::disk('local')->put($post->featured_image, '<svg>blog</svg>');

    $this->artisan('media:reconcile-public', ['--dry-run' => true])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('planned: 2');
    Storage::disk('public')->assertMissing($productPath);
    Storage::disk('public')->assertMissing($post->featured_image);

    $this->artisan('media:reconcile-public')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('copied: 2');
    Storage::disk('public')->assertExists($productPath);
    Storage::disk('public')->assertExists($post->featured_image);
    Storage::disk('local')->assertExists($productPath);
    Storage::disk('local')->assertExists($post->featured_image);

    $this->artisan('media:reconcile-public')
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('skipped: 2');
});

test('fake payment gateway is unavailable in production even when configured by alias', function (): void {
    $service = new StorefrontPaymentGateway(new PaymentGatewayRegistry([new FakePaymentGateway]));
    $originalEnvironment = app()->environment();
    config()->set('payment.storefront_gateway', 'fake');
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect($service->alias())->toBeNull();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
});

test('media reconciliation skips absolute and traversal paths', function (): void {
    $product = mediaProduct('invalid-media-product');
    ProductImage::query()->create(['product_id' => $product->id, 'path' => 'C:\\private\\secret.svg']);
    ProductImage::query()->create(['product_id' => $product->id, 'path' => '../outside.svg']);

    $this->artisan('media:reconcile-public', ['--dry-run' => true])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('invalid: 2');
});
