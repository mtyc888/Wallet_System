<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected $message = 'Insufficient funds.';

    public function render(){
        return response()->json(['message' => $this->message], 422);
    }
}
