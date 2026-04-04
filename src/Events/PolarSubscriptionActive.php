<?php

namespace AkyrosLabs\Polar\Events;

class PolarSubscriptionActive
{
    public function __construct(public mixed $billable, public array $data) {}
}
