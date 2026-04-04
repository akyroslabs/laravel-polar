<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('polar_orders', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('polar_id')->nullable()->unique();
            $table->string('status');
            $table->integer('amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('refunded_amount')->default(0);
            $table->integer('refunded_tax_amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('billing_reason')->nullable();
            $table->string('polar_customer_id')->nullable();
            $table->string('product_id')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index('polar_customer_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('polar_orders');
    }
};
