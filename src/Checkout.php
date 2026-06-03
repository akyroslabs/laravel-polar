<?php

namespace AkyrosLabs\Polar;

use Illuminate\Http\RedirectResponse;

class Checkout
{
    private string $successUrl;
    private ?string $returnUrl = null;
    private ?int $amount = null;
    private ?string $currency = null;
    private ?string $discountId = null;
    private ?string $subscriptionType = null;
    private array $customerMetadata = [];
    private array $metadata = [];

    public function __construct(
        private mixed $billable,
        private array $products,
        array $options = [],
    ) {
        $this->successUrl = url(config('polar.redirect_after_checkout', '/dashboard'));
        $this->amount = $options['amount'] ?? null;
        $this->subscriptionType = $options['subscription_type'] ?? null;
    }

    public function successUrl(string $url): self { $this->successUrl = url($url); return $this; }
    public function returnUrl(string $url): self { $this->returnUrl = url($url); return $this; }
    public function amount(int $cents): self { $this->amount = $cents; return $this; }
    public function currency(string $currency): self { $this->currency = $currency; return $this; }
    public function discount(string $discountId): self { $this->discountId = $discountId; return $this; }
    public function metadata(array $data): self { $this->metadata = $data; return $this; }
    public function customerMetadata(array $data): self { $this->customerMetadata = $data; return $this; }

    public function create(): array
    {
        $this->billable->ensureCustomer();

        $client = app(PolarClient::class);

        $params = [
            'products' => $this->products,
            'customer_id' => $this->billable->polarCustomerId(),
            'success_url' => $this->successUrl,
            'metadata' => array_merge($this->metadata, [
                'billable_type' => get_class($this->billable),
                'billable_id' => (string) $this->billable->id,
            ]),
            'customer_metadata' => array_merge($this->customerMetadata, [
                'billable_type' => get_class($this->billable),
                'billable_id' => (string) $this->billable->id,
            ]),
        ];

        if ($this->amount) $params['amount'] = $this->amount;
        if ($this->currency) $params['currency'] = $this->currency;
        if ($this->discountId) $params['discount_id'] = $this->discountId;
        if ($this->returnUrl) $params['return_url'] = $this->returnUrl;

        // Remove null values
        $params = array_filter($params, fn ($v) => $v !== null);

        return $client->createCheckout($params);
    }

    public function redirect(): RedirectResponse
    {
        $checkout = $this->create();
        return redirect($checkout['url']);
    }

    public function url(): string
    {
        return $this->create()['url'];
    }
}
