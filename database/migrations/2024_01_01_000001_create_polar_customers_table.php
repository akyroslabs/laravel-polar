<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('polar_customers', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('polar_id')->nullable()->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->index('polar_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('polar_customers');
    }
};
