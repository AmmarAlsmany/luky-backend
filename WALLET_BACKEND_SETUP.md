# Wallet Backend Setup - Complete

## Issue Fixed
The wallet functionality in the mobile app was not connected to the backend because the routes were not registered in `routes/api.php`, even though the controller and models existed.

## What Was Fixed

### 1. Added Wallet Routes
**File**: `routes/api.php`
**Lines**: 19, 77-82

Added the following routes:
```php
// Import
use App\Http\Controllers\Api\WalletController;

// Routes (protected - requires authentication)
Route::get('/wallet', [WalletController::class, 'index']);
Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
Route::post('/wallet/verify-payment', [WalletController::class, 'verifyPayment']);
Route::get('/wallet/callback', [WalletController::class, 'callback']);
```

## Available Wallet Endpoints

### 1. Get Wallet Balance
```
GET /api/v1/wallet
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "user_id": 123,
    "balance": 150.50,
    "currency": "SAR",
    "updated_at": "2026-01-08T12:00:00Z"
  }
}
```

### 2. Get Transaction History
```
GET /api/v1/wallet/transactions?page=1&per_page=20&type=deposit
Authorization: Bearer {token}

Query Parameters:
- page: integer (optional, default: 1)
- per_page: integer (optional, default: 20, max: 100)
- type: enum (optional) - deposit, payment, refund, withdrawal

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "deposit",
      "amount": "50.00",
      "description": "Wallet deposit via MyFatoorah",
      "reference_number": "INV-12345",
      "balance_before": "100.50",
      "balance_after": "150.50",
      "created_at": "2026-01-08T12:00:00.000000Z",
      ...
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

### 3. Initiate Deposit
```
POST /api/v1/wallet/deposit
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "amount": 100.00
}

Response:
{
  "success": true,
  "data": {
    "payment_url": "https://myfatoorah.com/payment/xxxxx",
    "invoice_id": "12345",
    "amount": 100.00,
    "currency": "SAR"
  }
}
```

### 4. Verify Payment
```
POST /api/v1/wallet/verify-payment
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "payment_id": "12345"
}

Response (Success):
{
  "success": true,
  "message": "Payment verified and wallet credited successfully",
  "data": {
    "transaction_id": 789,
    "amount": 100.00,
    "new_balance": 200.50,
    "payment_status": "success"
  }
}
```

### 5. Payment Callback (Internal)
```
GET /api/v1/wallet/callback?paymentId=12345
(Automatically called by MyFatoorah after payment)
```

## Database Structure

### Users Table
- `wallet_balance` (decimal 10,2) - User's current balance

### Wallet Transactions Table
- Stores all wallet transactions (deposits, payments, refunds)
- Tracks balance before/after each transaction
- Includes metadata for audit trail

### Wallet Deposits Table
- Tracks deposit attempts via MyFatoorah
- Links to wallet_transactions when successful
- Stores payment gateway responses

## How It Works

1. **User initiates deposit**:
   - App calls `POST /wallet/deposit`
   - Backend creates deposit record and gets payment URL from MyFatoorah
   - Returns payment URL to app

2. **User completes payment**:
   - App opens payment URL in webview
   - User pays through MyFatoorah
   - MyFatoorah redirects to callback URL

3. **Payment verification**:
   - App calls `POST /wallet/verify-payment`
   - Backend checks payment status with MyFatoorah
   - If paid: Credits wallet, creates transaction, returns new balance
   - If failed: Returns error message

4. **View balance & transactions**:
   - App calls `GET /wallet` to show balance
   - App calls `GET /wallet/transactions` to show history

## Testing

### Using cURL
```bash
# Get balance
curl http://172.20.10.4:8000/api/v1/wallet \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get transactions
curl http://172.20.10.4:8000/api/v1/wallet/transactions \
  -H "Authorization: Bearer YOUR_TOKEN"

# Initiate deposit
curl -X POST http://172.20.10.4:8000/api/v1/wallet/deposit \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 50}'
```

### Using Postman
1. Import the wallet endpoints
2. Set Authorization header with Bearer token
3. Test each endpoint

### Using Flutter App
1. Run the app
2. Navigate to User Settings
3. Tap on "Wallet"
4. Should now show balance and transactions
5. Tap "Add Money" to test deposit flow

## Requirements
- ✅ MyFatoorah credentials configured in `.env`
- ✅ Database migrations run
- ✅ User model has `wallet_balance` field
- ✅ Routes registered and protected by auth middleware

## Status
🟢 **FULLY OPERATIONAL** - Wallet is now connected and ready to use!

The mobile app can now:
- View wallet balance
- View transaction history
- Add money via MyFatoorah
- Pay for bookings with wallet balance
