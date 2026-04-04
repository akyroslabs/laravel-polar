<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\Models\Customer;
use AkyrosLabs\Polar\PolarClient;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait ManagesCustomer
{
    public function customer(): MorphOne
    {
        return $this->morphOne(Customer::class, 'billable');
    }

    public function createAsCustomer(array $attributes = []): Customer
    {
        $client = app(PolarClient::class);

        $result = $client->createCustomer(
            $this->polarEmail(),
            $this->polarName(),
            array_merge($attributes, [
                'billable_type' => get_class($this),
                'billable_id' => (string) $this->id,
            ])
        );

        return $this->customer()->create([
            'polar_id' => $result['id'],
        ]);
    }

    public function polarCustomerId(): ?string
    {
        return $this->customer?->polar_id;
    }

    public function ensureCustomer(): Customer
    {
        if ($this->customer && $this->customer->polar_id) {
            return $this->customer;
        }

        // Try find by email in Polar
        $client = app(PolarClient::class);
        $existing = $client->getCustomerByEmail($this->polarEmail());

        if ($existing) {
            if ($this->customer) {
                $this->customer->update(['polar_id' => $existing['id']]);
                return $this->customer->fresh();
            }
            return $this->customer()->create(['polar_id' => $existing['id']]);
        }

        return $this->createAsCustomer();
    }

    public function polarName(): string
    {
        return $this->name ?? 'Customer';
    }

    public function polarEmail(): string
    {
        // Try direct email, then owner's email (for Tenant models)
        return $this->email ?? $this->owner?->email ?? throw new \RuntimeException('Billable model must have an email.');
    }

    public function customerPortalUrl(): string
    {
        $customer = $this->ensureCustomer();
        $client = app(PolarClient::class);
        $session = $client->createCustomerSession($customer->polar_id);
        return $session['customer_portal_url'];
    }

    public function redirectToCustomerPortal(): \Illuminate\Http\RedirectResponse
    {
        return redirect($this->customerPortalUrl());
    }
}
