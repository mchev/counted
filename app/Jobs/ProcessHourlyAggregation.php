<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\HourlyAggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHourlyAggregation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes

    public $tries = 3;

    public $maxExceptions = 3;

    private ?int $siteId;

    private bool $useChunking;

    public function __construct(?int $siteId = null, bool $useChunking = false)
    {
        $this->siteId = $siteId;
        $this->useChunking = $useChunking;
        $this->onQueue('analytics');
    }

    public function handle(HourlyAggregationService $service): void
    {
        $startTime = microtime(true);

        Log::info('Starting hourly aggregation job', [
            'site_id' => $this->siteId,
            'use_chunking' => $this->useChunking,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            if ($this->siteId) {
                // Process single site
                $site = Site::find($this->siteId);
                if (! $site) {
                    Log::error('Site not found for hourly aggregation', ['site_id' => $this->siteId]);

                    return;
                }

                if ($this->useChunking) {
                    $service->aggregateWithChunking($site);
                } else {
                    $service->aggregate($site);
                }
            } else {
                // Process all sites in batches
                $siteIds = Site::pluck('id')->toArray();
                $service->aggregateBatch($siteIds);
            }

            $duration = round(microtime(true) - $startTime, 2);
            Log::info('Hourly aggregation job completed successfully', [
                'site_id' => $this->siteId,
                'duration_seconds' => $duration,
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('Hourly aggregation job failed', [
                'site_id' => $this->siteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Hourly aggregation job failed permanently', [
            'site_id' => $this->siteId,
            'error' => $exception->getMessage(),
            'job_id' => $this->job->getJobId(),
        ]);
    }
}
