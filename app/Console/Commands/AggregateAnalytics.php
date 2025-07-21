<?php

namespace App\Console\Commands;

use App\Services\AnalyticsAggregationService;
use Illuminate\Console\Command;

class AggregateAnalytics extends Command
{
    protected $signature = 'analytics:aggregate {type=all : hourly|daily|monthly|all}';
    protected $description = 'Agrège les données analytics selon le type spécifié';

    public function handle(AnalyticsAggregationService $service): int
    {
        $type = $this->argument('type') ?? 'all';
        
        $this->info("Début de l'agrégation {$type}...");
        
        try {
            switch ($type) {
                case 'all':
                    $service->aggregateHourly();
                    $this->info('✅ Agrégation horaire terminée');
                    $service->aggregateDaily();
                    $this->info('✅ Agrégation quotidienne terminée');
                    $service->aggregateMonthly();
                    $this->info('✅ Agrégation mensuelle terminée');
                    break;
                    
                case 'hourly':
                    $service->aggregateHourly();
                    $this->info('✅ Agrégation horaire terminée');
                    break;
                    
                case 'daily':
                    $service->aggregateDaily();
                    $this->info('✅ Agrégation quotidienne terminée');
                    break;
                    
                case 'monthly':
                    $service->aggregateMonthly();
                    $this->info('✅ Agrégation mensuelle terminée');
                    break;
                    
                default:
                    $this->error("Type d'agrégation invalide: {$type}");
                    return 1;
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'agrégation: " . $e->getMessage());
            return 1;
        }
    }
} 