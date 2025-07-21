<?php

namespace App\Services;

use App\Helpers\DatabaseHelper;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsAggregationService
{
    // Configuration flags
    private bool $enableCleanup;

    private bool $enableCache;

    public function __construct()
    {
        $this->enableCleanup = config('analytics.cleanup.enabled', true);
        $this->enableCache = config('analytics.cache.enabled', true);
    }

    /**
     * Active ou désactive le nettoyage automatique
     */
    public function setCleanupEnabled(bool $enabled): self
    {
        $this->enableCleanup = $enabled;

        return $this;
    }

    /**
     * Active ou désactive le cache
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->enableCache = $enabled;

        return $this;
    }

    /**
     * Récupère les périodes de rétention configurées
     */
    public function getRetentionPeriods(): array
    {
        return config('analytics.retention', [
            'page_views' => 7,
            'events' => 7,
            'hourly' => 7,
            'daily' => 365,
            'monthly' => 1825,
        ]);
    }

    /**
     * Check if cleanup is enabled
     */
    public function isCleanupEnabled(): bool
    {
        return $this->enableCleanup;
    }

    /**
     * Check if cache is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    /**
     * Met à jour les périodes de rétention
     */
    public function setRetentionPeriods(array $periods): self
    {
        // Validate periods
        foreach ($periods as $type => $value) {
            if (! in_array($type, ['page_views', 'events', 'hourly', 'daily', 'monthly'])) {
                throw new \InvalidArgumentException("Invalid retention type: {$type}");
            }
            if (! is_numeric($value) || $value < 0) {
                throw new \InvalidArgumentException("Invalid retention value for {$type}: {$value}");
            }
        }

        // Update constants (in a real implementation, you'd use config files)
        // For now, we'll use reflection or store in cache
        Cache::put('analytics_retention_periods', $periods, 86400); // 24 hours

        return $this;
    }

    /**
     * Agrège les données horaires depuis la table page_views
     */
    public function aggregateHourly(): void
    {
        $hourStart = Carbon::now()->startOfHour();

        // Récupérer tous les sites
        $sites = Site::all();

        foreach ($sites as $site) {
            try {
                $this->aggregateSiteHourly($site, $hourStart);
            } catch (\Exception $e) {
                Log::error("Erreur agrégation horaire pour site {$site->id}: ".$e->getMessage());
            }
        }

        // Cleanup source data after aggregation
        if ($this->enableCleanup) {
            $this->cleanupAllSourceData();
        }

        // Clear cache after aggregation
        if ($this->enableCache) {
            $this->clearAnalyticsCache();
        }
    }

    /**
     * Agrège les données quotidiennes depuis les données horaires
     */
    public function aggregateDaily(): void
    {
        $date = Carbon::yesterday();

        $sites = Site::all();

        foreach ($sites as $site) {
            try {
                $this->aggregateSiteDaily($site, $date);
            } catch (\Exception $e) {
                Log::error("Erreur agrégation quotidienne pour site {$site->id}: ".$e->getMessage());
            }
        }

        // Cleanup source data after aggregation
        if ($this->enableCleanup) {
            $this->cleanupAllSourceData();
        }

        // Clear cache after aggregation
        if ($this->enableCache) {
            $this->clearAnalyticsCache();
        }
    }

    /**
     * Agrège les données mensuelles depuis les données quotidiennes
     */
    public function aggregateMonthly(): void
    {
        $lastMonth = Carbon::now()->subMonth();
        $yearMonth = $lastMonth->format('Y-m');

        $sites = Site::all();

        foreach ($sites as $site) {
            try {
                $this->aggregateSiteMonthly($site, $yearMonth);
            } catch (\Exception $e) {
                Log::error("Erreur agrégation mensuelle pour site {$site->id}: ".$e->getMessage());
            }
        }

        // Cleanup source data after aggregation
        if ($this->enableCleanup) {
            $this->cleanupAllSourceData();
        }

        // Clear cache after aggregation
        if ($this->enableCache) {
            $this->clearAnalyticsCache();
        }
    }

    public function aggregateSiteHourly(Site $site): void
    {
        // On boucle sur toutes les heures depuis l'élément le plus vieux de la table page_views
        // On crée l'agrégation pour chaque heure
        // On ne fait pas l'agrégation pour les 24 dernières heures

        $last24Hours = Carbon::now()->subHours(24);

        // Récupérer l'heure la plus ancienne dans page_views pour ce site
        $oldestPageView = $site->pageViews()
            ->where('created_at', '<', $last24Hours)
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $oldestPageView) {
            return; // Pas de données à agréger
        }

        // Début de l'heure la plus ancienne
        $startHour = Carbon::parse($oldestPageView->created_at)->startOfHour();

        // Boucler sur chaque heure jusqu'à il y a 24h
        $currentHour = $startHour;

        while ($currentHour < $last24Hours) {
            $hourEnd = $currentHour->copy()->addHour();

            // Vérifier si l'agrégation pour cette heure existe déjà
            $existingAggregation = DB::table('analytics_hourly')
                ->where('site_id', $site->id)
                ->where('hour_start', $currentHour)
                ->exists();

            if (! $existingAggregation) {
                // Récupérer les statistiques pour cette heure
                $hourlyStats = $site->pageViews()
                    ->whereBetween('created_at', [$currentHour, $hourEnd])
                    ->selectRaw('
                        COUNT(*) as page_views,
                        COUNT(DISTINCT session_id) as unique_visitors
                    ')
                    ->first();

                // Top pages de cette heure
                $topPages = $site->pageViews()
                    ->whereBetween('created_at', [$currentHour, $hourEnd])
                    ->selectRaw('url, COUNT(*) as count')
                    ->groupBy('url')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->toArray();

                // Top referrers de cette heure
                $topReferrers = $site->pageViews()
                    ->whereBetween('created_at', [$currentHour, $hourEnd])
                    ->whereNotNull('referrer')
                    ->selectRaw('referrer, COUNT(*) as count')
                    ->groupBy('referrer')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->toArray();

                // Insérer l'agrégation horaire
                DB::table('analytics_hourly')->insert([
                    'site_id' => $site->id,
                    'hour_start' => $currentHour,
                    'page_views' => $hourlyStats->page_views ?? 0,
                    'unique_visitors' => $hourlyStats->unique_visitors ?? 0,
                    'top_pages' => json_encode($topPages),
                    'referrers' => json_encode($topReferrers),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Passer à l'heure suivante
            $currentHour->addHour();
        }
    }

    public function aggregateSiteDaily(Site $site, Carbon $date): void
    {
        // Agrégation depuis les données horaires
        $stats = DB::table('analytics_hourly')
            ->where('site_id', $site->id)
            ->whereDate('hour_start', $date)
            ->selectRaw('
                SUM(page_views) as page_views,
                SUM(unique_visitors) as unique_visitors
            ')
            ->first();

        // Fusionner les top pages de toutes les heures
        $allTopPages = DB::table('analytics_hourly')
            ->where('site_id', $site->id)
            ->whereDate('hour_start', $date)
            ->whereNotNull('top_pages')
            ->pluck('top_pages')
            ->map(fn ($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('page_url')
            ->map(fn ($group) => ['page_url' => $group->first()['page_url'], 'count' => $group->sum('count')])
            ->sortByDesc('count')
            ->take(20)
            ->values()
            ->toArray();

        // Même chose pour les referrers
        $allReferrers = DB::table('analytics_hourly')
            ->where('site_id', $site->id)
            ->whereDate('hour_start', $date)
            ->whereNotNull('referrers')
            ->pluck('referrers')
            ->map(fn ($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('referrer')
            ->map(fn ($group) => ['referrer' => $group->first()['referrer'], 'count' => $group->sum('count')])
            ->sortByDesc('count')
            ->take(20)
            ->values()
            ->toArray();

        DB::table('analytics_daily')->updateOrInsert(
            ['site_id' => $site->id, 'date' => $date->toDateString()],
            [
                'page_views' => $stats->page_views ?? 0,
                'unique_visitors' => $stats->unique_visitors ?? 0,
                'top_pages' => json_encode($allTopPages),
                'referrers' => json_encode($allReferrers),
                'updated_at' => now(),
            ]
        );
    }

    public function aggregateSiteMonthly(Site $site, string $yearMonth): void
    {
        // Agrégation depuis les données quotidiennes
        $monthFormat = DatabaseHelper::getMonthFormatFunction('date');
        $stats = DB::table('analytics_daily')
            ->where('site_id', $site->id)
            ->whereRaw("{$monthFormat} = ?", [$yearMonth])
            ->selectRaw('
                SUM(page_views) as page_views,
                SUM(unique_visitors) as unique_visitors
            ')
            ->first();

        // Fusionner les top pages du mois
        $allTopPages = DB::table('analytics_daily')
            ->where('site_id', $site->id)
            ->whereRaw("{$monthFormat} = ?", [$yearMonth])
            ->whereNotNull('top_pages')
            ->pluck('top_pages')
            ->map(fn ($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('page_url')
            ->map(fn ($group) => ['page_url' => $group->first()['page_url'], 'count' => $group->sum('count')])
            ->sortByDesc('count')
            ->take(50)
            ->values()
            ->toArray();

        DB::table('analytics_monthly')->updateOrInsert(
            ['site_id' => $site->id, 'year_month' => $yearMonth],
            [
                'page_views' => $stats->page_views ?? 0,
                'unique_visitors' => $stats->unique_visitors ?? 0,
                'top_pages' => json_encode($allTopPages),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Nettoie toutes les données sources après agrégation
     */
    public function cleanupAllSourceData(): array
    {
        $stats = [
            'page_views_deleted' => 0,
            'events_deleted' => 0,
            'sites_processed' => 0,
        ];

        $sites = Site::all();

        foreach ($sites as $site) {
            try {
                // Cleanup page_views data
                $pageViewsDeleted = $this->cleanupPageViewsData($site);
                $stats['page_views_deleted'] += $pageViewsDeleted;

                // Cleanup events data
                $eventsDeleted = $this->cleanupEventsData($site);
                $stats['events_deleted'] += $eventsDeleted;

                $stats['sites_processed']++;
            } catch (\Exception $e) {
                Log::error("Error cleaning up source data for site {$site->id}: ".$e->getMessage());
            }
        }

        Log::info('Analytics source data cleanup completed', $stats);

        return $stats;
    }

    /**
     * Nettoie les données page_views après agrégation
     * Note: page_views is the source for migration-based aggregation
     *
     * @return int Number of records deleted
     */
    public function cleanupPageViewsData(Site $site): int
    {
        $retentionDays = config('analytics.retention.page_views', 7);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = DB::table('page_views')
            ->where('site_id', $site->id)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleanup page_views data for site {$site->id}: {$deletedCount} records deleted");
        }

        return $deletedCount;
    }

    /**
     * Nettoie les données events après agrégation
     *
     * @return int Number of records deleted
     */
    public function cleanupEventsData(Site $site): int
    {
        $retentionDays = config('analytics.retention.events', 7);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = DB::table('events')
            ->where('site_id', $site->id)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleanup events data for site {$site->id}: {$deletedCount} records deleted");
        }

        return $deletedCount;
    }

    /**
     * Nettoie les données horaires après agrégation quotidienne
     * Note: We clean up analytics_hourly after daily aggregation because
     * daily aggregation reads FROM analytics_hourly and writes TO analytics_daily
     */
    private function cleanupHourlyData(Site $site): void
    {
        $retentionDays = config('analytics.retention.hourly', 7);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = DB::table('analytics_hourly')
            ->where('site_id', $site->id)
            ->where('hour_start', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleanup hourly data for site {$site->id}: {$deletedCount} records deleted");
        }
    }

    /**
     * Nettoie les données quotidiennes après agrégation mensuelle
     * Note: We clean up analytics_daily after monthly aggregation because
     * monthly aggregation reads FROM analytics_daily and writes TO analytics_monthly
     */
    private function cleanupDailyData(Site $site): void
    {
        $retentionDays = config('analytics.retention.daily', 365);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = DB::table('analytics_daily')
            ->where('site_id', $site->id)
            ->where('date', '<', $cutoffDate->toDateString())
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleanup daily data for site {$site->id}: {$deletedCount} records deleted");
        }
    }

    /**
     * Nettoie les données mensuelles anciennes
     */
    public function cleanupMonthlyData(): void
    {
        $retentionDays = config('analytics.retention.monthly', 1825);
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $cutoffYearMonth = $cutoffDate->format('Y-m');

        $deletedCount = DB::table('analytics_monthly')
            ->where('year_month', '<', $cutoffYearMonth)
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleanup monthly data: {$deletedCount} records deleted");
        }
    }

    /**
     * Efface le cache analytics après agrégation
     */
    public function clearAnalyticsCache(): void
    {
        // Clear site-specific analytics cache
        $sites = Site::all();
        foreach ($sites as $site) {
            Cache::forget("site_{$site->id}_analytics_stats");
            Cache::forget("site_{$site->id}_analytics_chart");
            Cache::forget("site_{$site->id}_analytics_top_pages");
            Cache::forget("site_{$site->id}_analytics_top_referrers");
        }

        // Clear global analytics cache
        Cache::forget('global_analytics_summary');
        Cache::forget('dashboard_analytics_overview');
    }

    /**
     * Récupère les statistiques avec cache
     */
    public function getSiteStatsWithCache(Site $site, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        if (! $this->enableCache) {
            return $this->getSiteStats($site, $startDate, $endDate, $period);
        }

        $cacheKey = "site_{$site->id}_stats_{$period}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
        $cacheTtl = $this->getCacheTtl($period);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($site, $startDate, $endDate, $period) {
            return $this->getSiteStats($site, $startDate, $endDate, $period);
        });
    }

    /**
     * Récupère les données de graphique avec cache
     */
    public function getChartDataWithCache(Site $site, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        if (! $this->enableCache) {
            return $this->getChartData($site, $startDate, $endDate, $period);
        }

        $cacheKey = "site_{$site->id}_chart_{$period}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
        $cacheTtl = $this->getCacheTtl($period);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($site, $startDate, $endDate, $period) {
            return $this->getChartData($site, $startDate, $endDate, $period);
        });
    }

    /**
     * Détermine le TTL du cache selon la période
     */
    private function getCacheTtl(string $period): int
    {
        $cacheTtl = config('analytics.cache.ttl', [
            'hourly' => 300,
            'daily' => 1800,
            'monthly' => 3600,
        ]);

        return $cacheTtl[$period] ?? 1800;
    }

    /**
     * Récupère les statistiques du site
     */
    private function getSiteStats(Site $site, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        $table = $this->getAggregationTable($period);
        $dateColumn = $this->getDateColumn($period);

        $stats = DB::table($table)
            ->where('site_id', $site->id)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->selectRaw('
                SUM(page_views) as total_page_views,
                SUM(unique_visitors) as total_unique_visitors
            ')
            ->first();

        return [
            'total_page_views' => $stats->total_page_views ?? 0,
            'total_unique_visitors' => $stats->total_unique_visitors ?? 0,
        ];
    }

    /**
     * Récupère les données de graphique
     */
    private function getChartData(Site $site, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        $table = $this->getAggregationTable($period);
        $dateColumn = $this->getDateColumn($period);

        $data = DB::table($table)
            ->where('site_id', $site->id)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->select($dateColumn, 'page_views', 'unique_visitors')
            ->orderBy($dateColumn)
            ->get();

        return [
            'labels' => $data->pluck($dateColumn)->map(fn ($date) => $this->formatDate($date, $period))->toArray(),
            'page_views' => $data->pluck('page_views')->toArray(),
            'unique_visitors' => $data->pluck('unique_visitors')->toArray(),
        ];
    }

    /**
     * Détermine la table d'agrégation selon la période
     */
    private function getAggregationTable(string $period): string
    {
        return match ($period) {
            'hourly' => 'analytics_hourly',
            'daily' => 'analytics_daily',
            'monthly' => 'analytics_monthly',
            default => 'analytics_daily',
        };
    }

    /**
     * Détermine la colonne de date selon la période
     */
    private function getDateColumn(string $period): string
    {
        return match ($period) {
            'hourly' => 'hour_start',
            'daily' => 'date',
            'monthly' => 'year_month',
            default => 'date',
        };
    }

    /**
     * Formate la date selon la période
     */
    private function formatDate($date, string $period): string
    {
        return match ($period) {
            'hourly' => Carbon::parse($date)->format('H:i'),
            'daily' => Carbon::parse($date)->format('M j'),
            'monthly' => Carbon::parse($date)->format('M Y'),
            default => Carbon::parse($date)->format('M j'),
        };
    }
}
