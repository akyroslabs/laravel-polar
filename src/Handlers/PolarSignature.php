<?php

namespace AkyrosLabs\Polar\Handlers;

/**
 * Standard-Webhooks-style signature verification with Polar-specific
 * prefix handling.
 *
 * Polar ships webhook secrets as `polar_whs_<base64>`; the
 * Standard-Webhooks reference uses `whsec_<base64>`. We strip whichever
 * prefix is present, then try both interpretations of the remainder
 * (base64-decoded bytes — the spec contract — and the raw remainder
 * — what some integrators store) as the HMAC key. Any delivered
 * `v<n>,<base64-signature>` that matches either expected signature
 * passes verification.
 */
class PolarSignature
{
    public static function verify(string $payload, string $webhookId, string $webhookTimestamp, string $webhookSignature, string $secret): bool
    {
        $toSign = "{$webhookId}.{$webhookTimestamp}.{$payload}";

        $stripped = $secret;
        foreach (['polar_whs_', 'whsec_'] as $prefix) {
            if (str_starts_with($stripped, $prefix)) {
                $stripped = substr($stripped, strlen($prefix));
                break;
            }
        }

        $keys = array_values(array_unique(array_filter([
            base64_decode($stripped, true) ?: null,
            $stripped,
        ])));
        $expectedSigs = array_map(fn ($k) => hash_hmac('sha256', $toSign, $k, true), $keys);

        foreach (explode(' ', $webhookSignature) as $sig) {
            $parts = explode(',', $sig);
            if (count($parts) < 2) continue;
            $sigBytes = base64_decode($parts[1], true);
            if ($sigBytes === false) continue;

            foreach ($expectedSigs as $expected) {
                if (hash_equals($sigBytes, $expected)) {
                    return true;
                }
            }
        }

        return false;
    }
}
