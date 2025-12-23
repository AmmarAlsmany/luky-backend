<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;

class CacheWarmUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warmup
                            {--clear : Clear all caches before warming up}
                            {--force : Force cache refresh even if already cached}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up application caches for better performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔥 Starting cache warm-up...');
        $this->newLine();

        // Clear caches if requested
        if ($this->option('clear')) {
            $this->warn('Clearing all caches...');
            CacheService::clearAll();
            \Cache::flush();
            $this->info('✓ Caches cleared');
            $this->newLine();
        }

        $results = [];
        $startTime = microtime(true);

        // Warm up caches with progress bar
        $tasks = [
            'Service Categories' => fn() => CacheService::getServiceCategories(),
            'Cities' => fn() => CacheService::getCities(),
            'App Settings' => fn() => CacheService::getAllAppSettings(),
            'Home Banners' => fn() => CacheService::getHomeBanners(),
            'Featured Providers' => fn() => CacheService::getFeaturedProviders(),
            'Trending Services' => fn() => CacheService::getTrendingServices(),
        ];

        $bar = $this->output->createProgressBar(count($tasks));
        $bar->start();

        foreach ($tasks as $name => $task) {
            try {
                $taskStartTime = microtime(true);
                $result = $task();
                $duration = round((microtime(true) - $taskStartTime) * 1000, 2);

                $count = is_countable($result) ? count($result) : (is_object($result) && method_exists($result, 'count') ? $result->count() : 0);

                $results[] = [
                    'name' => $name,
                    'status' => 'success',
                    'count' => $count,
                    'duration' => $duration . 'ms'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'name' => $name,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results table
        $this->table(
            ['Cache', 'Status', 'Items', 'Duration'],
            array_map(function ($result) {
                return [
                    $result['name'],
                    $result['status'] === 'success' ? '✓ Success' : '✗ Failed',
                    $result['count'] ?? '-',
                    $result['duration'] ?? ($result['error'] ?? '-')
                ];
            }, $results)
        );

        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
        $this->newLine();
        $this->info("✓ Cache warm-up completed in {$totalDuration}ms");

        // Warm up city-specific caches
        if ($this->confirm('Do you want to warm up city-specific caches? (This may take longer)', false)) {
            $this->warmUpCitySpecificCaches();
        }

        return Command::SUCCESS;
    }

    /**
     * Warm up city-specific caches (featured providers and trending services per city)
     */
    protected function warmUpCitySpecificCaches(): void
    {
        $this->newLine();
        $this->info('Warming up city-specific caches...');

        $cities = \App\Models\City::where('is_active', true)->pluck('id');
        $bar = $this->output->createProgressBar($cities->count() * 2); // 2 cache types per city
        $bar->start();

        foreach ($cities as $cityId) {
            try {
                CacheService::getFeaturedProviders($cityId);
                $bar->advance();

                CacheService::getTrendingServices($cityId);
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("Failed to cache for city {$cityId}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ City-specific caches warmed up');
    }
}
