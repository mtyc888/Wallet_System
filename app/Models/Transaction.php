<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TransactionType;
class Transaction extends Model
{
    protected $table = 'transactions';
    const UPDATED_AT = null;
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'created_at'
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'type' => TransactionType::class
    ];
    public function wallet():BelongsTo{
        return $this->belongsTo(Wallet::class);
    }
}
