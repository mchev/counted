<?php

namespace App\Services;

use Jenssegers\Agent\Agent;

class UserAgentParserService
{
    private Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    public function parse(string $userAgent): array
    {
        if (empty($userAgent) || $userAgent === 'NULL') {
            return [
                'device_type' => null,
                'browser' => null,
                'os' => null,
                'platform' => null,
                'is_robot' => false,
                'is_mobile' => false,
                'is_tablet' => false,
                'is_desktop' => false,
            ];
        }

        $this->agent->setUserAgent($userAgent);

        return [
            'device_type' => $this->getDeviceType(),
            'browser' => $this->getBrowser(),
            'os' => $this->getOperatingSystem(),
            'platform' => $this->getPlatform(),
            'is_robot' => $this->agent->isRobot(),
            'is_mobile' => $this->agent->isMobile(),
            'is_tablet' => $this->agent->isTablet(),
            'is_desktop' => $this->agent->isDesktop(),
        ];
    }

    private function getDeviceType(): ?string
    {
        if ($this->agent->isRobot()) {
            return 'robot';
        }
        
        if ($this->agent->isTablet()) {
            return 'tablet';
        }
        
        if ($this->agent->isMobile()) {
            return 'mobile';
        }
        
        if ($this->agent->isDesktop()) {
            return 'desktop';
        }
        
        return null;
    }

    private function getBrowser(): ?string
    {
        $browser = $this->agent->browser();
        $version = $this->agent->version($browser);
        
        if (empty($browser)) {
            return null;
        }
        
        return $version ? "{$browser} {$version}" : $browser;
    }

    private function getOperatingSystem(): ?string
    {
        $platform = $this->agent->platform();
        $version = $this->agent->version($platform);
        
        if (empty($platform)) {
            return null;
        }
        
        return $version ? "{$platform} {$version}" : $platform;
    }

    private function getPlatform(): ?string
    {
        return $this->agent->platform();
    }
} 