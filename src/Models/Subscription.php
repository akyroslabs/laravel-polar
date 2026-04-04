<?php

namespace AkyrosLabs\Polar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use AkyrosLabs\Polar\PolarClient;

class Subscription extends Model
{
    protected $table = 'polar_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    // Status checks
    public function active(): bool
    {
        return $this->status === 'active';
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            || ($this->trial_ends_at && $this->trial_ends_at->isFuture());
    }

    public function canceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled()
            && $this->current_period_end
            && $this->current_period_end->isFuture();
    }

    public function pastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function unpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    public function ended(): bool
    {
        return $this->canceled() && !$this->onGracePeriod();
    }

    // Check if on specific product
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    // Actions via API
    public function swap(string $productPriceId): self
    {
        $client = app(PolarClient::class);
        $result = $client->updateSubscription($this->polar_id, $productPriceId);
        $this->sync($result);
        return $this;
    }

    public function cancel(): self
    {
        $client = app(PolarClient::class);
        $result = $client->cancelSubscription($this->polar_id);
        $this->sync($result);
        return $this;
    }

    public function resume(): self
    {
        if (!$this->onGracePeriod()) {
            throw new \RuntimeException('Cannot resume a subscription that is not on a grace period.');
        }
        $client = app(PolarClient::class);
        $result = $client->resumeSubscription($this->polar_id);
        $this->sync($result);
        return $this;
    }

    // Sync from API/webhook data
    public function sync(array $attributes): self
    {
        $this->update([
            'status' => $attributes['status'] ?? $this->status,
            'product_id' => $attributes['product_id'] ?? $this->product_id,
            'price_id' => $attributes['price_id'] ?? $attributes['recurring_price_id'] ?? $this->price_id,
            'current_period_end' => isset($attributes['current_period_end'])
                ? \Carbon\Carbon::parse($attributes['current_period_end'])
                : $this->current_period_end,
            'trial_ends_at' => isset($attributes['trial_ends_at'])
                ? \Carbon\Carbon::parse($attributes['trial_ends_at'])
                : $this->trial_ends_at,
            'ends_at' => isset($attributes['ends_at'])
                ? \Carbon\Carbon::parse($attributes['ends_at'])
                : $this->ends_at,
        ]);
        return $this;
    }

    // Scopes
    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeTrialing($query) { return $query->where('status', 'trialing'); }
    public function scopeCanceled($query) { return $query->where('status', 'canceled'); }
    public function scopeValid($query) {
        return $query->where(function ($q) {
            $q->whereIn('status', ['active', 'trialing'])
              ->orWhere(function ($q2) {
                  $q2->where('status', 'canceled')
                     ->where('current_period_end', '>', now());
              });
        });
    }
}
