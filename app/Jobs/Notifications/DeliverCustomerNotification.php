<?php

namespace App\Jobs\Notifications;

use App\Models\CustomerNotification;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverCustomerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $notificationId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(CustomerNotificationService $notifications): void
    {
        $notifications->deliver(CustomerNotification::query()->findOrFail($this->notificationId));
    }

    public function failed(Throwable $exception): void
    {
        app(CustomerNotificationService::class)->markFailed($this->notificationId, $exception);
    }
}
