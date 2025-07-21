<?php

namespace App\Console\Commands;

use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAnalyticsData extends Command
{
    protected $signature = 'analytics:migrate {--days=30 : Nombre de jours à migrer}';

    protected $description = 'Migre les données analytics existantes vers les tables d\'agrégation';

    public function handle(): int
    {
        $days = $this->option('days');
        $this->info("Migration des données analytics des {$days} derniers jours...");

        $sites = Site::all();

        foreach ($sites as $site) {
            $this->info("Migration pour le site: {$site->name}");

            try {
                $this->migrateSiteData($site, $days);
                $this->info("✅ Site {$site->name} migré avec succès");
            } catch (\Exception $e) {
                $this->error("❌ Erreur pour le site {$site->name}: ".$e->getMessage());
            }
        }

        $this->info('Migration terminée !');

        return 0;
    }

    private function migrateSiteData(Site $site, int $days): void
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Migration des données quotidiennes
        $this->migrateDailyData($site, $startDate, $endDate);

        // Migration des données horaires (7 derniers jours)
        $hourlyStartDate = Carbon::now()->subDays(7)->startOfDay();
        $this->migrateHourlyData($site, $hourlyStartDate, $endDate);
    }

    private function migrateDailyData(Site $site, Carbon $startDate, Carbon $endDate): void
    {
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $date = $current->toDateString();

            // Vérifier si les données existent déjà
            $exists = DB::table('analytics_daily')
                ->where('site_id', $site->id)
                ->where('date', $date)
                ->exists();

            if ($exists) {
                $current->addDay();

                continue;
            }

            // Récupérer les données de la journée
            $pageViews = $site->pageViews()
                ->whereDate('created_at', $current)
                ->get();

            $totalPageViews = $pageViews->count();
            $uniqueVisitors = $pageViews->unique('session_id')->count();

            // Top pages du jour
            $topPages = $pageViews->groupBy('url')
                ->map(fn ($group) => ['page_url' => $group->first()->url, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(20)
                ->values()
                ->toArray();

            // Top referrers du jour
            $topReferrers = $pageViews->whereNotNull('referrer')
                ->groupBy('referrer')
                ->map(fn ($group) => ['referrer' => $group->first()->referrer, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(20)
                ->values()
                ->toArray();

            // Insérer les données agrégées
            DB::table('analytics_daily')->insert([
                'site_id' => $site->id,
                'date' => $date,
                'page_views' => $totalPageViews,
                'unique_visitors' => $uniqueVisitors,
                'top_pages' => json_encode($topPages),
                'referrers' => json_encode($topReferrers),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current->addDay();
        }
    }

    private function migrateHourlyData(Site $site, Carbon $startDate, Carbon $endDate): void
    {
        $current = $startDate->copy()->startOfHour();

        while ($current <= $endDate) {
            $hourStart = $current->copy();
            $hourEnd = $current->copy()->endOfHour();

            // Vérifier si les données existent déjà
            $exists = DB::table('analytics_hourly')
                ->where('site_id', $site->id)
                ->where('hour_start', $hourStart)
                ->exists();

            if ($exists) {
                $current->addHour();

                continue;
            }

            // Récupérer les données de l'heure
            $pageViews = $site->pageViews()
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->get();

            $totalPageViews = $pageViews->count();
            $uniqueVisitors = $pageViews->unique('session_id')->count();

            // Top pages de l'heure
            $topPages = $pageViews->groupBy('url')
                ->map(fn ($group) => ['page_url' => $group->first()->url, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(10)
                ->values()
                ->toArray();

            // Top referrers de l'heure
            $topReferrers = $pageViews->whereNotNull('referrer')
                ->groupBy('referrer')
                ->map(fn ($group) => ['referrer' => $group->first()->referrer, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(10)
                ->values()
                ->toArray();

            // Insérer les données agrégées
            DB::table('analytics_hourly')->insert([
                'site_id' => $site->id,
                'hour_start' => $hourStart,
                'page_views' => $totalPageViews,
                'unique_visitors' => $uniqueVisitors,
                'top_pages' => json_encode($topPages),
                'referrers' => json_encode($topReferrers),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current->addHour();
        }
    }
}
