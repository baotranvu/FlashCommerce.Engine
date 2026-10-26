<?php

namespace FlashCommerce\Catalog\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulateInventoryUnderflowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:demo-underflow {--product-id=1} {--quantity=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate MySQL Error 1690 (UNSIGNED underflow) vs Safe Atomic Decrement';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $productId = (int) $this->option('product-id');
        $deductQty = (int) $this->option('quantity');

        $this->info("================================================================================");
        $this->info("🧪 FLASHCOMMERCE CHAOS LAB: INVENTORY RACE & MYSQL 1690 UNDERFLOW SIMULATION");
        $this->info("================================================================================");

        $product = DB::table('products')->where('id', $productId)->first();

        if (!$product) {
            $this->error("❌ Product with ID {$productId} not found in database.");
            return self::FAILURE;
        }

        $this->line("📦 Target Product ID: {$product->id} | SKU: {$product->sku}");
        $this->line("📦 Product Name:      {$product->name}");
        $this->line("📊 Physical Stock:    {$product->physical_stock} units");
        $this->line("⚡ Attempting to deduct: {$deductQty} units");
        $this->newLine();

        // -------------------------------------------------------------------------
        // STAGE 1: The Naive Deduction (Triggers MySQL Error 1690)
        // -------------------------------------------------------------------------
        $this->warn("⚠️  [STAGE 1] Executing Unsafe Naive Deduction without inventory check:");
        $sqlUnsafe = "UPDATE products SET physical_stock = physical_stock - ? WHERE id = ?";
        $this->line("   Query: <fg=yellow>{$sqlUnsafe}</> with bindings [{$deductQty}, {$productId}]");

        try {
            DB::update($sqlUnsafe, [$deductQty, $productId]);
            $this->error("❌ Unexpected: Deduction succeeded without error.");
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("🔥 [CRASH REPRODUCED] Database Exception Caught!");
            $this->line("   Error Code:   <fg=red>" . $e->getCode() . "</>");
            $this->line("   Error Message:<fg=red> " . $e->getMessage() . "</>");

            // Log diagnostic telemetry
            Log::channel('telemetry')->error('MySQL 1690 Underflow Caught during Naive Decrement', [
                'stage' => 'naive_deduction',
                'product_id' => $productId,
                'current_stock' => $product->physical_stock,
                'requested_deduction' => $deductQty,
                'error_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'sql' => $sqlUnsafe,
            ]);

            Log::channel('audit')->warning('Potential oversell prevented by MySQL strict unsigned invariant', [
                'product_id' => $productId,
                'attempted_quantity' => $deductQty,
            ]);

            $this->info("📝 Diagnostic telemetry recorded in: storage/logs/telemetry.log");
            $this->info("📝 Security audit event recorded in:  storage/logs/audit.log");
        }

        $this->newLine();

        // -------------------------------------------------------------------------
        // STAGE 2: Safe Atomic Conditional Deduction (Zero Oversell Invariant)
        // -------------------------------------------------------------------------
        $this->info("🛡️  [STAGE 2] Executing Safe Atomic Conditional Update (Zero-Oversell Invariant):");
        $sqlSafe = "UPDATE products SET physical_stock = physical_stock - ? WHERE id = ? AND physical_stock >= ?";
        $this->line("   Query: <fg=cyan>{$sqlSafe}</> with bindings [{$deductQty}, {$productId}, {$deductQty}]");

        $affected = DB::update($sqlSafe, [$deductQty, $productId, $deductQty]);

        if ($affected === 0) {
            $this->info("✅ [ZERO OVERSELL GUARANTEED] Affected Rows = 0.");
            $this->line("   Database rejected decrement safely at storage engine layer without throwing 1690!");
            $this->line("   Stock remains intact at: {$product->physical_stock} units.");
        } else {
            $this->info("✅ Deducted successfully! Affected Rows = {$affected}.");
        }

        $this->newLine();

        // -------------------------------------------------------------------------
        // STAGE 3: Legitimate Deduction within limits
        // -------------------------------------------------------------------------
        $safeQty = max(1, (int) floor($product->physical_stock / 2));
        if ($safeQty > 0) {
            $this->info("✨ [STAGE 3] Executing Valid Atomic Deduction ({$safeQty} units):");
            $affected = DB::update($sqlSafe, [$safeQty, $productId, $safeQty]);
            $updatedProduct = DB::table('products')->where('id', $productId)->first();

            $this->info("✅ Stock deducted cleanly: Affected Rows = {$affected}");
            $this->line("   Previous Stock: {$product->physical_stock} ➔ New Stock: {$updatedProduct->physical_stock}");

            Log::channel('audit')->info('Legitimate stock decrement executed', [
                'product_id' => $productId,
                'deducted_qty' => $safeQty,
                'old_stock' => $product->physical_stock,
                'new_stock' => $updatedProduct->physical_stock,
            ]);
        }

        $this->newLine();
        $this->info("🎯 Check your database: docker exec -it ecom-mysql mysql -uroot -psecretpassword flashcommerce_db -e 'SELECT id, sku, physical_stock FROM products WHERE id={$productId};'");
        $this->info("================================================================================");

        return self::SUCCESS;
    }
}
