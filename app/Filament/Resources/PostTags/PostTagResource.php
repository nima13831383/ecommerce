<?php

namespace App\Filament\Resources\PostTags;

use App\Filament\Resources\PostTags\Pages\CreatePostTag;
use App\Filament\Resources\PostTags\Pages\EditPostTag;
use App\Filament\Resources\PostTags\Pages\ListPostTags;
use App\Models\PostTag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

class PostTagResource extends Resource
{
    protected static ?string $model = PostTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?string $navigationLabel = 'برچسب‌های وبلاگ';

    protected static ?string $modelLabel = 'برچسب وبلاگ';

    protected static ?string $pluralModelLabel = 'برچسب‌های وبلاگ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('نام')->required()->maxLength(255),
            TextInput::make('slug')->label('نامک')->maxLength(255)
                ->rule(fn (?PostTag $record) => Rule::unique('post_tags', 'slug')->ignore($record?->getKey())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('نام')->searchable()->sortable(),
            TextColumn::make('slug')->label('نامک')->searchable(),
            TextColumn::make('posts_count')->label('نوشته‌ها')->counts('posts')->sortable(),
        ])->defaultSort('name')->recordActions([
            EditAction::make()->label('ویرایش')->authorize('update'),
            DeleteAction::make()->label('حذف')->authorize('delete'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPostTags::route('/'), 'create' => CreatePostTag::route('/create'), 'edit' => EditPostTag::route('/{record}/edit')];
    }
}
