<?php

namespace AkyrosLabs\Polar\Console;

use AkyrosLabs\Polar\Models\Customer;
use AkyrosLabs\Polar\Models\Subscription;
use AkyrosLabs\Polar\PolarClient;
use Illuminate\Console\Command;

class SyncSubscriptionsCommand extends Command
{
    protected $signature = 'polar:sync
                            {--customer= : Sync only a specific Polar customer ID}';

    protected $description = 'Sync all subscriptions from the Polar API';

    public function handle(PolarClient $client): int
    {
        $specificCustomer = $this->option('customer');

        if ($specificCustomer) {
            $customers = Customer::where('polar_id', $specificCustomer)->get();
        } else {
            $customers = Customer::whereNotNull('polar_id')->get();
        }

        if ($customers->isEmpty()) {
            $this->warn('No Polar customers found in the database.');
            return self::SUCCESS;
        }

        $this->info("Syncing subscriptions for {$customers->count()} customer(s)...");
        $bar = $this->output->createProgressBar($customers->count());

        $synced = 0;
        $errors = 0;

        foreach ($customers as $customer) {
            try {
                $apiSubscriptions = $client->listSubscriptions($customer->polar_id);

                foreach ($apiSubscriptions as $apiSub) {
                    $sub = Subscription::updateOrCreate(
                        ['polar_id' => $apiSub['id']],
                        [
                            'billable_type' => $customer->billable_type,
                            'billable_id' => $customer->billable_id,
                            'type' => 'default',
                            'status' => $apiSub['status'] ?? 'active',
                            'product_id' => $apiSub['product_id'] ?? '',
                            'price_id' => $apiSub['price_id'] ?? $apiSub['recurring_price_id'] ?? null,
                            'polar_customer_id' => $customer->polar_id,
                            'current_period_end' => isset($apiSub['current_period_end'])
                                ? \Carbon\Carbon::parse($apiSub['current_period_end'])
                                : null,
                            'trial_ends_at' => isset($apiSub['trial_ends_at'])
                                ? \Carbon\Carbon::parse($apiSub['trial_ends_at'])
                                : null,
                            'ends_at' => isset($apiSub['ends_at'])
                                ? \Carbon\Carbon::parse($apiSub['ends_at'])
                                : null,
                        ]
                    );
                    $synced++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed to sync customer {$customer->polar_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Synced {$synced} subscription(s).");

        if ($errors > 0) {
            $this->warn("{$errors} customer(s) had errors during sync.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
