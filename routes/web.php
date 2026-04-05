<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Wallet_Controller;
Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/wallets/{wallet}/deposit',[Wallet_Controller::class, 'deposit']);
Route::post('/api/wallets/{wallet}/withdraw', [Wallet_Controller::class, 'withdrawal']);
Route::get('/api/wallets/{wallet}/transactions', [Wallet_Controller::class, 'getTransactions']);
Route::get('/api/wallets/{wallet}/balance', [Wallet_Controller::class, 'getBalance']);
