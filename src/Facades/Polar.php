<?php

namespace AkyrosLabs\Polar\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createCustomer(string $email, string $name, array $metadata = [])
 * @method static array getCustomer(string $customerId)
 * @method static array|null getCustomerByEmail(string $email)
 * @method static array createCheckout(array $params)
 * @method static array getSubscription(string $subscriptionId)
 * @method static array listSubscriptions(string $customerId)
 * @method static array updateSubscription(string $subscriptionId, string $productPriceId)
 * @method static array cancelSubscription(string $subscriptionId)
 * @method static array resumeSubscription(string $subscriptionId)
 * @method static array createCustomerSession(string $customerId)
 * @method static array listProducts(array $params = [])
 * @method static array listBenefits(?string $organizationId = null)
 * @method static array getBenefit(string $benefitId)
 * @method static array listBenefitGrants(string $benefitId)
 * @method static void ingestUsageEvent(string $customerId, string $eventName, array $metadata = [])
 * @method static void ingestUsageEvents(string $customerId, array $events)
 * @method static array listCustomerMeters(string $customerId, ?string $meterId = null)
 *
 * @see \AkyrosLabs\Polar\PolarClient
 */
class Polar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AkyrosLabs\Polar\PolarClient::class;
    }
}
