<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\PolarClient;

trait ManagesUsage
{
    public function ingestUsageEvent(string $eventName, array $metadata = []): void
    {
        $customerId = $this->polarCustomerId();
        if (!$customerId) return; // fire-and-forget: silently skip if no customer

        app(PolarClient::class)->ingestUsageEvent($customerId, $eventName, $metadata);
    }

    public function ingestUsageEvents(array $events): void
    {
        $customerId = $this->polarCustomerId();
        if (!$customerId) return;

        app(PolarClient::class)->ingestUsageEvents($customerId, $events);
    }

    public function listCustomerMeters(?string $meterId = null): array
    {
        $customerId = $this->polarCustomerId();
        if (!$customerId) return [];

        return app(PolarClient::class)->listCustomerMeters($customerId, $meterId);
    }
}
