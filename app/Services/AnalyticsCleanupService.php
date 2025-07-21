<?php

namespace App\Services;

use App\Models\PageView;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsCleanupService
{
    /**
     * Nettoie les anciennes données après agrégation
     * Garde seulement la semaine courante
     */
    public function cleanupOldData(): array
    {
        $stats = [
            'page_views_deleted' => 0,
            'events_deleted' => 0,
            'sites_processed' => 0,
        ];

        // Date limite : début de la semaine courante
        $currentWeekStart = Carbon::now()->startOfWeek();
        
        Log::info('AnalyticsCleanupService: Starting cleanup', [
            'current_week_start' => $currentWeekStart->toDateTimeString(),
        ]);

        // Récupérer tous les sites
        $sites = \App\Models\Site::all();
        
        foreach ($sites as $site) {
            $this->cleanupSiteData($site, $currentWeekStart, $stats);
            $stats['sites_processed']++;
        }

        Log::info('AnalyticsCleanupService: Cleanup completed', $stats);
        
        return $stats;
    }

    /**
     * Nettoie les données d'un site spécifique
     */
    private function cleanupSiteData($site, Carbon $currentWeekStart, array &$stats): void
    {
        // Supprimer les page views anciennes
        $deletedPageViews = PageView::where('site_id', $site->id)
            ->where('created_at', '<', $currentWeekStart)
            ->delete();
        
        $stats['page_views_deleted'] += $deletedPageViews;

        // Supprimer les events anciens
        $deletedEvents = Event::where('site_id', $site->id)
            ->where('created_at', '<', $currentWeekStart)
            ->delete();
        
        $stats['events_deleted'] += $deletedEvents;

        Log::info('AnalyticsCleanupService: Site cleanup completed', [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'page_views_deleted' => $deletedPageViews,
            'events_deleted' => $deletedEvents,
        ]);
    }

    /**
     * Nettoie les données d'un site spécifique (méthode publique)
     */
    public function cleanupSite($site): array
    {
        $stats = [
            'page_views_deleted' => 0,
            'events_deleted' => 0,
        ];

        $currentWeekStart = Carbon::now()->startOfWeek();
        $this->cleanupSiteData($site, $currentWeekStart, $stats);

        return $stats;
    }
} 