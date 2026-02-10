<?php

namespace App\Controllers;

use App\Traits\ResponseTrait;
use App\Database\DB;
use Swoole\Http\Request;

/**
 * Benchmark endpoint to find performance bottlenecks
 * GET /api/v1/benchmark/book/{id}
 */
class BenchmarkController
{
    use ResponseTrait;

    /**
     * Benchmark book details query - shows where time is spent
     */
    public function bookDetails(Request $request, int $id): array
    {
        $results = [
            'book_id' => $id,
            'timings' => [],
            'server_info' => [],
        ];

        // 1. اطلاعات سرور
        $results['server_info'] = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : 'N/A (Windows)',
            'db_host' => $_ENV['DB_HOST'] ?? 'unknown',
        ];

        // 2. تست اتصال به دیتابیس (connection pool)
        $t0 = hrtime(true);
        $pdo = DB::get();
        $t1 = hrtime(true);
        $results['timings']['db_connection_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 3. Query ساده (ping)
        $t0 = hrtime(true);
        DB::fetch("SELECT 1 as ping");
        $t1 = hrtime(true);
        $results['timings']['db_ping_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 4. Query اصلی book details (بدون JOIN)
        $t0 = hrtime(true);
        $product = DB::fetch("SELECT * FROM products WHERE id = ? AND type = 'book'", [$id]);
        $t1 = hrtime(true);
        $results['timings']['query_product_only_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 5. Query کامل با همه JOIN ها
        $sql = "
            SELECT 
                p.id, p.title, p.slug, p.description, p.cover_image,
                p.price, p.price_with_discount, p.rate_avg, p.rate_count,
                p.view_count, p.attributes, p.created_at, p.updated_at,
                c.id as category_id, c.title as category_title, c.full_path as category_path,
                pub.id as publisher_id, pub.title as publisher_title,
                string_agg(per.full_name, ', ') FILTER (WHERE pc.role = 'author') as authors,
                string_agg(per.full_name, ', ') FILTER (WHERE pc.role = 'translator') as translators
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN publishers pub ON pub.id = p.publisher_id
            LEFT JOIN product_contributors pc ON pc.product_id = p.id
            LEFT JOIN persons per ON per.id = pc.person_id
            WHERE p.id = ? AND p.type = 'book' AND p.deleted_at IS NULL
            GROUP BY p.id, c.id, pub.id
        ";

        $t0 = hrtime(true);
        $row = DB::fetch($sql, [$id]);
        $t1 = hrtime(true);
        $results['timings']['query_full_with_joins_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 6. JSON decode
        $t0 = hrtime(true);
        $attr = json_decode($row['attributes'] ?? '{}', true) ?: [];
        $t1 = hrtime(true);
        $results['timings']['json_decode_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 7. ساخت response array
        $t0 = hrtime(true);
        $response = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'authors' => $row['authors'],
            // ... بقیه فیلدها
        ];
        $t1 = hrtime(true);
        $results['timings']['build_response_ms'] = round(($t1 - $t0) / 1e6, 3);

        // 8. محاسبه کل
        $totalQueryTime = $results['timings']['query_full_with_joins_ms'];
        $totalOverhead = array_sum($results['timings']) - $totalQueryTime;
        
        $results['summary'] = [
            'total_query_time_ms' => $totalQueryTime,
            'total_overhead_ms' => round($totalOverhead, 3),
            'bottleneck' => $totalQueryTime > $totalOverhead ? 'DATABASE' : 'PHP_PROCESSING',
        ];

        // 9. EXPLAIN ANALYZE (فقط plan، بدون اجرا مجدد)
        try {
            $explain = DB::fetchAll("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) " . $sql, [$id]);
            $plan = $explain[0]['QUERY PLAN'] ?? null;
            if ($plan) {
                $planData = json_decode($plan, true);
                $results['query_plan'] = [
                    'execution_time_ms' => $planData[0]['Execution Time'] ?? null,
                    'planning_time_ms' => $planData[0]['Planning Time'] ?? null,
                    'shared_hit_blocks' => $planData[0]['Plan']['Shared Hit Blocks'] ?? null,
                    'shared_read_blocks' => $planData[0]['Plan']['Shared Read Blocks'] ?? null,
                ];
                
                // اگر shared_read_blocks زیاد باشه یعنی cache miss داریم
                $hitBlocks = $results['query_plan']['shared_hit_blocks'] ?? 0;
                $readBlocks = $results['query_plan']['shared_read_blocks'] ?? 0;
                if ($readBlocks > 0) {
                    $cacheHitRatio = $hitBlocks / ($hitBlocks + $readBlocks) * 100;
                    $results['query_plan']['cache_hit_ratio_percent'] = round($cacheHitRatio, 1);
                }
            }
        } catch (\Exception $e) {
            $results['query_plan_error'] = $e->getMessage();
        }

        // 10. Memory usage بعد از اجرا
        $results['server_info']['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        return $results;
    }

    /**
     * Quick benchmark - multiple runs to get average
     */
    public function bookDetailsAvg(Request $request, int $id): array
    {
        $runs = min((int)($request->get['runs'] ?? 5), 20);
        $times = [];

        $sql = "
            SELECT 
                p.id, p.title, p.slug, p.description, p.cover_image,
                p.price, p.price_with_discount, p.rate_avg, p.rate_count,
                p.view_count, p.attributes, p.created_at, p.updated_at,
                c.id as category_id, c.title as category_title, c.full_path as category_path,
                pub.id as publisher_id, pub.title as publisher_title,
                string_agg(per.full_name, ', ') FILTER (WHERE pc.role = 'author') as authors,
                string_agg(per.full_name, ', ') FILTER (WHERE pc.role = 'translator') as translators
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN publishers pub ON pub.id = p.publisher_id
            LEFT JOIN product_contributors pc ON pc.product_id = p.id
            LEFT JOIN persons per ON per.id = pc.person_id
            WHERE p.id = ? AND p.type = 'book' AND p.deleted_at IS NULL
            GROUP BY p.id, c.id, pub.id
        ";

        for ($i = 0; $i < $runs; $i++) {
            $t0 = hrtime(true);
            DB::fetch($sql, [$id]);
            $t1 = hrtime(true);
            $times[] = ($t1 - $t0) / 1e6;
        }

        sort($times);
        
        return [
            'book_id' => $id,
            'runs' => $runs,
            'times_ms' => array_map(fn($t) => round($t, 3), $times),
            'stats' => [
                'min_ms' => round(min($times), 3),
                'max_ms' => round(max($times), 3),
                'avg_ms' => round(array_sum($times) / count($times), 3),
                'median_ms' => round($times[(int)(count($times) / 2)], 3),
            ],
            'analysis' => $this->analyzeResults($times),
        ];
    }

    private function analyzeResults(array $times): array
    {
        $avg = array_sum($times) / count($times);
        $min = min($times);
        
        $analysis = [];
        
        if ($avg > 100) {
            $analysis[] = '⚠️ Average > 100ms - Check database indexes and query plan';
        } elseif ($avg > 50) {
            $analysis[] = '⚡ Average 50-100ms - Acceptable but could be optimized';
        } else {
            $analysis[] = '✅ Average < 50ms - Good performance';
        }

        // بررسی variance
        if ($min > 0 && max($times) / $min > 3) {
            $analysis[] = '⚠️ High variance - First query might be cold (no cache)';
        }

        // پیشنهادات
        if ($avg > 50) {
            $analysis[] = '💡 Suggestions: Check network latency to DB, increase shared_buffers, add indexes';
        }

        return $analysis;
    }
}
