<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckAnalyticsJobs extends Command
{
    protected $signature = 'analytics:jobs {--failed : Show only failed jobs} {--recent : Show recent logs}';

    protected $description = 'Check the status of analytics aggregation jobs';

    public function handle(): int
    {
        if ($this->option('failed')) {
            return $this->showFailedJobs();
        }

        if ($this->option('recent')) {
            return $this->showRecentLogs();
        }

        return $this->showJobStatus();
    }

    private function showJobStatus(): int
    {
        $this->info('📊 Status des jobs d\'agrégation analytics');

        // Check failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'analytics')
            ->orWhere('payload', 'like', '%ProcessAnalyticsAggregation%')
            ->count();

        if ($failedJobs > 0) {
            $this->warn("⚠️  {$failedJobs} jobs échoués trouvés");
        } else {
            $this->info('✅ Aucun job échoué');
        }

        // Check pending jobs
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'analytics')
            ->orWhere('payload', 'like', '%ProcessAnalyticsAggregation%')
            ->count();

        if ($pendingJobs > 0) {
            $this->info("⏳ {$pendingJobs} jobs en attente");
        } else {
            $this->info('✅ Aucun job en attente');
        }

        $this->newLine();
        $this->info('💡 Commandes utiles:');
        $this->line('  php artisan analytics:jobs --failed    # Voir les jobs échoués');
        $this->line('  php artisan analytics:jobs --recent     # Voir les logs récents');
        $this->line('  php artisan queue:work --queue=analytics # Traiter les jobs');
        $this->line('  php artisan queue:failed               # Voir tous les jobs échoués');

        return 0;
    }

    private function showFailedJobs(): int
    {
        $this->info('❌ Jobs d\'agrégation échoués:');

        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'analytics')
            ->orWhere('payload', 'like', '%ProcessAnalyticsAggregation%')
            ->orderBy('failed_at', 'desc')
            ->limit(10)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('✅ Aucun job échoué trouvé');

            return 0;
        }

        foreach ($failedJobs as $job) {
            $this->error("Job ID: {$job->id}");
            $this->line("Queue: {$job->queue}");
            $this->line("Failed at: {$job->failed_at}");
            $this->line("Exception: {$job->exception}");
            $this->newLine();
        }

        $this->info('💡 Pour retenter un job:');
        $this->line('  php artisan queue:retry <job_id>');

        return 0;
    }

    private function showRecentLogs(): int
    {
        $this->info('📋 Logs récents d\'agrégation:');

        // This is a simplified version - in production you might want to use a proper log parser
        $this->line('💡 Pour voir les logs complets:');
        $this->line('  tail -f storage/logs/laravel.log | grep -i "analytics"');
        $this->line('  tail -f storage/logs/laravel.log | grep -i "aggregation"');

        return 0;
    }
}
