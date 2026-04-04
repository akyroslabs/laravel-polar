<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $model = config('polar.billable_model', 'App\\Models\\User');
        $table = (new $model)->getTable();

        Schema::table($table, function (Blueprint $table) {
            $table->string('polar_customer_id')->nullable()->after('id');
            $table->string('polar_subscription_id')->nullable();
            $table->string('polar_product_id')->nullable();
            $table->string('polar_price_id')->nullable();
            $table->string('subscription_status')->default('inactive');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();

            $table->index('polar_customer_id');
            $table->index('polar_subscription_id');
        });
    }

    public function down(): void
    {
        $model = config('polar.billable_model', 'App\\Models\\User');
        $table = (new $model)->getTable();

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn([
                'polar_customer_id',
                'polar_subscription_id',
                'polar_product_id',
                'polar_price_id',
                'subscription_status',
                'trial_ends_at',
                'current_period_end',
            ]);
        });
    }
};
