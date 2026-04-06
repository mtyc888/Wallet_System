<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;
use App\Jobs\CalculateRebate;
use Illuminate\Http\JsonResponse;
class Wallet_Controller extends Controller
{
    /**
     *  Deposit funds into a wallet and dispatch a 1% rebate calculation job.
     *  @param Wallet $wallet
     *  @param Request $request
     *  @return JsonResponse
     */
    public function deposit(Wallet $wallet, Request $request){
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01'
        ]);
        DB::transaction(function () use ($wallet, $validated){
            // we should lockForUpdate and use atomic operations for race condition mitigation
            $wallet = Wallet::lockForUpdate()->find($wallet->id);

            $wallet->increment('balance', $validated['amount']);

            $wallet->transactions()->create([
                'type' => TransactionType::DEPOSIT,
                'amount' => $validated['amount']
            ]);
            CalculateRebate::dispatch($wallet, $validated['amount'])->afterCommit();
        });
        return response()->json([
            'message' => 'deposit successful.',
            'wallet_id' => $wallet->id
        ], 201);
    }
    /**
     *  Withdraw funds from a wallet and making sure that it does not get overdrawn.
     *  @param Wallet $wallet
     *  @param Request $request
     *  @return JsonResponse
     */
    public function withdrawal(Wallet $wallet, Request $request){
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01'
        ]);
        $success = DB::transaction(function() use ($wallet, $validated){
            $wallet = Wallet::lockForUpdate()->find($wallet->id);
            if($wallet->balance >= $validated['amount']){
                $wallet->decrement('balance', $validated['amount']);
                $wallet->transactions()->create([
                    'type' => TransactionType::WITHDRAWAL,
                    'amount' => $validated['amount']
                ]);
            }else{
                return false;
            }
            return true;
        });
        if($success){
            return response()->json([
                'message' => 'Withdrawal Successful.'
            ], 201);
        }else{
            return response()->json([
                'message' => 'Insufficient Funds.'
            ], 422);
        }
    }
    /**
     *  Get the balance from a wallet
     *  @param Wallet $wallet
     *  @return JsonResponse
     */
    public function getBalance(Wallet $wallet){
        return response()->json([
            'wallet_id' => $wallet->id,
            'balance' => $wallet->balance
        ]);
    }
    /**
     *  Get the paginated transactions from a wallet
     *  @param Wallet $wallet
     *  @return JsonResponse
     */
    public function getTransactions(Wallet $wallet){
        return response()->json([
            'wallet_id' => $wallet->id,
            'transactions' => $wallet->transactions()->latest()->paginate(15)
        ]);
    }
}
