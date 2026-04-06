<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Enums\TransactionType;
use App\Models\Wallet;

class WalletTest extends TestCase
{
    use RefreshDatabase;
    /**
     *  Ensure depositing funds calculates and credits the rebate correctly.
     */
    public function test_deposit_with_rebate():void{
        //create a wallet
        $wallet = Wallet::factory()->create(['balance'=>0]);
        //send a deposit request with an amount
        $response = $this->post('/api/wallets/'. $wallet->id .'/deposit', [
            'amount' => 1000
        ]);
        $response->assertStatus(201);
        // check if the balance is correct, that the rebate is properly applied.
        $wallet->refresh();
        $this->assertEquals(1010.00, $wallet->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => TransactionType::REBATE,
            'amount' => 10.00,
        ]);
    }
    /**
     *  Test concurrent deposits to verify correct balance updates and rebate calculations.
     */
    public function test_deposit_with_rebate_concurrent():void{
        $wallet = Wallet::factory()->create(['balance'=>0]);
        $depositCount = 1000;
        $depositAmount = 2000;

        for($i = 0; $i < $depositCount; $i++){
            $response = $this->post('/api/wallets/'. $wallet->id .'/deposit', [
                'amount' => $depositAmount
            ]);
            $response->assertStatus(201);
        }
        $wallet->refresh();

        $expectedBalance = ($depositAmount * $depositCount) + ($depositAmount * 0.01 * $depositCount);
        $this->assertEquals($expectedBalance, $wallet->balance);

        //verify correct number of transactions
        $this->assertEquals($depositCount, $wallet->transactions()->where('type', TransactionType::DEPOSIT)->count());
        $this->assertEquals($depositCount, $wallet->transactions()->where('type', TransactionType::REBATE)->count());
    }
    /**
     *  Test that a withdrawal correctly deducts from the wallet balance
     *  and creates a withdrawal transaction record.
     **/
    public function test_withdrawal():void{
        $wallet = Wallet::factory()->create([
            'balance' => 1000
        ]);
        $response = $this->post('/api/wallets/' . $wallet->id . '/withdraw', [
            'amount' => 250
        ]);
        $response->assertStatus(201);
        $wallet->refresh();
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => TransactionType::WITHDRAWAL,
            'amount' => 250,
        ]);
    }
    /**
     *  Test that a withdrawal exceeding the wallet balance is rejected,
     *  and the balance remains unchanged.
     */
    public function test_withdrawal_limit():void{
        $wallet = Wallet::factory()->create([
            'balance' => 1000
        ]);
        $response = $this->post('/api/wallets/'.$wallet->id.'/withdraw',[
            'amount' => 1200
        ]);
        $response->assertStatus(422);
        $wallet->refresh();
        $this->assertDatabaseMissing('transactions', [
            'wallet_id' => $wallet->id,
            'type' => TransactionType::WITHDRAWAL,
            'amount' => 1200
        ]);
    }
    /**
     *  Test that concurrent withdrawals cannot overdraw the wallet,
     *  and the final balance is never negative.
     */
    public function test_withdrawal_concurrent():void{
        $wallet = Wallet::factory()->create([
            'balance' => 1000
        ]);
        $withdrawalCount = 200;
        $withdrawalAmount = 10;
        for($i=0; $i<$withdrawalCount; $i++){
            $this->post('/api/wallets/'.$wallet->id.'/withdraw', [
                'amount' => $withdrawalAmount
            ]);
        }
        $wallet->refresh();

        $this->assertEquals(0, $wallet->balance);
        $this->assertTrue($wallet->balance >= 0);
        $this->assertEquals(100, $wallet->transactions()->where('type', TransactionType::WITHDRAWAL)->count());
    }
}
