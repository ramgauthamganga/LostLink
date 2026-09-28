/**
 * Report Item Page — Client-Side Interactions
 * Sidebar/dropdown/theme handled by components.js and theme.js.
 */
document.addEventListener('DOMContentLoaded', () => {

    const scrollContainer = document.querySelector('.app-main-content');

    // =========================================================================
    // REPORT TYPE TOGGLE
    // =========================================================================
    const isEditMode = Boolean(window.LOSTLINK_IS_EDIT_MODE);
    const urlParams = new URLSearchParams(window.location.search);
    const typeLostBtn = document.getElementById('type-lost');
    const typeFoundBtn = document.getElementById('type-found');
    const initialType = typeFoundBtn && typeFoundBtn.classList.contains('active') ? 'found' : (urlParams.get('type') === 'found' ? 'found' : 'lost');
    let reportType = initialType;
    let isTypeLocked = isEditMode;
    const headingEl = document.getElementById('report-heading');
    const subtitleEl = document.getElementById('report-subtitle');
    const step2Subtitle = document.getElementById('step2-subtitle');

    function updateReportType(type) {
        if (isTypeLocked) return;
        reportType = type;
        if (typeLostBtn && typeFoundBtn) {
            typeLostBtn.classList.toggle('active', type === 'lost');
            typeFoundBtn.classList.toggle('active', type === 'found');
        }
        if (headingEl) {
            headingEl.textContent = type === 'lost' ? 'Report a Lost Item' : 'Report a Found Item';
        }
        if (subtitleEl) {
            subtitleEl.textContent = type === 'lost'
                ? 'Help others by reporting your lost item'
                : 'Help reunite this item with its owner';
        }
        if (step2Subtitle) {
            step2Subtitle.textContent = type === 'lost'
                ? 'Where and when did you lose this item?'
                : 'Where and when did you find this item?';
        }
    }

    // Initialize with current type if not editing
    if (!isEditMode) {
        updateReportType(initialType);
    }

    if (typeLostBtn) typeLostBtn.addEventListener('click', () => updateReportType('lost'));
    if (typeFoundBtn) typeFoundBtn.addEventListener('click', () => updateReportType('found'));

    // =========================================================================
    // STEP NAVIGATION
    // =========================================================================
    let currentStep = 1;
    const totalSteps = 4;
    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');
    const btnSubmit = document.getElementById('btn-submit');
    const btnCancel = document.getElementById('btn-cancel');
    const stepElements = document.querySelectorAll('.report-stepper .step');
    const stepLines = document.querySelectorAll('.report-stepper .step-line');
    const formSteps = document.querySelectorAll('.form-step');

    function updateStepperUI() {
        stepElements.forEach(el => {
            const num = parseInt(el.getAttribute('data-step'));
            const circle = el.querySelector('.step-circle');
            el.classList.remove('active', 'completed');
            if (num === currentStep) {
                el.classList.add('active');
                circle.innerHTML = num;
            } else if (num < currentStep) {
                el.classList.add('completed');
                circle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            } else {
                circle.innerHTML = num;
            }
        });
        stepLines.forEach((line, i) => {
            line.classList.toggle('completed', i + 1 < currentStep);
        });
        formSteps.forEach(el => el.classList.remove('active'));
        const activeStep = document.getElementById('step-' + currentStep);
        if (activeStep) activeStep.classList.add('active');

        if (btnPrev) btnPrev.style.display = currentStep > 1 ? 'inline-flex' : 'none';
        if (btnCancel) btnCancel.style.display = currentStep === 1 ? 'inline-flex' : 'none';
        if (btnNext) btnNext.style.display = currentStep < totalSteps ? 'inline-flex' : 'none';
        if (btnSubmit) btnSubmit.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';

        if (scrollContainer) scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================
    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
        document.querySelectorAll('.field-invalid').forEach(el => el.classList.remove('field-invalid'));
    }

    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById('error-' + fieldId);
        if (field) field.classList.add('field-invalid');
        if (error) error.textContent = message;
    }

    function validateStep(step) {
        clearErrors();
        let valid = true;

        if (step === 1) {
            const category = document.getElementById('category');
            const itemName = document.getElementById('item_name');
            const description = document.getElementById('description');

            if (!category || !category.value) {
                showError('category', 'Please select a category');
                valid = false;
            }
            if (!itemName || !itemName.value.trim()) {
                showError('item_name', 'Please enter the item name');
                valid = false;
            }
            if (!description || !description.value.trim()) {
                showError('description', 'Please enter a description');
                valid = false;
            }
        } else if (step === 2) {
            const location = document.getElementById('location');
            const reportDate = document.getElementById('report_date');

            if (!location || !location.value.trim()) {
                showError('location', 'Please enter the location');
                valid = false;
            }
            if (!reportDate || !reportDate.value) {
                showError('report_date', 'Please select a date');
                valid = false;
            }
        }
        // Steps 3 and 4 have no required fields
        return valid;
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (!validateStep(currentStep)) return;
            if (currentStep === 1) {
                isTypeLocked = true;
                if (typeLostBtn) typeLostBtn.classList.add('locked');
                if (typeFoundBtn) typeFoundBtn.classList.add('locked');
            }
            if (currentStep < totalSteps) {
                currentStep++;
                if (currentStep === totalSteps) populateReview();
                updateStepperUI();
            }
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (currentStep > 1) {
                clearErrors();
                currentStep--;
                updateStepperUI();
            }
        });
    }
    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            window.location.href = '../dashboard/dashboard.php';
        });
    }
    if (btnSubmit) {
        btnSubmit.addEventListener('click', async () => {
            // Prevent duplicate submissions
            if (btnSubmit.disabled) return;

            // Final frontend validation (just in case)
            if (!validateStep(1) || !validateStep(2)) {
                alert('Please fill out all required fields before submitting.');
                return;
            }

            // Temporarily disable the Submit button and show loading state
            const originalText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = isEditMode ? 'Updating...' : 'Submitting...';
            btnSubmit.style.opacity = '0.7';
            btnSubmit.style.cursor = 'not-allowed';

            try {
                // Build FormData
                const formData = new FormData();

                // Edit parameters if in Edit Mode
                if (isEditMode) {
                    const editCodeInput = document.getElementById('edit_item_code');
                    if (editCodeInput && editCodeInput.value) {
                        formData.append('edit_item_code', editCodeInput.value);
                    }
                    formData.append('removed_image_ids', JSON.stringify(removedImageIds));
                }

                // CSRF Token
                const csrfInput = document.getElementById('csrf_token') || document.querySelector('meta[name="csrf-token"]');
                const csrfVal = csrfInput ? (csrfInput.value || csrfInput.getAttribute('content')) : '';
                if (csrfVal) {
                    formData.append('csrf_token', csrfVal);
                }
                
                // Item Details
                formData.append('type', reportType);
                formData.append('category', document.getElementById('category').value);
                formData.append('item_name', document.getElementById('item_name').value);
                formData.append('description', document.getElementById('description').value);
                
                // Location & Time
                formData.append('location', document.getElementById('location').value);
                formData.append('report_date', document.getElementById('report_date').value);
                formData.append('report_time', document.getElementById('report_time').value);
                
                // Additional Info
                formData.append('distinguishing_features', document.getElementById('distinguishing_features').value);
                formData.append('verification_method', document.getElementById('verification_method').value);
                formData.append('verification_detail', document.getElementById('verification_detail').value);
                formData.append('contact_preference', document.getElementById('contact_preference').value);

                // Images
                uploadedFiles.forEach((item) => {
                    formData.append('images[]', item.file);
                });

                // Send POST request to backend
                const response = await fetch('../backend/items/report.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                    // Do NOT set Content-Type header when using FormData
                });

                // Check if response is valid JSON before parsing
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid server response:', text);
                    throw new Error('The server returned an invalid response.');
                }

                if (response.ok && result.success) {
                    // Success!
                    showSuccessModal(result);
                } else {
                    // Handle Validation / Server Errors
                    if (result.errors) {
                        let errorMsg = 'Please correct the following errors:\n\n';
                        
                        // Handle associative array (field errors) or sequential array (upload errors)
                        if (Array.isArray(result.errors)) {
                            result.errors.forEach(err => errorMsg += `- ${err}\n`);
                        } else {
                            for (const [field, msg] of Object.entries(result.errors)) {
                                errorMsg += `- ${msg}\n`;
                                // Highlight the field visually if it exists
                                showError(field, msg);
                            }
                        }
                        alert(errorMsg);
                    } else {
                        alert('An unknown error occurred while submitting your report.');
                    }
                    
                    // Restore button
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = originalText;
                    btnSubmit.style.opacity = '1';
                    btnSubmit.style.cursor = 'pointer';
                }

            } catch (error) {
                console.error('Submission error:', error);
                alert('Connection error. Please check your network and try again.');
                
                // Restore button
                btnSubmit.disabled = false;
                btnSubmit.textContent = originalText;
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
            }
        });
    }

    updateStepperUI();

    // =========================================================================
    // CHARACTER COUNTER
    // =========================================================================
    const descInput = document.getElementById('description');
    const charCount = document.getElementById('desc-char-count');
    if (descInput && charCount) {
        charCount.textContent = descInput.value.length + ' / ' + (descInput.getAttribute('maxlength') || 300);
        descInput.addEventListener('input', () => {
            charCount.textContent = descInput.value.length + ' / ' + (descInput.getAttribute('maxlength') || 300);
        });
    }

    // =========================================================================
    // PHOTO UPLOAD & PREVIEWS
    // =========================================================================
    const uploadTrigger = document.getElementById('upload-trigger');
    const fileInput = document.getElementById('file-input');
    const previewsContainer = document.getElementById('photo-previews');
    const uploadNotice = document.getElementById('upload-notice');
    const MAX_PHOTOS = 5;
    let uploadedFiles = []; // Array of { file, dataUrl }
    let existingImages = (window.LOSTLINK_EXISTING_IMAGES && Array.isArray(window.LOSTLINK_EXISTING_IMAGES))
        ? window.LOSTLINK_EXISTING_IMAGES.map(img => ({ ...img }))
        : [];
    let removedImageIds = [];

    function renderPreviews() {
        if (!previewsContainer) return;
        previewsContainer.innerHTML = '';

        // Render existing images first
        existingImages.forEach((img, idx) => {
            const div = document.createElement('div');
            div.className = 'photo-preview-item';
            div.setAttribute('data-existing-id', img.id);
            div.innerHTML = `
                <img src="${img.url}" alt="Existing Photo ${idx + 1}">
                <button type="button" class="remove-photo" data-type="existing" data-id="${img.id}" aria-label="Remove photo">&times;</button>
            `;
            previewsContainer.appendChild(div);
        });

        // Render newly uploaded files
        uploadedFiles.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'photo-preview-item';
            div.innerHTML = `
                <img src="${item.dataUrl}" alt="Photo ${existingImages.length + index + 1}">
                <button type="button" class="remove-photo" data-type="new" data-index="${index}" aria-label="Remove photo">&times;</button>
            `;
            previewsContainer.appendChild(div);
        });

        // Attach remove handlers
        previewsContainer.querySelectorAll('.remove-photo').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const type = btn.getAttribute('data-type');
                if (type === 'existing') {
                    const id = parseInt(btn.getAttribute('data-id'), 10);
                    if (!removedImageIds.includes(id)) {
                        removedImageIds.push(id);
                    }
                    existingImages = existingImages.filter(img => img.id !== id);
                } else {
                    const idx = parseInt(btn.getAttribute('data-index'), 10);
                    uploadedFiles.splice(idx, 1);
                }
                renderPreviews();
                hideNotice();
            });
        });
    }

    function hideNotice() {
        if (uploadNotice) {
            uploadNotice.classList.remove('visible');
            uploadNotice.textContent = '';
        }
    }

    function addFiles(fileList) {
        let discarded = 0;
        const files = Array.from(fileList);
        files.forEach(file => {
            if ((existingImages.length + uploadedFiles.length) >= MAX_PHOTOS) {
                discarded++;
                return;
            }
            if (!file.type.match(/^image\/(jpeg|png)$/)) return;
            if (file.size > 5 * 1024 * 1024) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                if ((existingImages.length + uploadedFiles.length) < MAX_PHOTOS) {
                    uploadedFiles.push({ file: file, dataUrl: e.target.result });
                    renderPreviews();
                }
            };
            reader.readAsDataURL(file);
        });

        if (discarded > 0 && uploadNotice) {
            uploadNotice.textContent = 'Maximum 5 photos allowed. ' + discarded + ' extra photo(s) ignored.';
            uploadNotice.classList.add('visible');
        }
    }

    // Initialize existing photos preview if in edit mode
    renderPreviews();

    if (uploadTrigger && fileInput) {
        uploadTrigger.addEventListener('click', () => fileInput.click());
        uploadTrigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                addFiles(fileInput.files);
                fileInput.value = '';
            }
        });
        uploadTrigger.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadTrigger.classList.add('drag-over');
        });
        uploadTrigger.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadTrigger.classList.remove('drag-over');
        });
        uploadTrigger.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadTrigger.classList.remove('drag-over');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                addFiles(e.dataTransfer.files);
            }
        });
    }

    // =========================================================================
    // REVIEW STEP
    // =========================================================================
    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function populateReview() {
        const summary = document.getElementById('review-summary');
        if (!summary) return;

        const category = document.getElementById('category');
        const itemName = document.getElementById('item_name');
        const description = document.getElementById('description');
        const location = document.getElementById('location');
        const reportDate = document.getElementById('report_date');
        const reportTime = document.getElementById('report_time');
        const features = document.getElementById('distinguishing_features');
        const verifyMethod = document.getElementById('verification_method');
        const verifyDetail = document.getElementById('verification_detail');
        const contact = document.getElementById('contact_preference');

        const typeLabel = reportType === 'lost' ? 'Lost Item' : 'Found Item';
        const categoryText = category && category.value ? category.options[category.selectedIndex].text : 'Not selected';

        let html = '';

        // Item Details Group
        html += '<div class="review-group">';
        html += '<h4 class="review-group-title">Item Details</h4>';
        html += `<div class="review-row"><span class="review-label">Report Type</span><span class="review-value">${esc(typeLabel)}</span></div>`;
        html += `<div class="review-row"><span class="review-label">Category</span><span class="review-value">${esc(categoryText)}</span></div>`;
        html += `<div class="review-row"><span class="review-label">Item Name</span><span class="review-value">${esc(itemName ? itemName.value : '')}</span></div>`;
        html += `<div class="review-row"><span class="review-label">Description</span><span class="review-value">${esc(description ? description.value : '')}</span></div>`;
        html += '</div>';

        // Location & Time Group
        html += '<div class="review-group">';
        html += '<h4 class="review-group-title">Location & Time</h4>';
        html += `<div class="review-row"><span class="review-label">Location</span><span class="review-value">${esc(location ? location.value : '')}</span></div>`;
        html += `<div class="review-row"><span class="review-label">Date</span><span class="review-value">${esc(reportDate ? reportDate.value : '')}</span></div>`;
        if (reportTime && reportTime.value) {
            html += `<div class="review-row"><span class="review-label">Time</span><span class="review-value">${esc(reportTime.value)}</span></div>`;
        }
        html += '</div>';

        // Additional Info Group
        const hasFeatures = features && features.value.trim();
        const hasVerifyMethod = verifyMethod && verifyMethod.value;
        const hasVerifyDetail = verifyDetail && verifyDetail.value.trim();
        
        html += '<div class="review-group">';
        html += '<h4 class="review-group-title">Additional Info</h4>';
        if (hasFeatures) {
            html += `<div class="review-row"><span class="review-label">Features</span><span class="review-value">${esc(features.value)}</span></div>`;
        }
        if (hasVerifyMethod) {
            html += `<div class="review-row"><span class="review-label">Verification</span><span class="review-value">${esc(verifyMethod.options[verifyMethod.selectedIndex].text)}</span></div>`;
        }
        if (hasVerifyDetail) {
            html += `<div class="review-row"><span class="review-label">Verification Detail</span><span class="review-value">${esc(verifyDetail.value)}</span></div>`;
        }
        if (contact && contact.value) {
            html += `<div class="review-row"><span class="review-label">Contact</span><span class="review-value">${esc(contact.options[contact.selectedIndex].text)}</span></div>`;
        }
        html += '</div>';

        // Photos Group
        const totalPhotosCount = existingImages.length + uploadedFiles.length;
        if (totalPhotosCount > 0) {
            html += '<div class="review-group">';
            html += `<h4 class="review-group-title">Photos (${totalPhotosCount})</h4>`;
            html += '<div class="review-photos">';
            existingImages.forEach((img, i) => {
                html += `<img src="${img.url}" alt="Existing Photo ${i + 1}">`;
            });
            uploadedFiles.forEach((item, i) => {
                html += `<img src="${item.dataUrl}" alt="Photo ${existingImages.length + i + 1}">`;
            });
            html += '</div>';
            html += '</div>';
        }

        summary.innerHTML = html;
    }

});

