<?php

namespace App\Contracts\Payments;

interface ZarinPalClientInterface
{
    /** @return array{code:int,authority:?string,message:?string,fee_type:?string,fee:?int,amount:?int} */
    public function request(
        int $amount,
        string $description,
        string $callbackUrl,
        string $currency,
        ?string $mobile = null,
        ?string $email = null,
    ): array;

    /** @return array{code:int,ref_id:?string,card_pan:?string,card_hash:?string,fee_type:?string,fee:?int,message:?string} */
    public function verify(string $authority, int $amount): array;

    public function redirectUrl(string $authority): string;
}
