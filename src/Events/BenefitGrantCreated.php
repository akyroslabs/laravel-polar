<?php

namespace AkyrosLabs\Polar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BenefitGrantCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data,
    ) {}
}
