/**
 * Mobile Form Enhancements
 * Improves form usability on mobile devices
 */

document.addEventListener('DOMContentLoaded', function() {
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        initMobileFormEnhancements();
    }
    
    // Re-initialize on resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const newIsMobile = window.innerWidth <= 768;
            if (newIsMobile && !document.body.classList.contains('mobile-form-enhanced')) {
                initMobileFormEnhancements();
            }
        }, 250);
    });
});

function initMobileFormEnhancements() {
    document.body.classList.add('mobile-form-enhanced');
    
    // 1. Auto-scroll to active form step
    enhanceStepNavigation();
    
    // 2. Improve date picker experience
    enhanceDatePickers();
    
    // 3. Add visual feedback for form validation
    enhanceFormValidation();
    
    // 4. Improve file upload experience
    enhanceFileUpload();
    
    // 5. Add character counter for textareas
    enhanceTextareas();
    
    // 6. Improve select dropdowns
    enhanceSelects();
    
    // 7. Add touch-friendly interactions
    addTouchInteractions();
    
    // 8. Prevent accidental form submission
    preventAccidentalSubmission();
    
    // 9. Add progress save functionality
    addProgressSave();
    
    console.log('Mobile form enhancements initialized');
}

function enhanceStepNavigation() {
    // Smooth scroll to top when changing steps
    const originalNextStep = window.nextStep;
    const originalPrevStep = window.prevStep;
    
    if (typeof originalNextStep === 'function') {
        window.nextStep = function(step) {
            originalNextStep(step);
            setTimeout(function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        };
    }
    
    if (typeof originalPrevStep === 'function') {
        window.prevStep = function(step) {
            originalPrevStep(step);
            setTimeout(function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        };
    }
    
    // Add step progress indicator at top
    addStickyProgressBar();
}

function addStickyProgressBar() {
    const progressContainer = document.querySelector('.progress-container');
    if (!progressContainer) return;
    
    // Create sticky progress bar
    const stickyProgress = document.createElement('div');
    stickyProgress.className = 'mobile-sticky-progress';
    stickyProgress.innerHTML = `
        <div class="sticky-progress-bar">
            <div class="sticky-progress-fill" style="width: 33.33%"></div>
        </div>
        <div class="sticky-progress-text">Step <span id="current-step-num">1</span> of 3</div>
    `;
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-sticky-progress {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            z-index: 1000;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: none;
        }
        
        .mobile-sticky-progress.visible {
            display: block;
        }
        
        .sticky-progress-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .sticky-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .sticky-progress-text {
            font-size: 0.85rem;
            font-weight: 600;
            color: #667eea;
            text-align: center;
        }
        
        body.mobile-form-enhanced {
            padding-top: 0;
        }
        
        body.mobile-form-enhanced.sticky-progress-active {
            padding-top: 60px;
        }
    `;
    document.head.appendChild(style);
    
    document.body.insertBefore(stickyProgress, document.body.firstChild);
    
    // Show/hide based on scroll
    let lastScroll = 0;
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 200) {
            stickyProgress.classList.add('visible');
            document.body.classList.add('sticky-progress-active');
        } else {
            stickyProgress.classList.remove('visible');
            document.body.classList.remove('sticky-progress-active');
        }
        
        lastScroll = currentScroll;
    });
    
    // Update progress on step change
    const observer = new MutationObserver(function() {
        updateStickyProgress();
    });
    
    const formSteps = document.querySelectorAll('.form-step');
    formSteps.forEach(step => {
        observer.observe(step, { attributes: true, attributeFilter: ['class'] });
    });
    
    function updateStickyProgress() {
        const activeStep = document.querySelector('.form-step.active');
        if (!activeStep) return;
        
        const stepNum = activeStep.id.replace('step', '');
        const progressFill = document.querySelector('.sticky-progress-fill');
        const stepNumText = document.getElementById('current-step-num');
        
        if (progressFill && stepNumText) {
            progressFill.style.width = (stepNum / 3 * 100) + '%';
            stepNumText.textContent = stepNum;
        }
    }
}

function enhanceDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        // Add calendar icon
        const wrapper = document.createElement('div');
        wrapper.className = 'mobile-date-wrapper';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const icon = document.createElement('i');
        icon.className = 'fas fa-calendar-alt mobile-date-icon';
        wrapper.appendChild(icon);
        
        // Add touch feedback
        input.addEventListener('focus', function() {
            wrapper.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            wrapper.classList.remove('focused');
        });
    });
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-date-wrapper {
            position: relative;
            display: block;
        }
        
        .mobile-date-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            pointer-events: none;
            font-size: 1.1rem;
        }
        
        .mobile-date-wrapper.focused .mobile-date-icon {
            color: #764ba2;
        }
        
        .mobile-date-wrapper input[type="date"] {
            padding-right: 40px !important;
        }
    `;
    document.head.appendChild(style);
}

function enhanceFormValidation() {
    const form = document.querySelector('form');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        // Real-time validation feedback
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value) {
                this.classList.add('is-invalid');
                showValidationMessage(this, 'This field is required');
            } else if (this.validity && !this.validity.valid) {
                this.classList.add('is-invalid');
                showValidationMessage(this, this.validationMessage);
            } else {
                this.classList.remove('is-invalid');
                hideValidationMessage(this);
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                if (this.validity.valid && this.value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    hideValidationMessage(this);
                }
            }
        });
    });
    
    function showValidationMessage(input, message) {
        let feedback = input.parentNode.querySelector('.mobile-invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'mobile-invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    }
    
    function hideValidationMessage(input) {
        const feedback = input.parentNode.querySelector('.mobile-invalid-feedback');
        if (feedback) {
            feedback.style.display = 'none';
        }
    }
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            padding: 0.5rem;
            background: #f8d7da;
            border-radius: 6px;
            border-left: 3px solid #dc3545;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
            animation: shake 0.3s ease;
        }
        
        .is-valid {
            border-color: #28a745 !important;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);
}

