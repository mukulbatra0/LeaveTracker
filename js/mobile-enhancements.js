/**
 * Mobile Enhancement Script - Immediate Visual Improvements
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add mobile detection
    const isMobile = window.innerWidth <= 768;
    const isTablet = window.innerWidth <= 992 && window.innerWidth > 768;
    
    // Add device class to body
    if (isMobile) {
        document.body.classList.add('mobile-device');
    } else if (isTablet) {
        document.body.classList.add('tablet-device');
    } else {
        document.body.classList.add('desktop-device');
    }
    
    // Mobile-specific enhancements
    if (isMobile) {
        initMobileEnhancements();
    }
    
    // Handle window resize
    window.addEventListener('resize', debounce(handleResize, 250));
    
    function initMobileEnhancements() {
        // Add mobile classes to key elements
        addMobileClasses();
        
        // Enhance tables for mobile
        enhanceTablesForMobile();
        
        // Add touch feedback
        addTouchFeedback();
        
        // Improve form interactions
        improveForms();
        
        // Add mobile navigation
        enhanceMobileNavigation();
        
        // Mobile indicator removed
    }
    
    function addMobileClasses() {
        // Add mobile-specific classes
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.classList.add('mobile-card');
        });
        
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.classList.add('mobile-btn');
        });
        
        const forms = document.querySelectorAll('.form-control, .form-select');
        forms.forEach(form => {
            form.classList.add('mobile-form-control');
        });
    }
    
    function enhanceTablesForMobile() {
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            // Add mobile table wrapper
            if (!table.closest('.mobile-table-enhanced')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'mobile-table-enhanced';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
                
                // Mobile indicator removed
            }
            
            // Add data labels to cells if not present
            const rows = table.querySelectorAll('tbody tr');
            const headers = table.querySelectorAll('thead th');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index] && !cell.getAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index].textContent.trim());
                    }
                });
            });
        });
    }
    
    function addTouchFeedback() {
        // Add ripple effect to buttons
        const buttons = document.querySelectorAll('.btn, .nav-link');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function(e) {
                const ripple = document.createElement('span');
                ripple.className = 'touch-ripple';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }
    
    function improveForms() {
        // Add mobile-friendly form enhancements
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            // Prevent zoom on iOS
            if (input.type !== 'file') {
                input.style.fontSize = '16px';
            }
            
            // Add focus indicators
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('mobile-focus');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('mobile-focus');
            });
        });
        
        // Enhance form submission
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitBtn.disabled = true;
                }
            });
        });
    }
    
    function enhanceMobileNavigation() {
        // Add mobile navigation enhancements
        const navToggler = document.querySelector('.navbar-toggler');
        if (navToggler) {
            navToggler.addEventListener('click', function() {
                document.body.classList.toggle('mobile-nav-open');
            });
        }
        
        // Add swipe support for tabs
        const tabContainer = document.querySelector('.nav-tabs');
        if (tabContainer) {
            let startX = 0;
            let scrollLeft = 0;
            
            tabContainer.addEventListener('touchstart', function(e) {
                startX = e.touches[0].pageX - tabContainer.offsetLeft;
                scrollLeft = tabContainer.scrollLeft;
            });
            
            tabContainer.addEventListener('touchmove', function(e) {
                e.preventDefault();
                const x = e.touches[0].pageX - tabContainer.offsetLeft;
                const walk = (x - startX) * 2;
                tabContainer.scrollLeft = scrollLeft - walk;
            });
        }
    }
    
    function showMobileIndicator() {
        // Mobile indicator function removed
    }
    
    function handleResize() {
        const newIsMobile = window.innerWidth <= 768;
        const newIsTablet = window.innerWidth <= 992 && window.innerWidth > 768;
        
        // Update body classes
        document.body.classList.remove('mobile-device', 'tablet-device', 'desktop-device');
        
        if (newIsMobile) {
            document.body.classList.add('mobile-device');
            if (!document.body.classList.contains('mobile-enhanced')) {
                initMobileEnhancements();
                document.body.classList.add('mobile-enhanced');
            }
        } else if (newIsTablet) {
            document.body.classList.add('tablet-device');
        } else {
            document.body.classList.add('desktop-device');
        }
    }
    
    // Utility function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Add visual feedback for mobile interactions
    const style = document.createElement('style');
    style.textContent = `
        .mobile-device .mobile-card {
            transform: scale(0.98);
            transition: transform 0.2s ease;
        }
        
        .mobile-device .mobile-card:hover {
            transform: scale(1);
        }
        
        .mobile-device .mobile-btn {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .mobile-device .mobile-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .mobile-focus {
            background-color: rgba(69, 104, 130, 0.05) !important;
            border-radius: 8px;
            padding: 0.25rem;
            margin: -0.25rem;
        }
        
        .touch-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        /* Mobile indicators removed */
        
        .mobile-nav-open .navbar-collapse {
            background: rgba(27, 60, 83, 0.95);
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    `;
    document.head.appendChild(style);
});

// Add immediate visual feedback on load
window.addEventListener('load', function() {
    if (window.innerWidth <= 768) {
        document.body.style.background = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
        
        // Add a subtle animation to show mobile mode is active
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'fadeInUp 0.5s ease forwards';
            }, index * 100);
        });
        
        // Add fadeInUp animation
        const fadeStyle = document.createElement('style');
        fadeStyle.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(fadeStyle);
    }
});