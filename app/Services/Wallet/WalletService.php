<?php
// app/Services/Wallet/WalletService.php
namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\Exceptions\InsufficientBalanceException;
use App\Services\Wallet\Exceptions\WalletException;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * کیف پول کاربر را برمی‌گرداند و در صورت نبود، می‌سازد.
     */
    public function resolve(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'is_active' => true]
        );
    }

    /**
     * شارژ کیف پول (اعتبار). مبلغ به ریال و صحیح مثبت.
     */
    public function credit(
        Wallet $wallet,
        int $amount,
        string $type = 'deposit',
        ?string $description = null,
        array $meta = []
    ): WalletTransaction {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $meta) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            $this->assertActive($locked);

            $balanceBefore = $locked->balance;
            $balanceAfter  = $balanceBefore + $amount;

            $locked->update(['balance' => $balanceAfter]);

            return $this->log($locked, $amount, 'credit', $type, $balanceBefore, $balanceAfter, $description, $meta);
        });
    }

    /**
     * برداشت از کیف پول (بدهکار). مبلغ به ریال و صحیح مثبت.
     */
    public function debit(
        Wallet $wallet,
        int $amount,
        string $type = 'withdrawal',
        ?string $description = null,
        array $meta = []
    ): WalletTransaction {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $meta) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            $this->assertActive($locked);

            if ($locked->balance < $amount) {
                throw new InsufficientBalanceException(
                    "موجودی کافی نیست. موجودی: {$locked->balance}، درخواست: {$amount}"
                );
            }

            $balanceBefore = $locked->balance;
            $balanceAfter  = $balanceBefore - $amount;

            $locked->update(['balance' => $balanceAfter]);

            return $this->log($locked, $amount, 'debit', $type, $balanceBefore, $balanceAfter, $description, $meta);
        });
    }

    /**
     * برگشت یک تراکنش (مثلاً کنسل سفارش پرداخت‌شده با کیف پول).
     * تراکنش معکوس ثبت می‌کند و به تراکنش اصلی لینک می‌دهد.
     */
    public function reverse(WalletTransaction $original, ?string $description = null): WalletTransaction
    {
        if ($original->reversed_at !== null) {
            throw new WalletException('این تراکنش قبلاً برگشت خورده است.');
        }

        return DB::transaction(function () use ($original, $description) {
            $locked = Wallet::whereKey($original->wallet_id)->lockForUpdate()->first();

            $balanceBefore = $locked->balance;

            // جهت معکوس: credit اصلی → debit و برعکس
            if ($original->direction === 'credit') {
                if ($locked->balance < $original->amount) {
                    throw new InsufficientBalanceException(
                        'موجودی برای برگشت این اعتبار کافی نیست.'
                    );
                }
                $balanceAfter = $balanceBefore - $original->amount;
                $direction    = 'debit';
            } else {
                $balanceAfter = $balanceBefore + $original->amount;
                $direction    = 'credit';
            }

            $locked->update(['balance' => $balanceAfter]);

            $original->update(['reversed_at' => now()]);

            return $this->log(
                $locked,
                $original->amount,
                $direction,
                'reversal',
                $balanceBefore,
                $balanceAfter,
                $description ?? "برگشت تراکنش #{$original->id}",
                ['reverses_transaction_id' => $original->id]
            );
        });
    }

    private function log(
        Wallet $wallet,
        int $amount,
        string $direction,
        string $type,
        int $balanceBefore,
        int $balanceAfter,
        ?string $description,
        array $meta
    ): WalletTransaction {
        return $wallet->transactions()->create([
            'amount'         => $amount,
            'direction'      => $direction,
            'type'           => $type,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'description'    => $description,
            'meta'           => $meta,
        ]);
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new WalletException('مبلغ باید عددی صحیح و مثبت باشد.');
        }
    }

    private function assertActive(Wallet $wallet): void
    {
        if (! $wallet->is_active) {
            throw new WalletException('کیف پول غیرفعال است.');
        }
    }
}
