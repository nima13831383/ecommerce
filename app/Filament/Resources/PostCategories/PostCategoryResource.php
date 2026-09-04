<?php

namespace App\Filament\Resources\PostCategories;

use App\Filament\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategories\Pages\EditPostCategory;
use App\Filament\Resources\PostCategories\Pages\ListPostCategories;
use App\Models\PostCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

class PostCategoryResource extends Resource
{
    protected static ?string $model = PostCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?string $navigationLabel = 'دسته‌بندی‌های وبلاگ';

    protected static ?string $modelLabel = 'دسته‌بندی وبلاگ';

    protected static ?string $pluralModelLabel = 'دسته‌بندی‌های وبلاگ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(255),
            TextInput::make('slug')->label('نامک')->maxLength(255)
                ->rule(fn (?PostCategory $record) => Rule::unique('post_categories', 'slug')->ignore($record?->getKey())),
            Select::make('parent_id')->label('دسته والد')->options(fn (?PostCategory $record): array => PostCategory::query()->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(false),
            Textarea::make('description')->label('توضیحات')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('نام')->searchable()->sortable(),
            TextColumn::make('slug')->label('نامک')->searchable(),
            TextColumn::make('parent.name')->label('والد'),
            TextColumn::make('posts_count')->label('نوشته‌ها')->counts('posts')->sortable(),
        ])->defaultSort('name')->recordActions([
            EditAction::make()->label('ویرایش')->authorize('update'),
            DeleteAction::make()->label('حذف')->authorize('delete'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPostCategories::route('/'), 'create' => CreatePostCategory::route('/create'), 'edit' => EditPostCategory::route('/{record}/edit')];
    }
}
