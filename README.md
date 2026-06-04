# Laravel Polar

> [!CAUTION]
> **This package is deprecated and unmaintained.**
> Polar's API has moved on (checkout body shape, webhook secret format,
> signature verification semantics) and keeping this package in step
> would be a permanent moving-target chase. Use
> **[`danestves/laravel-polar`](https://github.com/danestves/laravel-polar)**
> instead — it ships against the current Polar API, is built on the
> official [`polar-sh/sdk`](https://github.com/polarsource/polar-php),
> and has a healthy upstream maintenance cadence.
>
> #### Quick migration map (≈30 min for a typical app)
>
> | This package | `danestves/laravel-polar` |
> |---|---|
> | `use AkyrosLabs\Polar\Billable;` | `use Danestves\LaravelPolar\Billable;` |
> | `$user->checkout($productId)` | `$user->checkout([$productId])` (array) |
> | `->metadata([...])` / `->successUrl(...)` | `->withMetadata([...])` / `->withSuccessUrl(...)` |
> | Event: `AkyrosLabs\Polar\Events\OrderCreated` with `$event->data` array | `Danestves\LaravelPolar\Events\OrderCreated` with `$event->billable`, `$event->order`, `$event->payload` |
> | `POLAR_API_KEY`, `POLAR_SANDBOX=true\|false` | `POLAR_ACCESS_TOKEN`, `POLAR_SERVER=sandbox\|production`, `POLAR_ORGANIZATION_ID` |
> | `polar_orders.polar_customer_id` column | renames to `customer_id` |
> | Webhook URL `/polar/webhook` | unchanged (`/polar/webhook`) |
>
> The webhook controller + event names are deliberately close enough
> that most listeners just need an import swap and a small refactor of
> how they read order metadata (the new event ships the full SDK
> payload as `$event->payload`; round-trip via
> `json_decode(json_encode($event->payload), true)` if you need the
> flat array shape this package used to provide).
>
> The repository will be archived shortly. Issues and PRs will not be
> reviewed.

---

Polar.sh integration for Laravel — subscriptions, checkout, customer portal, webhooks, plan limits, usage billing, benefits, and more. Polar as Merchant of Record.

No tax headaches. No invoice logic. Polar handles it all.

---

## Installation

```bash
composer require akyroslabs/laravel-polar
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=polar-config
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

Configure plans in `config/polar.php`:

```php
'plans' => [
    'free' => [
        'product_id' => null,
        'limits' => ['servers' => 2, 'users' => 1],
        'features' => ['basic_monitoring'],
    ],
    'starter' => [
        'product_id' => 'polar-product-id',
        'limits' => ['servers' => 10, 'users' => 3],
        'features' => ['*'],
    ],
    'pro' => [
        'product_id' => 'polar-product-id',
        'limits' => ['servers' => 50, 'users' => 10],
        'features' => ['*'],
    ],
],
```

## Setup

Add the `Billable` trait to your model:

```php
use AkyrosLabs\Polar\Billable;

class Tenant extends Model
{
    use Billable;
}
```

Register the webhook URL in your Polar dashboard:

```
https://your-app.com/polar/webhook
```

## Checkout

```php
// Redirect to Polar checkout
return $tenant->checkout('price_id')
    ->successUrl('/dashboard?upgraded=1')
    ->redirect();

// Subscribe to a plan
return $tenant->subscribe('price_id')
    ->successUrl('/dashboard')
    ->redirect();

// One-time charge
return $tenant->charge(4999, 'product_id')
    ->redirect();

// Just get the URL
$url = $tenant->checkout('price_id')->url();
```

## Subscription Management

```php
// Check status
$tenant->subscribed();              // Has active subscription?
$tenant->subscribed('default', $productId);  // On specific product?
$tenant->onTrial();                 // In trial?
$tenant->onGracePeriod();           // Canceled but still active?

// Get subscription
$sub = $tenant->subscription();
$sub->active();                     // Active?
$sub->canceled();                   // Canceled?
$sub->valid();                      // Active, trial, or grace period?
$sub->ended();                      // Fully ended?
$sub->pastDue();                    // Payment past due?

// Actions
$sub->swap('new_price_id');         // Change plan
$sub->cancel();                     // Cancel at period end
$sub->resume();                     // Resume during grace period
```

## Customer Portal

```php
return redirect($tenant->customerPortalUrl());

// Or shorthand
return $tenant->redirectToCustomerPortal();
```

## Plan Limits & Features

```php
$tenant->planName();                    // "pro"
$tenant->planLimit('servers');          // 50
$tenant->planLimit('users');            // 10
$tenant->hasFeature('attack_mode');     // true
$tenant->onFreePlan();                  // false
$tenant->exceedsLimit('servers', 48);   // false
$tenant->exceedsLimit('servers', 50);   // true
$tenant->planLimits();                  // ['servers' => 50, 'users' => 10]
$tenant->planFeatures();               // ['*']
```

## Usage-Based Billing

```php
// Track single event
$tenant->ingestUsageEvent('api_call', ['endpoint' => '/servers']);

// Batch events
$tenant->ingestUsageEvents([
    ['name' => 'api_call', 'metadata' => ['endpoint' => '/alerts']],
    ['name' => 'api_call', 'metadata' => ['endpoint' => '/servers']],
]);

// Get meters
$tenant->listCustomerMeters();
```

## Benefits

```php
$tenant->listBenefits();
$tenant->getBenefit('benefit_id');
$tenant->listBenefitGrants('benefit_id');
```

## Orders

```php
$tenant->orders;                          // All orders
$tenant->hasPurchasedProduct('product_id'); // Check purchase
```

## Webhooks

The package automatically processes these Polar events:

| Event | Action |
|-------|--------|
| `subscription.created` | Creates subscription + customer record |
| `subscription.updated` | Syncs status, product, period end |
| `subscription.active` | Sets status to active |
| `subscription.canceled` | Sets status to canceled |
| `subscription.revoked` | Sets status to revoked |
| `order.created` | Creates order record |
| `order.updated` | Syncs order data |
| `customer.created/updated/deleted` | Syncs customer records |
| `checkout.created/updated` | Fires events |
| `benefit_grant.created/updated/revoked` | Fires events |

### Custom Event Listeners

```php
use AkyrosLabs\Polar\Events\SubscriptionCreated;

class HandleNewSubscription
{
    public function handle(SubscriptionCreated $event): void
    {
        $tenant = $event->billable;
        // Send welcome email, provision resources, etc.
    }
}
```

**17 events available:** WebhookReceived, WebhookHandled, SubscriptionCreated, SubscriptionUpdated, SubscriptionActive, SubscriptionCanceled, SubscriptionRevoked, OrderCreated, OrderUpdated, CustomerCreated, CustomerUpdated, CustomerDeleted, CheckoutCreated, CheckoutUpdated, BenefitGrantCreated, BenefitGrantUpdated, BenefitGrantRevoked

## Middleware

```php
// In bootstrap/app.php — auto-registered as 'subscribed'
Route::middleware('subscribed')->group(function () {
    // Requires any active subscription
});

Route::middleware('subscribed:pro')->group(function () {
    // Requires specific plan
});
```

## Blade Directives

```blade
@subscribed
    <p>You have an active subscription.</p>
@endsubscribed

@onPlan('pro')
    <p>Pro features here.</p>
@endonPlan

@onTrial
    <p>Your trial ends soon.</p>
@endonTrial

@feature('attack_mode')
    <button>Activate Attack Mode</button>
@endfeature
```

## Artisan Commands

```bash
# Sync all subscriptions with Polar
php artisan polar:sync

# List all products
php artisan polar:products
```

## Facade

```php
use AkyrosLabs\Polar\Facades\Polar;

$products = Polar::listProducts();
$subscription = Polar::getSubscription($id);
$session = Polar::createCustomerSession($customerId);
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `polar_customers` | Billable ↔ Polar customer mapping, generic trial |
| `polar_subscriptions` | Active subscriptions with status, product, period |
| `polar_orders` | Orders with amounts, tax, refund tracking |

All tables use polymorphic `billable` relationships — works with any model.

## Sandbox Mode

Set `POLAR_SANDBOX=true` to use `sandbox-api.polar.sh` for testing.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## License

MIT License

---

Built by [Akyros Labs LLC](https://akyroslabs.com) — hello@akyroslabs.com