<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseHelper;
use App\Models\Site;
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
        $analyticsData = $this->getAnalyticsData($site, $startDate, $endDate);

        return Inertia::render('Sites/Show', [
            'site' => $site,
            'analyticsData' => $analyticsData,
            'period' => $period,
        ]);
    }

    private function getAnalyticsData(Site $site, $startDate, $endDate): array
    {
        $pageViews = $site->pageViews()->whereBetween('created_at', [$startDate, $endDate]);
        $events = $site->events()->whereBetween('created_at', [$startDate, $endDate]);

        // Chart data
        $chartData = $this->getChartData($site, $startDate, $endDate);

        // Top pages
        $topPages = $pageViews->clone()
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
            });

        // Top referrers
        $topReferrers = $pageViews->clone()
            ->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as count')
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'referrer' => $item->referrer,
                    'count' => $item->count,
                ];
            });

        // Device types
        $deviceStats = $pageViews->clone()
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'device' => $item->device_type ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        // Browsers
        $browserStats = $pageViews->clone()
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'browser' => $item->browser ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        // Operating systems
        $osStats = $pageViews->clone()
            ->selectRaw('os, COUNT(*) as count')
            ->groupBy('os')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'os' => $item->os ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        // Screen resolutions
        $screenStats = $pageViews->clone()
            ->whereNotNull('screen_resolution')
            ->selectRaw('screen_resolution, COUNT(*) as count')
            ->groupBy('screen_resolution')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'resolution' => $item->screen_resolution,
                    'count' => $item->count,
                ];
            });

        // Top events
        $topEvents = $events->clone()
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
            });

        // Overall stats
        $totalPageViews = $pageViews->count();
        $uniqueVisitors = $pageViews->distinct('session_id')->count();
        $bounceRate = $totalPageViews > 0 
            ? round(($pageViews->where('is_bounce', true)->count() / $totalPageViews) * 100, 2)
            : 0;
        $avgTimeOnPage = $pageViews->whereNotNull('time_on_page')->avg('time_on_page') ?? 0;
        $totalEvents = $events->count();

        return [
            'chartData' => $chartData,
            'stats' => [
                'totalPageViews' => $totalPageViews,
                'uniqueVisitors' => $uniqueVisitors,
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

    private function getChartData(Site $site, $startDate, $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate);
        $isHourly = $daysDiff < 3;

        // Vérifier si les tables d'agrégation ont des données
        $hasAggregatedData = DB::table('analytics_daily')
            ->where('site_id', $site->id)
            ->exists();
            


        if ($hasAggregatedData) {
            // Utiliser les données agrégées si disponibles
            if ($isHourly) {
                $hourlyFormat = DatabaseHelper::getDateFormatFunction('H:i', 'hour_start');
                $data = DB::table('analytics_hourly')
                    ->where('site_id', $site->id)
                    ->whereBetween('hour_start', [$startDate, $endDate])
                    ->selectRaw("
                        {$hourlyFormat} as label,
                        page_views,
                        unique_visitors
                    ")
                    ->orderBy('hour_start')
                    ->get();
            } else {
                $dailyFormat = DatabaseHelper::getDateFormatFunction('j M', 'date');
                $data = DB::table('analytics_daily')
                    ->where('site_id', $site->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->selectRaw("
                        {$dailyFormat} as label,
                        page_views,
                        unique_visitors
                    ")
                    ->orderBy('date')
                    ->get();
            }

            $result = [
                'labels' => $data->pluck('label')->toArray(),
                'pageViews' => $data->pluck('page_views')->toArray(),
                'visitors' => $data->pluck('unique_visitors')->toArray(),
                'isHourly' => $isHourly,
            ];
            

            
            return $result;
        } else {
            // Fallback vers les anciennes données si pas d'agrégation
            $labels = [];
            $pageViewsData = [];
            $visitorsData = [];

            if ($isHourly) {
                // Hourly data for ranges less than 3 days
                $current = $startDate->copy()->startOfHour();
                while ($current <= $endDate) {
                    $labels[] = $current->format('H:i');

                    // Get page views for this hour
                    $pageViews = $site->pageViews()
                        ->whereBetween('created_at', [
                            $current->copy()->startOfHour(),
                            $current->copy()->endOfHour()
                        ])
                        ->count();
                    $pageViewsData[] = $pageViews;

                    // Get unique visitors for this hour
                    $visitors = $site->pageViews()
                        ->whereBetween('created_at', [
                            $current->copy()->startOfHour(),
                            $current->copy()->endOfHour()
                        ])
                        ->distinct('session_id')
                        ->count();
                    $visitorsData[] = $visitors;

                    $current->addHour();
                }
            } else {
                // Daily data for ranges 3 days or more
                $current = $startDate->copy();
                while ($current <= $endDate) {
                    $labels[] = $current->format('j M');

                    // Get page views for this date
                    $pageViews = $site->pageViews()
                        ->whereDate('created_at', $current)
                        ->count();
                    $pageViewsData[] = $pageViews;

                    // Get unique visitors for this date
                    $visitors = $site->pageViews()
                        ->whereDate('created_at', $current)
                        ->distinct('session_id')
                        ->count();
                    $visitorsData[] = $visitors;

                    $current->addDay();
                }
            }

            return [
                'labels' => $labels,
                'pageViews' => $pageViewsData,
                'visitors' => $visitorsData,
                'isHourly' => $isHourly,
            ];
        }
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
            'domain' => 'required|string|max:255|unique:sites,domain,' . $site->id,
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