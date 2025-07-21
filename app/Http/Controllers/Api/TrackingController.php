<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventTrackingRequest;
use App\Http\Requests\TrackingRequest;
use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use Jenssegers\Agent\Agent;

class TrackingController extends Controller
{
    public function track(TrackingRequest $request)
    {

        $site = Site::where('tracking_id', $request->site_id)
            ->where('is_active', true)
            ->first();

        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $agent = new Agent;
        $agent->setUserAgent($request->userAgent());

        $sessionId = $this->generateSessionId($request);

        $pageView = PageView::create([
            'site_id' => $site->id,
            'session_id' => $sessionId,
            'url' => $request->url,
            'referrer' => $request->referrer,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'country' => $this->getCountry($request->ip()),
            'city' => $this->getCity($request->ip()),
            'device_type' => $this->getDeviceType($agent),
            'browser' => $agent->browser(),
            'os' => $agent->platform(),
            'screen_resolution' => $request->screen_resolution,
            'time_on_page' => $request->time_on_page,
            'is_bounce' => $this->isBounce($site->id, $sessionId),
        ]);

        return response()->json(['success' => true], 201);
    }

    public function event(EventTrackingRequest $request)
    {

        $site = Site::where('tracking_id', $request->site_id)
            ->where('is_active', true)
            ->first();

        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $sessionId = $this->generateSessionId($request);

        Event::create([
            'site_id' => $site->id,
            'session_id' => $sessionId,
            'name' => $request->name,
            'properties' => $request->properties,
            'url' => $request->url,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true], 201);
    }

    private function generateSessionId(TrackingRequest|EventTrackingRequest $request): string
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();

        // Create a session ID based on user agent and IP
        // In a real implementation, you might want to use cookies or more sophisticated session management
        return md5($userAgent.$ip.date('Y-m-d'));
    }

    private function getDeviceType(Agent $agent): string
    {
        if ($agent->isTablet()) {
            return 'tablet';
        }

        if ($agent->isMobile()) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function getCountry(string $ip): ?string
    {
        // In a real implementation, you would use a GeoIP service
        // For now, return null
        return null;
    }

    private function getCity(string $ip): ?string
    {
        // In a real implementation, you would use a GeoIP service
        // For now, return null
        return null;
    }

    private function isBounce(int $siteId, string $sessionId): bool
    {
        // Check if this is the first page view for this session
        $existingViews = PageView::where('site_id', $siteId)
            ->where('session_id', $sessionId)
            ->count();

        return $existingViews === 0;
    }
}
