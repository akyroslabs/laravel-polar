<?php

namespace AkyrosLabs\Polar;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class PolarClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('polar.api_key', '');
        $this->baseUrl = config('polar.sandbox')
            ? 'https://sandbox-api.polar.sh/v1'
            : 'https://api.polar.sh/v1';
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    private function handleResponse($response): array
    {
        if ($response->status() === 401) {
            throw new Exceptions\PolarApiError('Unauthorized — check your POLAR_API_KEY.');
        }
        if (!$response->successful()) {
            throw new Exceptions\PolarApiError("Polar API error {$response->status()}: {$response->body()}");
        }
        return $response->json() ?? [];
    }

    // Customers
    public function createCustomer(string $email, string $name, array $metadata = []): array
    {
        return $this->handleResponse(
            $this->request()->post('/customers', compact('email', 'name', 'metadata'))
        );
    }

    public function getCustomer(string $customerId): array
    {
        return $this->handleResponse($this->request()->get("/customers/{$customerId}"));
    }

    public function getCustomerByEmail(string $email): ?array
    {
        $result = $this->handleResponse($this->request()->get('/customers', ['email' => $email]));
        $items = $result['items'] ?? $result['result'] ?? [];
        return !empty($items) ? $items[0] : null;
    }

    // Checkouts
    public function createCheckout(array $params): array
    {
        return $this->handleResponse($this->request()->post('/checkouts', $params));
    }

    // Subscriptions
    public function getSubscription(string $subscriptionId): array
    {
        return $this->handleResponse($this->request()->get("/subscriptions/{$subscriptionId}"));
    }

    public function listSubscriptions(string $customerId): array
    {
        $result = $this->handleResponse($this->request()->get('/subscriptions', ['customer_id' => $customerId]));
        return $result['items'] ?? $result['result'] ?? [];
    }

    public function updateSubscription(string $subscriptionId, string $productPriceId): array
    {
        return $this->handleResponse(
            $this->request()->patch("/subscriptions/{$subscriptionId}", ['product_price_id' => $productPriceId])
        );
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->handleResponse($this->request()->delete("/subscriptions/{$subscriptionId}"));
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->handleResponse(
            $this->request()->patch("/subscriptions/{$subscriptionId}", ['cancel_at_period_end' => false])
        );
    }

    // Customer Portal
    public function createCustomerSession(string $customerId): array
    {
        return $this->handleResponse(
            $this->request()->post('/customer-sessions', ['customer_id' => $customerId])
        );
    }

    // Products
    public function listProducts(array $params = []): array
    {
        $result = $this->handleResponse($this->request()->get('/products', $params));
        return $result['items'] ?? $result['result'] ?? [];
    }

    // Benefits
    public function listBenefits(?string $organizationId = null): array
    {
        $params = $organizationId ? ['organization_id' => $organizationId] : [];
        $result = $this->handleResponse($this->request()->get('/benefits', $params));
        return $result['items'] ?? $result['result'] ?? [];
    }

    public function getBenefit(string $benefitId): array
    {
        return $this->handleResponse($this->request()->get("/benefits/{$benefitId}"));
    }

    public function listBenefitGrants(string $benefitId): array
    {
        $result = $this->handleResponse($this->request()->get("/benefits/{$benefitId}/grants"));
        return $result['items'] ?? $result['result'] ?? [];
    }

    // Usage / Metering
    public function ingestUsageEvent(string $customerId, string $eventName, array $metadata = []): void
    {
        $this->request()->post('/customer-meters/events', [
            'customer_id' => $customerId,
            'name' => $eventName,
            'metadata' => $metadata,
        ]);
    }

    public function ingestUsageEvents(string $customerId, array $events): void
    {
        $formatted = array_map(fn ($e) => [
            'customer_id' => $customerId,
            'name' => $e['name'],
            'metadata' => $e['metadata'] ?? [],
        ], $events);
        $this->request()->post('/customer-meters/events/batch', ['events' => $formatted]);
    }

    public function listCustomerMeters(string $customerId, ?string $meterId = null): array
    {
        $params = ['customer_id' => $customerId];
        if ($meterId) $params['meter_id'] = $meterId;
        $result = $this->handleResponse($this->request()->get('/customer-meters', $params));
        return $result['items'] ?? $result['result'] ?? [];
    }
}
