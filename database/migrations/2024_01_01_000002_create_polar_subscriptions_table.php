<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('polar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('type')->default('default');
            $table->string('polar_id')->unique();
            $table->string('status');
            $table->string('product_id');
            $table->string('price_id')->nullable();
            $table->string('polar_customer_id')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['billable_type', 'billable_id', 'type']);
            $table->index('polar_customer_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('polar_subscriptions');
    }
};