function enhanceFileUpload() {
    const fileInput = document.getElementById('attachment');
    if (!fileInput) return;
    
    const wrapper = fileInput.closest('.file-upload-wrapper');
    if (!wrapper) return;
    
    // Add drag and drop
    wrapper.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    
    wrapper.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });
    
    wrapper.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });
    
    // Show preview on file select
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            showFilePreview(this.files[0]);
        }
    });
    
    function showFilePreview(file) {
        const preview = document.getElementById('document-preview');
        if (!preview) return;
        
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileIcon = getFileIcon(file.type);
        
        preview.innerHTML = `
            <div class="file-preview-card">
                <div class="file-preview-icon">
                    <i class="${fileIcon}"></i>
                </div>
                <div class="file-preview-info">
                    <div class="file-preview-name">${file.name}</div>
                    <div class="file-preview-size">${fileSize} MB</div>
                </div>
                <button type="button" class="file-preview-remove" onclick="removeFilePreview()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }
    
    function getFileIcon(type) {
        if (type.includes('pdf')) return 'fas fa-file-pdf text-danger';
        if (type.includes('word')) return 'fas fa-file-word text-primary';
        if (type.includes('image')) return 'fas fa-file-image text-success';
        return 'fas fa-file text-secondary';
    }
    
    // Global function to remove file
    window.removeFilePreview = function() {
        fileInput.value = '';
        document.getElementById('document-preview').innerHTML = '';
    };
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .file-upload-wrapper.drag-over {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea !important;
        }
        
        .file-preview-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .file-preview-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #2c3e50;
            word-break: break-word;
        }
        
        .file-preview-size {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .file-preview-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .file-preview-remove:active {
            transform: scale(0.95);
        }
    `;
    document.head.appendChild(style);
}

