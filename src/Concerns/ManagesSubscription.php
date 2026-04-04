<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesSubscription
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'billable');
    }

    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()->where('type', $type)->first();
    }

    public function subscribed(string $type = 'default', ?string $productId = null): bool
    {
        $subscription = $this->subscription($type);
        if (!$subscription) return false;
        if ($productId && !$subscription->hasProduct($productId)) return false;
        return $subscription->valid();
    }

    public function onTrial(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);
        if ($subscription && $subscription->onTrial()) return true;

        // Check generic trial on customer
        return $this->customer?->onGenericTrial() ?? false;
    }

    public function subscribedToProduct(string $productId, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);
        return $subscription && $subscription->hasProduct($productId) && $subscription->valid();
    }

    public function onGracePeriod(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);
        return $subscription && $subscription->onGracePeriod();
    }
}
