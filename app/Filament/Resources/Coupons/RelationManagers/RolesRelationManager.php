<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use App\Filament\Resources\Coupons\RelationManagers\Concerns\GuardsCouponTargeting;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RolesRelationManager extends RelationManager
{
    use GuardsCouponTargeting;

    protected static string $relationship = 'roles';

    protected static ?string $title = 'نقش‌های کاربری';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('نقش')->searchable(),
            IconColumn::make('is_excluded')->label('مستثنی')->boolean()->getStateUsing(fn ($record): bool => (bool) $record->pivot->is_excluded),
        ])->headerActions([
            AttachAction::make()->multiple()->preloadRecordSelect(false)->schema(fn (AttachAction $action): array => [
                $action->getRecordSelect(),
                Toggle::make('is_excluded')->label('مستثنی شود')->default(false),
            ])
                ->before(fn (AttachAction $action) => $this->guardActionTargetingMutation($action, 'roles')),
        ])
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش')
                    ->schema([
                        Toggle::make('is_excluded')->label('مستثنی شود'),
                    ])
                    ->fillForm(fn ($record): array => [
                        'is_excluded' => (bool) $record->pivot->is_excluded,
                    ])
                    ->before(fn (EditAction $action) => $this->guardActionTargetingMutation($action, 'roles', (int) $action->getRecord()->getKey())),
                DetachAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markExcluded')
                        ->label('مستثنی کردن')
                        ->color('danger')
                        ->action(fn (Collection $records) => $this->setExcluded($records, true)),
                    BulkAction::make('markIncluded')
                        ->label('مجاز کردن')
                        ->color('success')
                        ->action(fn (Collection $records) => $this->setExcluded($records, false)),
                    DetachBulkAction::make()->label('حذف انتخاب‌شده‌ها'),
                ]),
            ]);
    }

    /** @param Collection<int, Role> $records */
    protected function setExcluded(Collection $records, bool $excluded): void
    {
        $ids = $records->modelKeys();

        $this->guardTargetingMutation('roles', $excluded, $ids);

        $this->coupon()->roles()->updateExistingPivot($ids, ['is_excluded' => $excluded]);
    }
}
