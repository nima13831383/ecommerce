<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('محتوا')->schema([
                TextInput::make('title')->label('عنوان')->required()->maxLength(255),
                TextInput::make('slug')->label('نامک')->maxLength(255)->helperText('در صورت خالی بودن از عنوان ساخته می‌شود و پس از ایجاد خودکار تغییر نمی‌کند.'),
                Textarea::make('excerpt')->label('خلاصه')->rows(3)->maxLength(1000),
                RichEditor::make('content')->label('محتوا')->required()->columnSpanFull(),
            ])->columns(2),
            Section::make('نویسنده و دسته‌بندی')->schema([
                Select::make('author_id')
                    ->label('نویسنده')
                    ->relationship('author', 'name', fn ($query) => $query->whereNull('deleted_at'))
                    ->searchable()
                    ->preload(false)
                    ->required(),
                Select::make('categories')->label('دسته‌بندی‌ها')->relationship('categories', 'name')->multiple()->searchable()->preload(false),
                Select::make('postTags')->label('برچسب‌ها')->relationship('postTags', 'name')->multiple()->searchable()->preload(false),
            ])->columns(2),
            Section::make('رسانه')->schema([
                FileUpload::make('featured_image')->label('تصویر شاخص')->image()->disk(config('media.public_disk', 'public'))->directory('blog'),
            ]),
            Section::make('سئو')->schema([
                TextInput::make('seo_meta.meta_title')->label('عنوان سئو')->maxLength(255),
                Textarea::make('seo_meta.meta_description')->label('توضیحات سئو')->maxLength(500)->rows(3),
                TextInput::make('seo_meta.canonical_url')->label('نشانی canonical')->url()->maxLength(255),
                Toggle::make('seo_meta.no_index')->label('عدم نمایش در موتورهای جستجو'),
                Toggle::make('seo_meta.no_follow')->label('عدم دنبال‌کردن پیوندها'),
            ])->columns(2)->collapsed(),
        ]);
    }
}
