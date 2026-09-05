<?php

namespace App\Filament\Resources\InventoryTransactions\Actions;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidInventoryAdjustmentException;
use App\Filament\Resources\Inventory\Support\InventoryPresentation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Inventory\InventoryService;
use App\Support\PersianNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class AdjustInventoryAction
{
    public static function make(): Action
    {
        return Action::make('adjustInventory')
            ->label('تنظیم موجودی')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->can('inventory.adjust') === true)
            ->authorize(fn (): bool => auth()->user()?->can('inventory.adjust') === true)
            ->requiresConfirmation()
            ->modalHeading('تنظیم دستی موجودی')
            ->modalDescription('این عملیات یک رکورد immutable در دفتر تراکنش‌های موجودی ثبت می‌کند.')
            ->modalSubmitActionLabel('ثبت تغییر موجودی')
            ->form([
                Select::make('owner_type')
                    ->label('نوع مالک موجودی')
                    ->options([
                        Product::class => 'محصول',
                        ProductVariation::class => 'تنوع محصول',
                    ])
                    ->required()
                    ->live(),
                Select::make('owner_id')
                    ->label('محصول یا تنوع محصول')
                    ->required()
                    ->searchable()
                    ->options(fn (Get $get): array => self::ownerOptions($get('owner_type')))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => self::ownerSearchResults($get('owner_type'), $search))
                    ->getOptionLabelUsing(fn (int|string $value, Get $get): ?string => self::ownerLabel($get('owner_type'), $value))
                    ->live(),
                Placeholder::make('owner_context')
                    ->label('وضعیت فعلی')
                    ->content(fn (Get $get): string => self::context($get)),
                TextInput::make('new_on_hand')
                    ->label('موجودی جدید')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->live(),
                Placeholder::make('delta_preview')
                    ->label('مقدار تغییر')
                    ->content(fn (Get $get): string => self::deltaPreview($get)),
                Textarea::make('reason')
                    ->label('دلیل تغییر')
                    ->required()
                    ->minLength(3)
                    ->maxLength(255)
                    ->helperText('ثبت دلیل برای هر تغییر موجودی الزامی است.'),
                Textarea::make('note')
                    ->label('توضیحات تکمیلی')
                    ->maxLength(1000)
                    ->nullable(),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('inventory.adjust') === true, 403);

                try {
                    $owner = self::resolveOwner($data['owner_type'] ?? null, $data['owner_id'] ?? null);
                    $transaction = app(InventoryService::class)->adjustToOnHand(
                        $owner,
                        (int) $data['new_on_hand'],
                        (string) $data['reason'],
                        [
                            'source' => 'filament_manual_adjustment',
                            'note' => filled($data['note'] ?? null) ? $data['note'] : null,
                        ],
                        auth()->id(),
                    );

                    if ($transaction === null) {
                        Notification::make()->warning()->title('تغییری ثبت نشد')->body('موجودی جدید با موجودی فعلی برابر است و رکوردی در دفتر ثبت نشد.')->send();

                        return;
                    }

                    Notification::make()->success()->title('موجودی با موفقیت به‌روزرسانی شد.')->body('مقدار تغییر: '.InventoryPresentation::delta($transaction->quantity_delta))->send();
                } catch (InsufficientStockException|InvalidInventoryAdjustmentException $exception) {
                    Notification::make()->danger()->title('امکان ثبت تغییر وجود ندارد')->body($exception->getMessage())->send();
                } catch (ModelNotFoundException) {
                    Notification::make()->danger()->title('مالک موجودی یافت نشد')->body('محصول یا تنوع انتخاب‌شده دیگر در دسترس نیست.')->send();
                } catch (\Throwable $exception) {
                    Log::error('Filament inventory adjustment failed.', ['user_id' => auth()->id(), 'exception' => $exception]);
                    Notification::make()->danger()->title('ثبت تغییر موجودی ناموفق بود')->body('خطای غیرمنتظره‌ای رخ داد. دوباره تلاش کنید.')->send();
                }
            });
    }

    private static function resolveOwner(?string $type, mixed $id): Product|ProductVariation
    {
        abort_unless(in_array($type, [Product::class, ProductVariation::class], true) && filled($id), 422);

        return $type::query()->findOrFail($id);
    }

    private static function ownerOptions(?string $type): array
    {
        if (! in_array($type, [Product::class, ProductVariation::class], true)) {
            return [];
        }

        return $type::query()->when($type === ProductVariation::class, fn ($query) => $query->with('product'))->latest('id')->limit(50)->get()->mapWithKeys(fn (Model $owner): array => [$owner->getKey() => self::ownerLabelForModel($owner)])->all();
    }

    private static function ownerSearchResults(?string $type, string $search): array
    {
        if (! in_array($type, [Product::class, ProductVariation::class], true)) {
            return [];
        }

        $query = $type::query()->when($type === ProductVariation::class, fn ($query) => $query->with('product'))->limit(50);

        if ($type === Product::class) {
            $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        } else {
            $query->where(fn ($query) => $query->where('sku', 'like', "%{$search}%")->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%")));
        }

        return $query->get()->mapWithKeys(fn (Model $owner): array => [$owner->getKey() => self::ownerLabelForModel($owner)])->all();
    }

    private static function ownerLabel(?string $type, mixed $id): ?string
    {
        if (! in_array($type, [Product::class, ProductVariation::class], true) || blank($id)) {
            return null;
        }

        $owner = $type::query()->find($id);

        return $owner ? self::ownerLabelForModel($owner) : null;
    }

    private static function ownerLabelForModel(Model $owner): string
    {
        return InventoryPresentation::ownerLabel($owner);
    }

    private static function context(Get $get): string
    {
        $owner = self::ownerFromGet($get);

        if ($owner === null) {
            return 'ابتدا مالک موجودی را انتخاب کنید.';
        }

        $summary = InventoryPresentation::stockSummary($owner, app(InventoryService::class));

        return sprintf('مالک: %s | فیزیکی: %s | رزروشده: %s | قابل فروش: %s', InventoryPresentation::ownerLabel($owner), PersianNumber::integer($summary['on_hand']), PersianNumber::integer($summary['reserved']), PersianNumber::integer($summary['available']));
    }

    private static function deltaPreview(Get $get): string
    {
        $owner = self::ownerFromGet($get);
        $newOnHand = $get('new_on_hand');

        if ($owner === null || ! is_numeric($newOnHand)) {
            return 'پس از انتخاب مالک و موجودی جدید محاسبه می‌شود.';
        }

        return InventoryPresentation::delta((int) $newOnHand - (int) $owner->stock_quantity);
    }

    private static function ownerFromGet(Get $get): Product|ProductVariation|null
    {
        $type = $get('owner_type');
        $id = $get('owner_id');

        return in_array($type, [Product::class, ProductVariation::class], true) && filled($id) ? $type::query()->find($id) : null;
    }
}
