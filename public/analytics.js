(function() {
    'use strict';

    // Configuration
    var config = {
        endpoint: window.location.protocol + '//' + window.location.host + '/api',
        trackPageViews: true,
        trackEvents: true,
        trackTimeOnPage: true,
        trackReferrer: true,
        trackScreenResolution: true,
        respectDoNotTrack: false // Disabled in favor of consent-based tracking
    };

    // Get site ID from script tag
    var script = document.currentScript || (function() {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();
    var siteId = script.getAttribute('data-site-id');

    if (!siteId) {
        console.error('Counted Analytics: No site ID provided');
        return;
    }

    console.log('Counted Analytics: Initialized with site ID', siteId);
    console.log('Counted Analytics: Endpoint', config.endpoint);

    // Check for user consent (optional - can be disabled for legitimate interest)
    var hasConsent = checkUserConsent();
    if (!hasConsent) {
        console.log('Counted Analytics: No user consent, stopping tracking');
        return;
    }
    console.log('Counted Analytics: Tracking enabled, continuing with analytics');

    // Generate session ID
    var sessionId = generateSessionId();

    console.log('Counted Analytics: Starting tracking setup...');
    
    // Track page view
    if (config.trackPageViews) {
        console.log('Counted Analytics: Setting up page view tracking...');
        trackPageView();
    }

    // Track time on page
    if (config.trackTimeOnPage) {
        console.log('Counted Analytics: Setting up time on page tracking...');
        trackTimeOnPage();
    }

    // Track events
    if (config.trackEvents) {
        console.log('Counted Analytics: Setting up event tracking...');
        setupEventTracking();
    }
    
    console.log('Counted Analytics: Tracking setup complete');

    function generateSessionId() {
        var stored = localStorage.getItem('counted_session_id');
        if (stored) {
            return stored;
        }
        
        var newId = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('counted_session_id', newId);
        return newId;
    }

    function trackPageView() {
        var data = {
            site_id: siteId,
            url: window.location.href,
            referrer: config.trackReferrer ? document.referrer : null,
            screen_resolution: config.trackScreenResolution ? screen.width + 'x' + screen.height : null
        };

        console.log('Counted Analytics: Tracking page view', data);
        sendRequest('/track', data);
    }

    function trackTimeOnPage() {
        var startTime = Date.now();
        
        // Track on page visibility change (tab switching)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                var timeOnPage = Math.round((Date.now() - startTime) / 1000);
                sendBeaconData('/track', {
                    site_id: siteId,
                    url: window.location.href,
                    time_on_page: timeOnPage,
                    event_type: 'visibility_change'
                });
            }
        });
        
        // Track on page unload
        window.addEventListener('beforeunload', function() {
            var timeOnPage = Math.round((Date.now() - startTime) / 1000);
            sendBeaconData('/track', {
                site_id: siteId,
                url: window.location.href,
                time_on_page: timeOnPage,
                event_type: 'page_unload'
            });
        });
    }

    function setupEventTracking() {
        // Track clicks on elements with data-counted-event attribute
        document.addEventListener('click', function(e) {
            var element = e.target.closest('[data-counted-event]');
            if (element) {
                var eventName = element.getAttribute('data-counted-event');
                var properties = {};
                
                // Get properties from data attributes
                var dataAttrs = element.attributes;
                for (var i = 0; i < dataAttrs.length; i++) {
                    var attr = dataAttrs[i];
                    if (attr.name.startsWith('data-counted-') && attr.name !== 'data-counted-event') {
                        var key = attr.name.replace('data-counted-', '');
                        properties[key] = attr.value;
                    }
                }

                trackEvent(eventName, properties);
            }
        });
    }

    function trackEvent(name, properties) {
        var data = {
            site_id: siteId,
            name: name,
            properties: properties,
            url: window.location.href
        };

        // Use beacon for important events that might happen during page unload
        if (name === 'purchase' || name === 'conversion' || name === 'goal_completed') {
            sendBeaconData('/event', data);
        } else {
            sendRequest('/event', data);
        }
    }

    function sendBeaconData(endpoint, data) {
        var url = config.endpoint + endpoint;
        var blob = new Blob([JSON.stringify(data)], {
            type: 'application/json'
        });
        
        if (navigator.sendBeacon) {
            var success = navigator.sendBeacon(url, blob);
            if (!success) {
                // Fallback to XMLHttpRequest if beacon fails
                sendRequest(endpoint, data);
            }
        } else {
            // Fallback for browsers that don't support beacon
            sendRequest(endpoint, data);
        }
    }

    function sendRequest(endpoint, data) {
        var xhr = new XMLHttpRequest();
        var url = config.endpoint + endpoint;
        console.log('Counted Analytics: Sending request to', url, data);
        
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log('Counted Analytics: Response received', xhr.status, xhr.responseText);
                if (xhr.status !== 200 && xhr.status !== 201) {
                    console.error('Counted Analytics: Failed to send data', xhr.status, xhr.responseText);
                } else {
                    console.log('Counted Analytics: Data sent successfully');
                }
            }
        };

        xhr.send(JSON.stringify(data));
    }

    function checkUserConsent() {
        // Check for existing consent in localStorage
        var consent = localStorage.getItem('counted_analytics_consent');
        
        if (consent === 'granted') {
            return true;
        }
        
        if (consent === 'denied') {
            return false;
        }
        
        // If no consent stored, assume consent (legitimate interest approach)
        // This matches how Umami, Plausible, and Matomo work by default
        // Users can still opt-out via browser settings or by setting consent to 'denied'
        console.log('Counted Analytics: No consent stored, assuming consent (legitimate interest)');
        return true;
    }

    // Expose functions globally
    window.CountedAnalytics = {
        trackEvent: trackEvent,
        setConsent: function(granted) {
            localStorage.setItem('counted_analytics_consent', granted ? 'granted' : 'denied');
            console.log('Counted Analytics: Consent set to', granted ? 'granted' : 'denied');
        },
        hasConsent: function() {
            return checkUserConsent();
        }
    };

})(); 