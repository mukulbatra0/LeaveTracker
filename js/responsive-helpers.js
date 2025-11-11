/**
 * Responsive Helper Functions for LeaveTracker Admin Pages
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize responsive features
    initResponsiveFeatures();
    
    // Handle window resize
    window.addEventListener('resize', debounce(handleResize, 250));
    
    function initResponsiveFeatures() {
        // Make tables responsive
        makeTablesResponsive();
        
        // Enhance form interactions for mobile
        enhanceMobileForms();
        
        // Improve tab navigation for mobile
        enhanceTabNavigation();
        
        // Add touch-friendly interactions
        addTouchInteractions();
    }
    
    function makeTablesResponsive() {
        const tables = document.querySelectorAll('table:not(.table-responsive table)');
        tables.forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive mobile-table-wrapper';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
    
    function enhanceMobileForms() {
        // Add loading states to form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Saving...';
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 5000);
                }
            });
        });
        
        // Improve select dropdowns on mobile
        const selects = document.querySelectorAll('select.form-select');
        selects.forEach(select => {
            if (window.innerWidth <= 768) {
                select.style.fontSize = '16px'; // Prevent zoom on iOS
            }
        });
    }
    
    function enhanceTabNavigation() {
        const tabContainer = document.querySelector('.nav-tabs');
        if (tabContainer && window.innerWidth <= 768) {
            // Add scroll indicators
            addScrollIndicators(tabContainer);
            
            // Smooth scroll to active tab
            const activeTab = tabContainer.querySelector('.nav-link.active');
            if (activeTab) {
                setTimeout(() => {
                    activeTab.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest', 
                        inline: 'center' 
                    });
                }, 100);
            }
        }
    }
    
    function addScrollIndicators(container) {
        const wrapper = container.parentElement;
        if (!wrapper.querySelector('.scroll-indicator')) {
            // Left indicator
            const leftIndicator = document.createElement('div');
            leftIndicator.className = 'scroll-indicator scroll-indicator-left';
            leftIndicator.innerHTML = '<i class="fas fa-chevron-left"></i>';
            
            // Right indicator  
            const rightIndicator = document.createElement('div');
            rightIndicator.className = 'scroll-indicator scroll-indicator-right';
            rightIndicator.innerHTML = '<i class="fas fa-chevron-right"></i>';
            
            wrapper.style.position = 'relative';
            wrapper.appendChild(leftIndicator);
            wrapper.appendChild(rightIndicator);
            
            // Update indicators on scroll
            container.addEventListener('scroll', () => updateScrollIndicators(container, leftIndicator, rightIndicator));
            updateScrollIndicators(container, leftIndicator, rightIndicator);
            
            // Click handlers
            leftIndicator.addEventListener('click', () => {
                container.scrollBy({ left: -100, behavior: 'smooth' });
            });
            
            rightIndicator.addEventListener('click', () => {
                container.scrollBy({ left: 100, behavior: 'smooth' });
            });
        }
    }
    
    function updateScrollIndicators(container, leftIndicator, rightIndicator) {
        const { scrollLeft, scrollWidth, clientWidth } = container;
        
        leftIndicator.style.opacity = scrollLeft > 0 ? '1' : '0';
        rightIndicator.style.opacity = scrollLeft < scrollWidth - clientWidth ? '1' : '0';
    }
    
    function addTouchInteractions() {
        // Add ripple effect to buttons on touch
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function(e) {
                if (!this.querySelector('.ripple')) {
                    const ripple = document.createElement('span');
                    ripple.className = 'ripple';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                }
            });
        });
        
        // Improve dropdown behavior on touch devices
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('touchend', function(e) {
                e.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                    dropdownMenu.classList.toggle('show');
                }
            });
        });
    }
    
    function handleResize() {
        // Re-initialize responsive features on resize
        enhanceTabNavigation();
        enhanceMobileForms();
    }
    
    // Utility function to debounce events
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
    
    // Add keyboard navigation for accessibility
    document.addEventListener('keydown', function(e) {
        // Tab navigation with arrow keys
        if (e.target.classList.contains('nav-link') && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            e.preventDefault();
            const tabs = Array.from(document.querySelectorAll('.nav-link'));
            const currentIndex = tabs.indexOf(e.target);
            let nextIndex;
            
            if (e.key === 'ArrowLeft') {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
            } else {
                nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
            }
            
            tabs[nextIndex].focus();
            tabs[nextIndex].click();
        }
    });
});

// Add CSS for scroll indicators and ripple effect
const style = document.createElement('style');
style.textContent = `
    .scroll-indicator {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        transition: opacity 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .scroll-indicator-left {
        left: 5px;
    }
    
    .scroll-indicator-right {
        right: 5px;
    }
    
    .scroll-indicator:hover {
        background: rgba(255, 255, 255, 1);
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @media (max-width: 768px) {
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .form-control:focus,
        .form-select:focus {
            transform: none; /* Prevent zoom on focus */
        }
        
        .dropdown-menu.show {
            display: block;
        }
    }
`;
document.head.appendChild(style);