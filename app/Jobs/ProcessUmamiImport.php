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
use Illuminate\Support\Facades\Storage;

class ProcessUmamiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 heure

    public $tries = 3;

    public $backoff = [60, 300, 600]; // Retry delays

    public function __construct(
        private int $importHistoryId,
        private string $filePath,
        private int $userId,
        private ?int $siteId = null,
        private bool $dryRun = false
    ) {}

    public function handle(): void
    {
        $importHistory = ImportHistory::findOrFail($this->importHistoryId);

        // Créer le service avec l'ID utilisateur
        $umamiService = new UmamiImportService($this->userId);

        try {
            // Mettre à jour le statut
            $importHistory->update(['status' => 'processing']);

            // Convertir le chemin relatif en chemin absolu
            $fullPath = storage_path('app/'.$this->filePath);

            // Debug logging
            Log::info('ProcessUmamiImport: Starting import', [
                'import_id' => $this->importHistoryId,
                'relative_path' => $this->filePath,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'is_readable' => is_readable($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 'N/A',
            ]);

            if (! file_exists($fullPath)) {
                throw new \Exception("File not found: {$fullPath}");
            }

            if (! is_readable($fullPath)) {
                throw new \Exception("File not readable: {$fullPath}");
            }

            if ($this->dryRun) {
                // Analyse seulement
                $stats = $umamiService->analyzeDump($fullPath);

                $importHistory->update([
                    'status' => 'completed',
                    'summary' => "Dry run: {$stats['page_views']} page views, {$stats['events']} events found",
                    'details' => $stats,
                ]);
            } else {
                // Première passe : traiter les sites
                $stats = [];
                $sitesMap = $umamiService->processWebsites($fullPath, $stats);

                // Détecter les positions des INSERT website_event
                $insertPositions = $this->findWebsiteEventInserts($fullPath);
                $chunks = count($insertPositions);

                Log::info('Found website_event INSERT positions', [
                    'insert_count' => $chunks,
                    'positions' => array_slice($insertPositions, 0, 5), // Log les 5 premiers
                ]);

                // Créer un chunk pour chaque INSERT
                foreach ($insertPositions as $index => $position) {
                    $startLine = $position['start'];
                    $endLine = $position['end'];

                    ProcessUmamiChunk::dispatch(
                        $this->importHistoryId,
                        $this->userId,
                        $fullPath,
                        $startLine,
                        $endLine,
                        $sitesMap
                    )->delay(now()->addSeconds($index * 2)); // Délai progressif pour éviter la surcharge
                }

                // Marquer l'import comme en cours de traitement
                $importHistory->update([
                    'status' => 'processing_chunks',
                    'summary' => "Processing {$chunks} chunks...",
                    'details' => [
                        'total_chunks' => $chunks,
                        'chunks_processed' => 0,
                        'page_views_imported' => 0,
                        'events_imported' => 0,
                    ],
                ]);
            }

            // Marquer l'import comme terminé avec succès
            $importHistory->update([
                'status' => 'completed',
                'summary' => "Import completed - {$chunks} chunks dispatched",
                'details' => [
                    'total_chunks' => $chunks,
                    'chunks_processed' => 0, // Sera mis à jour par les chunks
                    'page_views_imported' => 0, // Sera mis à jour par les chunks
                    'events_imported' => 0, // Sera mis à jour par les chunks
                    'file_size_mb' => file_exists($fullPath) ? round(filesize($fullPath) / 1024 / 1024, 2) : 0,
                    'processing_started_at' => now()->toDateTimeString(),
                ],
            ]);

            // Nettoyer le fichier temporaire
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }

        } catch (\Exception $e) {
            Log::error('Umami import failed', [
                'import_id' => $this->importHistoryId,
                'relative_path' => $this->filePath,
                'full_path' => $fullPath ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $importHistory->update([
                'status' => 'failed',
                'summary' => 'Import failed: '.$e->getMessage(),
            ]);

            // Nettoyer le fichier en cas d'erreur
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $importHistory = ImportHistory::find($this->importHistoryId);
        if ($importHistory) {
            $importHistory->update([
                'status' => 'failed',
                'summary' => 'Job failed: '.$exception->getMessage(),
            ]);
        }

        // Nettoyer le fichier
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
        }
    }

    private function findWebsiteEventInserts(string $filePath): array
    {
        // Utiliser grep pour trouver rapidement les lignes INSERT
        $command = "gunzip -c '{$filePath}' | grep -n 'INSERT INTO \`website_event\`'";
        $output = shell_exec($command);

        if (! $output) {
            Log::warning('No website_event INSERT found with grep');

            return [];
        }

        $lines = explode("\n", trim($output));
        $positions = [];

        // Créer des chunks de 10 INSERT à la fois pour éviter trop de jobs
        $chunkSize = 10;
        $insertPositions = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d+):/', $line, $matches)) {
                $insertPositions[] = (int) $matches[1];
            }
        }

        // Diviser en chunks de $chunkSize INSERT
        $chunks = array_chunk($insertPositions, $chunkSize);

        foreach ($chunks as $chunk) {
            $startLine = $chunk[0];
            $endLine = end($chunk) + 5000; // Estimation: chaque INSERT fait ~5000 lignes

            $positions[] = [
                'start' => $startLine,
                'end' => $endLine,
            ];
        }

        Log::info('Detected website_event INSERT positions with grep', [
            'total_inserts' => count($positions),
            'first_insert' => $positions[0] ?? null,
            'last_insert' => end($positions) ?: null,
        ]);

        return $positions;
    }
}
