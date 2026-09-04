<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\TaxType;
use App\Filament\Forms\Components\JalaliDateTimePicker;
use App\Filament\Resources\TaxClasses\Schemas\TaxClassForm;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Models\TaxClass;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
// app/Filament/Resources/Products/Schemas/ProductForm.php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Product')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    self::generalTab(),
                    self::pricingTab(),
                    self::inventoryTab(),
                    self::variationsTab(),
                    self::groupedTab(),
                    self::externalTab(),
                    self::downloadsTab(),
                    self::imagesTab(),
                    self::shippingTab(),
                    self::associationsTab(),
                    self::seoTab(),
                    self::publishTab(),
                ]),
        ]);
    }

    protected static function generalTab(): Tab
    {
        return Tab::make('General')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->schema([
                Select::make('type')
                    ->label('Product type')
                    ->options([
                        'simple' => 'Simple product',
                        'variable' => 'Variable product',
                        'grouped' => 'Grouped product',
                        'external' => 'External / Affiliate',
                        'downloadable' => 'Downloadable',
                    ])
                    ->default('simple')
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'product')))
                    ->maxLength(255),

                // این یکی در فرم اصلی است، پس unique(ignoreRecord: true) درست کار می‌کند
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Textarea::make('short_description')->rows(3)->columnSpanFull(),
                Textarea::make('description')->rows(6)->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function pricingTab(): Tab
    {
        return Tab::make('Pricing')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (Get $get) => in_array($get('type'), ['simple', 'external', 'downloadable']))
            ->schema([
                TextInput::make('price')->numeric()->step(1)->minValue(0)->required()->prefix('IRR'),
                TextInput::make('sale_price')->numeric()->step(1)->minValue(0)->prefix('IRR'),
                JalaliDateTimePicker::make('sale_starts_at'),
                JalaliDateTimePicker::make('sale_ends_at'),
            ])
            ->columns(2);
    }

    protected static function inventoryTab(): Tab
    {
        return Tab::make('Inventory')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->visible(fn (Get $get) => in_array($get('type'), ['simple', 'variable', 'downloadable']))
            ->schema([
                TextInput::make('sku')->label('SKU')->maxLength(255),
                Placeholder::make('variable_inventory_notice')
                    ->label('موجودی محصول متغیر')
                    ->content('موجودی و وضعیت فروش محصول متغیر از تنوع‌های فعال آن مدیریت می‌شود.')
                    ->visible(fn (Get $get) => $get('type') === 'variable')
                    ->columnSpanFull(),
                Toggle::make('manage_stock')->default(true)->live()
                    ->visible(fn (Get $get) => $get('type') !== 'variable'),
                TextInput::make('stock_quantity')->numeric()->default(0)
                    ->visible(fn (Get $get) => $get('type') !== 'variable' && $get('manage_stock')),
                Select::make('stock_status')
                    ->options([
                        'in_stock' => 'In stock',
                        'out_of_stock' => 'Out of stock',
                        'on_backorder' => 'On backorder',
                    ])->default('in_stock')->required()
                    ->visible(fn (Get $get) => $get('type') !== 'variable'),
                TextInput::make('low_stock_threshold')->numeric()
                    ->visible(fn (Get $get) => $get('type') !== 'variable' && $get('manage_stock')),
            ])
            ->columns(2);
    }

    protected static function shippingTab(): Tab
    {
        return Tab::make('Shipping')
            ->icon(Heroicon::OutlinedTruck)
            ->visible(fn (Get $get) => in_array($get('type'), ['simple', 'variable']) && ! $get('is_virtual'))
            ->schema([
                TextInput::make('weight')->label('وزن')->numeric()->gt(0)->suffix('کیلوگرم'),
                TextInput::make('volume')->label('حجم')->numeric()->gt(0)->suffix('سانتی‌متر مکعب'),
                Select::make('parcel_type')->label('نوع مرسوله / شکستنی بودن')->options([
                    'normal' => 'عادی',
                    'fragile' => 'شکستنی',
                ])->default('normal')->required(),
            ])
            ->columns(2);
    }

    protected static function externalTab(): Tab
    {
        return Tab::make('External')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn (Get $get) => $get('type') === 'external')
            ->schema([
                TextInput::make('external_url')->url()->required()->columnSpanFull(),
                TextInput::make('button_text')->default('Buy now')->maxLength(255),
            ])
            ->columns(2);
    }

    protected static function downloadsTab(): Tab
    {
        return Tab::make('Downloads')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (Get $get) => $get('type') === 'downloadable')
            ->schema([
                Toggle::make('is_virtual')->default(true),
                Toggle::make('is_downloadable')->default(true),

                Repeater::make('downloads')
                    ->relationship('downloads')
                    ->schema([
                        TextInput::make('name')->required(),
                        FileUpload::make('file_path')
                            ->disk('private')
                            ->directory('downloads')
                            ->required(),
                    ])
                    ->columns(2)->columnSpanFull(),

                TextInput::make('download_limit')->numeric()->helperText('خالی = نامحدود'),
                TextInput::make('download_expiry')->numeric()->suffix('days'),
            ])
            ->columns(2);
    }

    protected static function groupedTab(): Tab
    {
        return Tab::make('Grouped products')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->visible(fn (Get $get) => $get('type') === 'grouped')
            ->schema([
                Select::make('grouped_products')
                    ->relationship('groupedChildren', 'name')
                    ->multiple()->searchable()->preload()
                    ->columnSpanFull(),
            ]);
    }

    protected static function variationsTab(): Tab
    {
        return Tab::make('Variations')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn (Get $get) => $get('type') === 'variable')
            ->schema([

                // ── Step 1: ویژگی‌ها و مقادیر ──
                Repeater::make('variation_attributes')
                    ->label('Attributes')
                    ->helperText('برای هر ویژگی، مقادیر مورد نظر را انتخاب کن. تعداد ویژگی‌ها محدودیتی ندارد.')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Attribute')
                            ->getSearchResultsUsing(fn (string $search): array => Attribute::query()
                                ->where('is_variation', true)
                                ->where('name', 'like', "%{$search}%")
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Attribute::find($value)?->name)
                            ->required()->live()->distinct()->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->createOptionForm([
                                TextInput::make('name')->required()->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'attr'))),

                                // ⚠️ داخل مدال از unique() فیلامنت استفاده نمی‌کنیم؛
                                // چون رکورد جاری (Product) را وارد کوئری می‌کند و SQL خراب می‌شود.
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->rule(Rule::unique('attributes', 'slug')),

                                Toggle::make('is_variation')->label('برای واریشن استفاده شود')->default(true),
                            ])
                            ->createOptionUsing(fn (array $data) => Attribute::create($data)->getKey()),

                        Select::make('value_ids')
                            ->label('Values')
                            ->multiple()->required()->live()->searchable()
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => filled($get('attribute_id'))
                                ? AttributeValue::where('attribute_id', $get('attribute_id'))
                                    ->where('value', 'like', "%{$search}%")
                                    ->orderBy('sort_order')
                                    ->limit(50)
                                    ->pluck('value', 'id')
                                    ->all()
                                : [])
                            ->getOptionLabelsUsing(fn (array $values): array => AttributeValue::query()
                                ->whereIn('id', $values)
                                ->pluck('value', 'id')
                                ->all())
                            ->createOptionForm([
                                TextInput::make('value')->required()->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'value'))),

                                // یکتایی در محدوده همان attribute؛ اگر attribute_id پیدا نشد، unique ساده
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->rule(function (Get $get) {
                                        $rule = Rule::unique('attribute_values', 'slug');
                                        $attributeId = static::resolveAttributeId($get);

                                        return $attributeId
                                            ? $rule->where('attribute_id', $attributeId)
                                            : $rule;
                                    }),

                                TextInput::make('sort_order')->numeric()->default(0),
                            ])
                            ->createOptionUsing(function (array $data, Get $get) {
                                $attributeId = static::resolveAttributeId($get);

                                if (blank($attributeId)) {
                                    Notification::make()->warning()->title('اول یک Attribute انتخاب کن')->send();

                                    return null;
                                }

                                $data['attribute_id'] = $attributeId;

                                return AttributeValue::create($data)->getKey();
                            }),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add attribute')
                    ->columnSpanFull(),

                // ── Step 2: تولید ترکیب‌ها ──
                Actions::make([
                    Action::make('generateVariations')
                        ->label('تولید واریشن‌ها')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('ترکیب‌های جدید اضافه می‌شوند. واریشن‌های موجود دست‌نخورده می‌مانند.')
                        ->action(function (Get $get, $set) {
                            $rows = collect($get('variation_attributes') ?? [])
                                ->filter(fn ($r) => ! empty($r['attribute_id']) && ! empty($r['value_ids']));

                            if ($rows->isEmpty()) {
                                Notification::make()->warning()->title('هیچ ویژگی معتبری انتخاب نشده')->send();

                                return;
                            }

                            $combinationCount = $rows
                                ->pluck('value_ids')
                                ->reduce(fn (int $count, array $valueIds) => $count * count($valueIds), 1);

                            if ($combinationCount > 100) {
                                Notification::make()
                                    ->warning()
                                    ->title('تولید تنوع متوقف شد')
                                    ->body("این انتخاب {$combinationCount} ترکیب تولید می‌کند. برای جلوگیری از ایجاد ناخواسته تعداد زیاد، مقادیر را محدود کنید.")
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $combos = static::cartesian($rows->pluck('value_ids')->values()->all());
                            $existing = collect($get('variations') ?? []);
                            $known = $existing->map(fn ($v) => static::comboKey($v['attribute_value_ids'] ?? ''))->all();

                            $new = [];
                            foreach ($combos as $combo) {
                                if (in_array(static::comboKey($combo), $known, true)) {
                                    continue;
                                }
                                $new[] = [
                                    'id' => null,
                                    'attribute_value_ids' => implode(',', array_map('intval', $combo)),
                                    'sku' => null,
                                    'price' => $get('price') ?? 0,
                                    'sale_price' => null,
                                    'stock_quantity' => 0,
                                    'is_active' => true,
                                    'is_dismissed' => false,
                                ];
                            }

                            $set('variations', [...$existing->all(), ...$new]);

                            Notification::make()->success()->title(count($new).' واریشن جدید ساخته شد')->send();
                        }),

                    Action::make('addVariationManually')
                        ->label('افزودن واریشن دستی')
                        ->icon('heroicon-o-plus')
                        ->color('gray')
                        ->schema(function (Get $get): array {
                            $rows = collect($get('variation_attributes') ?? [])
                                ->filter(fn ($r) => ! empty($r['attribute_id']) && ! empty($r['value_ids']));

                            return $rows->map(function (array $row) {
                                $attribute = Attribute::find($row['attribute_id']);

                                return Select::make("attr_{$row['attribute_id']}")
                                    ->label($attribute?->name ?? 'Attribute')
                                    ->options(AttributeValue::whereIn('id', $row['value_ids'])
                                        ->orderBy('sort_order')->pluck('value', 'id'))
                                    ->required()
                                    ->native(false);
                            })->values()->all();
                        })
                        ->action(function (array $data, Get $get, $set) {
                            $valueIds = array_values(array_filter(array_map('intval', $data)));

                            if (empty($valueIds)) {
                                Notification::make()->warning()->title('اول در بخش Attributes ویژگی و مقدار انتخاب کن')->send();

                                return;
                            }

                            $existing = collect($get('variations') ?? []);
                            $key = static::comboKey($valueIds);

                            if ($existing->contains(fn ($v) => static::comboKey($v['attribute_value_ids'] ?? '') === $key)) {
                                Notification::make()->warning()->title('این ترکیب از قبل وجود دارد')->send();

                                return;
                            }

                            $set('variations', [...$existing->all(), [
                                'id' => null,
                                'attribute_value_ids' => implode(',', $valueIds),
                                'sku' => null,
                                'price' => $get('price') ?? 0,
                                'sale_price' => null,
                                'stock_quantity' => 0,
                                'is_active' => true,
                                'is_dismissed' => false,
                            ]]);

                            Notification::make()->success()->title('واریشن اضافه شد')->send();
                        }),
                ])->columnSpanFull(),

                // ── Step 3: واریشن‌ها (ذخیره دستی در trait) ──
                Repeater::make('variations')
                    ->label('Variations')
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('attribute_value_ids'),
                        Hidden::make('is_dismissed')->default(false),

                        Placeholder::make('combination')
                            ->label('ترکیب')
                            ->content(fn (Get $get) => static::comboLabel($get('attribute_value_ids')))
                            ->columnSpanFull(),

                        TextInput::make('sku')->maxLength(100),
                        TextInput::make('price')->numeric()->step(1)->minValue(0)->required()->prefix('﷼'),
                        TextInput::make('sale_price')->numeric()->step(1)->minValue(0)->prefix('﷼')->lte('price'),
                        TextInput::make('stock_quantity')->label('Stock')->numeric()->default(0),
                        TextInput::make('weight')->label('وزن جایگزین (کیلوگرم)')->numeric()->gt(0),
                        TextInput::make('volume')->label('حجم جایگزین (سانتی‌متر مکعب)')->numeric()->gt(0),
                        Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                    ])
                    ->columns(3)
                    ->itemLabel(function (array $state): string {
                        $label = static::comboLabel($state['attribute_value_ids'] ?? '');

                        return ($state['is_dismissed'] ?? false) ? "🚫 {$label} (dismissed)" : $label;
                    })
                    ->extraItemActions([
                        Action::make('toggleDismiss')
                            ->icon(fn (array $arguments, Repeater $component): string => ($component->getRawItemState($arguments['item'])['is_dismissed'] ?? false)
                                ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-x-circle')
                            ->color(fn (array $arguments, Repeater $component): string => ($component->getRawItemState($arguments['item'])['is_dismissed'] ?? false)
                                ? 'success' : 'danger')
                            ->action(function (array $arguments, Repeater $component): void {
                                $path = $component->getStatePath();
                                $key = $arguments['item'];
                                $livewire = $component->getLivewire();
                                $current = (bool) data_get($livewire, "{$path}.{$key}.is_dismissed", false);
                                data_set($livewire, "{$path}.{$key}.is_dismissed", ! $current);
                            }),
                    ])
                    ->deletable(true)
                    ->addable(false)
                    ->collapsible()->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    // protected static function associationsTab(): Tab
    // {
    //     return Tab::make('Associations')
    //         ->schema([
    //             Select::make('brand_id')
    //                 ->relationship('brand', 'name')
    //                 ->searchable()
    //                 ->preload(),

    //             Select::make('categories')
    //                 ->relationship('categories', 'name')
    //                 ->multiple()
    //                 ->searchable()
    //                 ->preload(),

    //             Select::make('tags')
    //                 ->relationship('tags', 'name')
    //                 ->multiple()
    //                 ->searchable()
    //                 ->preload()
    //                 ->createOptionForm([
    //                     TextInput::make('name')
    //                         ->required()
    //                         ->live(onBlur: true)
    //                         ->afterStateUpdated(fn($state, callable $set) => $set('slug', static::makeSlug($state, 'tag'))),

    //                     // همان قاعده‌ی خام؛ داخل مدال است پس unique() فیلامنت ممنوع
    //                     TextInput::make('slug')
    //                         ->required()
    //                         ->maxLength(255)
    //                         ->rule(Rule::unique('tags', 'slug')),
    //                 ]),
    //         ])
    //         ->columns(2);
    // }

    protected static function associationsTab(): Tab
    {
        return Tab::make('Associations')
            ->icon(Heroicon::OutlinedLink)
            ->schema([
                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->createOptionForm(self::brandFormSchema())
                    ->createOptionUsing(fn (array $data) => Brand::create($data)->getKey())
                    ->editOptionForm(self::brandFormSchema())
                    ->createOptionModalHeading('برند جدید')
                    ->editOptionModalHeading('ویرایش برند'),

                Select::make('categories')
                    ->label('Categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->createOptionForm(self::categoryFormSchema())
                    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
                    ->editOptionForm(self::categoryFormSchema())
                    ->createOptionModalHeading('دسته‌بندی جدید')
                    ->editOptionModalHeading('ویرایش دسته‌بندی')
                    ->columnSpanFull(),

                Select::make('tags')
                    ->label('Tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->createOptionForm(self::tagFormSchema())
                    ->createOptionUsing(fn (array $data) => Tag::create($data)->getKey())
                    ->editOptionForm(self::tagFormSchema())
                    ->createOptionModalHeading('تگ جدید')
                    ->columnSpanFull(),

                Select::make('tax_class_id')
                    ->label('کلاس مالیاتی')
                    ->relationship(
                        name: 'taxClass',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TaxClass $record): string => sprintf(
                        '%s (%s)',
                        $record->name,
                        TaxClassForm::formatValue($record),
                    ))
                    ->getOptionLabelFromRecordUsing(fn (TaxClass $record): string => sprintf(
                        '%s (%s)',
                        $record->name,
                        TaxClassForm::formatValue($record),
                    ))
                    ->default(fn (): ?int => TaxClass::query()
                        ->where('is_default', true)
                        ->where('is_active', true)
                        ->value('id'))
                    ->searchable()
                    ->native(false)
                    ->nullable()
                    ->helperText('خالی بگذارید تا مالیات از تنظیمات سراسری فروشگاه محاسبه شود.')
                    ->createOptionForm([
                        TextInput::make('name')->label('نام')->required()->maxLength(255),
                        Select::make('type')
                            ->label('نوع')
                            ->options(TaxType::class)
                            ->default(TaxType::Percent)
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('value')
                            ->label('نرخ')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(fn (Get $get): ?int => TaxType::parse($get('type')) === TaxType::Percent ? 100 : null)
                            ->suffix(fn (Get $get): ?string => TaxType::parse($get('type'))?->affix()),
                        Toggle::make('is_active')->label('فعال')->default(true),
                    ])
                    ->createOptionForm(TaxClassForm::fields(includeSlug: false))
                    ->createOptionUsing(function (array $data): int {
                        $data['slug'] = TaxClassForm::slugFor($data['name']);

                        return TaxClass::create($data)->getKey();
                    }),

            ])
            ->columns(2);
    }

    /** @return array<Component> */
    protected static function brandFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('نام برند')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'brand'))),

            // داخل مدال از unique() فیلامنت استفاده نمی‌کنیم (رکورد Product را وارد کوئری می‌کند)
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->rule(fn (?Model $record) => static::uniqueSlugRule('brands', $record, softDeletes: true)),

            Textarea::make('description')->rows(2)->columnSpanFull(),

            FileUpload::make('logo')
                ->image()
                ->disk('public')
                ->directory('brands')
                ->imageEditor()
                ->columnSpanFull(),

            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->label('فعال')->default(true),
            Toggle::make('is_featured')->label('ویژه')->default(false),
        ];
    }

    /** @return array<Component> */
    protected static function categoryFormSchema(): array
    {
        return [
            Select::make('parent_id')
                ->label('دستهٔ والد')
                ->options(fn (?Model $record) => Category::query()
                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                    ->orderBy('sort_order')->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->placeholder('بدون والد (سطح اول)')
                ->columnSpanFull(),

            TextInput::make('name')
                ->label('نام دسته')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'category'))),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->rule(fn (?Model $record) => static::uniqueSlugRule('categories', $record, softDeletes: true)),

            Textarea::make('description')->rows(2)->columnSpanFull(),

            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->label('فعال')->default(true),
            Toggle::make('is_featured')->label('ویژه')->default(false),
            Toggle::make('is_hidden')->label('مخفی در منو')->default(false),
        ];
    }

    /** @return array<Component> */
    protected static function tagFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', static::makeSlug($state, 'tag'))),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->rule(fn (?Model $record) => static::uniqueSlugRule('tags', $record)),
        ];
    }

    /**
     * unique خام برای استفاده داخل مدال‌های createOption/editOption.
     * در حالت edit، رکورد جاری از کوئری کنار گذاشته می‌شود.
     */
    protected static function uniqueSlugRule(string $table, ?Model $record = null, bool $softDeletes = false): Unique
    {
        $rule = Rule::unique($table, 'slug');

        if ($record?->exists) {
            $rule->ignore($record->getKey());
        }

        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $rule;
    }

    protected static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->schema([
                TextInput::make('meta_title')->maxLength(255),
                Textarea::make('meta_description')->maxLength(500)->rows(3),
            ]);
    }

    protected static function publishTab(): Tab
    {
        return Tab::make('Publish')
            ->schema([
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'pending' => 'Pending review',
                        'private' => 'Private',
                    ])
                    ->default('draft')
                    ->required(),

                Toggle::make('is_featured'),

                JalaliDateTimePicker::make('published_at')->seconds(false),
            ])
            ->columns(2);
    }

    protected static function imagesTab(): Tab
    {
        return Tab::make('Images')
            ->icon(Heroicon::OutlinedPhoto)
            ->schema([
                Repeater::make('images')
                    ->relationship('images')
                    ->label('Product Images')
                    ->schema([
                        FileUpload::make('path')
                            ->label('Image')
                            ->image()
                            ->disk(ProductImage::storageDisk())
                            ->directory('products')
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('alt')->label('Alt text')->maxLength(255),

                        Toggle::make('is_primary')->label('Primary image')->inline(false),
                    ])
                    ->columns(2)
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['alt'] ?? 'Image')
                    ->defaultItems(1)
                    ->addActionLabel('Add image')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * پیدا کردن attribute_id از داخل مدال createOption (مسیر نسبی متفاوت است).
     */
    protected static function resolveAttributeId(Get $get): ?int
    {
        foreach (['attribute_id', '../attribute_id', '../../attribute_id'] as $path) {
            try {
                $value = $get($path);
            } catch (\Throwable) {
                continue;
            }

            if (filled($value) && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Str::slug روی متن فارسی رشته‌ی خالی می‌دهد و بعد required شکست می‌خورد.
     */
    protected static function makeSlug(?string $value, string $prefix = 'item'): string
    {
        $slug = Str::slug((string) $value);

        if (blank($slug)) {
            $slug = Str::slug(Str::ascii((string) $value));
        }

        return blank($slug) ? $prefix.'-'.Str::lower(Str::random(6)) : $slug;
    }

    /** ضرب دکارتی: [[1,2],[5,6]] → [[1,5],[1,6],[2,5],[2,6]] */
    protected static function cartesian(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $values) {
            $append = [];
            foreach ($result as $combo) {
                foreach ($values as $v) {
                    $append[] = [...$combo, $v];
                }
            }
            $result = $append;
        }

        return $result;
    }

    protected static array $valueLabelCache = [];

    protected static function comboLabel(string|array|null $ids): string
    {
        $ids = static::normalizeIds($ids);

        if (empty($ids)) {
            return 'New variation';
        }

        $missing = array_diff($ids, array_keys(static::$valueLabelCache));

        if ($missing) {
            AttributeValue::with('attribute')->whereIn('id', $missing)->get()
                ->each(fn ($v) => static::$valueLabelCache[$v->id] = [
                    'label' => "{$v->attribute->name}: {$v->value}",
                    'order' => $v->attribute->sort_order,
                ]);
        }

        return collect($ids)
            ->map(fn ($id) => static::$valueLabelCache[$id] ?? null)
            ->filter()
            ->sortBy('order')
            ->pluck('label')
            ->implode(' / ');
    }

    /** @return array<int> */
    public static function normalizeIds(string|array|null $ids): array
    {
        $raw = is_array($ids) ? $ids : explode(',', (string) $ids);

        return array_values(array_filter(array_map('intval', $raw)));
    }

    /** کلید یکتای مستقل از ترتیب */
    protected static function comboKey(string|array|null $ids): string
    {
        $ids = static::normalizeIds($ids);
        sort($ids);

        return implode('|', $ids);
    }
}
