<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'جزئیات کاربر';

    protected function resolveRecord(int|string $key): Model
    {
        $user = parent::resolveRecord($key)->load('roles');
        $orders = $user->orders()
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'user_id', 'order_number', 'status', 'payment_status', 'grand_total', 'currency', 'created_at'])
            ->load(['payments:id,order_id,payment_number,status,amount,currency,reconciliation_required,verified_at,created_at']);

        return $user->setRelation('orders', $orders);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageRoles')
                ->label('مدیریت نقش‌ها')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->visible(fn (User $record): bool => $this->mayManageRoles($record))
                ->authorize(fn (User $record): bool => $this->mayManageRoles($record))
                ->fillForm(fn (User $record): array => ['roles' => $record->roles->pluck('name')->all()])
                ->form([
                    Select::make('roles')
                        ->label('نقش‌ها')
                        ->options(fn (): array => $this->roleOptions())
                        ->multiple()
                        ->searchable(),
                ])
                ->requiresConfirmation()
                ->modalHeading('مدیریت نقش‌های کاربر')
                ->modalSubmitActionLabel('ذخیره نقش‌ها')
                ->action(function (User $record, array $data): void {
                    Gate::forUser(auth()->user())->authorize('manageRoles', [$record, $data['roles'] ?? []]);

                    DB::transaction(function () use ($record, $data): void {
                        $record->syncRoles($data['roles'] ?? []);
                    });

                    Log::info('Filament user roles updated.', [
                        'actor_user_id' => auth()->id(),
                        'target_user_id' => $record->getKey(),
                        'roles' => array_values($data['roles'] ?? []),
                    ]);

                    Notification::make()->success()->title('نقش‌های کاربر به‌روزرسانی شد.')->send();
                }),
            DeleteAction::make()
                ->label('حذف حساب')
                ->modalHeading('حذف نرم حساب کاربری')
                ->modalDescription('حساب حذف می‌شود اما سفارش‌ها، پرداخت‌ها و سوابق تاریخی حفظ خواهند شد.')
                ->authorize('delete')
                ->successNotificationTitle('حساب کاربری حذف شد.'),
            RestoreAction::make()
                ->label('بازیابی حساب')
                ->modalHeading('بازیابی حساب کاربری')
                ->authorize('restore')
                ->successNotificationTitle('حساب کاربری بازیابی شد.'),
        ];
    }

    private function mayManageRoles(User $record): bool
    {
        return auth()->user()?->can('users.manage_roles') === true
            && ! $record->trashed()
            && Gate::forUser(auth()->user())->allows('manageRoles', [$record, $record->roles->pluck('name')->all()]);
    }

    private function roleOptions(): array
    {
        $options = ['admin' => 'مدیر'];

        if (auth()->user()?->hasRole('super-admin')) {
            $options['super-admin'] = 'مدیر ارشد';
        }

        return $options;
    }
}
