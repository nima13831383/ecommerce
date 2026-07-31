<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات برند')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('نام برند')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('slug', static::makeSlug($state))),

                    TextInput::make('slug')
                        ->label('نامک')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(4)
                        ->columnSpanFull(),

                    FileUpload::make('logo')
                        ->label('لوگو')
                        ->image()
                        ->disk('public')
                        ->directory('brands')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('meta_title')->label('Meta title')->maxLength(255),
                    Textarea::make('meta_description')->label('Meta description')->rows(3)->maxLength(500),
                ]),

            Section::make('نمایش')
                ->columns(3)
                ->schema([
                    TextInput::make('sort_order')->label('ترتیب')->numeric()->default(0),
                    Toggle::make('is_active')->label('فعال')->default(true)->inline(false),
                    Toggle::make('is_featured')->label('ویژه')->default(false)->inline(false),
                ]),
        ]);
    }

    protected static function makeSlug(?string $value): string
    {
        $slug = Str::slug((string) $value);

        if (blank($slug)) {
            $slug = Str::slug(Str::ascii((string) $value));
        }

        return blank($slug) ? 'brand-' . Str::lower(Str::random(6)) : $slug;
    }
}
