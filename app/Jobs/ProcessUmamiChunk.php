<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\UmamiImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUmamiChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public $tries = 3;

    public function __construct(
        private int $importId,
        private int $userId,
        private string $filePath,
        private int $startLine,
        private int $endLine,
        private array $sitesMap
    ) {}

    public function handle(): void
    {
        Log::info('Processing Umami chunk', [
            'import_id' => $this->importId,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'lines_count' => $this->endLine - $this->startLine + 1,
        ]);

        try {
            $service = new UmamiImportService($this->userId);
            $stats = $service->processChunk($this->filePath, $this->startLine, $this->endLine, $this->sitesMap);

            // Mettre à jour l'import history avec les statistiques
            $import = ImportHistory::find($this->importId);
            if ($import) {
                $currentDetails = $import->details ?? [];
                $currentDetails['page_views_imported'] = ($currentDetails['page_views_imported'] ?? 0) + ($stats['page_views'] ?? 0);
                $currentDetails['events_imported'] = ($currentDetails['events_imported'] ?? 0) + ($stats['events'] ?? 0);
                $currentDetails['chunks_processed'] = ($currentDetails['chunks_processed'] ?? 0) + 1;

                // Calculer le pourcentage de progression
                $totalChunks = $currentDetails['total_chunks'] ?? 1;
                $progress = round(($currentDetails['chunks_processed'] / $totalChunks) * 100, 1);

                $import->update([
                    'details' => $currentDetails,
                    'summary' => "Processing... {$progress}% complete - {$currentDetails['page_views_imported']} page views, {$currentDetails['events_imported']} events",
                ]);
            }

            Log::info('Umami chunk processed successfully', [
                'import_id' => $this->importId,
                'page_views_imported' => $stats['page_views_imported'] ?? 0,
                'events_imported' => $stats['events_imported'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Umami chunk processing failed', [
                'import_id' => $this->importId,
                'start_line' => $this->startLine,
                'end_line' => $this->endLine,
                'error' => $e->getMessage(),
            ]);

            // Marquer l'import comme échoué
            $import = ImportHistory::find($this->importId);
            if ($import) {
                $import->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
