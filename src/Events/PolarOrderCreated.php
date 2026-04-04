<?php

namespace AkyrosLabs\Polar\Events;

class PolarOrderCreated
{
    public function __construct(public array $data) {}
}
