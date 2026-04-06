<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Wallet;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
