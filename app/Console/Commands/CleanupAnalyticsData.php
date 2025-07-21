<?php

namespace App\Console\Commands;

use App\Services\AnalyticsCleanupService;
use Illuminate\Console\Command;

class CleanupAnalyticsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:cleanup {--site= : Clean specific site by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old analytics data, keeping only current week';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsCleanupService $cleanupService)
    {
        $this->info('🧹 Starting analytics data cleanup...');

        if ($siteId = $this->option('site')) {
            // Nettoyer un site spécifique
            $site = \App\Models\Site::find($siteId);
            if (!$site) {
                $this->error("Site with ID {$siteId} not found.");
                return 1;
            }

            $stats = $cleanupService->cleanupSite($site);
            
            $this->info("✅ Cleanup completed for site: {$site->name}");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Page Views Deleted', $stats['page_views_deleted']],
                    ['Events Deleted', $stats['events_deleted']],
                ]
            );
        } else {
            // Nettoyer tous les sites
            $stats = $cleanupService->cleanupOldData();
            
            $this->info('✅ Cleanup completed for all sites');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Sites Processed', $stats['sites_processed']],
                    ['Page Views Deleted', $stats['page_views_deleted']],
                    ['Events Deleted', $stats['events_deleted']],
                ]
            );
        }

        return 0;
    }
}
