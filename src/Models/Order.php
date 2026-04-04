<?php

namespace AkyrosLabs\Polar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Order extends Model
{
    protected $table = 'polar_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'refunded_amount' => 'integer',
            'refunded_tax_amount' => 'integer',
            'ordered_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function paid(): bool { return $this->status === 'paid'; }
    public function refunded(): bool { return $this->status === 'refunded'; }
    public function partiallyRefunded(): bool { return $this->status === 'partially_refunded'; }
    public function void(): bool { return $this->status === 'void'; }

    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    public function sync(array $attributes): self
    {
        $this->update([
            'status' => $attributes['status'] ?? $this->status,
            'amount' => $attributes['amount'] ?? $this->amount,
            'tax_amount' => $attributes['tax_amount'] ?? $this->tax_amount,
            'refunded_amount' => $attributes['refunded_amount'] ?? $this->refunded_amount,
            'refunded_tax_amount' => $attributes['refunded_tax_amount'] ?? $this->refunded_tax_amount,
            'currency' => $attributes['currency'] ?? $this->currency,
            'refunded_at' => isset($attributes['refunded_at']) ? \Carbon\Carbon::parse($attributes['refunded_at']) : $this->refunded_at,
        ]);
        return $this;
    }
}
