<?php

namespace AkyrosLabs\Polar\Console;

use AkyrosLabs\Polar\PolarClient;
use Illuminate\Console\Command;

class ListProductsCommand extends Command
{
    protected $signature = 'polar:products';

    protected $description = 'List all products from the Polar API';

    public function handle(PolarClient $client): int
    {
        $this->info('Fetching products from Polar...');

        try {
            $products = $client->listProducts();
        } catch (\Throwable $e) {
            $this->error("Failed to fetch products: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (empty($products)) {
            $this->warn('No products found.');
            return self::SUCCESS;
        }

        $rows = array_map(fn ($p) => [
            $p['id'] ?? '-',
            $p['name'] ?? '-',
            $p['type'] ?? '-',
            $p['is_recurring'] ?? false ? 'Yes' : 'No',
            isset($p['prices']) ? count($p['prices']) : 0,
            $p['is_archived'] ?? false ? 'Yes' : 'No',
            $p['created_at'] ?? '-',
        ], $products);

        $this->table(
            ['ID', 'Name', 'Type', 'Recurring', 'Prices', 'Archived', 'Created'],
            $rows
        );

        $this->newLine();
        $this->info(count($products) . ' product(s) found.');

        // Show price details
        foreach ($products as $product) {
            if (!empty($product['prices'])) {
                $this->newLine();
                $this->line("<fg=cyan>{$product['name']}</> prices:");
                foreach ($product['prices'] as $price) {
                    $amount = isset($price['price_amount']) ? number_format($price['price_amount'] / 100, 2) : '?';
                    $currency = strtoupper($price['price_currency'] ?? 'usd');
                    $interval = $price['recurring_interval'] ?? 'one-time';
                    $this->line("  - {$price['id']}: {$amount} {$currency} / {$interval}");
                }
            }
        }

        return self::SUCCESS;
    }
}
