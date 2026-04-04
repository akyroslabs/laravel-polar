<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\PolarClient;

trait ManagesBenefits
{
    public function listBenefits(?string $organizationId = null): array
    {
        return app(PolarClient::class)->listBenefits($organizationId);
    }

    public function getBenefit(string $benefitId): array
    {
        return app(PolarClient::class)->getBenefit($benefitId);
    }

    public function listBenefitGrants(string $benefitId): array
    {
        return app(PolarClient::class)->listBenefitGrants($benefitId);
    }
}
