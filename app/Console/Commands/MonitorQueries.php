<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queries:monitor
                            {--threshold=100 : Slow query threshold in milliseconds}
                            {--limit=50 : Maximum number of queries to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor database queries and identify slow queries';

    /**
     * Query statistics
     */
    protected array $queries = [];
    protected int $queryCount = 0;
    protected float $totalTime = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Database Query Monitor');
        $this->info('Monitoring queries in real-time. Press Ctrl+C to stop.');
        $this->newLine();

        $threshold = (int) $this->option('threshold');
        $limit = (int) $this->option('limit');

        // Enable query logging
        DB::enableQueryLog();

        // Listen to database queries
        DB::listen(function ($query) use ($threshold) {
            $time = $query->time; // Time in milliseconds
            $sql = $query->sql;
            $bindings = $query->bindings;

            $this->queryCount++;
            $this->totalTime += $time;

            // Store query info
            $this->queries[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => $time,
                'timestamp' => now()->format('H:i:s'),
            ];

            // Alert on slow queries
            if ($time >= $threshold) {
                $this->warn("⚠ Slow query detected ({$time}ms):");
                $this->line("  " . $this->formatQuery($sql, $bindings));
                $this->newLine();
            } else {
                $this->line("[{$this->queryCount}] {$time}ms - " . $this->formatQuery($sql, $bindings, 100));
            }
        });

        // Keep the command running
        $this->info('Monitoring started. Execute database operations to see queries...');
        $this->newLine();

        // Run sample query to demonstrate
        $this->runSampleQueries();

        // Display summary
        $this->displaySummary($threshold, $limit);

        return Command::SUCCESS;
    }

    /**
     * Format query for display
     */
    protected function formatQuery(string $sql, array $bindings, int $maxLength = null): string
    {
        // Replace placeholders with actual values
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'{$binding}'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        if ($maxLength && strlen($sql) > $maxLength) {
            $sql = substr($sql, 0, $maxLength) . '...';
        }

        return $sql;
    }

    /**
     * Run sample queries to demonstrate monitoring
     */
    protected function runSampleQueries(): void
    {
        $this->info('Running sample queries...');
        $this->newLine();

        // Sample queries
        \App\Models\ServiceCategory::where('is_active', true)->get();
        \App\Models\City::where('is_active', true)->limit(5)->get();
        \App\Models\User::where('user_type', 'client')->limit(10)->get();

        $this->newLine();
    }

    /**
     * Display query statistics summary
     */
    protected function displaySummary(int $threshold, int $limit): void
    {
        $this->newLine();
        $this->info('=== Query Statistics ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $this->queryCount],
                ['Total Time', round($this->totalTime, 2) . 'ms'],
                ['Average Time', $this->queryCount > 0 ? round($this->totalTime / $this->queryCount, 2) . 'ms' : '0ms'],
                ['Slow Queries (>' . $threshold . 'ms)', count(array_filter($this->queries, fn($q) => $q['time'] >= $threshold))],
            ]
        );

        // Show slowest queries
        if (!empty($this->queries)) {
            $this->newLine();
            $this->info('=== Slowest Queries ===');
            $this->newLine();

            $slowest = collect($this->queries)
                ->sortByDesc('time')
                ->take($limit)
                ->values();

            $tableData = $slowest->map(function ($query, $index) {
                return [
                    $index + 1,
                    round($query['time'], 2) . 'ms',
                    $query['timestamp'],
                    $this->formatQuery($query['sql'], $query['bindings'], 80)
                ];
            })->toArray();

            $this->table(
                ['#', 'Time', 'Timestamp', 'Query'],
                $tableData
            );
        }

        // Recommendations
        $this->newLine();
        $this->info('=== Recommendations ===');

        $slowQueries = array_filter($this->queries, fn($q) => $q['time'] >= $threshold);
        if (!empty($slowQueries)) {
            $this->warn('• Found ' . count($slowQueries) . ' slow queries. Consider:');
            $this->line('  - Adding database indexes');
            $this->line('  - Using eager loading to prevent N+1 queries');
            $this->line('  - Caching frequently accessed data');
            $this->line('  - Optimizing WHERE clauses');
        } else {
            $this->info('• All queries are performing well!');
        }

        $avgTime = $this->queryCount > 0 ? $this->totalTime / $this->queryCount : 0;
        if ($avgTime > 50) {
            $this->warn('• Average query time is high (' . round($avgTime, 2) . 'ms)');
            $this->line('  - Review query complexity');
            $this->line('  - Check database server performance');
        }

        if ($this->queryCount > 100) {
            $this->warn('• High number of queries detected (' . $this->queryCount . ')');
            $this->line('  - Consider using eager loading');
            $this->line('  - Batch database operations');
        }
    }
}
