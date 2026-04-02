/**
 * Initialize AJAX functionality for Admin and HOD pages
 */

(function() {
    'use strict';

    // Convert forms to AJAX
    function convertFormsToAjax() {
        // Find all forms in modals
        const modalForms = document.querySelectorAll('.modal form');
        
        modalForms.forEach(form => {
            // Skip if already converted
            if (form.classList.contains('ajax-converted')) {
                return;
            }
            
            form.classList.add('ajax-converted');
            
            // Update form action to ajax_handler.php
            const originalAction = form.action;
            form.action = form.action.replace(/\/(users|departments|leave_types)\.php/, '/ajax_handler.php');
            
            // Add action field based on submit button name
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                
                // Determine action from button name
                let action = '';
                if (formData.has('add_user')) {
                    action = 'add_user';
                } else if (formData.has('edit_user')) {
                    action = 'edit_user';
                } else if (formData.has('add_department')) {
                    action = 'add_department';
                } else if (formData.has('edit_department')) {
                    action = 'edit_department';
                } else if (formData.has('add_leave_type')) {
                    action = 'add_leave_type';
                } else if (formData.has('edit_leave_type')) {
                    action = 'edit_leave_type';
                } else if (formData.has('action')) {
                    // Action already set (e.g., reset_password)
                    action = formData.get('action');
                }
                
                if (action) {
                    formData.set('action', action);
                }
                
                // Show loading state
                const originalText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
                
                // Submit via AJAX
                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    
                    if (data.success) {
                        showNotification(data.message, 'success');
                        
                        // Close modal
                        const modal = this.closest('.modal');
                        if (modal) {
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) {
                                bsModal.hide();
                            }
                        }
                        
                        // Reset form
                        this.reset();
                        
                        // Reload page after short delay
                        if (data.reload) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        showNotification(data.message || 'An error occurred', 'danger');
                    }
                })
                .catch(error => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                });
            });
        });
    }

    // Show notification toast
    function showNotification(message, type = 'success') {
        // Remove existing notifications
        const existing = document.querySelector('.ajax-notification');
        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');
        notification.className = `ajax-notification alert alert-${type} alert-dismissible fade show`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
        `;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    // Add CSS animations
    function addAnimationStyles() {
        if (document.getElementById('ajax-animations')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'ajax-animations';
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize
    function init() {
        addAnimationStyles();
        convertFormsToAjax();
        
        // Re-initialize when modals are shown (for dynamically loaded content)
        document.addEventListener('shown.bs.modal', function() {
            convertFormsToAjax();
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
