# Wallet System

This is a wallet management system that supports concurrent deposit and withdrawal operations.

## Set up instructions:

### Requirements
- PHP 8.3+
- Composer
- MySQL

### Installation

1. Clone the repository
```bash
   git clone https://github.com/mtyc888/Wallet_System.git
   cd Laravel_Wallet
```

2. Install dependencies
```bash
   composer install
```

3. Configure environment
```bash
   cp .env.example .env
   php artisan key:generate
```

4. Update `.env` with your database credentials

- DB_CONNECTION=mysql
- DB_DATABASE=wallet_system
- DB_USERNAME=root
- DB_PASSWORD=your_password
- QUEUE_CONNECTION=database

5. Run migrations
```bash
   php artisan migrate
```

6. Seed the database (creates test users and wallets)
```bash
   php artisan db:seed
```

7. Start the application

Option A: Artisan Serve
```bash
   php artisan serve
```
Option B: Laravel Herd

```bash
herd link
```
Access the app at `http://laravel-wallet.test`

Option C: Serve directly

```bash
php -S 127.0.0.1:8000 -t public
```
Access the app at `http://127.0.0.1:8000`
## API Endpoints

### Deposit
- **POST** `/api/wallets/{wallet}/deposit`
- **Body:** `{ "amount": 1000 }`
- **Response (201):** `{ "message": "deposit successful.", "wallet_id": 1 }`

### Withdraw
- **POST** `/api/wallets/{wallet}/withdraw`
- **Body:** `{ "amount": 500 }`
- **Response (201):** `{ "message": "Withdrawal Successful." }`
- **Response (422):** `{ "message": "Insufficient Funds." }`

### Get Balance
- **GET** `/api/wallets/{wallet}/balance`
- **Response (200):** `{ "wallet_id": 1, "balance": "1010.00" }`

### Get Transactions
- **GET** `/api/wallets/{wallet}/transactions`
- **Response (200):** Paginated list of transactions (15 per page). Use `?page=2` for next page.

## Testing with Postman

1. Create requests in Postman
2. For POST requests (deposit/withdraw):
   - Set method to **POST**
   - Go to **Body** tab → select **raw** → choose **JSON**
   - Enter the request body, e.g. `{ "amount": 1000 }`
   - Set the **Accept** header to `application/json`

##  Concurrency Handling

I used pessimistic locking (`lockForUpdate()`) inside a database transaction to prevent race conditions. When a request locks a wallet row, any other request trying to update the same wallet must wait until the first transaction completes. This guarantees that balance updates are processed one at a time.

```php
DB::transaction(function () use ($wallet, $validated){
    $wallet = Wallet::lockForUpdate()->find($wallet->id);

    $wallet->increment('balance', $validated['amount']);

    $wallet->transactions()->create([
        'type' => TransactionType::DEPOSIT,
        'amount' => $validated['amount']
    ]);
    CalculateRebate::dispatch($wallet, $validated['amount'])->afterCommit();
});
```

### Why perssimistic locking?
- **Pessimistic locking** locks the row immediately, forcing other requests to wait. This is a better locking for financial operations where every transaction must be accurate.

### Rebate Job Timing
The rebate calculation job is dispatched with `afterCommit()` to ensure the job only runs after the deposit transaction has been fully committed to the database. Without this, the job could execute before the deposit is persisted, leading to incorrect rebate calculations.
 
## Running tests

- cd Laravel_Wallet

- php artisan test --filter=WalletTest

Author: Marvin Tan 
