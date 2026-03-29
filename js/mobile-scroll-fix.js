/**
 * Mobile Scroll Fix - Prevents page refresh on scroll
 * Addresses mobile browser viewport changes and scroll-triggered events
 */

(function() {
    'use strict';
    
    // Prevent viewport resize on scroll (mobile browser address bar)
    let lastHeight = window.innerHeight;
    let isScrolling = false;
    let scrollTimeout;
    
    // Detect if we're actually scrolling vs resizing
    window.addEventListener('scroll', function() {
        isScrolling = true;
        clearTimeout(scrollTimeout);
        
        scrollTimeout = setTimeout(function() {
            isScrolling = false;
        }, 150);
    }, { passive: true });
    
    // Override resize behavior during scroll
    const originalAddEventListener = window.addEventListener;
    let resizeListeners = [];
    
    window.addEventListener = function(type, listener, options) {
        if (type === 'resize') {
            // Store resize listeners to filter them
            resizeListeners.push({ listener, options });
            
            // Wrap resize listener to ignore scroll-triggered resizes
            const wrappedListener = function(event) {
                const heightChange = Math.abs(window.innerHeight - lastHeight);
                
                // Ignore small height changes (likely mobile address bar)
                if (heightChange < 100 && isScrolling) {
                    return;
                }
                
                // Ignore if only height changed (mobile address bar)
                if (heightChange > 0 && heightChange < 200) {
                    lastHeight = window.innerHeight;
                    return;
                }
                
                // Real resize event
                lastHeight = window.innerHeight;
                listener.call(this, event);
            };
            
            originalAddEventListener.call(this, type, wrappedListener, options);
        } else {
            originalAddEventListener.call(this, type, listener, options);
        }
    };
    
    // Prevent zoom on double-tap
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = Date.now();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, { passive: false });
    
    // Stabilize viewport on mobile
    if (window.innerWidth <= 768) {
        // Set initial viewport height
        const setViewportHeight = function() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        };
        
        setViewportHeight();
        
        // Only update on orientation change, not scroll
        window.addEventListener('orientationchange', function() {
            setTimeout(setViewportHeight, 100);
        });
    }
    
    // Prevent pull-to-refresh on mobile
    let touchStartY = 0;
    document.addEventListener('touchstart', function(e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        const touchY = e.touches[0].clientY;
        const touchDiff = touchY - touchStartY;
        
        // Prevent pull-to-refresh if at top of page
        if (touchDiff > 0 && window.scrollY === 0) {
            e.preventDefault();
        }
    }, { passive: false });
    
    console.log('Mobile scroll fix initialized - page refresh on scroll prevented');
})();
