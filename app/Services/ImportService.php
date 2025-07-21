<?php

namespace App\Services;

use App\Jobs\ProcessUmamiImport;
use App\Models\ImportHistory;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    public function __construct() {}

    public function importUmamiFromFtp(string $fileName, bool $dryRun = false): array
    {
        $filePath = 'imports/'.$fileName;
        $fullPath = storage_path('app/'.$filePath);

        // Debug pour voir ce qui se passe
        \Log::info('FTP import attempt', [
            'fileName' => $fileName,
            'filePath' => $filePath,
            'fullPath' => $fullPath,
            'storage_exists' => Storage::exists($filePath),
            'file_exists' => file_exists($fullPath),
            'is_file' => is_file($fullPath),
        ]);

        if (! file_exists($fullPath)) {
            return [
                'success' => false,
                'message' => 'File not found in uploads directory: '.$fileName,
            ];
        }

        $fileSize = filesize($fullPath);

        // Créer un enregistrement d'historique
        $importHistory = ImportHistory::create([
            'user_id' => auth()->id(),
            'site_id' => null, // Sera mis à jour après l'analyse
            'site_name' => 'Multiple sites (auto-detected)',
            'status' => 'processing',
            'type' => 'umami',
            'file_name' => $fileName,
            'details' => [
                'file_size' => $fileSize,
                'file_size_formatted' => $this->formatBytes($fileSize),
                'file_extension' => pathinfo($fileName, PATHINFO_EXTENSION),
                'is_compressed' => pathinfo($fileName, PATHINFO_EXTENSION) === 'gz',
                'upload_method' => 'ftp',
            ],
        ]);

        try {
            // Créer le service avec l'ID utilisateur
            $umamiService = new UmamiImportService(auth()->id());

            // Analyser rapidement le fichier pour l'estimation
            $quickStats = $umamiService->analyzeDump($fullPath);

            // Mettre à jour avec les stats initiales et sites trouvés
            $importHistory->update([
                'details' => array_merge(
                    $importHistory->details ?? [],
                    [
                        'estimated_records' => $quickStats['page_views'] + $quickStats['events'],
                        'estimated_duration' => $quickStats['estimated_duration'],
                        'initial_analysis' => $quickStats,
                        'websites_found' => $quickStats['websites_found'],
                        'websites_count' => count($quickStats['websites_found']),
                    ]
                ),
            ]);

            // Décider si on traite en synchrone ou asynchrone
            $shouldUseQueue = $this->shouldUseQueue($fileSize, $quickStats);

            if ($shouldUseQueue) {
                // Traitement asynchrone pour les gros fichiers
                ProcessUmamiImport::dispatch(
                    $importHistory->id,
                    $filePath,
                    auth()->id(), // ID utilisateur
                    null, // Pas de site spécifique
                    $dryRun
                );

                return [
                    'success' => true,
                    'message' => 'Import started in background. You will be notified when complete.',
                    'job_queued' => true,
                    'estimated_duration' => $quickStats['estimated_duration'],
                    'stats' => $quickStats,
                ];
            } else {
                // Traitement synchrone pour les petits fichiers
                if ($dryRun) {
                    $stats = $umamiService->analyzeDump($fullPath);

                    $importHistory->update([
                        'status' => 'completed',
                        'summary' => "Dry run: {$stats['page_views']} page views, {$stats['events']} events found",
                        'details' => array_merge($importHistory->details ?? [], $stats),
                    ]);

                    return [
                        'success' => true,
                        'message' => "Dry run completed. Found {$stats['page_views']} page views and {$stats['events']} events.",
                        'stats' => $stats,
                    ];
                } else {
                    // Import réel synchrone
                    $importedStats = $umamiService->importData($fullPath);

                    $importHistory->update([
                        'status' => 'completed',
                        'summary' => "Imported {$importedStats['page_views']} page views and {$importedStats['events']} events for {$importedStats['websites_created']} new sites",
                        'details' => array_merge($importHistory->details ?? [], $importedStats),
                    ]);

                    return [
                        'success' => true,
                        'message' => "Successfully imported {$importedStats['page_views']} page views and {$importedStats['events']} events for {$importedStats['websites_created']} new sites.",
                        'stats' => $importedStats,
                    ];
                }
            }

        } catch (\Exception $e) {
            $importHistory->update([
                'status' => 'failed',
                'summary' => 'Import failed: '.$e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ];
        }
    }

    private function shouldUseQueue(int $fileSize, array $stats): bool
    {
        // Utiliser la queue si :
        // - Fichier > 50MB
        // - Plus de 100k enregistrements
        // - Temps estimé > 5 minutes
        return $fileSize > 50 * 1024 * 1024 ||
               ($stats['page_views'] + $stats['events']) > 100000 ||
               $stats['estimated_duration'] > 300;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 Bytes';
        }
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2).' '.$sizes[$i];
    }

    public function getImportHistory(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return ImportHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
