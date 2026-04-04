<?php

namespace AkyrosLabs\Polar\Handlers;

class PolarSignature
{
    public static function verify(string $payload, string $webhookId, string $webhookTimestamp, string $webhookSignature, string $secret): bool
    {
        $toSign = "{$webhookId}.{$webhookTimestamp}.{$payload}";
        $signatures = explode(' ', $webhookSignature);

        foreach ($signatures as $sig) {
            $parts = explode(',', $sig);
            if (count($parts) < 2) continue;
            $sigBytes = base64_decode($parts[1]);
            $expected = hash_hmac('sha256', $toSign, base64_decode($secret), true);
            if (hash_equals($sigBytes, $expected)) {
                return true;
            }
        }
        return false;
    }
}
