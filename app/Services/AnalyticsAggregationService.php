<?php

namespace App\Services;

use App\Helpers\DatabaseHelper;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsAggregationService
{
    /**
     * Agrège les données horaires depuis la table temps réel
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
                Log::error("Erreur agrégation horaire pour site {$site->id}: " . $e->getMessage());
            }
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
                Log::error("Erreur agrégation quotidienne pour site {$site->id}: " . $e->getMessage());
            }
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
                Log::error("Erreur agrégation mensuelle pour site {$site->id}: " . $e->getMessage());
            }
        }
    }

    private function aggregateSiteHourly(Site $site, Carbon $hourStart): void
    {
        // Compter les page views et visiteurs uniques
        $stats = DB::table('analytics_realtime')
            ->where('site_id', $site->id)
            ->whereBetween('created_at', [$hourStart, $hourStart->copy()->addHour()])
            ->selectRaw('
                COUNT(*) as page_views,
                COUNT(DISTINCT session_id) as unique_visitors
            ')
            ->first();

        // Top pages de l'heure
        $topPages = DB::table('analytics_realtime')
            ->where('site_id', $site->id)
            ->whereBetween('created_at', [$hourStart, $hourStart->copy()->addHour()])
            ->selectRaw('page_url, COUNT(*) as count')
            ->groupBy('page_url')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        // Top referrers
        $topReferrers = DB::table('analytics_realtime')
            ->where('site_id', $site->id)
            ->whereBetween('created_at', [$hourStart, $hourStart->copy()->addHour()])
            ->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as count')
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        // Insérer ou mettre à jour l'agrégation horaire
        DB::table('analytics_hourly')->updateOrInsert(
            ['site_id' => $site->id, 'hour_start' => $hourStart],
            [
                'page_views' => $stats->page_views,
                'unique_visitors' => $stats->unique_visitors,
                'top_pages' => json_encode($topPages),
                'referrers' => json_encode($topReferrers),
                'updated_at' => now(),
            ]
        );

        // Nettoyer les données temps réel de plus de 24h
        DB::table('analytics_realtime')
            ->where('site_id', $site->id)
            ->where('created_at', '<', Carbon::now()->subDay())
            ->delete();
    }

    private function aggregateSiteDaily(Site $site, Carbon $date): void
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
            ->map(fn($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('page_url')
            ->map(fn($group) => ['page_url' => $group->first()['page_url'], 'count' => $group->sum('count')])
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
            ->map(fn($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('referrer')
            ->map(fn($group) => ['referrer' => $group->first()['referrer'], 'count' => $group->sum('count')])
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

    private function aggregateSiteMonthly(Site $site, string $yearMonth): void
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
            ->map(fn($json) => json_decode($json, true))
            ->flatten(1)
            ->groupBy('page_url')
            ->map(fn($group) => ['page_url' => $group->first()['page_url'], 'count' => $group->sum('count')])
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
} 