<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Wallet;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\Http;

class ConcurrencyPoolTest extends TestCase
{
    /**
     *  Test that concurrent withdrawals cannot overdraw the wallet,
     *  and the final balance is never negative.
     */
    public function test_withdrawal_concurrent_with_pool(): void
    {
        // Create a wallet on php artisan tinker then use that specific wallet's id
        $walletId = 1;
        $withdrawalAmount = 10;
        $concurrentRequests = 100;

        $wallet = Wallet::findOrFail($walletId);
        $wallet->update(['balance' => 100]);
        $wallet->transactions()->delete();

        Http::pool(function ($pool) use ($walletId, $withdrawalAmount, $concurrentRequests) {
            for ($i = 0; $i < $concurrentRequests; $i++) {
                $pool->post('http://127.0.0.1:8000/api/wallets/' . $walletId . '/withdraw', [
                    'amount' => $withdrawalAmount,
                ]);
            }
        });

        sleep(3);

        $wallet->refresh();

        $this->assertTrue($wallet->balance >= 0);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals(10, $wallet->transactions()->where('type', TransactionType::WITHDRAWAL)->count());
        $wallet->transactions()->delete();
    }
    /**
     *  Test concurrent deposits to verify correct balance updates and rebate calculations.
     */
    public function test_deposit_concurrent_with_pool(): void
    {
        // Create a wallet on php artisan tinker then use that specific wallet's id
        $walletId = 1;
        $depositAmount = 100;
        $concurrentRequests = 100;

        $wallet = Wallet::findOrFail($walletId);
        $wallet->update(['balance' => 0]);
        $wallet->transactions()->delete();

        Http::pool(function ($pool) use ($walletId, $depositAmount, $concurrentRequests) {
            for ($i = 0; $i < $concurrentRequests; $i++) {
                $pool->post('http://127.0.0.1:8000/api/wallets/' . $walletId . '/deposit', [
                    'amount' => $depositAmount,
                ]);
            }
        });

        sleep(3);

        $wallet->refresh();

        $expectedBalance = ($depositAmount * $concurrentRequests) + ($depositAmount * 0.01 * $concurrentRequests);
        $this->assertEquals($expectedBalance, $wallet->balance);
        $this->assertEquals($concurrentRequests, $wallet->transactions()->where('type', TransactionType::DEPOSIT)->count());
        $this->assertEquals($concurrentRequests, $wallet->transactions()->where('type', TransactionType::REBATE)->count());
        $wallet->transactions()->delete();
    }
}