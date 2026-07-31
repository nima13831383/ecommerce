<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات اصلی')
                ->schema([
                    Select::make('parent_id')
                        ->label('دستهٔ والد')
                        ->options(fn(?Model $record) => Category::query()
                            ->when($record, fn($q) => $q->whereKeyNot($record->getKey()))
                            ->orderBy('sort_order')->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder('بدون والد (سطح اول)')
                        ->columnSpanFull(),

                    TextInput::make('name')
                        ->label('نام دسته')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('slug', static::makeSlug($state))),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->rule(fn(?Model $record) => static::uniqueSlugRule('categories', $record)),

                    Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('تصاویر')
                ->schema([
                    FileUpload::make('image')
                        ->label('تصویر دسته')
                        ->image()
                        ->disk('public')
                        ->directory('categories')
                        ->imageEditor(),

                    FileUpload::make('icon')
                        ->label('آیکون')
                        ->image()
                        ->disk('public')
                        ->directory('categories/icons'),
                ])
                ->columns(2),

            Section::make('سئو')
                ->schema([
                    TextInput::make('meta_title')->label('Meta title')->maxLength(255),
                    Textarea::make('meta_description')->label('Meta description')->rows(3)->maxLength(500),
                ])
                ->columns(1)
                ->collapsed(),

            Section::make('تنظیمات نمایش')
                ->schema([
                    TextInput::make('sort_order')->label('ترتیب')->numeric()->default(0),
                    Toggle::make('is_active')->label('فعال')->default(true),
                    Toggle::make('is_featured')->label('ویژه')->default(false),
                    Toggle::make('is_hidden')->label('مخفی در منو')->default(false),
                ])
                ->columns(2),
        ]);
    }

    protected static function makeSlug(?string $value): string
    {
        $slug = Str::slug((string) $value);

        if (blank($slug)) {
            $slug = Str::slug(Str::ascii((string) $value));
        }

        return blank($slug) ? 'category-' . Str::lower(Str::random(6)) : $slug;
    }

    protected static function uniqueSlugRule(string $table, ?Model $record = null): Unique
    {
        $rule = Rule::unique($table, 'slug')->whereNull('deleted_at');

        if ($record?->exists) {
            $rule->ignore($record->getKey());
        }

        return $rule;
    }
}
