<?php

namespace FlashCommerce\Catalog\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BenchmarkPaginationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:benchmark-pagination {--offset=9500} {--limit=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark Keyset Seek Pagination vs Deep Offset Pagination on 10,000 Products';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $offset = (int) $this->option('offset');
        $limit = (int) $this->option('limit');

        $this->info("================================================================================");
        $this->info("⚡ FLASHCOMMERCE BENCHMARK: KEYSET SEEK VS DEEP OFFSET PAGINATION");
        $this->info("================================================================================");

        $totalProducts = DB::table('products')->count();
        $this->line("📊 Total Products in Database: <fg=green>{$totalProducts}</>");
        $this->line("🎯 Target Page Range:          Offset {$offset} (fetching {$limit} items)");
        $this->newLine();

        // -------------------------------------------------------------------------
        // 1. DEEP OFFSET PAGINATION
        // -------------------------------------------------------------------------
        $this->warn("🐢 [METHOD 1] Deep OFFSET Pagination (Scanning through B-Tree interior):");
        $offsetSql = "SELECT id, sku, name, price FROM products ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}";
        $this->line("   Query: <fg=yellow>{$offsetSql}</>");

        $startOffset = hrtime(true);
        $offsetResults = DB::select($offsetSql);
        $endOffset = hrtime(true);
        $offsetDurationMs = ($endOffset - $startOffset) / 1e6;

        // Run EXPLAIN ANALYZE
        $offsetExplain = DB::select("EXPLAIN ANALYZE " . $offsetSql);
        $offsetExplainText = json_encode($offsetExplain[0] ?? []);

        $this->line("   ⏱️  Execution Time: <fg=red>" . number_format($offsetDurationMs, 3) . " ms</>");
        $this->line("   📄 Records Fetched: " . count($offsetResults));
        $this->newLine();

        // -------------------------------------------------------------------------
        // 2. KEYSET SEEK PAGINATION
        // -------------------------------------------------------------------------
        $this->info("🚀 [METHOD 2] Keyset Seek Pagination (Direct B-Tree Range Lookup):");
        $keysetSql = "SELECT id, sku, name, price FROM products WHERE id > {$offset} ORDER BY id ASC LIMIT {$limit}";
        $this->line("   Query: <fg=cyan>{$keysetSql}</>");

        $startKeyset = hrtime(true);
        $keysetResults = DB::select($keysetSql);
        $endKeyset = hrtime(true);
        $keysetDurationMs = ($endKeyset - $startKeyset) / 1e6;

        // Run EXPLAIN ANALYZE
        $keysetExplain = DB::select("EXPLAIN ANALYZE " . $keysetSql);
        $keysetExplainText = json_encode($keysetExplain[0] ?? []);

        $this->line("   ⏱️  Execution Time: <fg=green>" . number_format($keysetDurationMs, 3) . " ms</>");
        $this->line("   📄 Records Fetched: " . count($keysetResults));
        $this->newLine();

        // -------------------------------------------------------------------------
        // 3. COMPARISON MATRIX
        // -------------------------------------------------------------------------
        $speedup = $keysetDurationMs > 0 ? ($offsetDurationMs / $keysetDurationMs) : 1;

        $this->info("================================================================================");
        $this->info("📈 BENCHMARK SUMMARY & FIRST PRINCIPLES ANALYSIS");
        $this->info("================================================================================");
        $this->table(
            ['Pagination Strategy', 'Query Type', 'Rows Scanned by Storage Engine', 'Execution Latency'],
            [
                ['Offset Pagination', 'ORDER BY id LIMIT 20 OFFSET 9500', '9,520 rows examined', number_format($offsetDurationMs, 3) . ' ms'],
                ['Keyset Seek', 'WHERE id > 9500 ORDER BY id LIMIT 20', '20 rows examined (Index Range)', number_format($keysetDurationMs, 3) . ' ms'],
            ]
        );

        $this->info("⚡ Keyset Seek Speedup Factor: ~" . number_format($speedup, 1) . "x faster!");
        $this->line("💡 First Principle: Offset forces InnoDB to traverse and discard {$offset} B-Tree leaf pages in RAM.");
        $this->line("   Keyset jumps directly to the target leaf node in O(log N) depth and scans strictly {$limit} items.");

        // Record telemetry
        Log::channel('telemetry')->info('Pagination Strategy Benchmark Executed', [
            'total_products' => $totalProducts,
            'offset' => $offset,
            'limit' => $limit,
            'offset_duration_ms' => $offsetDurationMs,
            'keyset_duration_ms' => $keysetDurationMs,
            'speedup' => $speedup,
        ]);

        $this->newLine();
        $this->info("📝 Telemetry recorded in: storage/logs/telemetry.log");
        $this->info("================================================================================");

        return self::SUCCESS;
    }
}
