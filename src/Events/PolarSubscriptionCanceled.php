<?php

namespace AkyrosLabs\Polar\Events;

class PolarSubscriptionCanceled
{
    public function __construct(public mixed $billable, public array $data) {}
}
