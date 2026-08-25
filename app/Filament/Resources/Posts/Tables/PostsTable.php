<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->sortable()->weight('bold'),
                TextColumn::make('slug')->label('نامک')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('author.name')->label('نویسنده')->searchable()->sortable(),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (PostStatus|string|null $state): string => match ($state instanceof PostStatus ? $state : PostStatus::tryFrom((string) $state)) {
                        PostStatus::Published => 'منتشرشده',
                        PostStatus::Scheduled => 'زمان‌بندی‌شده',
                        default => 'پیش‌نویس',
                    })
                    ->color(fn (PostStatus|string|null $state): string => match ($state instanceof PostStatus ? $state : PostStatus::tryFrom((string) $state)) {
                        PostStatus::Published => 'success',
                        PostStatus::Scheduled => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('categories.name')->label('دسته‌بندی')->listWithLineBreaks()->limitList(2),
                TextColumn::make('published_at')->label('زمان انتشار')->dateTime('Y/m/d H:i')->sortable(),
                TextColumn::make('updated_at')->label('آخرین تغییر')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options([
                    'draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'scheduled' => 'زمان‌بندی‌شده',
                ]),
                TrashedFilter::make()->label('وضعیت حذف'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make()->label('ویرایش')->authorize('update'),
                DeleteAction::make()->label('حذف نرم')->authorize('delete'),
                RestoreAction::make()->label('بازیابی')->authorize('restore'),
            ]);
    }
}
