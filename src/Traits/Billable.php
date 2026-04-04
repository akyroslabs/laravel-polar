<?php

namespace AkyrosLabs\Polar\Traits;

use AkyrosLabs\Polar\PolarClient;
use AkyrosLabs\Polar\Checkout;

trait Billable
{
    /**
     * Check if the model has an active subscription.
     */
    public function subscribed(): bool
    {
        return in_array($this->subscription_status, ['active', 'trialing']);
    }

    /**
     * Check if on a specific plan.
     */
    public function onPlan(string $plan): bool
    {
        return $this->plan === $plan && $this->subscribed();
    }

    /**
     * Check if on trial.
     */
    public function onTrial(): bool
    {
        return $this->subscription_status === 'trialing'
            || ($this->trial_ends_at && $this->trial_ends_at->isFuture());
    }

    /**
     * Check if subscription is canceled but still active.
     */
    public function canceled(): bool
    {
        return $this->subscription_status === 'canceled'
            && $this->current_period_end
            && $this->current_period_end->isFuture();
    }

    /**
     * Check if subscription has fully expired.
     */
    public function expired(): bool
    {
        if ($this->plan === 'free') return false;
        return $this->subscription_status === 'canceled'
            && (!$this->current_period_end || $this->current_period_end->isPast());
    }

    /**
     * Get subscription end date.
     */
    public function subscriptionEndsAt(): ?\Carbon\Carbon
    {
        return $this->current_period_end;
    }

    /**
     * Get current plan name.
     */
    public function planName(): string
    {
        return $this->plan ?? 'free';
    }

    /**
     * Get a plan limit value.
     */
    public function planLimit(string $key, mixed $default = 0): mixed
    {
        $plans = config('polar.plans', []);
        return $plans[$this->plan]['limits'][$key] ?? $default;
    }

    /**
     * Check if current plan has a feature.
     */
    public function hasFeature(string $feature): bool
    {
        $plans = config('polar.plans', []);
        $features = $plans[$this->plan]['features'] ?? [];
        return in_array('*', $features) || in_array($feature, $features);
    }

    /**
     * Start a checkout session.
     */
    public function checkout(string $productPriceId): Checkout
    {
        return new Checkout($this, $productPriceId);
    }

    /**
     * Get the Polar customer portal URL.
     */
    public function portalUrl(): string
    {
        $client = app(PolarClient::class);

        // Ensure we have a Polar customer
        $this->ensurePolarCustomer();

        $session = $client->createCustomerSession($this->polar_customer_id);
        return $session['customer_portal_url'];
    }

    /**
     * Change to a different plan.
     */
    public function changePlan(string $plan, string $productPriceId): self
    {
        if (!$this->polar_subscription_id) {
            throw new \RuntimeException('No active subscription to change.');
        }

        $client = app(PolarClient::class);
        $client->updateSubscription($this->polar_subscription_id, $productPriceId);

        $this->update([
            'plan' => $plan,
            'polar_price_id' => $productPriceId,
        ]);

        return $this;
    }

    /**
     * Cancel the subscription at period end.
     */
    public function cancelSubscription(): self
    {
        if (!$this->polar_subscription_id) {
            throw new \RuntimeException('No active subscription to cancel.');
        }

        $client = app(PolarClient::class);
        $client->cancelSubscription($this->polar_subscription_id);

        $this->update(['subscription_status' => 'canceled']);

        return $this;
    }

    /**
     * Resume a canceled subscription (if still in current period).
     */
    public function resumeSubscription(): self
    {
        if (!$this->polar_subscription_id || !$this->canceled()) {
            throw new \RuntimeException('No canceled subscription to resume.');
        }

        $client = app(PolarClient::class);
        // Polar API: update subscription to remove cancellation
        $client->updateSubscription($this->polar_subscription_id, $this->polar_price_id);

        $this->update(['subscription_status' => 'active']);

        return $this;
    }

    /**
     * Ensure we have a Polar customer ID for this model.
     */
    public function ensurePolarCustomer(): void
    {
        if ($this->polar_customer_id) {
            return;
        }

        $client = app(PolarClient::class);

        // Try to find existing customer by email
        $email = $this->email ?? $this->owner?->email ?? null;
        if (!$email) {
            throw new \RuntimeException('Billable model must have an email or owner with email.');
        }

        $existing = $client->getCustomerByEmail($email);
        if ($existing) {
            $this->update(['polar_customer_id' => $existing['id']]);
            return;
        }

        // Create new customer
        $name = $this->name ?? $this->owner?->name ?? $email;
        $customer = $client->createCustomer($email, $name, [
            'model_type' => static::class,
            'model_id' => $this->id,
        ]);

        $this->update(['polar_customer_id' => $customer['id']]);
    }

    /**
     * Casts for the Billable fields.
     */
    public function initializeBillable(): void
    {
        $this->mergeCasts([
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
        ]);
    }
}
