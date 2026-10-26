<?php

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 255);
            $table->unsignedInteger('price'); // Stored in smallest integer currency unit (VND / Cents)
            $table->unsignedInteger('physical_stock')->default(0); // Physical units in warehouse
            $table->unsignedInteger('reserved_stock')->default(0); // Held in pending checkout TTLs
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // 🚀 Composite B-Tree Index for Keyset Seek Pagination (O(log N))
            $table->index(['is_active', 'id', 'price'], 'idx_products_keyset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
