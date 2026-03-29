/**
 * AJAX Form Handler for Admin and HOD Dashboards
 * Handles form submissions without page refresh
 */

(function() {
    'use strict';

    // Show toast notification
    function showToast(message, type = 'success') {
        // Remove existing toasts
        const existingToast = document.querySelector('.ajax-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `ajax-toast alert alert-${type} alert-dismissible fade show`;
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    // Show loading state
    function setLoadingState(button, loading = true) {
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }

    // Handle AJAX form submission
    function handleFormSubmit(form, callback) {
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        
        setLoadingState(submitButton, true);

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            setLoadingState(submitButton, false);
            
            if (data.success) {
                showToast(data.message, 'success');
                
                // Close modal if exists
                const modal = form.closest('.modal');
                if (modal) {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                }
                
                // Reset form
                form.reset();
                
                // Call callback if provided
                if (callback) {
                    callback(data);
                }
            } else {
                showToast(data.message || 'An error occurred', 'danger');
            }
        })
        .catch(error => {
            setLoadingState(submitButton, false);
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
        });
    }

    // Refresh table row or section
    function refreshTableRow(tableId, rowData) {
        const table = document.querySelector(`#${tableId}`);
        if (!table) return;

        // This is a placeholder - implement based on your specific needs
        // You might want to reload the entire table or just update specific rows
        console.log('Refreshing table:', tableId, rowData);
    }

    // Initialize AJAX forms
    function initAjaxForms() {
        // Handle all forms with class 'ajax-form'
        document.querySelectorAll('form.ajax-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleFormSubmit(this, function(data) {
                    // Reload the page section or update the table
                    if (data.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                });
            });
        });

        // Handle inline edit buttons
        document.querySelectorAll('.ajax-edit-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.dataset.url;
                const data = JSON.parse(this.dataset.data || '{}');
                
                // Populate modal or inline form with data
                populateEditForm(data);
            });
        });

        // Handle inline delete buttons
        document.querySelectorAll('.ajax-delete-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to delete this item?')) {
                    return;
                }

                const url = this.dataset.url;
                setLoadingState(this, true);

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'delete' })
                })
                .then(response => response.json())
                .then(data => {
                    setLoadingState(this, false);
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        
                        // Remove the row
                        const row = this.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    } else {
                        showToast(data.message || 'Delete failed', 'danger');
                    }
                })
                .catch(error => {
                    setLoadingState(this, false);
                    console.error('Error:', error);
                    showToast('An error occurred. Please try again.', 'danger');
                });
            });
        });
    }

    // Populate edit form with data
    function populateEditForm(data) {
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = data[key];
                } else {
                    input.value = data[key];
                }
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAjaxForms);
    } else {
        initAjaxForms();
    }

    // Re-initialize after dynamic content loads
    window.reinitAjaxForms = initAjaxForms;

})();
