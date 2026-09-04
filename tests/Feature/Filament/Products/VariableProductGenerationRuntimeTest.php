<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\Catalog\ProductVariantService;
use App\Services\Shipping\ShippingCostResolver;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function variationQaUser(array $permissions = ['products.view', 'products.create', 'products.update']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

/** @return array{0: Attribute, 1: array<int, AttributeValue>} */
function variationQaAxis(string $name, array $values): array
{
    $attribute = Attribute::query()->create([
        'name' => $name,
        'slug' => str($name)->slug().'-'.str()->uuid(),
        'is_variation' => true,
        'is_visible' => true,
    ]);

    return [$attribute, array_map(
        fn (string $value, int $sortOrder): AttributeValue => AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => str($value)->slug().'-'.str()->uuid(),
            'sort_order' => $sortOrder,
        ]),
        $values,
        array_keys($values),
    )];
}

function variationQaCreatePage(User $user, string $suffix)
{
    return Livewire::actingAs($user, 'web')
        ->test(CreateProduct::class)
        ->assertOk()
        ->fillForm([
            'type' => 'variable',
            'name' => "Variable {$suffix}",
            'slug' => "variable-{$suffix}",
            'manage_stock' => false,
            'weight' => 1.25,
            'volume' => 100,
            'parcel_type' => 'normal',
            'status' => 'draft',
            'images' => [],
        ], 'form');
}

/** @param array<int, AttributeValue> $values */
function variationQaRow(Attribute $attribute, array $values): array
{
    return [
        'attribute_id' => $attribute->id,
        'value_ids' => array_map(fn (AttributeValue $value): int => $value->id, $values),
    ];
}

function variationQaGenerate($page): void
{
    $page->callFormComponentAction('variations::data::tab', 'generateVariations')
        ->assertHasNoFormComponentActionErrors();
}

function variationQaGeneratedRows($page): array
{
    return array_values(array_filter(
        $page->get('data.variations'),
        fn (array $row): bool => filled($row['attribute_value_ids'] ?? null),
    ));
}

test('the actual variable-product form generates, replays, persists, and regenerates canonical combinations', function (): void {
    $admin = variationQaUser();
    [$color, [$red, $blue, $green]] = variationQaAxis('Color', ['Red', 'Blue', 'Green']);
    [$size, [$small, $large]] = variationQaAxis('Size', ['Small', 'Large']);
    $slug = 'variable-generation-'.str()->uuid();

    $page = variationQaCreatePage($admin, $slug)
        ->set('data.variation_attributes', [
            (string) str()->uuid() => variationQaRow($color, [$red, $blue]),
            (string) str()->uuid() => variationQaRow($size, [$small, $large]),
        ]);

    variationQaGenerate($page);
    expect(variationQaGeneratedRows($page))->toHaveCount(4);

    variationQaGenerate($page);
    expect(variationQaGeneratedRows($page))->toHaveCount(4);

    $page->set('data.variations', variationQaGeneratedRows($page))
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', "variable-{$slug}")->firstOrFail();
    $variations = $product->variations()->with('attributeValues')->get();
    $originalIds = $variations->pluck('id')->all();
    $customized = $variations->firstWhere('combination_signature', "{$color->id}:{$red->id}|{$size->id}:{$small->id}");

    expect($product->type)->toBe('variable')
        ->and($product->attributes()->pluck('attributes.id')->all())->toBe([$color->id, $size->id])
        ->and($product->attributeValues()->pluck('attribute_values.id')->sort()->values()->all())->toBe(collect([$red, $blue, $small, $large])->pluck('id')->sort()->values()->all())
        ->and($variations)->toHaveCount(4)
        ->and($variations->pluck('combination_signature')->unique())->toHaveCount(4)
        ->and($customized)->not->toBeNull();

    $edit = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()])->assertOk();
    $editRows = $edit->get('data.variations');
    $customizedKey = array_key_first(array_filter($editRows, fn (array $row): bool => $row['id'] === $customized->id));
    $editRows[$customizedKey] = [...$editRows[$customizedKey], 'sku' => 'PRESERVE-'.str()->upper(str()->random(8)), 'price' => 230000, 'stock_quantity' => 7, 'weight' => 2.5, 'volume' => 200];

    $edit
        ->set('data.variations', $editRows)
        ->set('data.variation_attributes', [
            (string) str()->uuid() => variationQaRow($size, [$large, $small]),
            (string) str()->uuid() => variationQaRow($color, [$green, $blue, $red]),
        ]);
    variationQaGenerate($edit);
    expect(variationQaGeneratedRows($edit))->toHaveCount(6);

    $edit->call('save')->assertHasNoFormErrors();

    $product->refresh();
    $reloaded = $product->variations()->with('attributeValues')->get();
    $preserved = $reloaded->find($customized->id);

    expect($reloaded)->toHaveCount(6)
        ->and($reloaded->pluck('id')->intersect($originalIds))->toHaveCount(4)
        ->and($reloaded->pluck('combination_signature')->unique())->toHaveCount(6)
        ->and($preserved->sku)->toStartWith('PRESERVE-')
        ->and($preserved->price)->toBe('230000')
        ->and($preserved->stock_quantity)->toBe(7)
        ->and($preserved->weight)->toBe('2.50')
        ->and($preserved->volume)->toBe('200.000000');

    $duplicatePage = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()]);
    $duplicatePage->callFormComponentAction('variations::data::tab', 'addVariationManually', [
        "attr_{$color->id}" => $red->id,
        "attr_{$size->id}" => $small->id,
    ])->assertHasNoFormComponentActionErrors();

    expect(variationQaGeneratedRows($duplicatePage))->toHaveCount(6)
        ->and($product->variations()->count())->toBe(6);
});

