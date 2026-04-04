# Laravel Polar

Polar.sh integration for Laravel — subscriptions, checkout, customer portal, webhooks, and plan management with Polar as Merchant of Record.

No tax headaches. No invoice logic. Polar handles it all.

---

## Installation

```bash
composer require akyroslabs/laravel-polar
```

Publish the config:

```bash
php artisan vendor:publish --tag=polar-config
```

Run the migration:

```bash
php artisan vendor:publish --tag=polar-migrations
php artisan migrate
```

## Configuration

Add to your `.env`:

```env
POLAR_API_KEY=polar_key_...
POLAR_WEBHOOK_SECRET=whsec_...
POLAR_SANDBOX=false
POLAR_BILLABLE_MODEL=App\Models\Tenant
```

Configure your plans in `config/polar.php`:

```php
'plans' => [
    'free' => [
        'product_id' => null,
        'limits' => ['servers' => 2, 'users' => 1],
        'features' => ['basic_monitoring'],
    ],
    'starter' => [
        'product_id' => 'polar-product-id-here',
        'limits' => ['servers' => 10, 'users' => 3],
        'features' => ['*'],
    ],
    'pro' => [
        'product_id' => 'polar-product-id-here',
        'limits' => ['servers' => 50, 'users' => 10],
        'features' => ['*'],
    ],
],
```

## Setup

Add the `Billable` trait to your model:

```php
use AkyrosLabs\Polar\Traits\Billable;

class Tenant extends Model
{
    use Billable;
}
```

Register the webhook URL in your Polar dashboard:

```
https://your-app.com/polar/webhook
```

## Usage

### Checkout

```php
// Redirect to Polar checkout
return $tenant->checkout('price_id_here')
    ->successUrl('/dashboard?upgraded=1')
    ->redirect();

// Or get the URL
$url = $tenant->checkout('price_id_here')->url();
```

### Subscription Checks

```php
$tenant->subscribed();          // Has active subscription?
$tenant->onPlan('pro');         // On specific plan?
$tenant->onTrial();             // In trial period?
$tenant->canceled();            // Canceled but still active?
$tenant->expired();             // Fully expired?
$tenant->planName();            // "pro"
$tenant->subscriptionEndsAt();  // Carbon date
```

### Plan Limits & Features

```php
$tenant->planLimit('servers');      // 50
$tenant->planLimit('users');        // 10
$tenant->hasFeature('attack_mode'); // true
```

### Plan Changes

```php
// Upgrade / downgrade
$tenant->changePlan('pro', 'new_price_id');

// Cancel at period end
$tenant->cancelSubscription();

// Resume canceled subscription
$tenant->resumeSubscription();
```

### Customer Portal

```php
return redirect($tenant->portalUrl());
```

## Webhooks

The package automatically handles these Polar webhook events:

| Event | Action |
|-------|--------|
| `subscription.created` | Sets plan, status, subscription IDs |
| `subscription.updated` | Syncs plan changes |
| `subscription.active` | Sets status to active |
| `subscription.canceled` | Sets status to canceled |
| `subscription.revoked` | Resets to free plan |
| `order.created` | Fires event (no DB change) |

### Custom Webhook Handling

Listen for events in your `EventServiceProvider` or a listener:

```php
use AkyrosLabs\Polar\Events\PolarSubscriptionCreated;

class HandleNewSubscription
{
    public function handle(PolarSubscriptionCreated $event): void
    {
        $tenant = $event->billable;
        $data = $event->data;

        // Send welcome email, provision resources, etc.
    }
}
```

Available events:
- `PolarWebhookReceived` — fired for every webhook
- `PolarSubscriptionCreated`
- `PolarSubscriptionUpdated`
- `PolarSubscriptionActive`
- `PolarSubscriptionCanceled`
- `PolarOrderCreated`

## Middleware

Protect routes by subscription status:

```php
// Require any active subscription
Route::middleware('polar.subscribed')->group(function () {
    // ...
});

// Require specific plan
Route::middleware('polar.subscribed:pro')->group(function () {
    // ...
});
```

Register the middleware alias in `bootstrap/app.php`:

```php
use AkyrosLabs\Polar\Http\Middleware\EnsureSubscribed;

$middleware->alias([
    'polar.subscribed' => EnsureSubscribed::class,
]);
```

## Artisan Commands

```bash
# Sync all subscriptions with Polar
php artisan polar:sync
```

## Sandbox Mode

Set `POLAR_SANDBOX=true` in `.env` to use Polar's sandbox environment for testing. All API calls will go to `sandbox-api.polar.sh`.

## Migration Columns

The published migration adds these columns to your billable model's table:

| Column | Type | Description |
|--------|------|-------------|
| `polar_customer_id` | string | Polar customer ID |
| `polar_subscription_id` | string | Active subscription ID |
| `polar_product_id` | string | Current product ID |
| `polar_price_id` | string | Current price ID |
| `subscription_status` | string | active, trialing, canceled, inactive |
| `trial_ends_at` | timestamp | Trial expiration |
| `current_period_end` | timestamp | Current billing period end |

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Polar.sh account

## License

MIT License

---

Built by [Akyros Labs LLC](https://akyroslabs.com) — hello@akyroslabs.com