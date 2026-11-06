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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gateway_provider', 32)->nullable()->after('status');
            $table->string('gateway_transaction_id', 128)->nullable()->after('gateway_provider');
            $table->timestamp('paid_at')->nullable()->after('total_amount');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');

            // Fast lookup index for webhook reconciliation
            $table->index(['gateway_provider', 'gateway_transaction_id'], 'idx_orders_gateway_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_gateway_lookup');
            $table->dropColumn([
                'gateway_provider',
                'gateway_transaction_id',
                'paid_at',
                'refunded_at',
            ]);
        });
    }
};