function enhanceTextareas() {
    const textareas = document.querySelectorAll('textarea');
    
    textareas.forEach(textarea => {
        // Auto-resize
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Character counter
        const maxLength = textarea.getAttribute('maxlength') || 500;
        const counter = document.createElement('div');
        counter.className = 'mobile-char-counter';
        counter.innerHTML = `<span class="current">0</span> / ${maxLength}`;
        
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const current = this.value.length;
            counter.querySelector('.current').textContent = current;
            
            if (current > maxLength * 0.9) {
                counter.classList.add('warning');
            } else {
                counter.classList.remove('warning');
            }
        });
    });
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .mobile-char-counter.warning {
            color: #dc3545;
            font-weight: 600;
        }
        
        .mobile-char-counter .current {
            font-weight: 600;
            color: #667eea;
        }
        
        textarea {
            resize: none;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);
}

function enhanceSelects() {
    const selects = document.querySelectorAll('select');
    
    selects.forEach(select => {
        // Add custom arrow
        const wrapper = document.createElement('div');
        wrapper.className = 'mobile-select-wrapper';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        
        const arrow = document.createElement('i');
        arrow.className = 'fas fa-chevron-down mobile-select-arrow';
        wrapper.appendChild(arrow);
        
        // Rotate arrow on focus
        select.addEventListener('focus', function() {
            arrow.style.transform = 'translateY(-50%) rotate(180deg)';
        });
        
        select.addEventListener('blur', function() {
            arrow.style.transform = 'translateY(-50%) rotate(0deg)';
        });
    });
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-select-wrapper {
            position: relative;
            display: block;
        }
        
        .mobile-select-arrow {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            pointer-events: none;
            transition: transform 0.3s ease;
            font-size: 0.9rem;
        }
        
        .mobile-select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 40px !important;
        }
    `;
    document.head.appendChild(style);
}

function addTouchInteractions() {
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('touchstart', function(e) {
            const ripple = document.createElement('span');
            ripple.className = 'mobile-ripple';
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.touches[0].clientX - rect.left - size / 2;
            const y = e.touches[0].clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .mobile-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

function preventAccidentalSubmission() {
    const form = document.querySelector('form');
    if (!form) return;
    
    let isSubmitting = false;
    
    form.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        
        isSubmitting = true;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            // Reset after 10 seconds as fallback
            setTimeout(() => {
                isSubmitting = false;
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        }
    });
}

function addProgressSave() {
    const form = document.querySelector('form');
    if (!form) return;
    
    // Save form data to localStorage on input
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        // Load saved value
        const savedValue = localStorage.getItem('leave_form_' + input.name);
        if (savedValue && !input.value && input.type !== 'file') {
            input.value = savedValue;
        }
        
        // Save on change
        input.addEventListener('change', function() {
            if (this.type !== 'file') {
                localStorage.setItem('leave_form_' + this.name, this.value);
            }
        });
    });
    
    // Clear saved data on successful submission
    form.addEventListener('submit', function() {
        setTimeout(() => {
            inputs.forEach(input => {
                localStorage.removeItem('leave_form_' + input.name);
            });
        }, 1000);
    });
    
    // Add clear draft button
    const clearDraftBtn = document.createElement('button');
    clearDraftBtn.type = 'button';
    clearDraftBtn.className = 'btn btn-outline-secondary btn-sm mt-2';
    clearDraftBtn.innerHTML = '<i class="fas fa-eraser me-2"></i>Clear Draft';
    clearDraftBtn.onclick = function() {
        if (confirm('Clear all saved form data?')) {
            inputs.forEach(input => {
                localStorage.removeItem('leave_form_' + input.name);
                if (input.type !== 'file') {
                    input.value = '';
                }
            });
            alert('Draft cleared!');
        }
    };
    
    const firstStep = document.getElementById('step1');
    if (firstStep) {
        firstStep.appendChild(clearDraftBtn);
    }
}