test('the actual generator permits 100 combinations and rejects an over-cap matrix without partial state', function (): void {
    $admin = variationQaUser();
    [$first, $firstValues] = variationQaAxis('First', array_map(fn (int $number): string => "F{$number}", range(1, 11)));
    [$second, $secondValues] = variationQaAxis('Second', array_map(fn (int $number): string => "S{$number}", range(1, 10)));
    $page = variationQaCreatePage($admin, 'cap-'.str()->uuid());

    $page->set('data.variation_attributes', [
        (string) str()->uuid() => variationQaRow($first, array_slice($firstValues, 0, 10)),
        (string) str()->uuid() => variationQaRow($second, $secondValues),
    ]);
    variationQaGenerate($page);
    expect(variationQaGeneratedRows($page))->toHaveCount(100);

    $page->set('data.variation_attributes', [
        (string) str()->uuid() => variationQaRow($first, $firstValues),
        (string) str()->uuid() => variationQaRow($second, $secondValues),
    ]);
    variationQaGenerate($page);

    expect(variationQaGeneratedRows($page))->toHaveCount(100)
        ->and(ProductVariation::query()->count())->toBe(0);
});

test('the variation form prevents empty generation, rejects duplicate UI selections, and blocks cross-product variation ids', function (): void {
    $admin = variationQaUser();
    [$color, [$red, $blue]] = variationQaAxis('Color', ['Red', 'Blue']);
    [$size, [$small]] = variationQaAxis('Size', ['Small']);
    $page = variationQaCreatePage($admin, 'invalid-'.str()->uuid());

    variationQaGenerate($page);
    expect($page->get('data.variations'))->toHaveCount(1)
        ->and($page->get('data.variations.0.attribute_value_ids'))->toBeNull();

    $page->set('data.variation_attributes', [
        (string) str()->uuid() => variationQaRow($color, [$red, $red]),
    ])->set('data.variations', [[
        'attribute_value_ids' => (string) $red->id,
        'price' => 10000,
        'stock_quantity' => 0,
        'is_active' => true,
        'is_dismissed' => false,
    ]]);
    $page->call('create')->assertHasFormErrors();
    expect(Product::query()->where('slug', 'like', 'variable-invalid-%')->exists())->toBeFalse();

    $productA = Product::query()->create(['type' => 'variable', 'name' => 'A', 'slug' => 'a-'.str()->uuid(), 'weight' => 1, 'volume' => 1]);
    $productB = Product::query()->create(['type' => 'variable', 'name' => 'B', 'slug' => 'b-'.str()->uuid(), 'weight' => 1, 'volume' => 1]);
    $productB->attributes()->sync([$color->id => ['sort_order' => 0], $size->id => ['sort_order' => 1]]);
    $productB->attributeValues()->sync([$red->id, $small->id]);
    $variationB = app(ProductVariantService::class)->create($productB, ['price' => 10000], [$red->id, $small->id]);

    $productA->attributes()->sync([$color->id => ['sort_order' => 0], $size->id => ['sort_order' => 1]]);
    $productA->attributeValues()->sync([$blue->id, $small->id]);
    $edit = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $productA->getRouteKey()]);
    $edit
        ->set('data.variation_attributes', [
            (string) str()->uuid() => variationQaRow($color, [$blue]),
            (string) str()->uuid() => variationQaRow($size, [$small]),
        ])
        ->set('data.variations', [[
            'id' => $variationB->id,
            'attribute_value_ids' => "{$blue->id},{$small->id}",
            'price' => 10000,
            'stock_quantity' => 0,
            'is_active' => true,
            'is_dismissed' => false,
        ]]);

    expect(fn () => $edit->call('save'))->toThrow(DomainException::class);
    expect($variationB->fresh()->product_id)->toBe($productB->id)
        ->and($productA->variations()->count())->toBe(0);
});

