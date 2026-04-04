<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\Checkout;

trait ManagesCheckouts
{
    public function checkout(string|array $products, array $options = []): Checkout
    {
        $products = is_string($products) ? [$products] : $products;
        return new Checkout($this, $products, $options);
    }

    public function subscribe(string $productPriceId, string $type = 'default', array $options = []): Checkout
    {
        return $this->checkout($productPriceId, array_merge($options, [
            'subscription_type' => $type,
        ]));
    }

    public function charge(int $amount, string|array $products, array $options = []): Checkout
    {
        $products = is_string($products) ? [$products] : $products;
        return new Checkout($this, $products, array_merge($options, [
            'amount' => $amount,
        ]));
    }
}
