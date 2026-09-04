<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Forms\Components\JalaliDateTimePicker;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Services\Blog\PostService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PostService::class)->update($record, $data, auth()->user());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['seo_meta'] = $this->record->seoMeta?->only([
            'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
            'og_image', 'no_index', 'no_follow', 'schema_markup',
        ]) ?? [];

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('انتشار')
                ->color('success')
                ->authorize('publish')
                ->requiresConfirmation()
                ->visible(fn (Post $record): bool => $record->status !== PostStatus::Published)
                ->action(function (Post $record): void {
                    app(PostService::class)->publish($record, auth()->user());
                    Notification::make()->title('نوشته منتشر شد.')->success()->send();
                }),
            Action::make('schedule')
                ->label('زمان‌بندی انتشار')
                ->authorize('publish')
                ->visible(fn (Post $record): bool => $record->status !== PostStatus::Published)
                ->form([JalaliDateTimePicker::make('published_at')->label('زمان انتشار')->required()->seconds(false)->minDate(now())])
                ->action(function (Post $record, array $data): void {
                    app(PostService::class)->schedule($record, now()->parse($data['published_at']), auth()->user());
                    Notification::make()->title('انتشار نوشته زمان‌بندی شد.')->success()->send();
                }),
            Action::make('unpublish')
                ->label('بازگشت به پیش‌نویس')
                ->authorize('publish')
                ->visible(fn (Post $record): bool => $record->status !== PostStatus::Draft)
                ->requiresConfirmation()
                ->action(fn (Post $record) => app(PostService::class)->unpublish($record, auth()->user())),
            DeleteAction::make()->label('حذف نرم')->authorize('delete'),
            RestoreAction::make()->label('بازیابی')->authorize('restore'),
        ];
    }
}
