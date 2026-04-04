<?php

namespace AkyrosLabs\Polar\Events;

class PolarWebhookReceived
{
    public function __construct(public ?string $type, public array $data) {}
}
