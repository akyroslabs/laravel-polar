<?php

namespace AkyrosLabs\Polar\Console;

use AkyrosLabs\Polar\PolarClient;
use Illuminate\Console\Command;

class SyncSubscriptionsCommand extends Command
{
    protected $signature = 'polar:sync';
    protected $description = 'Sync all subscription statuses with Polar.sh';

    public function handle(PolarClient $client): int
    {
        $modelClass = config('polar.billable_model');
        $billables = $modelClass::whereNotNull('polar_subscription_id')->get();

        $this->info("Syncing {$billables->count()} subscriptions...");
        $bar = $this->output->createProgressBar($billables->count());

        $synced = 0;
        foreach ($billables as $billable) {
            try {
                $subscription = $client->getSubscription($billable->polar_subscription_id);

                $plan = 'unknown';
                $plans = config('polar.plans', []);
                foreach ($plans as $name => $config) {
                    if (($config['product_id'] ?? null) === ($subscription['product_id'] ?? '')) {
                        $plan = $name;
                        break;
                    }
                }

                $billable->update([
                    'subscription_status' => $subscription['status'] ?? $billable->subscription_status,
                    'polar_product_id' => $subscription['product_id'] ?? $billable->polar_product_id,
                    'plan' => $plan !== 'unknown' ? $plan : $billable->plan,
                    'current_period_end' => isset($subscription['current_period_end'])
                        ? \Carbon\Carbon::parse($subscription['current_period_end'])
                        : $billable->current_period_end,
                ]);

                $synced++;
            } catch (\Exception $e) {
                $this->warn("  Failed to sync {$billable->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Synced {$synced}/{$billables->count()} subscriptions.");

        return self::SUCCESS;
    }
}
