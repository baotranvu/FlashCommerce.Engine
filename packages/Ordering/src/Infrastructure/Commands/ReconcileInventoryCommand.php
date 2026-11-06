<?php

declare(strict_types=1);

namespace FlashCommerce\Ordering\Infrastructure\Commands;

use FlashCommerce\Catalog\Domain\Model\Product;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderItemModel;
use FlashCommerce\Ordering\Infrastructure\Persistence\Eloquent\OrderModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ReconcileInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'flash-sale:reconcile-inventory
                            {--product_id= : The product ID to inspect/reconcile}
                            {--repair : Apply business-approved remediation (requires --ticket and --approved-by)}
                            {--ticket= : The business approval ticket ID (e.g. OPS-202611-01)}
                            {--approved-by= : Authorizing manager email or sign-off}';

    /**
     * The console command description.
     */
    protected $description = 'Forensic audit and business-approved reconciliation engine for dual-lane inventory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $productId = $this->option('product_id') ? (int) $this->option('product_id') : null;
        $isRepair = (bool) $this->option('repair');
        $ticket = (string) ($this->option('ticket') ?? '');
        $approvedBy = (string) ($this->option('approved-by') ?? '');

        // 🛡️ SEPARATION OF DUTIES GUARD:
        // Remediation requires mandatory business authorization ticket and sign-off
        if ($isRepair && (empty($ticket) || empty($approvedBy))) {
            $this->error('🚨 REMEDIATION REFUSED (Separation of Duties Policy):');
            $this->error('   Altering inventory balance sheets requires business authorization.');
            $this->error('   Please provide both --ticket="<ticket_id>" and --approved-by="<authorizer>".');
            return Command::FAILURE;
        }

        $products = $productId
            ? Product::query()->where('id', $productId)->get()
            : Product::query()->where('is_active', true)->get();

        if ($products->isEmpty()) {
            $this->warn('No active products found for reconciliation.');
            return Command::SUCCESS;
        }

        $this->info('========================================================================');
        $this->info('🔍 FLASHCOMMERCE INVENTORY FORENSIC AUDIT REPORT');
        $this->info('========================================================================');

        $hasDiscrepancies = false;

        foreach ($products as $product) {
            $report = $this->auditProduct($product);

            if ($report['drift'] !== 0 || !empty($report['zombie_holds'])) {
                $hasDiscrepancies = true;
            }

            $this->renderReport($product, $report);

            if ($isRepair && ($report['drift'] !== 0 || !empty($report['zombie_holds']))) {
                $this->remediateProduct($product, $report, $ticket, $approvedBy);
            }
        }

        if (!$hasDiscrepancies) {
            $this->info('✅ ALL INVENTORY COUNTERS PERFECTLY BALANCED: Drift = 0 across all products.');
        } elseif (!$isRepair) {
            $this->warn('⚠️  DRY-RUN INSPECTION COMPLETE: No modifications made to memory or disk.');
            $this->warn('   To apply business-approved remediation, re-run with --repair, --ticket, and --approved-by.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{
     *     physical_stock: int,
     *     reserved_stock: int,
     *     expected_virtual: int,
     *     actual_virtual: int,
     *     drift: int,
     *     zombie_holds: array<int, array{order_no: string, quantity: int, expires_at: string}>
     * }
     */
    public function auditProduct(Product $product): array
    {
        $stockPrefix = config('ordering.redis.stock_prefix', 'flash_sale:stock:');
        $redisKey = "{$stockPrefix}{$product->id}";

        $physicalStock = (int) $product->physical_stock;

        // Sum quantities of active RESERVED orders for this product
        $reservedStock = (int) OrderItemModel::query()
            ->where('product_id', $product->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'RESERVED');
            })
            ->sum('quantity');

        $expectedVirtual = max(0, $physicalStock - $reservedStock);
        $actualVirtual = Redis::get($redisKey);
        $actualVirtualInt = $actualVirtual !== false && $actualVirtual !== null ? (int) $actualVirtual : 0;

        $drift = $actualVirtualInt - $expectedVirtual;

        // Find Zombie Holds: RESERVED orders past TTL + grace, but NOT in Redis ZSET
        $graceThreshold = Carbon::now()->subSeconds((int) config('ordering.hold_ttl_seconds', 300) + 30);

        $expiredReservedOrders = OrderModel::query()
            ->where('status', 'RESERVED')
            ->where('hold_expires_at', '<', $graceThreshold)
            ->whereHas('items', function ($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->with(['items' => function ($query) use ($product) {
                $query->where('product_id', $product->id);
            }])
            ->get();

        $zombieHolds = [];
        foreach ($expiredReservedOrders as $order) {
            $score = Redis::zscore('flash_sale:expirations', $order->order_no);
            if ($score === false || $score === null) {
                $qty = (int) $order->items->sum('quantity');
                $zombieHolds[] = [
                    'order_no'   => $order->order_no,
                    'quantity'   => $qty,
                    'expires_at' => (string) $order->hold_expires_at,
                ];
            }
        }

        return [
            'physical_stock'   => $physicalStock,
            'reserved_stock'   => $reservedStock,
            'expected_virtual' => $expectedVirtual,
            'actual_virtual'   => $actualVirtualInt,
            'drift'            => $drift,
            'zombie_holds'     => $zombieHolds,
        ];
    }

    /**
     * @param array{
     *     physical_stock: int,
     *     reserved_stock: int,
     *     expected_virtual: int,
     *     actual_virtual: int,
     *     drift: int,
     *     zombie_holds: array<int, array{order_no: string, quantity: int, expires_at: string}>
     * } $report
     */
    private function renderReport(Product $product, array $report): void
    {
        $this->line("Product #{$product->id} - {$product->name} (SKU: {$product->sku})");
        $this->table(
            ['Metric', 'Value'],
            [
                ['MySQL Physical Stock', (string) $report['physical_stock']],
                ['Active RESERVED Sum', (string) $report['reserved_stock']],
                ['Expected Virtual Stock (RAM)', (string) $report['expected_virtual']],
                ['Actual Redis Counter (RAM)', (string) $report['actual_virtual']],
                ['Drift Variance (Delta)', ($report['drift'] > 0 ? "+{$report['drift']}" : (string) $report['drift'])],
            ]
        );

        if (!empty($report['zombie_holds'])) {
            $this->warn('  [ATTRIBUTION] Detected Zombie Holds (Orders missing from Redis ZSET):');
            foreach ($report['zombie_holds'] as $zombie) {
                $this->line("    • Order {$zombie['order_no']}: Qty {$zombie['quantity']} (Expired at: {$zombie['expires_at']})");
            }
        }

        if ($report['drift'] !== 0 && empty($report['zombie_holds'])) {
            $this->warn('  [ATTRIBUTION] Discrepancy likely caused by [TAG_REDIS_REPLICATION_LAG] or [TAG_OUT_OF_BAND_DB_MUTATION].');
        }

        $this->line('');
    }

    /**
     * @param array{
     *     physical_stock: int,
     *     reserved_stock: int,
     *     expected_virtual: int,
     *     actual_virtual: int,
     *     drift: int,
     *     zombie_holds: array<int, array{order_no: string, quantity: int, expires_at: string}>
     * } $report
     */
    private function remediateProduct(Product $product, array $report, string $ticket, string $approvedBy): void
    {
        $stockPrefix = config('ordering.redis.stock_prefix', 'flash_sale:stock:');
        $redisKey = "{$stockPrefix}{$product->id}";

        // 1. Clean up zombie holds if confirmed
        $purgedQty = 0;
        foreach ($report['zombie_holds'] as $zombie) {
            OrderModel::query()
                ->where('order_no', $zombie['order_no'])
                ->update(['status' => 'EXPIRED']);
            $purgedQty += (int) $zombie['quantity'];
        }

        // 2. Re-align Redis counter to expected ground truth (adding back purged zombie stock)
        $realignedVirtual = $report['expected_virtual'] + $purgedQty;
        Redis::set($redisKey, $realignedVirtual);

        // 3. Immutable compliance log
        Log::info('[INVENTORY_RECONCILIATION_AUDITED]', [
            'ticket'            => $ticket,
            'approved_by'       => $approvedBy,
            'product_id'        => $product->id,
            'old_redis_val'     => $report['actual_virtual'],
            'realigned_val'     => $realignedVirtual,
            'drift'             => $report['drift'],
            'zombies_purged'    => count($report['zombie_holds']),
            'purged_qty_reclaimed' => $purgedQty,
            'timestamp'         => now()->toIso8601String(),
        ]);

        $this->info("✅ [REMEDIATION APPLIED] Product #{$product->id} Redis stock realigned to {$realignedVirtual} (Ticket: {$ticket}).");
    }
}
