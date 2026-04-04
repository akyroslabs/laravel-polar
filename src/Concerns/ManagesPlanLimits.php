<?php

namespace AkyrosLabs\Polar\Concerns;

trait ManagesPlanLimits
{
    /**
     * Get current plan name resolved from active subscription.
     */
    public function planName(): string
    {
        $subscription = $this->subscription();
        if (!$subscription || !$subscription->valid()) {
            return 'free';
        }

        // Resolve plan name from product_id
        $plans = config('polar.plans', []);
        foreach ($plans as $name => $config) {
            if (($config['product_id'] ?? null) === $subscription->product_id) {
                return $name;
            }
        }

        return 'free';
    }

    /**
     * Get a plan limit value.
     */
    public function planLimit(string $key, mixed $default = 0): mixed
    {
        $plan = $this->planName();
        $plans = config('polar.plans', []);
        return $plans[$plan]['limits'][$key] ?? $default;
    }

    /**
     * Check if current plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $plan = $this->planName();
        $plans = config('polar.plans', []);
        $features = $plans[$plan]['features'] ?? [];
        return in_array('*', $features) || in_array($feature, $features);
    }

    /**
     * Get all plan limits as array.
     */
    public function planLimits(): array
    {
        $plan = $this->planName();
        $plans = config('polar.plans', []);
        return $plans[$plan]['limits'] ?? [];
    }

    /**
     * Get all plan features as array.
     */
    public function planFeatures(): array
    {
        $plan = $this->planName();
        $plans = config('polar.plans', []);
        return $plans[$plan]['features'] ?? [];
    }

    /**
     * Check if billable is on the free plan.
     */
    public function onFreePlan(): bool
    {
        return $this->planName() === 'free';
    }

    /**
     * Check if a resource count exceeds plan limit.
     */
    public function exceedsLimit(string $key, int $currentCount): bool
    {
        $limit = $this->planLimit($key);
        if ($limit === -1 || $limit === null) return false; // unlimited
        return $currentCount >= $limit;
    }
}
