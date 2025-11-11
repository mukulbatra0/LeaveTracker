// Immediate Mobile Detection and Visual Feedback
(function() {
    'use strict';
    
    // Detect mobile immediately
    const isMobile = window.innerWidth <= 768;
    const isTablet = window.innerWidth <= 992 && window.innerWidth > 768;
    
    if (isMobile || isTablet) {
        // Add immediate visual feedback
        document.documentElement.style.setProperty('--mobile-active', '1');
        
        // Add mobile indicator to page
        const indicator = document.createElement('div');
        indicator.id = 'mobile-indicator';
        indicator.innerHTML = `
            <div style="
                position: fixed;
                top: 10px;
                right: 10px;
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                animation: slideInRight 0.5s ease;
            ">
                <i class="fas fa-mobile-alt" style="margin-right: 5px;"></i>
                ${isMobile ? 'Mobile' : 'Tablet'} Mode (${window.innerWidth}px)
            </div>
        `;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            /* Immediate mobile styles */
            @media (max-width: 768px) {
                body { 
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
                    font-size: 14px !important;
                }
                
                .container-fluid {
                    padding-left: 8px !important;
                    padding-right: 8px !important;
                }
                
                .card {
                    border-radius: 12px !important;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
                    margin-bottom: 1rem !important;
                }
                
                .card-header {
                    background: linear-gradient(135deg, #007bff, #0056b3) !important;
                    color: white !important;
                    border-radius: 12px 12px 0 0 !important;
                    padding: 1rem !important;
                }
                
                .btn {
                    border-radius: 8px !important;
                    font-weight: 500 !important;
                    padding: 12px 20px !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                }
                
                .form-control, .form-select {
                    border-radius: 8px !important;
                    border: 2px solid #dee2e6 !important;
                    padding: 12px 16px !important;
                    font-size: 16px !important;
                }
                
                .form-control:focus, .form-select:focus {
                    border-color: #007bff !important;
                    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
                }
                
                h1 {
                    font-size: 1.5rem !important;
                    color: #495057 !important;
                }
                
                .breadcrumb {
                    background: rgba(255, 255, 255, 0.8) !important;
                    border-radius: 8px !important;
                    padding: 8px 12px !important;
                }
                
                .nav-tabs {
                    border: none !important;
                    background: rgba(255, 255, 255, 0.1) !important;
                    border-radius: 8px !important;
                    padding: 4px !important;
                    overflow-x: auto !important;
                    -webkit-overflow-scrolling: touch !important;
                }
                
                .nav-tabs .nav-link {
                    border: none !important;
                    border-radius: 6px !important;
                    margin: 0 2px !important;
                    padding: 8px 12px !important;
                    font-size: 13px !important;
                    white-space: nowrap !important;
                    background: rgba(255, 255, 255, 0.7) !important;
                    color: #495057 !important;
                }
                
                .nav-tabs .nav-link.active {
                    background: white !important;
                    color: #007bff !important;
                    font-weight: 600 !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                }
                
                .alert {
                    border-radius: 10px !important;
                    border: none !important;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
                }
            }
            
            @media (max-width: 992px) and (min-width: 769px) {
                body {
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%) !important;
                }
                
                .card {
                    border-radius: 10px !important;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Add indicator to page when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.appendChild(indicator);
                
                // Remove indicator after 4 seconds
                setTimeout(function() {
                    indicator.style.animation = 'slideOutRight 0.5s ease forwards';
                    setTimeout(function() {
                        if (indicator.parentNode) {
                            indicator.parentNode.removeChild(indicator);
                        }
                    }, 500);
                }, 4000);
            });
        } else {
            document.body.appendChild(indicator);
            
            // Remove indicator after 4 seconds
            setTimeout(function() {
                indicator.style.animation = 'slideOutRight 0.5s ease forwards';
                setTimeout(function() {
                    if (indicator.parentNode) {
                        indicator.parentNode.removeChild(indicator);
                    }
                }, 500);
            }, 4000);
        }
        
        // Add slide out animation
        const slideOutStyle = document.createElement('style');
        slideOutStyle.textContent = `
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(slideOutStyle);
    }
    
    // Add resize handler
    window.addEventListener('resize', function() {
        const newIsMobile = window.innerWidth <= 768;
        const newIsTablet = window.innerWidth <= 992 && window.innerWidth > 768;
        
        if ((newIsMobile || newIsTablet) && !document.getElementById('mobile-indicator')) {
            // Re-add indicator if resized to mobile/tablet
            location.reload(); // Simple solution to re-apply mobile styles
        }
    });
})();