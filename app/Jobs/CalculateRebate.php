<?php

namespace App\Jobs;

use App\Enums\TransactionType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
class CalculateRebate implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private Wallet $wallet, private float $depositAmount)
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $rebate = $this->depositAmount * 0.01;
        DB::transaction(function() use ($rebate){
            $this->wallet->increment('balance', $rebate);
            $this->wallet->transactions()->create([
                'type' => TransactionType::REBATE,
                'amount' => $rebate,
            ]);
        });
    }
}
