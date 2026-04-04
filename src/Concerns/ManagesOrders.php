<?php

namespace AkyrosLabs\Polar\Concerns;

use AkyrosLabs\Polar\Models\Order;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesOrders
{
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'billable');
    }

    public function hasPurchasedProduct(string $productId): bool
    {
        return $this->orders()->where('product_id', $productId)->where('status', 'paid')->exists();
    }
}
