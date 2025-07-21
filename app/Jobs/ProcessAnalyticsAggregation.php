<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\AnalyticsAggregationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnalyticsAggregation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public $tries = 3;

    public $maxExceptions = 3;

    public function __construct(
        private string $type,
        private ?int $siteId = null,
        private ?Carbon $date = null
    ) {
        $this->onQueue('analytics');
    }

    public function handle(AnalyticsAggregationService $service): void
    {
        $startTime = microtime(true);

        Log::info('Starting analytics aggregation job', [
            'type' => $this->type,
            'site_id' => $this->siteId,
            'date' => $this->date?->toDateString(),
        ]);

        try {
            if ($this->siteId) {
                // Process specific site
                $site = Site::findOrFail($this->siteId);
                $this->processSite($service, $site);
            } else {
                // Process all sites
                $sites = Site::all();
                foreach ($sites as $site) {
                    $this->processSite($service, $site);
                }
            }

            // Cleanup after all processing
            if ($service->isCleanupEnabled()) {
                $service->cleanupAllSourceData();
            }

            // Clear cache after aggregation
            if ($service->isCacheEnabled()) {
                $service->clearAnalyticsCache();
            }

            $duration = round(microtime(true) - $startTime, 2);
            Log::info('Analytics aggregation job completed', [
                'type' => $this->type,
                'duration' => $duration,
                'site_id' => $this->siteId,
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics aggregation job failed', [
                'type' => $this->type,
                'site_id' => $this->siteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function processSite(AnalyticsAggregationService $service, Site $site): void
    {
        $siteStartTime = microtime(true);

        Log::info('Processing site for aggregation', [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'type' => $this->type,
        ]);

        try {
            switch ($this->type) {
                case 'hourly':
                    $hourStart = Carbon::now()->startOfHour();
                    $service->aggregateSiteHourly($site, $hourStart);
                    break;

                case 'daily':
                    $date = $this->date ?? Carbon::yesterday();
                    $service->aggregateSiteDaily($site, $date);
                    break;

                case 'monthly':
                    $yearMonth = $this->date?->format('Y-m') ?? Carbon::now()->subMonth()->format('Y-m');
                    $service->aggregateSiteMonthly($site, $yearMonth);
                    break;

                case 'all':
                    // Process all types for this site
                    $hourStart = Carbon::now()->startOfHour();
                    $service->aggregateSiteHourly($site, $hourStart);

                    $date = Carbon::yesterday();
                    $service->aggregateSiteDaily($site, $date);

                    $yearMonth = Carbon::now()->subMonth()->format('Y-m');
                    $service->aggregateSiteMonthly($site, $yearMonth);
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid aggregation type: {$this->type}");
            }

            $siteDuration = round(microtime(true) - $siteStartTime, 2);
            Log::info('Site aggregation completed', [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'type' => $this->type,
                'duration' => $siteDuration,
            ]);

        } catch (\Exception $e) {
            Log::error('Site aggregation failed', [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics aggregation job failed permanently', [
            'type' => $this->type,
            'site_id' => $this->siteId,
            'error' => $exception->getMessage(),
        ]);
    }
}
