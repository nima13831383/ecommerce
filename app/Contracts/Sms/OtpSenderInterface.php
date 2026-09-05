<?php

namespace App\Contracts\Sms;

interface OtpSenderInterface
{
    public function sendVerificationCode(string $mobile, string $code): void;
}
