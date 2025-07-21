<?php

namespace App\Console\Commands;

use App\Jobs\ProcessHourlyAggregation;
use App\Models\Site;
use App\Services\HourlyAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AggregateAnalytics extends Command
{
    protected $signature = 'analytics:aggregate 
                            {type : Type of aggregation (hourly, daily, monthly, all)}
                            {--site= : Specific site ID to process}
                            {--chunking : Use chunking for very large datasets}
                            {--sync : Run synchronously instead of using jobs}
                            {--batch-size=1000 : Batch size for processing}';

    protected $description = 'Aggregate analytics data with optimized performance for large datasets';

    public function handle(): int
    {
        $type = $this->argument('type');
        $siteId = $this->option('site');
        $useChunking = $this->option('chunking');
        $sync = $this->option('sync');
        $batchSize = (int) $this->option('batch-size');

        $this->info("Starting analytics aggregation: {$type}");

        if ($siteId) {
            $this->info("Processing site ID: {$siteId}");
        }

        if ($useChunking) {
            $this->info('Using chunking mode for large datasets');
        }

        $startTime = microtime(true);

        try {
            switch ($type) {
                case 'hourly':
                    $this->aggregateHourly($siteId, $useChunking, $sync);
                    break;

                case 'daily':
                    $this->aggregateDaily($siteId, $sync);
                    break;

                case 'monthly':
                    $this->aggregateMonthly($siteId, $sync);
                    break;

                case 'all':
                    $this->aggregateAll($siteId, $useChunking, $sync);
                    break;

                default:
                    $this->error("Invalid aggregation type: {$type}");

                    return 1;
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✅ Aggregation completed successfully in {$duration} seconds");

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Aggregation failed: '.$e->getMessage());
            Log::error('Analytics aggregation command failed', [
                'type' => $type,
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    private function aggregateHourly(?int $siteId, bool $useChunking, bool $sync): void
    {
        if ($sync) {
            // Run synchronously
            $service = app(HourlyAggregationService::class);

            if ($siteId) {
                $site = Site::find($siteId);
                if (! $site) {
                    throw new \Exception("Site not found: {$siteId}");
                }

                if ($useChunking) {
                    $service->aggregateWithChunking($site);
                } else {
                    $service->aggregate($site);
                }
            } else {
                $service->aggregateBatch(Site::pluck('id')->toArray());
            }
        } else {
            // Dispatch job
            ProcessHourlyAggregation::dispatch($siteId, $useChunking);
            $this->info('Hourly aggregation job dispatched');
        }
    }

    private function aggregateDaily(?int $siteId, bool $sync): void
    {
        // Use existing daily aggregation logic
        $this->info('Daily aggregation not yet implemented');
    }

    private function aggregateMonthly(?int $siteId, bool $sync): void
    {
        // Use existing monthly aggregation logic
        $this->info('Monthly aggregation not yet implemented');
    }

    private function aggregateAll(?int $siteId, bool $useChunking, bool $sync): void
    {
        $this->info('Running all aggregations...');

        $this->aggregateHourly($siteId, $useChunking, $sync);

        if (! $sync) {
            // Add delays between jobs when running asynchronously
            sleep(5);
        }

        $this->aggregateDaily($siteId, $sync);

        if (! $sync) {
            sleep(5);
        }

        $this->aggregateMonthly($siteId, $sync);
    }
}
