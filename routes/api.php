<?php

use App\Http\Controllers\Wallet_Controller;
use Illuminate\Support\Facades\Route;

Route::post('/wallets/{wallet}/deposit',[Wallet_Controller::class, 'deposit']);
Route::post('/wallets/{wallet}/withdraw', [Wallet_Controller::class, 'withdrawal']);
Route::get('/wallets/{wallet}/transactions', [Wallet_Controller::class, 'getTransactions']);
Route::get('/wallets/{wallet}/balance', [Wallet_Controller::class, 'getBalance']);
