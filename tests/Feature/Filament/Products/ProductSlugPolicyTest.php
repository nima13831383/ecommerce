<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function slugPolicyAdmin(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::findOrCreate('products.view', 'web'),
        Permission::findOrCreate('products.update', 'web'),
    ]);

    return $user;
}

function slugPolicyProduct(string $name, ?string $slug = null, array $attributes = []): Product
{
    return Product::query()->create(array_replace([
        'name' => $name,
        'slug' => $slug,
        'type' => 'simple',
        'price' => 100000,
        'status' => 'draft',
    ], $attributes));
}

test('blank Persian product slugs are generated without transliteration', function (): void {
    $product = slugPolicyProduct('عطر مردانه دیور');

    expect($product->slug)->toBe('عطر-مردانه-دیور')->and($product->slug)->not->toContain('atr');
});

test('existing product slugs remain stable when the name changes', function (): void {
    $product = slugPolicyProduct('عطر دیور', 'عطر-دیور');

    $product->update(['name' => 'عطر مردانه دیور']);

    expect($product->fresh()->slug)->toBe('عطر-دیور');
});

test('generated slugs receive deterministic sequential suffixes', function (): void {
    slugPolicyProduct('عطر مردانه دیور', 'عطر-مردانه-دیور');
    slugPolicyProduct('عطر مردانه دیور', 'عطر-مردانه-دیور-2');
    slugPolicyProduct('عطر مردانه دیور', 'عطر-مردانه-دیور-3');

    expect(slugPolicyProduct('عطر مردانه دیور')->slug)->toBe('عطر-مردانه-دیور-4');
});

test('generated slugs collapse whitespace and preserve Persian digits', function (): void {
    expect(slugPolicyProduct('کرم     ضد   آفتاب')->slug)->toBe('کرم-ضد-آفتاب')
        ->and(slugPolicyProduct('محصول ۱۲۳')->slug)->toBe('محصول-۱۲۳');
});

test('explicit slugs are preserved exactly', function (): void {
    expect(slugPolicyProduct('عطر دیور', 'دیور-ساواج-اصلی')->slug)->toBe('دیور-ساواج-اصلی');
});

test('a blank slug on update is generated from the current name', function (): void {
    $product = slugPolicyProduct('کرم ضد آفتاب', 'temporary-slug');

    $product->update(['slug' => null]);

    expect($product->fresh()->slug)->toBe('کرم-ضد-آفتاب');
});

test('explicit duplicate slugs are rejected by the Filament form', function (): void {
    $admin = slugPolicyAdmin();
    $first = slugPolicyProduct('اول', 'slug-duplicate');
    $second = slugPolicyProduct('دوم', 'slug-second');

    Livewire::actingAs($admin, 'web')
        ->test(EditProduct::class, ['record' => $second->getRouteKey()])
        ->assertOk()
        ->fillForm(['slug' => $first->slug], 'form')
        ->call('save')
        ->assertHasFormErrors(['slug']);

    expect($second->fresh()->slug)->toBe('slug-second');
});

test('changing the Product name in Filament does not rewrite a populated slug', function (): void {
    $admin = slugPolicyAdmin();
    $product = slugPolicyProduct('عطر دیور', 'عطر-دیور');

    Livewire::actingAs($admin, 'web')
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertOk()
        ->set('data.name', 'عطر مردانه دیور')
        ->assertSet('data.slug', 'عطر-دیور')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->slug)->toBe('عطر-دیور');
});

test('a Persian Product slug resolves through the public storefront route', function (): void {
    $product = slugPolicyProduct('محصول فارسی', 'محصول-فارسی', ['status' => 'published']);

    $this->get('/products/'.rawurlencode($product->slug))->assertOk();
});

test('an empty Product name cannot generate an empty slug', function (): void {
    expect(fn () => slugPolicyProduct(''))->toThrow(ValidationException::class);
});
