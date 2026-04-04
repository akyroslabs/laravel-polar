<?php

namespace AkyrosLabs\Polar;

use Illuminate\Http\RedirectResponse;

class Checkout
{
    private string $successUrl;
    private array $metadata = [];

    public function __construct(
        private mixed $billable,
        private string $productPriceId,
    ) {
        $this->successUrl = url(config('polar.redirect_after_checkout', '/dashboard'));
    }

    public function successUrl(string $url): self
    {
        $this->successUrl = url($url);
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function create(): array
    {
        $this->billable->ensurePolarCustomer();

        $client = app(PolarClient::class);
        return $client->createCheckout(
            $this->productPriceId,
            $this->billable->polar_customer_id,
            $this->successUrl,
            array_merge($this->metadata, [
                'billable_type' => get_class($this->billable),
                'billable_id' => $this->billable->id,
            ]),
        );
    }

    public function redirect(): RedirectResponse
    {
        $checkout = $this->create();
        return redirect($checkout['url']);
    }

    public function url(): string
    {
        $checkout = $this->create();
        return $checkout['url'];
    }
}
