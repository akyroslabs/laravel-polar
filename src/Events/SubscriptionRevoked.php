<?php

namespace AkyrosLabs\Polar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public mixed $billable,
        public array $data,
    ) {}
}