test('removing an option requires explicitly removing its variations in the same actual edit form save', function (): void {
    $admin = variationQaUser();
    [$color, [$red, $blue, $green]] = variationQaAxis('Color', ['Red', 'Blue', 'Green']);
    [$size, [$small, $large]] = variationQaAxis('Size', ['Small', 'Large']);
    $product = Product::query()->create(['type' => 'variable', 'name' => 'Removal', 'slug' => 'removal-'.str()->uuid(), 'weight' => 1, 'volume' => 1]);
    $rows = [];

    foreach ([$red, $blue, $green] as $colorValue) {
        foreach ([$small, $large] as $sizeValue) {
            $rows[] = [
                'attribute_value_ids' => "{$colorValue->id},{$sizeValue->id}",
                'price' => 10000,
                'stock_quantity' => 0,
                'is_active' => true,
                'is_dismissed' => false,
            ];
        }
    }

    app(ProductVariantService::class)->synchronize($product, [
        variationQaRow($color, [$red, $blue, $green]),
        variationQaRow($size, [$small, $large]),
    ], $rows);

    $page = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()]);
    $withoutGreenAxes = [
        variationQaRow($color, [$red, $blue]),
        variationQaRow($size, [$small, $large]),
    ];
    $page->set('data.variation_attributes', $withoutGreenAxes);

    expect(fn () => $page->call('save'))->toThrow(DomainException::class);
    expect($product->variations()->count())->toBe(6)
        ->and($product->attributeValues()->pluck('attribute_values.id')->contains($green->id))->toBeTrue();

    $cleanup = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()]);
    $remainingRows = array_values(array_filter(
        variationQaGeneratedRows($cleanup),
        fn (array $row): bool => ! str($row['attribute_value_ids'])->explode(',')->map('intval')->contains($green->id),
    ));

    $cleanup
        ->set('data.variation_attributes', $withoutGreenAxes)
        ->set('data.variations', $remainingRows)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->variations()->count())->toBe(4)
        ->and($product->fresh()->attributeValues()->pluck('attribute_values.id')->contains($green->id))->toBeFalse();
});

test('the edit page blocks callers without products update permission and retains parent shipping fallback', function (): void {
    $admin = variationQaUser();
    $viewer = variationQaUser(['products.view']);
    [$color, [$red]] = variationQaAxis('Color', ['Red']);
    $product = Product::query()->create(['type' => 'variable', 'name' => 'Fallback', 'slug' => 'fallback-'.str()->uuid(), 'weight' => 1.75, 'volume' => 50]);
    $product->attributes()->sync([$color->id => ['sort_order' => 0]]);
    $product->attributeValues()->sync([$red->id]);
    $variation = app(ProductVariantService::class)->create($product, ['price' => 10000, 'weight' => null, 'volume' => null], [$red->id]);

    Livewire::actingAs($viewer, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()])->assertForbidden();

    $cart = Cart::query()->create(['user_id' => $admin->id, 'token' => str()->uuid()]);
    $cart->items()->create(['product_id' => $product->id, 'product_variation_id' => $variation->id, 'quantity' => 1, 'unit_price' => 10000, 'line_total' => 10000]);
    $metrics = app(ShippingCostResolver::class)->metrics($cart->fresh(['items.product', 'items.variation']));
    expect($metrics['weight_grams'])->toBe(1750)->and($metrics['volume'])->toBe(50.0);

    $edit = Livewire::actingAs($admin, 'web')->test(EditProduct::class, ['record' => $product->getRouteKey()]);
    $rows = $edit->get('data.variations');
    $key = array_key_first($rows);
    $rows[$key] = [...$rows[$key], 'sku' => 'EDITED-'.str()->upper(str()->random(8)), 'price' => 120000, 'stock_quantity' => 3, 'weight' => 2, 'volume' => 70, 'is_active' => false];
    $edit->set('data.variations', $rows)->call('save')->assertHasNoFormErrors();

    expect($variation->fresh()->sku)->toStartWith('EDITED-')
        ->and($variation->fresh()->price)->toBe('120000')
        ->and($variation->fresh()->stock_quantity)->toBe(3)
        ->and($variation->fresh()->weight)->toBe('2.00')
        ->and($variation->fresh()->volume)->toBe('70.000000')
        ->and($variation->fresh()->is_active)->toBeFalse();
});
