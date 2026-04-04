<?php

namespace AkyrosLabs\Polar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BenefitGrantUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data,
    ) {}
}
