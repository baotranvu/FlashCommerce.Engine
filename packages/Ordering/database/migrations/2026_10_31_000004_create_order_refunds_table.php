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
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->index();
            $table->string('gateway_provider', 32);
            $table->string('gateway_transaction_id', 128)->index();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 255);
            $table->string('status', 32)->default('PENDING'); // PENDING, SUCCESS, FAILED
            $table->string('refund_reference', 128)->nullable();
            $table->timestamps();

            $table->index(['order_no', 'status'], 'idx_order_refunds_order_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
