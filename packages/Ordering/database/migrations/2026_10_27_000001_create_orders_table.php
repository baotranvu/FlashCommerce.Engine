<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('status', 32)->default('PENDING');
            $table->unsignedInteger('total_amount');
            $table->string('hold_token', 64)->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            // High-throughput query indexes
            $table->index(['user_id', 'status'], 'idx_orders_user_status');
            $table->index(['status', 'hold_expires_at'], 'idx_orders_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
