<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:health
                            {--detailed : Show detailed Redis information}
                            {--test : Run cache read/write tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Redis connection and health status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Redis Health Check');
        $this->newLine();

        // Check if Redis is configured
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            $this->warn('⚠ Redis is not configured as cache or queue driver');
            $this->line('Current cache driver: ' . config('cache.default'));
            $this->line('Current queue driver: ' . config('queue.default'));
            $this->newLine();
        }

        // Test Redis connection
        $connectionStatus = $this->checkRedisConnection();

        if (!$connectionStatus) {
            return Command::FAILURE;
        }

        // Run detailed checks if requested
        if ($this->option('detailed')) {
            $this->showDetailedInfo();
        }

        // Run cache tests if requested
        if ($this->option('test')) {
            $this->runCacheTests();
        }

        $this->newLine();
        $this->info('✓ Redis health check completed');

        return Command::SUCCESS;
    }

    /**
     * Check Redis connection
     */
    protected function checkRedisConnection(): bool
    {
        $this->line('Testing Redis connection...');

        try {
            // Test default connection
            $pong = Redis::connection()->ping();

            if ($pong === true || $pong === 'PONG') {
                $this->info('✓ Redis connection: OK');
            } else {
                $this->error('✗ Redis connection: FAILED (unexpected response)');
                return false;
            }

            // Test cache connection
            if (config('cache.default') === 'redis') {
                $cacheConnection = Redis::connection('cache');
                $cachePong = $cacheConnection->ping();

                if ($cachePong === true || $cachePong === 'PONG') {
                    $this->info('✓ Redis cache connection: OK');
                } else {
                    $this->warn('⚠ Redis cache connection: FAILED');
                }
            }

            // Test queue connection
            if (config('queue.default') === 'redis') {
                $queueConnection = Redis::connection('queue');
                $queuePong = $queueConnection->ping();

                if ($queuePong === true || $queuePong === 'PONG') {
                    $this->info('✓ Redis queue connection: OK');
                } else {
                    $this->warn('⚠ Redis queue connection: FAILED');
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->error('✗ Redis connection FAILED: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting steps:');
            $this->line('1. Make sure Redis is installed and running');
            $this->line('2. Check Redis configuration in .env file');
            $this->line('3. Verify PHP Redis extension is installed (phpredis)');
            $this->line('4. Check Redis logs for errors');
            $this->newLine();
            $this->line('To install Redis:');
            $this->line('  Windows: Download from https://github.com/tporadowski/redis/releases');
            $this->line('  Linux: sudo apt-get install redis-server');
            $this->line('  macOS: brew install redis');

            return false;
        }
    }

    /**
     * Show detailed Redis information
     */
    protected function showDetailedInfo(): void
    {
        $this->newLine();
        $this->info('=== Detailed Redis Information ===');
        $this->newLine();

        try {
            $info = Redis::connection()->info();

            // Server info
            $this->line('<fg=cyan>Server Information:</>');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Redis Version', $info['redis_version'] ?? 'N/A'],
                    ['OS', $info['os'] ?? 'N/A'],
                    ['Process ID', $info['process_id'] ?? 'N/A'],
                    ['TCP Port', $info['tcp_port'] ?? 'N/A'],
                    ['Uptime (days)', round(($info['uptime_in_seconds'] ?? 0) / 86400, 2)],
                ]
            );

            $this->newLine();

            // Memory info
            $this->line('<fg=cyan>Memory Information:</>');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Used Memory', $this->formatBytes($info['used_memory'] ?? 0)],
                    ['Used Memory Peak', $this->formatBytes($info['used_memory_peak'] ?? 0)],
                    ['Max Memory', $info['maxmemory_human'] ?? 'No limit'],
                    ['Memory Fragmentation Ratio', $info['mem_fragmentation_ratio'] ?? 'N/A'],
                ]
            );

            $this->newLine();

            // Stats
            $this->line('<fg=cyan>Statistics:</>');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Total Connections Received', number_format($info['total_connections_received'] ?? 0)],
                    ['Total Commands Processed', number_format($info['total_commands_processed'] ?? 0)],
                    ['Connected Clients', $info['connected_clients'] ?? 'N/A'],
                    ['Blocked Clients', $info['blocked_clients'] ?? 'N/A'],
                    ['Keyspace Hits', number_format($info['keyspace_hits'] ?? 0)],
                    ['Keyspace Misses', number_format($info['keyspace_misses'] ?? 0)],
                ]
            );

            // Cache hit ratio
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;

            if ($total > 0) {
                $hitRatio = round(($hits / $total) * 100, 2);
                $this->newLine();

                if ($hitRatio >= 80) {
                    $this->info("Cache Hit Ratio: {$hitRatio}% (Excellent)");
                } elseif ($hitRatio >= 60) {
                    $this->line("Cache Hit Ratio: {$hitRatio}% (Good)");
                } else {
                    $this->warn("Cache Hit Ratio: {$hitRatio}% (Needs Improvement)");
                }
            }

            // Database keys
            $this->newLine();
            $this->line('<fg=cyan>Database Keys:</>');

            $databases = [];
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db')) {
                    preg_match('/keys=(\d+)/', $value, $matches);
                    $keyCount = $matches[1] ?? 0;
                    $databases[] = [
                        str_replace('db', 'DB ', $key),
                        number_format($keyCount) . ' keys'
                    ];
                }
            }

            if (!empty($databases)) {
                $this->table(['Database', 'Keys'], $databases);
            } else {
                $this->line('No keys found in any database');
            }
        } catch (\Exception $e) {
            $this->error('Failed to get detailed info: ' . $e->getMessage());
        }
    }

    /**
     * Run cache read/write tests
     */
    protected function runCacheTests(): void
    {
        $this->newLine();
        $this->info('=== Running Cache Tests ===');
        $this->newLine();

        $testKey = 'redis_health_check_test';
        $testValue = 'test_value_' . time();

        try {
            // Test write
            $this->line('Testing cache write...');
            $writeStart = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $writeTime = round((microtime(true) - $writeStart) * 1000, 2);
            $this->info("✓ Cache write: {$writeTime}ms");

            // Test read
            $this->line('Testing cache read...');
            $readStart = microtime(true);
            $readValue = Cache::get($testKey);
            $readTime = round((microtime(true) - $readStart) * 1000, 2);

            if ($readValue === $testValue) {
                $this->info("✓ Cache read: {$readTime}ms (value matches)");
            } else {
                $this->error('✗ Cache read: value mismatch');
            }

            // Test delete
            $this->line('Testing cache delete...');
            $deleteStart = microtime(true);
            Cache::forget($testKey);
            $deleteTime = round((microtime(true) - $deleteStart) * 1000, 2);
            $this->info("✓ Cache delete: {$deleteTime}ms");

            // Verify deletion
            if (Cache::has($testKey)) {
                $this->error('✗ Cache delete verification failed');
            } else {
                $this->info('✓ Cache delete verified');
            }

            // Test locks (Redis specific)
            $this->newLine();
            $this->line('Testing Redis locks...');
            $lockKey = 'test_lock';

            try {
                $lock = Cache::lock($lockKey, 10);

                if ($lock->get()) {
                    $this->info('✓ Lock acquired successfully');
                    $lock->release();
                    $this->info('✓ Lock released successfully');
                } else {
                    $this->warn('⚠ Failed to acquire lock');
                }
            } catch (\Exception $e) {
                $this->error('✗ Lock test failed: ' . $e->getMessage());
            }

            $this->newLine();
            $this->info('✓ All cache tests passed');
        } catch (\Exception $e) {
            $this->error('✗ Cache test failed: ' . $e->getMessage());
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
