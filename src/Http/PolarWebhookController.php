<?php

namespace AkyrosLabs\Polar\Http;

use AkyrosLabs\Polar\Events;
use AkyrosLabs\Polar\Handlers\PolarSignature;
use AkyrosLabs\Polar\Models\Customer;
use AkyrosLabs\Polar\Models\Order;
use AkyrosLabs\Polar\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PolarWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->verifySignature($request)) {
            Log::warning('Polar webhook: signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        event(new Events\WebhookReceived($type, $data));

        match ($type) {
            // Subscriptions
            'subscription.created' => $this->onSubscriptionCreated($data),
            'subscription.updated' => $this->onSubscriptionUpdated($data),
            'subscription.active' => $this->onSubscriptionActive($data),
            'subscription.canceled' => $this->onSubscriptionCanceled($data),
            'subscription.revoked' => $this->onSubscriptionRevoked($data),
            // Orders
            'order.created' => $this->onOrderCreated($data),
            'order.updated' => $this->onOrderUpdated($data),
            // Customers
            'customer.created' => $this->onCustomerCreated($data),
            'customer.updated' => $this->onCustomerUpdated($data),
            'customer.deleted' => $this->onCustomerDeleted($data),
            // Checkouts
            'checkout.created' => event(new Events\CheckoutCreated($data)),
            'checkout.updated' => event(new Events\CheckoutUpdated($data)),
            // Benefits
            'benefit_grant.created' => event(new Events\BenefitGrantCreated($data)),
            'benefit_grant.updated' => event(new Events\BenefitGrantUpdated($data)),
            'benefit_grant.revoked' => event(new Events\BenefitGrantRevoked($data)),
            // Default
            default => Log::debug("Polar webhook: unhandled type {$type}"),
        };

        event(new Events\WebhookHandled($type, $data));

        return response()->json(['received' => true]);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('polar.webhook_secret');
        if (!$secret) return true;

        return PolarSignature::verify(
            $request->getContent(),
            $request->header('webhook-id', ''),
            $request->header('webhook-timestamp', ''),
            $request->header('webhook-signature', ''),
            $secret,
        );
    }

    private function resolveBillable(array $data): ?object
    {
        // Try from customer metadata
        $metadata = $data['customer_metadata'] ?? $data['metadata'] ?? [];
        $type = $metadata['billable_type'] ?? null;
        $id = $metadata['billable_id'] ?? null;
        if ($type && $id && class_exists($type)) {
            return $type::find($id);
        }

        // Try from polar customer_id
        $customerId = $data['customer_id'] ?? $data['customer']['id'] ?? null;
        if ($customerId) {
            $customer = Customer::where('polar_id', $customerId)->first();
            return $customer?->billable;
        }

        return null;
    }

    private function onSubscriptionCreated(array $data): void
    {
        $billable = $this->resolveBillable($data);
        if (!$billable) { Log::warning('Polar webhook: billable not found for subscription.created', $data); return; }

        // Ensure customer record
        $customerId = $data['customer_id'] ?? null;
        if ($customerId) {
            $billable->customer()->updateOrCreate(
                ['billable_type' => get_class($billable), 'billable_id' => $billable->id],
                ['polar_id' => $customerId]
            );
        }

        $billable->subscriptions()->updateOrCreate(
            ['polar_id' => $data['id']],
            [
                'type' => 'default',
                'status' => $data['status'] ?? 'active',
                'product_id' => $data['product_id'] ?? '',
                'price_id' => $data['price_id'] ?? $data['recurring_price_id'] ?? null,
                'polar_customer_id' => $customerId,
                'current_period_end' => isset($data['current_period_end']) ? \Carbon\Carbon::parse($data['current_period_end']) : null,
                'trial_ends_at' => isset($data['trial_ends_at']) ? \Carbon\Carbon::parse($data['trial_ends_at']) : null,
            ]
        );

        event(new Events\SubscriptionCreated($billable, $data));
    }

    private function onSubscriptionUpdated(array $data): void
    {
        $sub = Subscription::where('polar_id', $data['id'])->first();
        if (!$sub) return;
        $sub->sync($data);
        event(new Events\SubscriptionUpdated($sub->billable, $data));
    }

    private function onSubscriptionActive(array $data): void
    {
        $sub = Subscription::where('polar_id', $data['id'])->first();
        if (!$sub) return;
        $sub->update(['status' => 'active']);
        if (isset($data['current_period_end'])) {
            $sub->update(['current_period_end' => \Carbon\Carbon::parse($data['current_period_end'])]);
        }
        event(new Events\SubscriptionActive($sub->billable, $data));
    }

    private function onSubscriptionCanceled(array $data): void
    {
        $sub = Subscription::where('polar_id', $data['id'])->first();
        if (!$sub) return;
        $sub->update([
            'status' => 'canceled',
            'ends_at' => isset($data['ends_at']) ? \Carbon\Carbon::parse($data['ends_at']) : $sub->current_period_end,
        ]);
        event(new Events\SubscriptionCanceled($sub->billable, $data));
    }

    private function onSubscriptionRevoked(array $data): void
    {
        $sub = Subscription::where('polar_id', $data['id'])->first();
        if (!$sub) return;
        $sub->update(['status' => 'revoked', 'ends_at' => now()]);
        event(new Events\SubscriptionRevoked($sub->billable, $data));
    }

    private function onOrderCreated(array $data): void
    {
        $billable = $this->resolveBillable($data);
        if (!$billable) return;

        $billable->orders()->updateOrCreate(
            ['polar_id' => $data['id']],
            [
                'status' => $data['status'] ?? 'paid',
                'amount' => $data['amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'currency' => $data['currency'] ?? 'usd',
                'billing_reason' => $data['billing_reason'] ?? null,
                'polar_customer_id' => $data['customer_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'ordered_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
            ]
        );

        event(new Events\OrderCreated($billable, $data));
    }

    private function onOrderUpdated(array $data): void
    {
        $order = Order::where('polar_id', $data['id'])->first();
        if (!$order) return;
        $order->sync($data);
        event(new Events\OrderUpdated($order->billable, $data));
    }

    private function onCustomerCreated(array $data): void
    {
        event(new Events\CustomerCreated($data));
    }

    private function onCustomerUpdated(array $data): void
    {
        $customer = Customer::where('polar_id', $data['id'])->first();
        if ($customer) event(new Events\CustomerUpdated($customer->billable, $data));
    }

    private function onCustomerDeleted(array $data): void
    {
        $customer = Customer::where('polar_id', $data['id'])->first();
        if ($customer) {
            event(new Events\CustomerDeleted($customer->billable, $data));
            $customer->delete();
        }
    }
}
