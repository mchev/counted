<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UmamiImportService
{
    private const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
    private const BATCH_SIZE = 1000; // Nombre d'insertions par batch
    private int $userId;
    private UserAgentParserService $userAgentParser;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->userAgentParser = new UserAgentParserService();
    }

    public function analyzeDump(string $filePath): array
    {
        $stats = [
            'page_views' => 0,
            'events' => 0,
            'websites' => 0,
            'sessions' => 0,
            'file_size' => filesize($filePath),
            'estimated_duration' => 0,
            'compressed' => $this->isGzipped($filePath),
            'websites_found' => [],
        ];

        $handle = $this->openFile($filePath);
        if (!$handle) {
            throw new \Exception('Cannot open file for analysis');
        }

        $lineCount = 0;
        while (($line = $this->readLine($handle)) !== false) {
            $lineCount++;
            
            if (str_contains(strtolower($line), 'insert into `website`')) {
                $stats['websites']++;
                $websiteData = $this->extractWebsiteData($line);
                if ($websiteData) {
                    $stats['websites_found'][] = $websiteData;
                }
            } elseif (str_contains(strtolower($line), 'insert into `session`')) {
                $stats['sessions']++;
            } elseif (str_contains(strtolower($line), 'insert into `pageview`')) {
                $stats['page_views']++;
            } elseif (str_contains(strtolower($line), 'insert into `event`')) {
                $stats['events']++;
            }

            // Limiter l'analyse pour les très gros fichiers
            if ($lineCount > 100000) {
                $stats['estimated_duration'] = $this->estimateProcessingTime($stats);
                break;
            }
        }

        $this->closeFile($handle);
        $stats['estimated_duration'] = $this->estimateProcessingTime($stats);
        
        return $stats;
    }

    public function importData(string $filePath, callable $progressCallback = null): array
    {
        // Debug: vérifier le fichier
        Log::info('UmamiImportService: Starting import', [
            'filePath' => $filePath,
            'file_exists' => file_exists($filePath),
            'is_file' => is_file($filePath),
            'file_size' => file_exists($filePath) ? filesize($filePath) : 'N/A',
        ]);

        $importedStats = [
            'page_views' => 0,
            'events' => 0,
            'sessions' => 0,
            'websites_created' => 0,
            'websites_updated' => 0,
            'batches_processed' => 0,
            'errors' => 0,
        ];

        $handle = $this->openFile($filePath);
        if (!$handle) {
            throw new \Exception('Cannot open file for import: ' . $filePath);
        }

        // Première passe : extraire et créer/mettre à jour les sites
        $sitesMap = $this->processWebsites($filePath, $importedStats);
        
        // Deuxième passe : importer les données
        $this->processData($filePath, $sitesMap, $importedStats, $progressCallback);

        $this->closeFile($handle);

        return $importedStats;
    }

    public function processChunk(string $filePath, int $startLine, int $endLine, array $sitesMap): array
    {
        $stats = [
            'page_views' => 0,
            'events' => 0,
            'errors' => 0,
            'batches_processed' => 0,
        ];

        // Parser les sessions pour ce chunk
        $sessionsMap = $this->processSessions($filePath, $startLine, $endLine, $sitesMap);

        // Traiter seulement les lignes du chunk spécifié
        $this->processDataChunk($filePath, $startLine, $endLine, $sitesMap, $sessionsMap, $stats);

        Log::info('UmamiImportService: Chunk processing complete', [
            'start_line' => $startLine,
            'end_line' => $endLine,
            'sessions_count' => count($sessionsMap),
            'stats' => $stats,
        ]);

        return $stats;
    }

    public function importFromJson(string $jsonPath): void
    {
        if (!file_exists($jsonPath)) {
            throw new \Exception("JSON file not found: {$jsonPath}");
        }

        $websites = json_decode(file_get_contents($jsonPath), true);
        if (!$websites) {
            throw new \Exception("Invalid JSON file: {$jsonPath}");
        }

        $sitesCreated = 0;
        $sitesUpdated = 0;

        foreach ($websites as $website) {
            $existingSite = Site::where('name', $website['name'])->first();
            
            if ($existingSite) {
                // Mettre à jour le site existant
                $existingSite->update([
                    'domain' => $website['domain'],
                    'tracking_id' => $website['share_id'] ?? $this->generateTrackingId(),
                ]);
                $sitesUpdated++;
                Log::info('Updated existing site from JSON', ['site_id' => $existingSite->id, 'name' => $website['name']]);
            } else {
                // Créer un nouveau site
                $site = Site::create([
                    'user_id' => $this->userId,
                    'name' => $website['name'],
                    'domain' => $website['domain'],
                    'tracking_id' => $website['share_id'] ?? $this->generateTrackingId(),
                ]);
                $sitesCreated++;
                Log::info('Created new site from JSON', ['site_id' => $site->id, 'name' => $website['name']]);
            }
        }

        Log::info('JSON import complete', ['sites_created' => $sitesCreated, 'sites_updated' => $sitesUpdated]);
    }

    public function processWebsites(string $filePath, array &$stats): array
    {
        $sitesMap = []; // website_id => Site model
        $handle = $this->openFile($filePath);
        $lineCount = 0;
        $websiteLines = 0;
        $currentWebsiteData = '';
        $inWebsiteInsert = false;
        
        // Initialiser les statistiques si elles n'existent pas
        if (!isset($stats['sites_created'])) $stats['sites_created'] = 0;
        if (!isset($stats['sites_updated'])) $stats['sites_updated'] = 0;
        
        Log::info('UmamiImportService: Processing websites');
        
        while (($line = $this->readLine($handle)) !== false) {
            $lineCount++;
            
            if (str_contains(strtolower($line), 'insert into `website`')) {
                $websiteLines++;
                $inWebsiteInsert = true;
                $currentWebsiteData = $line;
                Log::info('Found website line start', ['line_number' => $lineCount]);
            } elseif ($inWebsiteInsert) {
                $currentWebsiteData .= ' ' . trim($line);
                
                // Vérifier si c'est la fin de l'INSERT (contient des parenthèses fermantes)
                if (str_contains($line, ');')) {
                    $websitesData = $this->extractWebsiteData($currentWebsiteData);
                    if (!empty($websitesData)) {
                        Log::info('Extracted websites data', ['websites_count' => count($websitesData)]);
                        
                        foreach ($websitesData as $websiteData) {
                            $site = $this->findOrCreateSite($websiteData);
                            $sitesMap[$websiteData['id']] = $site;
                            
                            if ($site->wasRecentlyCreated) {
                                $stats['sites_created']++;
                                Log::info('Created new site', ['site_id' => $site->id, 'name' => $site->name]);
                            } else {
                                $stats['sites_updated']++;
                                Log::info('Updated existing site', ['site_id' => $site->id, 'name' => $site->name]);
                            }
                        }
                    } else {
                        Log::warning('Failed to extract website data', ['data' => substr($currentWebsiteData, 0, 200)]);
                    }
                    
                    $inWebsiteInsert = false;
                    $currentWebsiteData = '';
                }
            }
        }
        
        Log::info('UmamiImportService: Website processing complete', [
            'total_lines' => $lineCount,
            'website_lines' => $websiteLines,
            'sites_found' => count($sitesMap),
            'sites_created' => $stats['sites_created'],
            'sites_updated' => $stats['sites_updated'],
        ]);
        
        $this->closeFile($handle);
        return $sitesMap;
    }

    private function processData(string $filePath, array $sitesMap, array &$stats, callable $progressCallback = null): void
    {
        $handle = $this->openFile($filePath);
        $pageViewBatch = [];
        $eventBatch = [];
        $lineCount = 0;
        $pageviewLines = 0;
        $eventLines = 0;
        $currentEventData = '';
        $inEventInsert = false;

        Log::info('UmamiImportService: Processing data', [
            'sites_count' => count($sitesMap),
        ]);

        while (($line = $this->readLine($handle)) !== false) {
            $lineCount++;
            
            // Traiter les website_events (contient pageviews et events)
            if (str_contains(strtolower($line), 'insert into `website_event`')) {
                $eventLines++;
                $inEventInsert = true;
                $currentEventData = $line;
                Log::debug('Found website_event line start', ['line_number' => $lineCount]);
            } elseif ($inEventInsert) {
                $currentEventData .= ' ' . trim($line);
                
                // Vérifier si c'est la fin de l'INSERT (contient des parenthèses fermantes)
                if (str_contains($line, ');')) {
                    $eventsData = $this->extractWebsiteEventData($currentEventData, $sitesMap);
                    if (!empty($eventsData)) {
                        Log::info('Extracted website_event data', ['events_count' => count($eventsData)]);
                        
                        foreach ($eventsData as $data) {
                            // Séparer les pageviews des events
                            if ($data['event_type'] === '1' || $data['event_type'] === 1) {
                                // event_type = 1 signifie pageview dans Umami
                                $pageViewBatch[] = $this->convertToPageView($data);
                                $pageviewLines++;
                            } elseif ($data['event_type'] === '2' || $data['event_type'] === 2) {
                                // event_type = 2 signifie event dans Umami
                                $eventBatch[] = $this->convertToEvent($data);
                            }
                        }
                    } else {
                        Log::warning('Failed to extract website_event data', ['data' => substr($currentEventData, 0, 200)]);
                    }
                    
                    $inEventInsert = false;
                    $currentEventData = '';
                }
            }

            // Insérer par batch
            if (count($pageViewBatch) >= self::BATCH_SIZE) {
                $this->insertBatch('page_views', $pageViewBatch, $stats);
                $pageViewBatch = [];
                $stats['batches_processed']++;
            }

            if (count($eventBatch) >= self::BATCH_SIZE) {
                $this->insertBatch('events', $eventBatch, $stats);
                $eventBatch = [];
                $stats['batches_processed']++;
            }

            // Callback de progression
            if ($progressCallback && $lineCount % 10000 === 0) {
                $progressCallback([
                    'lines_processed' => $lineCount,
                    'page_views' => $stats['page_views'],
                    'events' => $stats['events'],
                    'batches' => $stats['batches_processed'],
                ]);
            }
        }

        // Insérer les derniers batches
        if (!empty($pageViewBatch)) {
            $this->insertBatch('page_views', $pageViewBatch, $stats);
            $stats['batches_processed']++;
        }
        if (!empty($eventBatch)) {
            $this->insertBatch('events', $eventBatch, $stats);
            $stats['batches_processed']++;
        }

        Log::info('UmamiImportService: Data processing complete', [
            'total_lines' => $lineCount,
            'pageview_lines' => $pageviewLines,
            'event_lines' => $eventLines,
            'page_views_imported' => $stats['page_views'],
            'events_imported' => $stats['events'],
            'batches_processed' => $stats['batches_processed'],
        ]);

        $this->closeFile($handle);
    }

    private function processSessions(string $filePath, int $startLine, int $endLine, array $sitesMap): array
    {
        $sessionsMap = []; // session_id => session_data
        $handle = $this->openFile($filePath);
        $lineCount = 0;
        
        Log::info('UmamiImportService: Processing sessions for chunk', [
            'start_line' => $startLine,
            'end_line' => $endLine,
        ]);
        
        while (($line = $this->readLine($handle)) !== false) {
            $lineCount++;
            
            // Ignorer les lignes hors du chunk
            if ($lineCount < $startLine || $lineCount > $endLine) {
                continue;
            }
            
            if (str_contains(strtolower($line), 'insert into `session`')) {
                $sessionsData = $this->extractSessionData($line);
                if (!empty($sessionsData)) {
                    Log::info('Extracted sessions data', ['sessions_count' => count($sessionsData)]);
                    
                    foreach ($sessionsData as $sessionData) {
                        $sessionsMap[$sessionData['session_id']] = $sessionData;
                    }
                }
            }
        }
        
        $this->closeFile($handle);
        
        Log::info('UmamiImportService: Sessions processing complete', [
            'sessions_found' => count($sessionsMap),
        ]);
        
        return $sessionsMap;
    }

    private function processDataChunk(string $filePath, int $startLine, int $endLine, array $sitesMap, array $sessionsMap, array &$stats): void
    {
        $handle = $this->openFile($filePath);
        $pageViewBatch = [];
        $eventBatch = [];
        $lineCount = 0;
        $pageviewLines = 0;
        $eventLines = 0;
        $currentEventData = '';
        $inEventInsert = false;

        Log::info('UmamiImportService: Processing data chunk', [
            'start_line' => $startLine,
            'end_line' => $endLine,
            'sites_count' => count($sitesMap),
        ]);

        while (($line = $this->readLine($handle)) !== false) {
            $lineCount++;
            
            // Ignorer les lignes en dehors de notre chunk
            if ($lineCount < $startLine) {
                continue;
            }
            
            if ($lineCount > $endLine) {
                break;
            }
            
            // Traiter les website_events (contient pageviews et events)
            if (str_contains(strtolower($line), 'insert into `website_event`')) {
                $eventLines++;
                $inEventInsert = true;
                $currentEventData = $line;
                Log::debug('Found website_event line start', ['line_number' => $lineCount]);
            } elseif ($inEventInsert) {
                $currentEventData .= ' ' . trim($line);
                
                // Vérifier si c'est la fin de l'INSERT (contient des parenthèses fermantes)
                if (str_contains($line, ');')) {
                    $eventsData = $this->extractWebsiteEventData($currentEventData, $sitesMap);
                    if (!empty($eventsData)) {
                        Log::info('Extracted website_event data', ['events_count' => count($eventsData)]);
                        
                        foreach ($eventsData as $data) {
                            // Séparer les pageviews des events
                            if ($data['event_type'] === '1' || $data['event_type'] === 1) {
                                // event_type = 1 signifie pageview dans Umami
                                $pageViewBatch[] = $this->convertToPageView($data, $sessionsMap);
                                $pageviewLines++;
                            } elseif ($data['event_type'] === '2' || $data['event_type'] === 2) {
                                // event_type = 2 signifie event dans Umami
                                $eventBatch[] = $this->convertToEvent($data, $sessionsMap);
                            }
                        }
                    } else {
                        Log::warning('Failed to extract website_event data', ['data' => substr($currentEventData, 0, 200)]);
                    }
                    
                    $inEventInsert = false;
                    $currentEventData = '';
                }
            }

            // Insérer par batch
            if (count($pageViewBatch) >= self::BATCH_SIZE) {
                $this->insertBatch('page_views', $pageViewBatch, $stats);
                $pageViewBatch = [];
                $stats['batches_processed']++;
            }

            if (count($eventBatch) >= self::BATCH_SIZE) {
                $this->insertBatch('events', $eventBatch, $stats);
                $eventBatch = [];
                $stats['batches_processed']++;
            }
        }

        // Insérer les derniers batches
        if (!empty($pageViewBatch)) {
            $this->insertBatch('page_views', $pageViewBatch, $stats);
            $stats['batches_processed']++;
        }
        if (!empty($eventBatch)) {
            $this->insertBatch('events', $eventBatch, $stats);
            $stats['batches_processed']++;
        }

        Log::info('UmamiImportService: Data chunk processing complete', [
            'start_line' => $startLine,
            'end_line' => $endLine,
            'pageview_lines' => $pageviewLines,
            'event_lines' => $eventLines,
            'page_views' => $stats['page_views'] ?? 0,
            'events' => $stats['events'] ?? 0,
            'batches_processed' => $stats['batches_processed'] ?? 0,
        ]);

        $this->closeFile($handle);
    }

    private function extractWebsiteData(string $line): array
    {
        // Debug: log la ligne pour voir le format
        Log::debug('Extracting website data from line', ['line' => substr($line, 0, 200)]);
        
        $websites = [];
        
        // Extraire toutes les valeurs du INSERT multi-lignes
        if (preg_match('/VALUES\s*(.+)/i', $line, $matches)) {
            $valuesString = trim($matches[1]);
            if (substr($valuesString, -1) === ';') {
                $valuesString = substr($valuesString, 0, -1);
            }
            
            // Diviser par les parenthèses fermantes pour obtenir chaque ligne de valeurs
            $valueLines = preg_split('/\),\s*\(/', $valuesString);
            
            foreach ($valueLines as $valueLine) {
                // Nettoyer les parenthèses
                $valueLine = trim($valueLine, '()');
                if (empty($valueLine)) continue;
                
                $values = $this->parseSqlValues($valueLine);
                
                Log::debug('Parsed website values', ['values' => $values]);
                
                if (count($values) >= 3) {
                    // Structure Umami website: website_id, name, domain, share_id, reset_at, user_id, created_at, updated_at, deleted_at, created_by, team_id
                    $websites[] = [
                        'id' => $values[0] ?? null,
                        'name' => $values[1] ?? '',
                        'domain' => $values[2] ?? '',
                        'share_id' => $values[3] ?? null,
                        'created_at' => $values[6] ?? now(),
                        'updated_at' => $values[7] ?? now(),
                    ];
                }
            }
        }
        
        return $websites;
    }

    private function findOrCreateSite(array $websiteData): Site
    {
        // Chercher un site existant avec le même nom ou domaine
        $site = Site::where('user_id', $this->userId)
            ->where(function($query) use ($websiteData) {
                $query->where('name', $websiteData['name'])
                      ->orWhere('domain', $websiteData['domain']);
            })
            ->first();

        if ($site) {
            // Mettre à jour le site existant
            $site->update([
                'name' => $websiteData['name'],
                'domain' => $websiteData['domain'],
            ]);
            return $site;
        }

        // Créer un nouveau site
        return Site::create([
            'user_id' => $this->userId,
            'name' => $websiteData['name'],
            'domain' => $websiteData['domain'],
            'tracking_id' => $this->generateTrackingId(),
        ]);
    }

    private function generateTrackingId(): string
    {
        return 'site_' . uniqid() . '_' . time();
    }

    private function extractSessionData(string $line): array
    {
        // Debug: log la ligne pour voir le format
        Log::debug('Extracting session data from line', ['line' => substr($line, 0, 200)]);
        
        $sessions = [];
        
        // Extraire toutes les valeurs du INSERT multi-lignes
        if (preg_match('/INSERT INTO `session`[^)]+\) VALUES\s*(.+)/i', $line, $matches)) {
            $valuesString = trim($matches[1]);
            if (substr($valuesString, -1) === ';') {
                $valuesString = substr($valuesString, 0, -1);
            }
            
            // Diviser par les parenthèses fermantes pour obtenir chaque ligne de valeurs
            $valueLines = preg_split('/\),\s*\(/', $valuesString);
            
            foreach ($valueLines as $valueLine) {
                // Nettoyer les parenthèses
                $valueLine = trim($valueLine, '()');
                if (empty($valueLine)) continue;
                
                $values = $this->parseSqlValues($valueLine);
                
                Log::debug('Parsed session values', ['values' => $values]);
                
                if (count($values) < 10) {
                    continue;
                }

                // Structure Umami session: session_id, website_id, browser, os, device, screen, language, country, region, city, created_at, distinct_id
                $sessions[] = [
                    'session_id' => $values[0] ?? null,
                    'website_id' => $values[1] ?? null,
                    'browser' => $values[2] ?? null,
                    'os' => $values[3] ?? null,
                    'device' => $values[4] ?? null,
                    'screen' => $values[5] ?? null,
                    'language' => $values[6] ?? null,
                    'country' => $values[7] ?? null,
                    'region' => $values[8] ?? null,
                    'city' => $values[9] ?? null,
                    'created_at' => $values[10] ?? now(),
                    'distinct_id' => $values[11] ?? null,
                ];
            }
        }
        
        return $sessions;
    }

    private function extractWebsiteEventData(string $line, array $sitesMap): array
    {
        // Debug: log la ligne pour voir le format
        Log::debug('Extracting website_event data from line', ['line' => substr($line, 0, 200)]);
        
        $events = [];
        
        // Extraire toutes les valeurs du INSERT multi-lignes
        if (preg_match('/INSERT INTO `website_event`[^)]+\) VALUES\s*(.+)/i', $line, $matches)) {
            $valuesString = trim($matches[1]);
            if (substr($valuesString, -1) === ';') {
                $valuesString = substr($valuesString, 0, -1);
            }
            
            // Diviser par les parenthèses fermantes pour obtenir chaque ligne de valeurs
            $valueLines = preg_split('/\),\s*\(/', $valuesString);
            
            foreach ($valueLines as $valueLine) {
                // Nettoyer les parenthèses
                $valueLine = trim($valueLine, '()');
                if (empty($valueLine)) continue;
                
                $values = $this->parseSqlValues($valueLine);
                
                Log::debug('Parsed website_event values', ['values' => $values]);
                
                if (count($values) < 10) {
                    continue;
                }

                $websiteId = $values[1] ?? null;
                if (!$websiteId) {
                    continue;
                }
                
                // Si le site n'existe pas dans notre map, le créer automatiquement
                if (!isset($sitesMap[$websiteId])) {
                    Log::info('Creating missing site automatically', ['website_id' => $websiteId]);
                    
                    $site = Site::create([
                        'user_id' => $this->userId,
                        'name' => 'Site ' . substr($websiteId, 0, 8), // Nom basé sur l'ID
                        'domain' => 'unknown-' . substr($websiteId, 0, 8) . '.com',
                        'tracking_id' => $this->generateTrackingId(),
                    ]);
                    
                    $sitesMap[$websiteId] = $site;
                }

                $site = $sitesMap[$websiteId];

                // Structure Umami website_event: event_id, website_id, session_id, created_at, url_path, url_query, referrer_path, referrer_query, referrer_domain, page_title, event_type, event_name, visit_id, tag, fbclid, gclid, li_fat_id, msclkid, ttclid, twclid, utm_campaign, utm_content, utm_medium, utm_source, utm_term, hostname
                $events[] = [
                    'event_id' => $values[0] ?? null,
                    'website_id' => $websiteId,
                    'site_id' => $site->id,
                    'session_id' => $values[2] ?? $this->generateSessionId(),
                    'created_at' => $values[3] ?? now(),
                    'url_path' => $values[4] ?? '',
                    'url_query' => $values[5] ?? '',
                    'referrer_path' => $values[6] ?? '',
                    'referrer_query' => $values[7] ?? '',
                    'referrer_domain' => $values[8] ?? '',
                    'page_title' => $values[9] ?? '',
                    'event_type' => $values[10] ?? '1', // 1 = pageview dans Umami
                    'event_name' => $values[11] ?? '',
                    'visit_id' => $values[12] ?? null,
                    'tag' => $values[13] ?? '',
                    'hostname' => $values[25] ?? '',
                ];
            }
        }
        
        return $events;
    }

    private function convertToPageView(array $websiteEventData, array $sessionsMap = []): array
    {
        $sessionData = $sessionsMap[$websiteEventData['session_id']] ?? [];
        
        return [
            'site_id' => $websiteEventData['site_id'],
            'session_id' => $websiteEventData['session_id'],
            'url' => $websiteEventData['url_path'] . ($websiteEventData['url_query'] ? '?' . $websiteEventData['url_query'] : ''),
            'referrer' => $websiteEventData['referrer_domain'] . $websiteEventData['referrer_path'] . ($websiteEventData['referrer_query'] ? '?' . $websiteEventData['referrer_query'] : ''),
            'user_agent' => null, // Pas stocké dans website_event
            'ip_address' => null, // Pas stocké dans website_event
            'country' => $sessionData['country'] ?? null,
            'city' => $sessionData['city'] ?? null,
            'device_type' => $sessionData['device'] ?? null,
            'browser' => $sessionData['browser'] ?? null,
            'os' => $sessionData['os'] ?? null,
            'hostname' => $websiteEventData['hostname'],
            'title' => $websiteEventData['page_title'],
            'created_at' => $websiteEventData['created_at'],
            'updated_at' => now(),
        ];
    }

    private function convertToEvent(array $websiteEventData, array $sessionsMap = []): array
    {
        $sessionData = $sessionsMap[$websiteEventData['session_id']] ?? [];
        
        return [
            'site_id' => $websiteEventData['site_id'],
            'session_id' => $websiteEventData['session_id'],
            'name' => $websiteEventData['event_name'],
            'properties' => json_encode([
                'url' => $websiteEventData['url_path'] . ($websiteEventData['url_query'] ? '?' . $websiteEventData['url_query'] : ''),
                'title' => $websiteEventData['page_title'],
                'referrer' => $websiteEventData['referrer_domain'] . $websiteEventData['referrer_path'] . ($websiteEventData['referrer_query'] ? '?' . $websiteEventData['referrer_query'] : ''),
                'tag' => $websiteEventData['tag'],
                'hostname' => $websiteEventData['hostname'],
                'device_type' => $sessionData['device'] ?? null,
                'browser' => $sessionData['browser'] ?? null,
                'os' => $sessionData['os'] ?? null,
                'country' => $sessionData['country'] ?? null,
                'city' => $sessionData['city'] ?? null,
            ]),
            'url' => $websiteEventData['url_path'] . ($websiteEventData['url_query'] ? '?' . $websiteEventData['url_query'] : ''),
            'user_agent' => null, // Pas stocké dans website_event
            'ip_address' => null, // Pas stocké dans website_event
            'created_at' => $websiteEventData['created_at'],
            'updated_at' => now(),
        ];
    }

    private function parseEventData(string $eventData): ?array
    {
        if (empty($eventData) || $eventData === '{}') {
            return null;
        }

        try {
            $decoded = json_decode($eventData, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de parsing JSON
        }

        return null;
    }

    private function generateSessionId(): string
    {
        return 'session_' . uniqid() . '_' . time();
    }

    private function isGzipped(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        
        $magic = fread($handle, 2);
        fclose($handle);
        
        // Signature gzip: 0x1f 0x8b
        return $magic === "\x1f\x8b";
    }

    private function openFile(string $filePath)
    {
        if ($this->isGzipped($filePath)) {
            return gzopen($filePath, 'r');
        } else {
            return fopen($filePath, 'r');
        }
    }

    private function readLine($handle)
    {
        if (is_resource($handle) && get_resource_type($handle) === 'stream') {
            // Fichier normal
            return fgets($handle);
        } else {
            // Fichier gzip
            return gzgets($handle);
        }
    }

    private function closeFile($handle): void
    {
        if (is_resource($handle)) {
            if (get_resource_type($handle) === 'stream') {
                fclose($handle);
            } else {
                gzclose($handle);
            }
        }
    }

    private function insertBatch(string $table, array $data, array &$stats): void
    {
        try {
            DB::table($table)->insert($data);
            
            if ($table === 'page_views') {
                $stats['page_views'] = ($stats['page_views'] ?? 0) + count($data);
            } elseif ($table === 'events') {
                $stats['events'] = ($stats['events'] ?? 0) + count($data);
            }
        } catch (\Exception $e) {
            Log::error("Batch insert failed for table {$table}", [
                'error' => $e->getMessage(),
                'batch_size' => count($data),
            ]);
            $stats['errors'] = ($stats['errors'] ?? 0) + 1;
        }
    }

    private function estimateProcessingTime(array $stats): int
    {
        // Estimation basée sur le nombre d'enregistrements
        $totalRecords = $stats['page_views'] + $stats['events'];
        
        // ~1000 records/second sur un serveur moyen
        $estimatedSeconds = $totalRecords / 1000;
        
        return (int) max(30, $estimatedSeconds); // Minimum 30 secondes
    }

    private function parseSqlValues(string $valuesString): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];

            if (!$inQuotes && ($char === "'" || $char === '"')) {
                $inQuotes = true;
                $quoteChar = $char;
                continue;
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
                continue;
            }

            if (!$inQuotes && $char === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $values[] = trim($current);
        }

        // Nettoyer les valeurs
        return array_map(function($value) {
            $value = trim($value);
            if (empty($value)) {
                return $value;
            }
            if (($value[0] === "'" && $value[-1] === "'") || ($value[0] === '"' && $value[-1] === '"')) {
                return substr($value, 1, -1);
            }
            return $value;
        }, $values);
    }
}