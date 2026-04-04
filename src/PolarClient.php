<?php

namespace AkyrosLabs\Polar;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

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

    // -------------------------------------------------------
    //  Customers
    // -------------------------------------------------------

    /**
     * Create a new customer in Polar.
     */
    public function createCustomer(string $email, string $name, array $metadata = []): array
    {
        $response = $this->request()->post('/customers', [
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve a customer by their Polar ID.
     */
    public function getCustomer(string $customerId): array
    {
        $response = $this->request()->get("/customers/{$customerId}");

        return $this->handleResponse($response);
    }

    /**
     * Look up a customer by email address. Returns null when not found.
     */
    public function getCustomerByEmail(string $email): ?array
    {
        $response = $this->request()->get('/customers', [
            'email' => $email,
        ]);

        $data = $this->handleResponse($response);

        $items = $data['items'] ?? $data['result'] ?? $data;

        if (is_array($items) && count($items) > 0) {
            return is_array(reset($items)) ? reset($items) : $data;
        }

        return null;
    }

    // -------------------------------------------------------
    //  Checkouts
    // -------------------------------------------------------

    /**
     * Create a checkout session for a given product price.
     */
    public function createCheckout(
        string $productPriceId,
        string $customerId,
        string $successUrl,
        array $metadata = [],
    ): array {
        $response = $this->request()->post('/checkouts/custom', [
            'product_price_id' => $productPriceId,
            'customer_id' => $customerId,
            'success_url' => $successUrl,
            'metadata' => $metadata,
        ]);

        return $this->handleResponse($response);
    }

    // -------------------------------------------------------
    //  Subscriptions
    // -------------------------------------------------------

    /**
     * Retrieve a single subscription.
     */
    public function getSubscription(string $subscriptionId): array
    {
        $response = $this->request()->get("/subscriptions/{$subscriptionId}");

        return $this->handleResponse($response);
    }

    /**
     * List all subscriptions for a given customer.
     */
    public function listSubscriptions(string $customerId): array
    {
        $response = $this->request()->get('/subscriptions', [
            'customer_id' => $customerId,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Cancel a subscription at the end of the current billing period.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        $response = $this->request()->patch("/subscriptions/{$subscriptionId}", [
            'cancel_at_period_end' => true,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Change a subscription to a different product price (up/downgrade).
     */
    public function updateSubscription(string $subscriptionId, string $productPriceId): array
    {
        $response = $this->request()->patch("/subscriptions/{$subscriptionId}", [
            'product_price_id' => $productPriceId,
        ]);

        return $this->handleResponse($response);
    }

    // -------------------------------------------------------
    //  Customer Portal
    // -------------------------------------------------------

    /**
     * Create a customer portal session and return the session payload
     * (includes the portal URL).
     */
    public function createCustomerSession(string $customerId): array
    {
        $response = $this->request()->post('/customer-sessions', [
            'customer_id' => $customerId,
        ]);

        return $this->handleResponse($response);
    }

    // -------------------------------------------------------
    //  Internal helpers
    // -------------------------------------------------------

    /**
     * Build an authenticated HTTP client pointed at the Polar API.
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    /**
     * Inspect the response and throw meaningful exceptions on failure.
     */
    private function handleResponse(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->status() === 401) {
            throw new \RuntimeException(
                'Polar API authentication failed. Please verify your POLAR_API_KEY is correct.'
            );
        }

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['detail'] ?? $body['message'] ?? $response->body();

            throw new \RuntimeException(
                "Polar API error [{$response->status()}]: {$message}"
            );
        }

        return $response->json() ?? [];
    }
}
