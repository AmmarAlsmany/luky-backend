# MyFatoorah Webhook Setup Guide

This guide explains how to configure MyFatoorah webhooks for the Luky application.

## Overview

The payment system now uses **webhooks only** (no browser callbacks). This is more reliable and secure:
- ✅ Works even if user closes browser
- ✅ Server-to-server communication
- ✅ Signature verification for security
- ✅ Automatic payment status updates

## Step 1: Configure Your .env File

Update your `.env` file with the following MyFatoorah settings:

```env
# MyFatoorah Payment Gateway Configuration
MYFATOORAH_API_KEY=your_actual_api_key_here
MYFATOORAH_API_URL=https://api.myfatoorah.com
MYFATOORAH_WEBHOOK_SECRET=your_webhook_secret_from_portal
PAYMENT_TIMEOUT_MINUTES=5
```

### Environment URLs:
- **Test Environment**: `https://apitest.myfatoorah.com`
- **Production Environment**: `https://api.myfatoorah.com`

## Step 2: Configure Webhook in MyFatoorah Portal

### 2.1 Login to MyFatoorah
1. Go to https://portal.myfatoorah.com/
2. Login with your credentials

### 2.2 Navigate to Webhooks Settings
1. Click on **Settings** in the left sidebar
2. Click on **Webhooks**
3. Click **Add Webhook** button

### 2.3 Configure Webhook
Fill in the webhook details:

**Webhook URL:**
```
https://api.luky.sa/api/v1/payments/webhook
```

**Webhook Version:** Select `Webhook V2`

**Events to Subscribe:**
- ✅ Transaction Status Changed
- ✅ Refund Transaction Status Changed (optional)

**Status:** Active

### 2.4 Get Webhook Secret
1. After creating the webhook, copy the **Webhook Secret**
2. Add it to your `.env` file:
   ```env
   MYFATOORAH_WEBHOOK_SECRET=the_secret_you_copied
   ```

### 2.5 Test Webhook Connection
1. In MyFatoorah portal, click **Test** button next to your webhook
2. You should see a success message
3. Check your Laravel logs at `storage/logs/laravel.log` for:
   ```
   === MYFATOORAH WEBHOOK RECEIVED ===
   ```

## Step 3: Verify Your Server Configuration

### 3.1 Ensure Webhook Route is Accessible
Test that your webhook endpoint is reachable:

```bash
curl -X POST https://api.luky.sa/api/v1/payments/webhook \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

You should see a response (not 404/500).

### 3.2 Check SSL Certificate
MyFatoorah requires HTTPS with a valid SSL certificate. Verify:

```bash
curl -I https://api.luky.sa
```

Should show `200 OK` with valid SSL.

## Step 4: Payment Flow

### How it Works:

1. **Client initiates payment** → Mobile app calls `POST /api/v1/payments/initiate`
2. **Server creates payment** → Payment record created with `status='pending'`
3. **Client redirected to MyFatoorah** → User pays on MyFatoorah website
4. **MyFatoorah sends webhook** → Server receives POST to `/api/v1/payments/webhook`
5. **Server processes payment** → Updates booking, marks notification as read
6. **Client sees update** → Notification disappears, booking shows "Paid"

### Webhook Payload Example:

```json
{
  "EventType": 1,
  "Event": "TransactionStatusChanged",
  "DateTime": "2025-11-17 10:30:00",
  "Data": {
    "InvoiceId": 123456,
    "InvoiceStatus": "Paid",
    "InvoiceError": null,
    "PaymentGateway": "visa",
    "CustomerReference": "booking_789"
  }
}
```

## Step 5: Testing

### Test Complete Payment Flow:

1. **Create a booking** in the mobile app
2. **Provider accepts** the booking
3. **Client receives payment notification** (booking_accepted type)
4. **Client clicks "Pay Now"**
5. **Complete payment** on MyFatoorah test page
6. **Check webhook received**:
   ```bash
   tail -f storage/logs/laravel.log | grep "WEBHOOK"
   ```
7. **Verify in app**:
   - ✅ Notification should disappear
   - ✅ Booking status shows "Paid"
   - ✅ Provider sees payment completed

### Test Wallet Payment:

Wallet payments don't use webhooks (direct backend operation):

```bash
curl -X POST https://api.luky.sa/api/v1/payments/wallet \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"booking_id": 123}'
```

## Step 6: Monitoring

### Watch Webhook Logs:

```bash
# All webhook activity
tail -f storage/logs/laravel.log | grep "WEBHOOK"

