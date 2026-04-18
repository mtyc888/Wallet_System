<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;
use App\Jobs\CalculateRebate;
use Illuminate\Http\JsonResponse;
use App\Exceptions\InsufficientFundsException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
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
            // using lockForUpdate for race condition mitigation
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
     *
     *  Notes:
     *  1) Using a boolean value to determine 201/422 does not rollback the transaction if something wrong were to happen.
     *     Since DB::transactions() only rollback on an exception. Hence, we have to throw an exception if wallet balance
     *     is not enough for the withdrawal.
     *
     *  2) Added specific error handling for catching DB failures and balance errors.
     *
     *
     *  @param Wallet $wallet
     *  @param Request $request
     *  @return JsonResponse
     */
    public function withdrawal(Wallet $wallet, Request $request){
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01'
        ]);
        try{
            DB::transaction(function() use ($wallet, $validated){
                // using lockForUpdate for race condition mitigation
                $lockedWallet = Wallet::lockForUpdate()->find($wallet->id);
                if($lockedWallet->balance >= $validated['amount']){
                    $lockedWallet->decrement('balance', $validated['amount']);
                    $lockedWallet->transactions()->create([
                        'type' => TransactionType::WITHDRAWAL,
                        'amount' => $validated['amount']
                    ]);
                }else{
                    throw new InsufficientFundsException('Insulfficient funds.');
                }
            });
        }catch(InsufficientFundsException $e){
            Log::error('Wallet insulfficient funds.',[
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => $e->getMessage()], 422);
        }catch(QueryException $e){
            Log::error('Withdrawal Error', [
                'wallet_id' => $wallet->id,
                'amount' => $validated['amount'],
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'something went wrong: ' . $e->getMessage()], 500);
        }
        return response()->json(['message' => 'withdrawal successful.'], 201);
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
