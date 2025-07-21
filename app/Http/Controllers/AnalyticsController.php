<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $sites = $user->sites()->withCount(['pageViews', 'events'])->get();

        return Inertia::render('Dashboard', [
            'sites' => $sites,
        ]);
    }

    public function site(Request $request, Site $site): Response
    {
        $this->authorize('view', $site);

        $period = $request->get('period', '30d');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $stats = $this->getSiteStats($site, $startDate, $endDate);
        $pageViews = $this->getPageViewsData($site, $startDate, $endDate);
        $topPages = $this->getTopPages($site, $startDate, $endDate);
        $topReferrers = $this->getTopReferrers($site, $startDate, $endDate);
        $deviceStats = $this->getDeviceStats($site, $startDate, $endDate);
        $browserStats = $this->getBrowserStats($site, $startDate, $endDate);
        $recentEvents = $this->getRecentEvents($site, $startDate, $endDate);

        return Inertia::render('Analytics/Site', [
            'site' => $site,
            'period' => $period,
            'stats' => $stats,
            'pageViews' => $pageViews,
            'topPages' => $topPages,
            'topReferrers' => $topReferrers,
            'deviceStats' => $deviceStats,
            'browserStats' => $browserStats,
            'recentEvents' => $recentEvents,
        ]);
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            '1y' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30),
        };
    }

    private function getSiteStats(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        $pageViews = $site->pageViews()->forPeriod($startDate, $endDate);
        $events = $site->events()->forPeriod($startDate, $endDate);

        $totalPageViews = $pageViews->count();
        $uniqueVisitors = $pageViews->distinct('session_id')->count();
        $bounceRate = $totalPageViews > 0 
            ? round(($pageViews->where('is_bounce', true)->count() / $totalPageViews) * 100, 2)
            : 0;
        $avgTimeOnPage = $pageViews->whereNotNull('time_on_page')->avg('time_on_page') ?? 0;
        $totalEvents = $events->count();

        return [
            'totalPageViews' => $totalPageViews,
            'uniqueVisitors' => $uniqueVisitors,
            'bounceRate' => $bounceRate,
            'avgTimeOnPage' => round($avgTimeOnPage),
            'totalEvents' => $totalEvents,
        ];
    }

    private function getPageViewsData(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $count = $site->pageViews()
                ->whereDate('created_at', $current)
                ->count();

            $data[] = [
                'date' => $current->format('Y-m-d'),
                'pageViews' => $count,
            ];

            $current->addDay();
        }

        return $data;
    }

    private function getTopPages(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        return $site->pageViews()
            ->forPeriod($startDate, $endDate)
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

    private function getTopReferrers(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        return $site->pageViews()
            ->forPeriod($startDate, $endDate)
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
            })
            ->toArray();
    }

    private function getDeviceStats(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        return $site->pageViews()
            ->forPeriod($startDate, $endDate)
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'device' => $item->device_type ?? 'Unknown',
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    private function getBrowserStats(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        return $site->pageViews()
            ->forPeriod($startDate, $endDate)
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
            })
            ->toArray();
    }

    private function getRecentEvents(Site $site, Carbon $startDate, Carbon $endDate): array
    {
        return $site->events()
            ->forPeriod($startDate, $endDate)
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
} 