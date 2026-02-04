/**
 * ScriptMarket Analytics Tracker
 * Kiralanan sitelere eklenir ve gerçek zamanlı veri toplar
 */

(function() {
    'use strict';
    
    // Konfigürasyon
    const config = {
        apiEndpoint: window.ANALYTICS_API_URL || 'https://yoursite.com/api/analytics/track',
        rentalId: window.RENTAL_ID || null,
        heartbeatInterval: 30000, // 30 saniye
        sendInterval: 5000 // 5 saniye
    };

    // Veri depolama
    let analyticsData = {
        sessionId: generateSessionId(),
        rentalId: config.rentalId,
        pageviews: [],
        startTime: Date.now(),
        lastActivity: Date.now()
    };

    /**
     * Session ID oluştur
     */
    function generateSessionId() {
        const stored = sessionStorage.getItem('sm_session_id');
        if (stored) return stored;
        
        const newId = 'sm_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('sm_session_id', newId);
        return newId;
    }

    /**
     * Ziyaretçi bilgilerini topla
     */
    function collectVisitorData() {
        return {
            session_id: analyticsData.sessionId,
            rental_id: config.rentalId,
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Analytics verilerini API'ye gönder
     */
    function sendAnalytics(data) {
        if (!config.rentalId) {
            console.warn('ScriptMarket Analytics: Rental ID tanımlı değil');
            return;
        }

        // Beacon API kullan (sayfa kapatılsa bile veri gönderilir)
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(config.apiEndpoint, blob);
        } else {
            // Fallback: fetch ile gönder
            fetch(config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(err => {
                console.error('ScriptMarket Analytics error:', err);
            });
        }
    }

    /**
     * Sayfa görüntüleme kaydı
     */
    function trackPageview() {
        const pageviewData = collectVisitorData();
        analyticsData.pageviews.push(pageviewData);
        analyticsData.lastActivity = Date.now();
        
        sendAnalytics({
            type: 'pageview',
            data: pageviewData
        });
    }

    /**
     * Heartbeat (aktif kullanıcı takibi)
     */
    function sendHeartbeat() {
        if (Date.now() - analyticsData.lastActivity < config.heartbeatInterval * 2) {
            sendAnalytics({
                type: 'heartbeat',
                data: {
                    session_id: analyticsData.sessionId,
                    rental_id: config.rentalId,
                    timestamp: new Date().toISOString()
                }
            });
        }
    }

    /**
     * Para yatırma işlemi takibi
     */
    function trackDeposit(amount, method, transactionId) {
        sendAnalytics({
            type: 'deposit',
            data: {
                session_id: analyticsData.sessionId,
                rental_id: config.rentalId,
                amount_try: parseFloat(amount),
                payment_method: method,
                transaction_id: transactionId,
                timestamp: new Date().toISOString()
            }
        });
    }

    /**
     * Kullanıcı aktivitesi takibi
     */
    function trackActivity() {
        analyticsData.lastActivity = Date.now();
    }

    /**
     * Event listeners
     */
    function initEventListeners() {
        // Kullanıcı aktivitesi
        ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, trackActivity, { passive: true });
        });

        // Sayfa değişiklikleri (SPA için)
        let lastUrl = window.location.href;
        new MutationObserver(() => {
            const currentUrl = window.location.href;
            if (currentUrl !== lastUrl) {
                lastUrl = currentUrl;
                trackPageview();
            }
        }).observe(document.body, { 
            childList: true, 
            subtree: true 
        });

        // Sayfa kapatılırken
        window.addEventListener('beforeunload', () => {
            sendAnalytics({
                type: 'session_end',
                data: {
                    session_id: analyticsData.sessionId,
                    rental_id: config.rentalId,
                    duration: Date.now() - analyticsData.startTime,
                    pageviews: analyticsData.pageviews.length,
                    timestamp: new Date().toISOString()
                }
            });
        });

        // Visibility change (sekme değişimi)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                sendAnalytics({
                    type: 'tab_hidden',
                    data: {
                        session_id: analyticsData.sessionId,
                        rental_id: config.rentalId,
                        timestamp: new Date().toISOString()
                    }
                });
            } else {
                sendHeartbeat();
            }
        });
    }

    /**
     * İlk yüklemede başlat
     */
    function init() {
        // İlk pageview
        trackPageview();
        
        // Event listeners
        initEventListeners();
        
        // Heartbeat interval
        setInterval(sendHeartbeat, config.heartbeatInterval);
        
        // Global API (manuel takip için)
        window.ScriptMarketAnalytics = {
            trackDeposit: trackDeposit,
            trackPageview: trackPageview,
            trackEvent: function(eventName, eventData) {
                sendAnalytics({
                    type: 'custom_event',
                    event_name: eventName,
                    data: {
                        session_id: analyticsData.sessionId,
                        rental_id: config.rentalId,
                        event_data: eventData,
                        timestamp: new Date().toISOString()
                    }
                });
            }
        };

        console.log('ScriptMarket Analytics initialized for Rental ID:', config.rentalId);
    }

    // Sayfa yüklendiğinde başlat
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
