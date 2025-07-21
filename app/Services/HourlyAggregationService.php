<?php

namespace App\Services;

use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HourlyAggregationService
{
    private const BATCH_SIZE = 1000;

    private const CHUNK_SIZE = 10000;

    public function aggregate(Site $site): void
    {
        $startTime = microtime(true);

        Log::info('Starting hourly aggregation for site', [
            'site_id' => $site->id,
            'site_name' => $site->name,
        ]);

        $last24Hours = Carbon::now()->subHours(24);

        // Get the oldest page view for this site (outside 24h window)
        $oldestPageView = $site->pageViews()
            ->where('created_at', '<', $last24Hours)
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $oldestPageView) {
            Log::info('No page views to aggregate for site', ['site_id' => $site->id]);

            return;
        }

        $startHour = Carbon::parse($oldestPageView->created_at)->startOfHour();
        $currentHour = $startHour;
        $processedHours = 0;

        while ($currentHour < $last24Hours) {
            $hourEnd = $currentHour->copy()->addHour();

            // Check if aggregation already exists
            $existingAggregation = DB::table('analytics_hourly')
                ->where('site_id', $site->id)
                ->where('hour_start', $currentHour)
                ->exists();

            if (! $existingAggregation) {
                $this->aggregateHour($site, $currentHour, $hourEnd);
                $processedHours++;
            }

            $currentHour->addHour();
        }

        $duration = round(microtime(true) - $startTime, 2);
        Log::info('Hourly aggregation completed for site', [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'processed_hours' => $processedHours,
            'duration_seconds' => $duration,
        ]);
    }

    private function aggregateHour(Site $site, Carbon $hourStart, Carbon $hourEnd): void
    {
        // Use a single optimized query with window functions for better performance
        $hourlyData = $this->getHourlyAggregatedData($site, $hourStart, $hourEnd);

        if (! $hourlyData) {
            return;
        }

        // Insert the aggregation
        DB::table('analytics_hourly')->insert([
            'site_id' => $site->id,
            'hour_start' => $hourStart,
            'page_views' => $hourlyData['page_views'],
            'unique_visitors' => $hourlyData['unique_visitors'],
            'top_pages' => json_encode($hourlyData['top_pages']),
            'referrers' => json_encode($hourlyData['top_referrers']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getHourlyAggregatedData(Site $site, Carbon $hourStart, Carbon $hourEnd): ?array
    {
        // Single optimized query using window functions and CTEs
        $result = DB::select("
            WITH hourly_stats AS (
                SELECT 
                    COUNT(*) as page_views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM page_views 
                WHERE site_id = ? 
                AND created_at >= ? 
                AND created_at < ?
            ),
            top_pages AS (
                SELECT 
                    url,
                    COUNT(*) as count
                FROM page_views 
                WHERE site_id = ? 
                AND created_at >= ? 
                AND created_at < ?
                GROUP BY url
                ORDER BY count DESC
                LIMIT 10
            ),
            top_referrers AS (
                SELECT 
                    referrer,
                    COUNT(*) as count
                FROM page_views 
                WHERE site_id = ? 
                AND created_at >= ? 
                AND created_at < ?
                AND referrer IS NOT NULL
                GROUP BY referrer
                ORDER BY count DESC
                LIMIT 10
            )
            SELECT 
                (SELECT page_views FROM hourly_stats) as page_views,
                (SELECT unique_visitors FROM hourly_stats) as unique_visitors,
                (SELECT JSON_ARRAYAGG(JSON_OBJECT('url', url, 'count', count)) FROM top_pages) as top_pages,
                (SELECT JSON_ARRAYAGG(JSON_OBJECT('referrer', referrer, 'count', count)) FROM top_referrers) as top_referrers
        ", [
            $site->id, $hourStart, $hourEnd,
            $site->id, $hourStart, $hourEnd,
            $site->id, $hourStart, $hourEnd,
        ]);

        if (empty($result) || $result[0]->page_views === 0) {
            return null;
        }

        return [
            'page_views' => $result[0]->page_views,
            'unique_visitors' => $result[0]->unique_visitors,
            'top_pages' => json_decode($result[0]->top_pages ?? '[]', true),
            'top_referrers' => json_decode($result[0]->top_referrers ?? '[]', true),
        ];
    }

    /**
     * Batch process multiple sites efficiently
     */
    public function aggregateBatch(array $siteIds): void
    {
        $startTime = microtime(true);
        $totalSites = count($siteIds);

        Log::info('Starting batch hourly aggregation', [
            'total_sites' => $totalSites,
            'site_ids' => $siteIds,
        ]);

        $processedSites = 0;

        foreach (array_chunk($siteIds, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $siteId) {
                try {
                    $site = Site::find($siteId);
                    if ($site) {
                        $this->aggregate($site);
                        $processedSites++;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to aggregate site', [
                        'site_id' => $siteId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Small delay to prevent overwhelming the database
            usleep(100000); // 0.1 second
        }

        $duration = round(microtime(true) - $startTime, 2);
        Log::info('Batch hourly aggregation completed', [
            'total_sites' => $totalSites,
            'processed_sites' => $processedSites,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Process aggregation in chunks to handle very large datasets
     */
    public function aggregateWithChunking(Site $site): void
    {
        $last24Hours = Carbon::now()->subHours(24);

        $oldestPageView = $site->pageViews()
            ->where('created_at', '<', $last24Hours)
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $oldestPageView) {
            return;
        }

        $startHour = Carbon::parse($oldestPageView->created_at)->startOfHour();
        $currentHour = $startHour;

        while ($currentHour < $last24Hours) {
            $hourEnd = $currentHour->copy()->addHour();

            // Check if aggregation exists
            $existingAggregation = DB::table('analytics_hourly')
                ->where('site_id', $site->id)
                ->where('hour_start', $currentHour)
                ->exists();

            if (! $existingAggregation) {
                $this->aggregateHourWithChunking($site, $currentHour, $hourEnd);
            }

            $currentHour->addHour();
        }
    }

    private function aggregateHourWithChunking(Site $site, Carbon $hourStart, Carbon $hourEnd): void
    {
        $pageViews = 0;
        $uniqueVisitors = collect();
        $pageCounts = [];
        $referrerCounts = [];

        // Process in chunks to avoid memory issues
        $site->pageViews()
            ->whereBetween('created_at', [$hourStart, $hourEnd])
            ->select(['session_id', 'url', 'referrer'])
            ->chunk(self::CHUNK_SIZE, function ($chunk) use (&$pageViews, &$uniqueVisitors, &$pageCounts, &$referrerCounts) {
                $pageViews += $chunk->count();

                foreach ($chunk as $pageView) {
                    $uniqueVisitors->push($pageView->session_id);

                    // Count pages
                    $pageCounts[$pageView->url] = ($pageCounts[$pageView->url] ?? 0) + 1;

                    // Count referrers
                    if ($pageView->referrer) {
                        $referrerCounts[$pageView->referrer] = ($referrerCounts[$pageView->referrer] ?? 0) + 1;
                    }
                }
            });

        $uniqueVisitorsCount = $uniqueVisitors->unique()->count();

        // Get top pages
        arsort($pageCounts);
        $topPages = array_slice($pageCounts, 0, 10, true);
        $topPagesArray = array_map(fn ($url, $count) => ['url' => $url, 'count' => $count], array_keys($topPages), $topPages);

        // Get top referrers
        arsort($referrerCounts);
        $topReferrers = array_slice($referrerCounts, 0, 10, true);
        $topReferrersArray = array_map(fn ($referrer, $count) => ['referrer' => $referrer, 'count' => $count], array_keys($topReferrers), $topReferrers);

        // Insert aggregation
        DB::table('analytics_hourly')->insert([
            'site_id' => $site->id,
            'hour_start' => $hourStart,
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitorsCount,
            'top_pages' => json_encode($topPagesArray),
            'referrers' => json_encode($topReferrersArray),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
