<?php

namespace AkyrosLabs\Polar\Http;

use AkyrosLabs\Polar\Events\PolarSubscriptionCreated;
use AkyrosLabs\Polar\Events\PolarSubscriptionUpdated;
use AkyrosLabs\Polar\Events\PolarSubscriptionCanceled;
use AkyrosLabs\Polar\Events\PolarSubscriptionActive;
use AkyrosLabs\Polar\Events\PolarOrderCreated;
use AkyrosLabs\Polar\Events\PolarWebhookReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PolarWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            Log::warning('Polar webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $event = $payload['type'] ?? null; // e.g. "subscription.created"
        $data = $payload['data'] ?? [];

        // Dispatch generic event for custom handling
        event(new PolarWebhookReceived($event, $data));

        // Handle specific events
        match ($event) {
            'subscription.created' => $this->handleSubscriptionCreated($data),
            'subscription.updated' => $this->handleSubscriptionUpdated($data),
            'subscription.active' => $this->handleSubscriptionActive($data),
            'subscription.canceled' => $this->handleSubscriptionCanceled($data),
            'subscription.revoked' => $this->handleSubscriptionRevoked($data),
            'order.created' => $this->handleOrderCreated($data),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('polar.webhook_secret');
        if (!$secret) {
            return true; // No secret configured, skip verification
        }

        $signature = $request->header('webhook-id');
        $timestamp = $request->header('webhook-timestamp');
        $body = $request->getContent();
        $webhookSignature = $request->header('webhook-signature');

        if (!$signature || !$timestamp || !$webhookSignature) {
            return false;
        }

        // Polar uses standard webhook signature: base64(hmac-sha256(msg_id.timestamp.body))
        $toSign = "{$signature}.{$timestamp}.{$body}";
        $expectedSignatures = explode(' ', $webhookSignature);

        foreach ($expectedSignatures as $sig) {
            $parts = explode(',', $sig);
            if (count($parts) < 2) continue;
            $sigBytes = base64_decode($parts[1]);
            $computed = hash_hmac('sha256', $toSign, base64_decode($secret), true);
            if (hash_equals($sigBytes, $computed)) {
                return true;
            }
        }

        return false;
    }

    private function findBillable(array $data): mixed
    {
        $modelClass = config('polar.billable_model', 'App\\Models\\User');
        $customerId = $data['customer_id'] ?? null;

        if (!$customerId) return null;

        return $modelClass::where('polar_customer_id', $customerId)->first();
    }

    private function resolvePlan(string $productId): string
    {
        $plans = config('polar.plans', []);
        foreach ($plans as $name => $config) {
            if (($config['product_id'] ?? null) === $productId) {
                return $name;
            }
        }
        return 'unknown';
    }

    private function handleSubscriptionCreated(array $data): void
    {
        $billable = $this->findBillable($data);
        if (!$billable) {
            Log::warning('Polar webhook: billable not found for subscription.created', $data);
            return;
        }

        $plan = $this->resolvePlan($data['product_id'] ?? '');

        $billable->update([
            'polar_subscription_id' => $data['id'],
            'polar_product_id' => $data['product_id'] ?? null,
            'polar_price_id' => $data['price_id'] ?? $data['recurring_price_id'] ?? null,
            'plan' => $plan,
            'subscription_status' => $data['status'] ?? 'active',
            'current_period_end' => isset($data['current_period_end']) ? \Carbon\Carbon::parse($data['current_period_end']) : null,
        ]);

        event(new PolarSubscriptionCreated($billable, $data));
    }

    private function handleSubscriptionUpdated(array $data): void
    {
        $billable = $this->findBillable($data);
        if (!$billable) return;

        $plan = $this->resolvePlan($data['product_id'] ?? '');

        $billable->update([
            'polar_product_id' => $data['product_id'] ?? $billable->polar_product_id,
            'polar_price_id' => $data['price_id'] ?? $data['recurring_price_id'] ?? $billable->polar_price_id,
            'plan' => $plan !== 'unknown' ? $plan : $billable->plan,
            'subscription_status' => $data['status'] ?? $billable->subscription_status,
            'current_period_end' => isset($data['current_period_end']) ? \Carbon\Carbon::parse($data['current_period_end']) : $billable->current_period_end,
        ]);

        event(new PolarSubscriptionUpdated($billable, $data));
    }

    private function handleSubscriptionActive(array $data): void
    {
        $billable = $this->findBillable($data);
        if (!$billable) return;

        $billable->update([
            'subscription_status' => 'active',
            'current_period_end' => isset($data['current_period_end']) ? \Carbon\Carbon::parse($data['current_period_end']) : $billable->current_period_end,
        ]);

        event(new PolarSubscriptionActive($billable, $data));
    }

    private function handleSubscriptionCanceled(array $data): void
    {
        $billable = $this->findBillable($data);
        if (!$billable) return;

        $billable->update([
            'subscription_status' => 'canceled',
        ]);

        event(new PolarSubscriptionCanceled($billable, $data));
    }

    private function handleSubscriptionRevoked(array $data): void
    {
        $billable = $this->findBillable($data);
        if (!$billable) return;

        $billable->update([
            'subscription_status' => 'inactive',
            'plan' => 'free',
            'polar_subscription_id' => null,
            'polar_product_id' => null,
            'polar_price_id' => null,
            'current_period_end' => null,
        ]);

        event(new PolarSubscriptionCanceled($billable, $data));
    }

    private function handleOrderCreated(array $data): void
    {
        event(new PolarOrderCreated($data));
    }
}
