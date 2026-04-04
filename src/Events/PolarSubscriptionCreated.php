<?php

namespace AkyrosLabs\Polar\Events;

class PolarSubscriptionCreated
{
    public function __construct(public mixed $billable, public array $data) {}
}