# Successful payments
tail -f storage/logs/laravel.log | grep "PAYMENT COMPLETED"

# Failed webhooks
tail -f storage/logs/laravel.log | grep "WEBHOOK PROCESSING FAILED"
```

### Important Log Entries:

| Log Message | Meaning |
|-------------|---------|
| `=== MYFATOORAH WEBHOOK RECEIVED ===` | Webhook arrived from MyFatoorah |
| `=== WEBHOOK PROCESSED SUCCESSFULLY ===` | Payment status updated |
| `=== PAYMENT COMPLETED SUCCESSFULLY ===` | Booking paid, notification cleared |
| `Invalid webhook signature` | Security: Webhook rejected (wrong secret) |
| `Payment record not found` | Payment ID doesn't exist in database |

## Troubleshooting

### Webhook Not Received

**Check 1:** Verify webhook URL in MyFatoorah portal
- Should be: `https://api.luky.sa/api/v1/payments/webhook`
- Must be HTTPS, not HTTP

**Check 2:** Test webhook manually:
```bash
curl -X POST https://api.luky.sa/api/v1/payments/webhook \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: test" \
  -d '{"Data":{"InvoiceId":12345}}'
```

**Check 3:** Check firewall/security groups
- Ensure port 443 is open
- Whitelist MyFatoorah IPs if using firewall

### Payment Status Not Updating

**Check 1:** Verify webhook secret in `.env` matches portal

**Check 2:** Check database - payment record exists:
```sql
SELECT * FROM payments WHERE payment_id = 'INVOICE_ID_FROM_MYFATOORAH';
```

**Check 3:** Check logs for errors:
```bash
tail -100 storage/logs/laravel.log | grep "ERROR"
```

### Signature Verification Fails

**Issue:** `Invalid webhook signature`

**Solution:**
1. Get correct secret from MyFatoorah portal
2. Update `.env`:
   ```env
   MYFATOORAH_WEBHOOK_SECRET=correct_secret_here
   ```
3. Restart Laravel: `php artisan config:clear`

### Notification Still Showing After Payment

**Check 1:** Verify notification was marked as read:
```sql
SELECT * FROM notifications
WHERE type = 'booking_accepted'
AND data->>'booking_id' = 'YOUR_BOOKING_ID';
```

**Check 2:** Check webhook processing logs:
```bash
grep "notification_marked_read" storage/logs/laravel.log
```

## Security Notes

1. **Always use HTTPS** - HTTP webhooks will fail
2. **Keep webhook secret confidential** - Don't commit to git
3. **Verify signatures** - Our code automatically rejects invalid signatures
4. **Monitor failed attempts** - Check logs for suspicious activity

## Production Checklist

Before going live:

- [ ] MYFATOORAH_API_URL set to production: `https://api.myfatoorah.com`
- [ ] MYFATOORAH_API_KEY updated to production key
- [ ] Webhook configured in production MyFatoorah portal
- [ ] MYFATOORAH_WEBHOOK_SECRET updated with production secret
- [ ] SSL certificate valid on api.luky.sa
- [ ] Test payment completed successfully
- [ ] Webhook logs showing successful processing
- [ ] Notification disappears after payment
- [ ] Test both MyFatoorah payment AND wallet payment

## Support

If you encounter issues:

1. **Check logs first**: `storage/logs/laravel.log`
2. **Check MyFatoorah portal**: Webhook delivery logs
3. **Test webhook manually**: Use curl commands above
4. **Contact MyFatoorah support**: https://myfatoorah.readme.io/docs/webhook

## API Endpoints Reference

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/payments/methods` | GET | Get available payment methods |
| `/api/v1/payments/initiate` | POST | Initiate MyFatoorah payment |
| `/api/v1/payments/wallet` | POST | Pay with wallet balance |
| `/api/v1/payments/webhook` | POST | Receive MyFatoorah webhooks |

## Webhook Handler Code Location

**Controller:** `app/Http/Controllers/Api/PaymentController.php`
- `webhook()` method (line 234)
- `processPaymentStatus()` helper (line 455)

**Route:** `routes/api.php` (line 182)
```php
Route::post('/v1/payments/webhook', [PaymentController::class, 'webhook']);
```

**Service:** `app/Services/MyFatoorahService.php`
- No callback URLs needed anymore
- Only API key and URL required
