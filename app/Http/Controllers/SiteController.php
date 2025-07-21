<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseHelper;
use App\Models\Site;
use App\Services\FaviconService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $sites = $user->sites()->get();

        // Utiliser les données agrégées pour les 7 derniers jours
        $startDate = now()->subDays(7)->startOfDay();
        $endDate = now()->endOfDay();

        foreach ($sites as $site) {
            // Récupérer les données agrégées des 7 derniers jours
            $aggregatedData = DB::table('analytics_daily')
                ->where('site_id', $site->id)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->selectRaw('
                    SUM(page_views) as total_page_views,
                    SUM(unique_visitors) as total_unique_visitors
                ')
                ->first();

            // Ajouter les données agrégées au site
            $site->page_views_count = $aggregatedData->total_page_views ?? 0;
            $site->unique_visitors_count = $aggregatedData->total_unique_visitors ?? 0;
        }

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sites/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:sites,domain',
            'description' => 'nullable|string',
        ]);

        $site = $request->user()->sites()->create([
            'name' => $request->name,
            'domain' => $request->domain,
            'description' => $request->description,
        ]);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Site created successfully!');
    }

    public function show(Site $site, Request $request): Response
    {
        $this->authorize('view', $site);

        $period = $request->get('period', '1d');
        $granularity = $request->get('granularity', 'daily');
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $site->loadCount(['pageViews', 'events']);
        $site->load(['pageViews' => function ($query) {
            $query->latest()->limit(10);
        }, 'events' => function ($query) {
            $query->latest()->limit(10);
        }]);

        // Get comprehensive analytics data
        $analyticsData = $this->getAnalyticsData($site, $startDate, $endDate, $granularity);

        // Determine the actual granularity that was used (might be auto-adjusted)
        $daysDiff = $startDate->diffInDays($endDate);
        $actualGranularity = $daysDiff <= 2 ? 'hourly' : $granularity;

        return Inertia::render('Sites/Show', [
            'site' => $site,
            'analyticsData' => $analyticsData,
            'period' => $period,
            'granularity' => $actualGranularity,
        ]);
    }

    private function getAnalyticsData(Site $site, $startDate, $endDate, string $granularity = 'daily'): array
    {
        // Auto-adjust granularity for short periods
        $daysDiff = $startDate->diffInDays($endDate);
        if ($daysDiff <= 2) {
            $granularity = 'hourly';
        }

        // Determine which aggregation table to use based on granularity preference
        switch ($granularity) {
            case 'hourly':
                $aggregationTable = 'analytics_hourly';
                $dateColumn = 'hour_start';
                $isHourly = true;
                break;
            case 'monthly':
                $aggregationTable = 'analytics_monthly';
                $dateColumn = 'year_month';
                $isHourly = false;
                break;
            case 'daily':
            default:
                $aggregationTable = 'analytics_daily';
                $dateColumn = 'date';
                $isHourly = false;
                break;
        }

        // Get aggregated data
        $aggregatedData = DB::table($aggregationTable)
            ->where('site_id', $site->id)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->get();

        // Calculate overall stats from aggregated data
        $totalPageViews = $aggregatedData->sum('page_views');
        $totalUniqueVisitors = $aggregatedData->sum('unique_visitors');

        // Calculate bounce rate (this might need to be stored in aggregated data)
        // For now, we'll use a placeholder or calculate from recent raw data
        $bounceRate = $this->calculateBounceRateFromAggregated($site, $startDate, $endDate);

        // Average time on page (might need to be stored in aggregated data)
        $avgTimeOnPage = $this->calculateAvgTimeFromAggregated($site, $startDate, $endDate);

        // Total events from aggregated data or recent raw data
        $totalEvents = $this->getTotalEventsFromAggregated($site, $startDate, $endDate);

        // Get chart data from aggregated data
        $chartData = $this->getChartDataFromAggregated($site, $startDate, $endDate, $aggregationTable, $dateColumn, $isHourly);

        // Get top pages from aggregated data
        $topPages = $this->getTopPagesFromAggregated($aggregatedData);

        // Get top referrers from aggregated data
        $topReferrers = $this->getTopReferrersFromAggregated($aggregatedData);

        // Fallback to recent data if aggregated data is empty
        if (empty($topPages)) {
            $topPages = $this->getTopPagesFromRecent($site, $startDate, $endDate);
        }
        if (empty($topReferrers)) {
            $topReferrers = $this->getTopReferrersFromRecent($site, $startDate, $endDate);
        }

        // For device, browser, OS, and screen stats, we might need to use recent raw data
        // or store these in aggregated data. For now, using recent raw data
        $deviceStats = $this->getDeviceStatsFromAggregated($aggregatedData);
        $browserStats = $this->getBrowserStatsFromAggregated($aggregatedData);
        $osStats = $this->getOsStatsFromAggregated($aggregatedData);
        $screenStats = $this->getScreenStatsFromAggregated($aggregatedData);
        $topEvents = $this->getTopEventsFromRecent($site, $startDate, $endDate);

        return [
            'chartData' => $chartData,
            'stats' => [
                'totalPageViews' => $totalPageViews,
                'uniqueVisitors' => $totalUniqueVisitors,
                'bounceRate' => $bounceRate,
                'avgTimeOnPage' => round($avgTimeOnPage),
                'totalEvents' => $totalEvents,
            ],
            'topPages' => $topPages,
            'topReferrers' => $topReferrers,
            'deviceStats' => $deviceStats,
            'browserStats' => $browserStats,
            'osStats' => $osStats,
            'screenStats' => $screenStats,
            'topEvents' => $topEvents,
        ];
    }

    private function calculateBounceRateFromAggregated(Site $site, $startDate, $endDate): float
    {
        // For now, calculate from recent raw data since bounce rate isn't stored in aggregated data
        $recentPageViews = $site->pageViews()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->limit(10000); // Limit to prevent performance issues

        $totalPageViews = $recentPageViews->count();
        if ($totalPageViews === 0) {
            return 0;
        }

        $bounceCount = $recentPageViews->where('is_bounce', true)->count();

        return round(($bounceCount / $totalPageViews) * 100, 2);
    }

    private function calculateAvgTimeFromAggregated(Site $site, $startDate, $endDate): float
    {
        // For now, calculate from recent raw data since avg time isn't stored in aggregated data
        $avgTime = $site->pageViews()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('time_on_page')
            ->limit(10000) // Limit to prevent performance issues
            ->avg('time_on_page');

        return $avgTime ?? 0;
    }

    private function getTotalEventsFromAggregated(Site $site, $startDate, $endDate): int
    {
        // For now, get from recent raw data since events aren't stored in aggregated data
        return $site->events()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->limit(10000) // Limit to prevent performance issues
            ->count();
    }

    private function getChartDataFromAggregated(Site $site, $startDate, $endDate, string $table, string $dateColumn, bool $isHourly): array
    {
        $data = DB::table($table)
            ->where('site_id', $site->id)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->orderBy($dateColumn)
            ->get();

        $labels = [];
        $pageViews = [];
        $visitors = [];

        // Check if we're spanning multiple days
        $daysDiff = $startDate->diffInDays($endDate);
        $isMultiDay = $daysDiff > 1;

        foreach ($data as $row) {
            if ($isHourly) {
                if ($isMultiDay) {
                    // For multiple days, show "Day DayNumber Hour" format (e.g., "Mon 15 14:00")
                    $labels[] = Carbon::parse($row->hour_start)->format('D j H:i');
                } else {
                    // For single day, just show time
                    $labels[] = Carbon::parse($row->hour_start)->format('H:i');
                }
            } else {
                $labels[] = Carbon::parse($row->$dateColumn)->format('M j');
            }
            $pageViews[] = $row->page_views;
            $visitors[] = $row->unique_visitors;
        }

        return [
            'labels' => $labels,
            'pageViews' => $pageViews,
            'visitors' => $visitors,
            'isHourly' => $isHourly,
        ];
    }

    private function getTopPagesFromAggregated($aggregatedData): array
    {
        $allPages = [];

        foreach ($aggregatedData as $row) {
            $topPages = json_decode($row->top_pages ?? '[]', true);

            if (! is_array($topPages)) {
                continue;
            }

            // Debug: Log the structure of top_pages
            if (! empty($topPages)) {
                \Log::info('Top pages structure:', ['first_item' => $topPages[0] ?? 'empty']);
            }

            foreach ($topPages as $page) {
                if (! is_array($page) || ! isset($page['url']) || ! isset($page['count'])) {
                    \Log::warning('Invalid page structure:', ['page' => $page]);

                    continue;
                }

                $url = $page['url'];
                $count = (int) $page['count'];

                if (! isset($allPages[$url])) {
                    $allPages[$url] = 0;
                }
                $allPages[$url] += $count;
            }
        }

        arsort($allPages);
        $topPages = array_slice($allPages, 0, 10, true);

        return array_map(function ($url, $count) {
            return [
                'url' => $url,
                'count' => $count,
            ];
        }, array_keys($topPages), $topPages);
    }

    private function getTopReferrersFromAggregated($aggregatedData): array
    {
        $allReferrers = [];

        foreach ($aggregatedData as $row) {
            $referrers = json_decode($row->referrers ?? '[]', true);

            if (! is_array($referrers)) {
                continue;
            }

            foreach ($referrers as $referrer) {
                if (! is_array($referrer) || ! isset($referrer['referrer']) || ! isset($referrer['count'])) {
                    continue;
                }

                $ref = $referrer['referrer'];
                $count = (int) $referrer['count'];

                if (! isset($allReferrers[$ref])) {
                    $allReferrers[$ref] = 0;
                }
                $allReferrers[$ref] += $count;
            }
        }

        arsort($allReferrers);
        $topReferrers = array_slice($allReferrers, 0, 10, true);

        $referrerData = array_map(function ($referrer, $count) {
            return [
                'referrer' => $referrer,
                'count' => $count,
            ];
        }, array_keys($topReferrers), $topReferrers);

        // Get favicons for referrers
        $faviconService = new FaviconService;
        $favicons = $faviconService->getFaviconForReferrers($referrerData);

        // Add favicon URLs to referrer data
        foreach ($referrerData as &$referrer) {
            if ($referrer['referrer'] === null) {
                $referrer['favicon'] = null; // Direct access
            } else {
                $hostname = $faviconService->extractHostname($referrer['referrer']);
                $referrer['favicon'] = $favicons[$hostname] ?? null;
            }
        }

        return $referrerData;
    }

    private function getTopPagesFromRecent(Site $site, $startDate, $endDate): array
    {
        return $site->pageViews()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('url, COUNT(*) as count')
            ->groupBy('url')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'url' => $item->url,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    private function getTopReferrersFromRecent(Site $site, $startDate, $endDate): array
    {
        $referrers = DatabaseHelper::getTopReferrersWithDirectAccess(
            $site->pageViews()->whereBetween('created_at', [$startDate, $endDate]),
            10
        );

        // Add favicons for referrers
        $faviconService = new FaviconService;
        $favicons = $faviconService->getFaviconForReferrers($referrers);

        // Add favicon URLs to referrer data
        foreach ($referrers as &$referrer) {
            if ($referrer['referrer'] === null) {
                $referrer['favicon'] = null; // Direct access
            } else {
                $hostname = $faviconService->extractHostname($referrer['referrer']);
                $referrer['favicon'] = $favicons[$hostname] ?? null;
            }
        }

        return $referrers;
    }

    private function getDeviceStatsFromAggregated($aggregatedData): array
    {
        $deviceCounts = [];
        foreach ($aggregatedData as $row) {
            $deviceData = json_decode($row->devices ?? '[]', true);
            if (is_array($deviceData)) {
                foreach ($deviceData as $device => $count) {
                    if (! isset($deviceCounts[$device])) {
                        $deviceCounts[$device] = 0;
                    }
                    $deviceCounts[$device] += $count;
                }
            }
        }

        arsort($deviceCounts);
        $topDevices = array_slice($deviceCounts, 0, 10, true);

        return array_map(function ($device, $count) {
            return [
                'device' => $device ?? 'Unknown',
                'count' => $count,
            ];
        }, array_keys($topDevices), $topDevices);
    }

    private function getBrowserStatsFromAggregated($aggregatedData): array
    {
        $browserCounts = [];
        foreach ($aggregatedData as $row) {
            $browserData = json_decode($row->browsers ?? '[]', true);
            if (is_array($browserData)) {
                foreach ($browserData as $browser => $count) {
                    if (! isset($browserCounts[$browser])) {
                        $browserCounts[$browser] = 0;
                    }
                    $browserCounts[$browser] += $count;
                }
            }
        }

        arsort($browserCounts);
        $topBrowsers = array_slice($browserCounts, 0, 10, true);

        return array_map(function ($browser, $count) {
            return [
                'browser' => $browser ?? 'Unknown',
                'count' => $count,
            ];
        }, array_keys($topBrowsers), $topBrowsers);
    }

    private function getOsStatsFromAggregated($aggregatedData): array
    {
        $osCounts = [];
        foreach ($aggregatedData as $row) {
            $osData = json_decode($row->os ?? '[]', true);
            if (is_array($osData)) {
                foreach ($osData as $os => $count) {
                    if (! isset($osCounts[$os])) {
                        $osCounts[$os] = 0;
                    }
                    $osCounts[$os] += $count;
                }
            }
        }

        arsort($osCounts);
        $topOs = array_slice($osCounts, 0, 10, true);

        return array_map(function ($os, $count) {
            return [
                'os' => $os ?? 'Unknown',
                'count' => $count,
            ];
        }, array_keys($topOs), $topOs);
    }

    private function getScreenStatsFromAggregated($aggregatedData): array
    {
        $screenCounts = [];
        foreach ($aggregatedData as $row) {
            $screenData = json_decode($row->screen_sizes ?? '[]', true);
            if (is_array($screenData)) {
                foreach ($screenData as $resolution => $count) {
                    if (! isset($screenCounts[$resolution])) {
                        $screenCounts[$resolution] = 0;
                    }
                    $screenCounts[$resolution] += $count;
                }
            }
        }

        arsort($screenCounts);
        $topScreens = array_slice($screenCounts, 0, 10, true);

        return array_map(function ($resolution, $count) {
            return [
                'resolution' => $resolution ?? 'Unknown',
                'count' => $count,
            ];
        }, array_keys($topScreens), $topScreens);
    }

    private function getTopEventsFromAggregated($aggregatedData): array
    {
        // Events might not be stored in aggregated data yet, so fallback to recent data
        // For now, return empty array and we'll handle events separately
        return [];
    }

    private function getTopEventsFromRecent(Site $site, $startDate, $endDate): array
    {
        return $site->events()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('name, COUNT(*) as count')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    private function getDaysFromPeriod(string $period): int
    {
        return match ($period) {
            '1d' => 1,
            '2d' => 2,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 7,
        };
    }

    public function edit(Site $site): Response
    {
        $this->authorize('update', $site);

        return Inertia::render('Sites/Edit', [
            'site' => $site,
        ]);
    }

    public function update(Request $request, Site $site)
    {
        $this->authorize('update', $site);

        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:sites,domain,'.$site->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $site->update([
            'name' => $request->name,
            'domain' => $request->domain,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Site updated successfully!');
    }

    public function destroy(Site $site)
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()->route('sites.index')
            ->with('success', 'Site deleted successfully!');
    }
}
