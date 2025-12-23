<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RegenerateMediaConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:regenerate
                            {--collection= : Only regenerate specific collection}
                            {--model= : Only regenerate for specific model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all media conversions (optimizes existing images)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting media conversions regeneration...');
        $this->info('This will optimize all existing images (resize + compress)');
        $this->newLine();

        $query = Media::query();

        // Filter by collection if specified
        if ($collection = $this->option('collection')) {
            $query->where('collection_name', $collection);
            $this->info("Filtering by collection: {$collection}");
        }

        // Filter by model if specified
        if ($model = $this->option('model')) {
            $query->where('model_type', $model);
            $this->info("Filtering by model: {$model}");
        }

        $mediaItems = $query->get();
        $totalItems = $mediaItems->count();

        if ($totalItems === 0) {
            $this->warn('No media items found to regenerate.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalItems} media items to process");
        $this->newLine();

        $bar = $this->output->createProgressBar($totalItems);
        $bar->setFormat('very_verbose');

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($mediaItems as $media) {
            try {
                // Get file size before optimization
                $sizeBefore = $media->size;

                // Regenerate conversions (this will optimize the image)
                $media->regenerateConversions();

                // Refresh to get new size
                $media->refresh();
                $sizeAfter = $media->getConversions()->sum(function ($conversion) use ($media) {
                    $path = $media->getPath($conversion['name']);
                    return file_exists($path) ? filesize($path) : 0;
                });

                $successful++;
                $bar->advance();
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'id' => $media->id,
                    'file' => $media->file_name,
                    'error' => $e->getMessage()
                ];
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Show results
        $this->info("✅ Successfully processed: {$successful} items");

        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed} items");
            $this->newLine();
            $this->error('Failed items:');
            foreach ($errors as $error) {
                $this->line("  - ID {$error['id']}: {$error['file']} - {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('✨ Media regeneration complete!');
        $this->info('All new uploads will be automatically optimized.');

        return Command::SUCCESS;
    }
}
