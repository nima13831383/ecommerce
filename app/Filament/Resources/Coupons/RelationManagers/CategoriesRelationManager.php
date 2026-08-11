<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'دسته‌بندی‌ها';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('خالی بودن فهرست = بدون محدودیت دسته‌بندی.')
            ->emptyStateHeading('دسته‌بندی‌ای تعیین نشده')
            ->columns([
                TextColumn::make('name')
                    ->label('دسته‌بندی')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('والد')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_excluded')
                    ->label('وضعیت')
                    ->boolean()
                    ->getStateUsing(fn($record): bool => (bool) $record->pivot->is_excluded)
                    ->trueIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
            ])
            ->filters([
                TernaryFilter::make('is_excluded')
                    ->label('نوع محدودیت')
                    ->placeholder('همه')
                    ->trueLabel('فقط مستثناها')
                    ->falseLabel('فقط شامل‌ها')
                    ->queries(
                        true: fn(Builder $query) => $query->where('coupon_category.is_excluded', true),
                        false: fn(Builder $query) => $query->where('coupon_category.is_excluded', false),
                        blank: fn(Builder $query) => $query,
                    ),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('افزودن دسته‌بندی')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'slug'])
                    ->schema(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_excluded')
                            ->label('مستثنا شود')
                            ->helperText('روشن = محصولات این دسته‌ها از کوپن حذف می‌شوند')
                            ->default(false),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش')
                    ->modalHeading('ویرایش محدودیت دسته‌بندی')
                    ->schema([
                        Toggle::make('is_excluded')->label('مستثنا شود'),
                    ])
                    ->fillForm(fn($record): array => [
                        'is_excluded' => (bool) $record->pivot->is_excluded,
                    ]),

                DetachAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markExcluded')
                        ->label('علامت‌گذاری به‌عنوان مستثنا')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn(Collection $records) => $this->setExcluded($records, true))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('markIncluded')
                        ->label('علامت‌گذاری به‌عنوان شامل')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn(Collection $records) => $this->setExcluded($records, false))
                        ->deselectRecordsAfterCompletion(),

                    DetachBulkAction::make()->label('حذف انتخاب‌شده‌ها'),
                ]),
            ]);
    }

    protected function setExcluded(Collection $records, bool $state): void
    {
        $records->each(fn($record) => $record->pivot->update(['is_excluded' => $state]));

        Notification::make()->title('وضعیت به‌روزرسانی شد')->success()->send();
    }
}
