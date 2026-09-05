<?php

namespace App\Services\Sms;

use App\Contracts\Sms\OtpSenderInterface;
use App\Exceptions\SmsGatewayException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsIrOtpSender implements OtpSenderInterface
{
    public function __construct(
        private readonly SmsGatewayConfiguration $configuration,
        private readonly SmsIrClientFactory $clients,
    ) {}

    public function sendVerificationCode(string $mobile, string $code): void
    {
        $settings = $this->configuration->settings();
        if ($settings === null) {
            throw new SmsGatewayException('تنظیمات سرویس پیامک کامل نیست.');
        }

        try {
            $result = $this->clients->make($settings->apiKey)->verifySend($mobile, $settings->templateId, [[
                'name' => $settings->parameterName,
                'value' => $code,
            ]]);

            if ($result->status === false || $result->status === 0 || $result->status === '0') {
                throw new SmsGatewayException('سرویس پیامک درخواست کد تأیید را نپذیرفت.');
            }

            Log::info('smsir.verify_send_succeeded', [
                'provider' => 'smsir',
                'sandbox' => $settings->sandbox,
                'endpoint' => 'api.sms.ir/v1/send/verify',
                'template_id' => $settings->templateId,
                'mobile_suffix' => substr($mobile, -4),
                'provider_status' => $result->status,
            ]);
        } catch (SmsGatewayException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('smsir.verify_send_failed', [
                'provider' => 'smsir',
                'sandbox' => $settings->sandbox,
                'endpoint' => 'api.sms.ir/v1/send/verify',
                'template_id' => $settings->templateId,
                'error_class' => $exception::class,
            ]);

            throw new SmsGatewayException('سرویس پیامک موقتاً در دسترس نیست.', previous: $exception);
        }
    }
}