function showSuccessModal(result) {
    // Prevent duplicate modals
    if (document.getElementById('success-modal-backdrop')) {
        return;
    }

    const isEdit = Boolean(result.is_edit || window.LOSTLINK_IS_EDIT_MODE);
    const backdrop = document.createElement('div');
    backdrop.id = 'success-modal-backdrop';
    backdrop.className = 'success-modal-backdrop';
    backdrop.setAttribute('role', 'dialog');
    backdrop.setAttribute('aria-modal', 'true');

    // Build internal HTML safely
    let reportIdHtml = '';
    if (result.item_code || result.report_id) {
        const idToDisplay = result.item_code || result.report_id;
        // Prevent XSS
        const safeId = String(idToDisplay).replace(/</g, "&lt;").replace(/>/g, "&gt;");
        reportIdHtml = `<div class="success-report-id">Report ID: #${safeId}</div>`;
    }

    const titleText = isEdit ? 'Report Updated Successfully' : 'Report Submitted Successfully';
    const descText = isEdit 
        ? 'Your report has been updated successfully and the changes are now active.' 
        : 'Your report has been submitted successfully and is now recorded in LostLink.';
    const btnText = isEdit ? 'View Updated Item' : 'Continue to Dashboard';

    backdrop.innerHTML = `
        <div class="success-modal-card">
            <div class="success-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h2>${titleText}</h2>
            <p>${descText}</p>
            ${reportIdHtml}
            <button id="success-continue-btn" class="btn btn-primary">${btnText}</button>
        </div>
    `;

    document.body.appendChild(backdrop);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';

    // Trigger entrance animation
    requestAnimationFrame(() => {
        backdrop.classList.add('show');
    });

    let redirectTimer = null;

    const navigateTarget = () => {
        if (redirectTimer) clearTimeout(redirectTimer);
        document.body.style.overflow = '';
        if (isEdit && result.item_code) {
            window.location.href = 'browse.php?item=' + encodeURIComponent(result.item_code);
        } else {
            window.location.href = '../dashboard/dashboard.php';
        }
    };

    const continueBtn = document.getElementById('success-continue-btn');
    if (continueBtn) {
        continueBtn.addEventListener('click', navigateTarget);
        continueBtn.focus();
    }

    // Auto redirect after 3 seconds
    redirectTimer = setTimeout(navigateTarget, 3000);
}
