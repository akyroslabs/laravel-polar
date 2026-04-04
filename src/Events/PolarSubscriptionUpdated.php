<?php

namespace AkyrosLabs\Polar\Events;

class PolarSubscriptionUpdated
{
    public function __construct(public mixed $billable, public array $data) {}
}
