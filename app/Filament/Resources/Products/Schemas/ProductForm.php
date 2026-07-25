<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Icons\Heroicon;
use App\Models\Attribute;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use App\Models\AttributeValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;


class ProductForm
{
    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema->components([

    //         Tabs::make('Product')
    //             ->columnSpanFull()
    //             ->tabs([
    //                 self::generalTab(),
    //                 self::pricingTab(),
    //                 self::inventoryTab(),
    //                 self::shippingTab(),
    //                 self::externalTab(),
    //                 self::associationsTab(),
    //                 self::seoTab(),
    //                 self::publishTab(),
    //                 self::imagesTab(),        // ← اضافه شد

    //             ]),



    //     ]);
    // }

    // protected static function generalTab(): Tab
    // {
    //     return Tab::make('General')
    //         ->schema([
    //             Select::make('type')
    //                 ->options([
    //                     'simple'       => 'Simple',
    //                     'variable'     => 'Variable',
    //                     'grouped'      => 'Grouped',
    //                     'external'     => 'External / Affiliate',
    //                     'downloadable' => 'Downloadable',
    //                 ])
    //                 ->default('simple')
    //                 ->required()
    //                 ->live(),

    //             TextInput::make('name')
    //                 ->required()
    //                 ->maxLength(255)
    //                 ->live(onBlur: true)
    //                 ->afterStateUpdated(
    //                     fn(Get $get, $state, $set) =>
    //                     blank($get('slug')) ? $set('slug', Str::slug($state)) : null
    //                 ),

    //             TextInput::make('slug')
    //                 ->required()
    //                 ->maxLength(255)
    //                 ->unique(ignoreRecord: true),

    //             TextInput::make('sku')
    //                 ->label('SKU')
    //                 ->maxLength(255),

    //             Textarea::make('short_description')
    //                 ->rows(3)
    //                 ->columnSpanFull(),

    //             Textarea::make('description')
    //                 ->rows(8)
    //                 ->columnSpanFull(),
    //         ])
    //         ->columns(2);
    // }

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
                        'simple'       => 'Simple product',
                        'variable'     => 'Variable product',
                        'grouped'      => 'Grouped product',
                        'external'     => 'External / Affiliate',
                        'downloadable' => 'Downloadable',
                    ])
                    ->default('simple')
                    ->required()
                    ->live()                      // ← کلید reactivity
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn($state, $set) =>
                    $set('slug', Str::slug($state)))
                    ->maxLength(255),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Textarea::make('short_description')->rows(3)->columnSpanFull(),
                Textarea::make('description')->rows(6)->columnSpanFull(),
            ])
            ->columns(2);
    }
    // protected static function pricingTab(): Tab
    // {
    //     return Tab::make('Pricing')
    //         ->schema([
    //             TextInput::make('price')
    //                 ->numeric()
    //                 ->required()
    //                 ->default(0)
    //                 ->minValue(0)
    //                 ->suffix('Toman'),

    //             TextInput::make('sale_price')
    //                 ->numeric()
    //                 ->minValue(0)
    //                 ->suffix('Toman'),

    //             DateTimePicker::make('sale_starts_at')->seconds(false),
    //             DateTimePicker::make('sale_ends_at')->seconds(false),
    //         ])
    //         ->columns(2);
    // }


    protected static function pricingTab(): Tab
    {
        return Tab::make('Pricing')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn($get) => in_array($get('type'), ['simple', 'external', 'downloadable']))
            ->schema([
                TextInput::make('price')->numeric()->required()->prefix('IRR'),
                TextInput::make('sale_price')->numeric()->prefix('IRR'),
                \Filament\Forms\Components\DateTimePicker::make('sale_starts_at'),
                \Filament\Forms\Components\DateTimePicker::make('sale_ends_at'),
            ])
            ->columns(2);
    }
    // protected static function inventoryTab(): Tab
    // {
    //     return Tab::make('Inventory')
    //         ->schema([
    //             Toggle::make('manage_stock')
    //                 ->default(true)
    //                 ->live(),

    //             TextInput::make('stock_quantity')
    //                 ->numeric()
    //                 ->default(0)
    //                 ->visible(fn(Get $get) => $get('manage_stock')),

    //             TextInput::make('low_stock_threshold')
    //                 ->numeric()
    //                 ->minValue(0)
    //                 ->visible(fn(Get $get) => $get('manage_stock')),

    //             Select::make('stock_status')
    //                 ->options([
    //                     'in_stock'     => 'In stock',
    //                     'out_of_stock' => 'Out of stock',
    //                     'on_backorder' => 'On backorder',
    //                 ])
    //                 ->default('in_stock')
    //                 ->required(),
    //         ])
    //         ->columns(2);
    // }
    protected static function inventoryTab(): Tab
    {
        return Tab::make('Inventory')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->visible(fn($get) => in_array($get('type'), ['simple', 'variable', 'downloadable']))
            ->schema([
                TextInput::make('sku')->label('SKU')->maxLength(255),
                Toggle::make('manage_stock')->default(true)->live(),
                TextInput::make('stock_quantity')->numeric()->default(0)
                    ->visible(fn($get) => $get('manage_stock')),
                Select::make('stock_status')
                    ->options([
                        'in_stock'     => 'In stock',
                        'out_of_stock' => 'Out of stock',
                        'on_backorder' => 'On backorder',
                    ])->default('in_stock')->required(),
                TextInput::make('low_stock_threshold')->numeric()
                    ->visible(fn($get) => $get('manage_stock')),
            ])
            ->columns(2);
    }


    // protected static function shippingTab(): Tab
    // {
    //     return Tab::make('Shipping')
    //         ->schema([
    //             Toggle::make('is_virtual')->live(),
    //             Toggle::make('is_downloadable'),

    //             Grid::make(4)
    //                 ->schema([
    //                     TextInput::make('weight')->numeric()->suffix('kg'),
    //                     TextInput::make('length')->numeric()->suffix('cm'),
    //                     TextInput::make('width')->numeric()->suffix('cm'),
    //                     TextInput::make('height')->numeric()->suffix('cm'),
    //                 ])
    //                 ->visible(fn(Get $get) => ! $get('is_virtual')),

    //             Select::make('tax_class_id')
    //                 ->relationship('taxClass', 'name')
    //                 ->searchable()
    //                 ->preload(),
    //         ])
    //         ->columns(2);
    // }

    // protected static function externalTab(): Tab
    // {
    //     return Tab::make('External')
    //         ->visible(fn(Get $get) => $get('type') === 'external')
    //         ->schema([
    //             TextInput::make('external_url')
    //                 ->url()
    //                 ->required(fn(Get $get) => $get('type') === 'external'),

    //             TextInput::make('button_text')
    //                 ->default('Buy product')
    //                 ->maxLength(255),
    //         ])
    //         ->columns(2);
    // }


    protected static function shippingTab(): Tab
    {
        return Tab::make('Shipping')
            ->icon(Heroicon::OutlinedTruck)
            ->visible(fn($get) =>
            in_array($get('type'), ['simple', 'variable']) && ! $get('is_virtual'))
            ->schema([
                TextInput::make('weight')->numeric()->suffix('kg'),
                // length/width/height در صورت وجود ستون
            ])
            ->columns(2);
    }
    protected static function externalTab(): Tab
    {
        return Tab::make('External')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn($get) => $get('type') === 'external')
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
            ->visible(fn($get) => $get('type') === 'downloadable')
            ->schema([
                Toggle::make('is_virtual')->default(true),
                Toggle::make('is_downloadable')->default(true),
                // Repeater::make('downloads')
                //     ->relationship('downloads')     // مدل ProductDownload اگر داری
                //     ->schema([
                //         TextInput::make('name')->required(),
                //         FileUpload::make('file')->disk('private')->required(),
                //     ])
                //     ->columns(2)->columnSpanFull(),
                Repeater::make('downloads')
                    ->relationship('downloads')
                    ->schema([
                        TextInput::make('name')->required(),
                        FileUpload::make('file_path')            // مطابق ستون جدول
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
            ->visible(fn($get) => $get('type') === 'grouped')
            ->schema([
                Select::make('grouped_products')
                    ->relationship('groupedChildren', 'name') // belongsToMany به products
                    ->multiple()->searchable()->preload()
                    ->columnSpanFull(),
            ]);
    }
    // protected static function variationsTab(): Tab
    // {
    //     return Tab::make('Variations')
    //         ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
    //         ->visible(fn($get) => $get('type') === 'variable')
    //         ->schema([
    //             // ۱) attributeهایی که واریشن می‌سازند
    //             // Select::make('variation_attributes')
    //             //     ->label('Attributes used for variations')
    //             //     ->relationship(
    //             //         name: 'attributes',
    //             //         titleAttribute: 'name',
    //             //         modifyQueryUsing: fn($query) => $query->where('attributes.is_variation', true),
    //             //     )
    //             //     ->multiple()->searchable()->preload()->live()
    //             //     ->columnSpanFull(),
    //             Select::make('attribute_id')
    //                 ->relationship(
    //                     name: 'attributes',
    //                     titleAttribute: 'name',
    //                     modifyQueryUsing: fn($query) => $query->where('attributes.is_variation', 1),
    //                 )
    //                 ->searchable()
    //                 ->preload()
    //                 ->createOptionForm([
    //                     TextInput::make('name')
    //                         ->required()
    //                         ->live(onBlur: true)
    //                         ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug($state))),

    //                     TextInput::make('slug')
    //                         ->required()
    //                         ->unique('attributes', 'slug'),

    //                     Toggle::make('is_variation')
    //                         ->label('برای واریشن استفاده شود')
    //                         ->default(true),
    //                 ])
    //                 ->createOptionUsing(function (array $data) {
    //                     return Attribute::create($data)->getKey();
    //                 }),

    //             // ۲) خود واریشن‌ها
    //             Repeater::make('variations')
    //                 ->relationship('variations')
    //                 ->label('Variations')
    //                 ->schema([
    //                     // مقادیر attribute که این واریشن را تعریف می‌کنند
    //                     Select::make('attribute_values')
    //                         ->relationship('attributeValues', 'value')
    //                         ->multiple()->preload()->searchable()
    //                         ->columnSpanFull(),

    //                     TextInput::make('sku')->label('SKU'),
    //                     TextInput::make('price')->numeric()->required()->prefix('IRR'),
    //                     TextInput::make('sale_price')->numeric()->prefix('IRR'),
    //                     TextInput::make('stock_quantity')->numeric()->default(0),
    //                     Select::make('stock_status')
    //                         ->options([
    //                             'in_stock'     => 'In stock',
    //                             'out_of_stock' => 'Out of stock',
    //                             'on_backorder' => 'On backorder',
    //                         ])->default('in_stock'),
    //                     TextInput::make('weight')->numeric()->suffix('kg'),
    //                     FileUpload::make('image')->image()->disk('public')
    //                         ->directory('variations')->imageEditor(),
    //                     Toggle::make('is_active')->default(true),
    //                 ])
    //                 ->columns(2)
    //                 ->itemLabel(fn(array $state): ?string => $state['sku'] ?? 'Variation')
    //                 ->collapsible()->cloneable()
    //                 ->addActionLabel('Add variation')
    //                 ->columnSpanFull(),


    //         ]);
    // }



    protected static function variationsTab(): Tab
    {
        return Tab::make('Variations')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn($get) => $get('type') === 'variable')
            ->schema([

                // ── Step 1: انتخاب Attributeها و Valueهای مجاز ──
                Repeater::make('variation_attributes')
                    ->label('Attributes')
                    ->helperText('برای هر ویژگی، مقادیر مورد نظر را انتخاب کن')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Attribute')
                            ->options(fn() => Attribute::where('is_variation', 1)
                                ->orderBy('name')->pluck('name', 'id'))
                            ->required()->live()->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->searchable()
                            // ➕ ساختن Attribute جدید همین‌جا
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn($state, callable $set) =>
                                    $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique('attributes', 'slug'),
                                Toggle::make('is_variation')
                                    ->label('برای واریشن استفاده شود')
                                    ->default(true),
                            ])
                            ->createOptionUsing(fn(array $data) =>
                            Attribute::create($data)->getKey()),

                        Select::make('value_ids')
                            ->label('Values')
                            ->multiple()
                            ->options(fn(Get $get) => $get('attribute_id')
                                ? AttributeValue::where('attribute_id', $get('attribute_id'))
                                ->orderBy('sort_order')->pluck('value', 'id')
                                : [])
                            ->required()->live()
                            ->searchable()
                            // ➕ ساختن Value جدید برای همین Attribute
                            ->createOptionForm([
                                TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn($state, callable $set) =>
                                    $set('slug', Str::slug($state))),
                                TextInput::make('slug')->required(),
                                TextInput::make('sort_order')->numeric()->default(0),
                            ])
                            ->createOptionUsing(function (array $data, Get $get) {
                                if (blank($get('attribute_id'))) {
                                    Notification::make()->warning()
                                        ->title('اول یک Attribute انتخاب کن')->send();
                                    return null;
                                }
                                $data['attribute_id'] = $get('attribute_id');
                                return AttributeValue::create($data)->getKey();
                            }),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add attribute')
                    ->columnSpanFull(),


                // ── Step 2: دکمه Generate (state-based) ──
                Actions::make([
                    Action::make('generateVariations')
                        ->label('تولید واریشن‌ها')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('ترکیب‌های جدید اضافه می‌شوند. واریشن‌های موجود دست‌نخورده می‌مانند.')
                        ->action(function (Get $get, $set) {
                            $rows = collect($get('variation_attributes') ?? [])
                                ->filter(fn($r) => ! empty($r['attribute_id']) && ! empty($r['value_ids']));

                            if ($rows->isEmpty()) {
                                Notification::make()->warning()
                                    ->title('هیچ ویژگی معتبری انتخاب نشده')->send();
                                return;
                            }

                            $sets   = $rows->map(fn($r) => $r['value_ids'])->values()->all();
                            $combos = static::cartesian($sets);

                            $existing = collect($get('variations') ?? []);

                            // هم موجودها هم dismiss‌شده‌ها به‌عنوان «شناخته‌شده» → دوباره ساخته نمی‌شوند
                            $knownKeys = $existing
                                ->map(fn($v) => static::comboKey($v['attributeValues'] ?? []))
                                ->all();

                            $new = [];
                            foreach ($combos as $combo) {
                                if (in_array(static::comboKey($combo), $knownKeys, true)) {
                                    continue; // موجود یا dismiss‌شده
                                }
                                $new[] = [
                                    'attributeValues' => array_values($combo),
                                    'sku'             => null,
                                    'price'           => $get('price') ?? 0,
                                    'sale_price'      => null,
                                    'stock_quantity'  => 0,
                                    'is_active'       => true,
                                    'is_dismissed'    => false,
                                ];
                            }

                            $set('variations', [...$existing->all(), ...$new]);

                            Notification::make()->success()
                                ->title(count($new) . ' واریشن جدید ساخته شد')->send();
                        }),

                ])->columnSpanFull(),

                // ── Step 3: Repeater واریشن‌ها ──
                Repeater::make('variations')
                    ->relationship('variations')
                    ->schema([
                        Select::make('attributeValues')
                            ->label('Combination')
                            ->relationship('attributeValues', 'value')
                            ->multiple()->preload()
                            ->disabled()->dehydrated(true)
                            ->columnSpanFull(),

                        TextInput::make('sku')->maxLength(100),
                        TextInput::make('price')->numeric()->required()->prefix('﷼'),
                        TextInput::make('sale_price')->numeric()->prefix('﷼')->lte('price'),
                        TextInput::make('stock_quantity')->label('Stock')->numeric()->default(0),
                        Toggle::make('is_active')->label('Active')->default(true)->inline(false),

                        Hidden::make('is_dismissed')->default(false),
                    ])
                    ->columns(3)
                    ->itemLabel(function (array $state): ?string {
                        $ids = $state['attributeValues'] ?? [];
                        $label = empty($ids)
                            ? 'New variation'
                            : AttributeValue::whereIn('id', $ids)->pluck('value')->implode(' / ');
                        return ($state['is_dismissed'] ?? false) ? "🚫 {$label} (dismissed)" : $label;
                    })
                    // آیتم‌های dismiss‌شده را جمع و مخفی نشان بده
                    ->extraItemActions([
                        Action::make('toggleDismiss')
                            ->icon(fn(array $arguments, Repeater $component): string => ($component->getRawItemState($arguments['item'])['is_dismissed'] ?? false)
                                ? 'heroicon-o-arrow-uturn-left'
                                : 'heroicon-o-x-circle')
                            ->color(fn(array $arguments, Repeater $component): string => ($component->getRawItemState($arguments['item'])['is_dismissed'] ?? false)
                                ? 'success' : 'danger')
                            ->action(function (array $arguments, Repeater $component): void {
                                $statePath = $component->getStatePath();
                                $key       = $arguments['item'];
                                $livewire  = $component->getLivewire();

                                $current = (bool) data_get($livewire, "{$statePath}.{$key}.is_dismissed", false);
                                data_set($livewire, "{$statePath}.{$key}.is_dismissed", ! $current);
                            }),
                    ])

                    ->deletable(false)   // حذف فیزیکی خاموش؛ فقط dismiss
                    ->collapsible()->collapsed()
                    ->columnSpanFull(),

            ]);
    }


    protected static function associationsTab(): Tab
    {
        return Tab::make('Associations')
            ->schema([
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn($state, $set) =>
                                $set('slug', Str::slug($state))
                            ),
                        TextInput::make('slug')->required(),
                    ]),
            ])
            ->columns(2);
    }

    protected static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->schema([
                TextInput::make('meta_title')->maxLength(255),
                Textarea::make('meta_description')
                    ->maxLength(500)
                    ->rows(3),
            ]);
    }

    protected static function publishTab(): Tab
    {
        return Tab::make('Publish')
            ->schema([
                Select::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                        'pending'   => 'Pending review',
                        'private'   => 'Private',
                    ])
                    ->default('draft')
                    ->required(),

                Toggle::make('is_featured'),

                DateTimePicker::make('published_at')->seconds(false),
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
                            ->disk('public')
                            ->directory('products')
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('alt')
                            ->label('Alt text')
                            ->maxLength(255),

                        Toggle::make('is_primary')
                            ->label('Primary image')
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn(array $state): ?string => $state['alt'] ?? 'Image')
                    ->defaultItems(1)
                    ->addActionLabel('Add image')
                    ->columnSpanFull(),
            ]);
    }
    /**
     * Cartesian product از [attribute_id => [value_ids]]
     * خروجی: آرایه‌ای از [attribute_id => value_id]
     */
    /** ضرب دکارتی روی لیستی از لیست‌ها: [[1,2],[5,6]] → [[1,5],[1,6],[2,5],[2,6]] */
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

    /** کلید یکتا مستقل از ترتیب */
    protected static function comboKey(array $valueIds): string
    {
        $ids = array_map('intval', array_values($valueIds));
        sort($ids);
        return implode('|', $ids);
    }


    /**
     * کلید یکتا برای تشخیص تکراری بودن یک ترکیب
     * مستقل از ترتیب: attribute_id مرتب می‌شود
     */
    // protected static function comboKey(array $combo): string
    // {
    //     ksort($combo);
    //     return implode('|', array_map(
    //         fn($k, $v) => "{$k}:{$v}",
    //         array_keys($combo),
    //         array_values($combo)
    //     ));
    // }



}
