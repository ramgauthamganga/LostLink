/**
 * LostLink — Centralized Reusable Item Details Component
 * Option C — Centered Large Sheet JavaScript Controller
 *
 * Implements:
 * - Singleton API: window.LostLinkItemDetails
 * - Deep linking & query string sync (?item=ITEM_ID)
 * - Browser Back/Forward navigation (popstate) without full reload
 * - Scroll position preservation & body scroll lock
 * - Image gallery with carousel, thumbnails, counter & touch swipe
 * - Focus trapping and Escape key dismiss
 * - Delegated card click listener across the application
 * - Inline Claim submission flow
 */

(function () {
  'use strict';

  class ItemDetailsModal {
    constructor() {
      this.backdrop = null;
      this.sheet = null;
      this.body = null;
      this.loadingEl = null;
      this.errorEl = null;
      this.contentEl = null;
      this.closeBtn = null;
      this.codeBadge = null;
      this.contextBadge = null;

      // Gallery elements
      this.viewportEl = null;
      this.mainImg = null;
      this.prevBtn = null;
      this.nextBtn = null;
      this.counterEl = null;
      this.currentNumEl = null;
      this.totalNumEl = null;
      this.thumbsTrack = null;

      // Metadata elements
      this.titleEl = null;
      this.badgeType = null;
      this.badgeStatus = null;
      this.valCategory = null;
      this.valLocation = null;
      this.valEventDate = null;
      this.valReported = null;
      this.valDescription = null;
      this.notesBox = null;
      this.valNotes = null;
      this.reporterAvatar = null;
      this.reporterName = null;
      this.reporterDept = null;
      this.claimsNotice = null;
      this.claimBox = null;
      this.claimForm = null;
      this.claimMessage = null;
      this.actionLeft = null;
      this.actionRight = null;

      // Contact Reporter Dialog Elements
      this.contactReporterBtn = null;
      this.contactBtnText = null;
      this.contactDialogBackdrop = null;
      this.contactDialog = null;
      this.contactDialogClose = null;
      this.contactItemTitle = null;
      this.contactItemMeta = null;
      this.contactRecipientName = null;
      this.contactForm = null;
      this.contactMessage = null;
      this.contactChars = null;
      this.contactFeedback = null;
      this.contactCancelBtn = null;
      this.contactSubmitBtn = null;

      // State tracking
      this.isOpen = false;
      this.currentItemId = null;
      this.currentItemData = null;
      this.currentImages = [];
      this.currentImageIndex = 0;
      this.previousActiveElement = null;
      this.savedScrollY = 0;
      this.cache = new Map();
      this.touchStartX = 0;
      this.touchEndX = 0;

      // Configuration
      this.baseUrl = '';
      this.defaultImage = '';
      this.defaultAvatar = '';

      this.init();
    }

    /**
     * Initialize DOM references and event listeners
     */
    init() {
      // Find DOM elements
      this.backdrop = document.getElementById('ll-item-details-backdrop');
      this.sheet = document.getElementById('ll-item-details-sheet');

      if (!this.backdrop || !this.sheet) {
        return; // Component HTML not included on this page
      }

      this.baseUrl = (this.sheet.dataset.baseUrl || '').replace(/\/+$/, '');
      this.defaultImage = this.sheet.dataset.defaultImage || '';
      this.defaultAvatar = this.sheet.dataset.defaultAvatar || '';

      this.body = document.getElementById('ll-item-sheet-body');
      this.loadingEl = document.getElementById('ll-item-loading');
      this.errorEl = document.getElementById('ll-item-error');
      this.contentEl = document.getElementById('ll-item-content');
      this.closeBtn = document.getElementById('ll-item-close-btn');
      this.codeBadge = document.getElementById('ll-item-code-badge');
      this.contextBadge = document.getElementById('ll-item-context-badge');

      // Gallery elements
      this.viewportEl = this.sheet.querySelector('.ll-gallery-viewport');
      this.mainImg = document.getElementById('ll-gallery-main-img');
      this.prevBtn = document.getElementById('ll-gallery-prev');
      this.nextBtn = document.getElementById('ll-gallery-next');
      this.counterEl = document.getElementById('ll-gallery-counter');
      this.currentNumEl = document.getElementById('ll-gallery-current');
      this.totalNumEl = document.getElementById('ll-gallery-total');
      this.thumbsTrack = document.getElementById('ll-gallery-thumbs');

      // Metadata elements
      this.titleEl = document.getElementById('ll-item-details-title');
      this.badgeType = document.getElementById('ll-badge-type');
      this.badgeStatus = document.getElementById('ll-badge-status');
      this.valCategory = document.getElementById('ll-val-category');
      this.valLocation = document.getElementById('ll-val-location');
      this.valEventDate = document.getElementById('ll-val-event-date');
      this.valReported = document.getElementById('ll-val-reported');
      this.valDescription = document.getElementById('ll-val-description');
      this.notesBox = document.getElementById('ll-additional-notes-box');
      this.valNotes = document.getElementById('ll-val-additional-note');
      this.reporterAvatar = document.getElementById('ll-reporter-avatar');
      this.reporterName = document.getElementById('ll-reporter-name');
      this.reporterDept = document.getElementById('ll-reporter-dept');
      this.claimsNotice = document.getElementById('ll-claims-notice');
      this.claimBox = document.getElementById('ll-claim-box');
      this.claimForm = document.getElementById('ll-claim-form');
      this.claimMessage = document.getElementById('ll-claim-message');
      this.actionLeft = document.getElementById('ll-action-left');
      this.actionRight = document.getElementById('ll-action-right');

      // Contact Dialog Elements
      this.contactReporterBtn = document.getElementById('ll-contact-reporter-btn');
      this.contactBtnText = document.getElementById('ll-contact-btn-text');
      this.contactDialogBackdrop = document.getElementById('ll-contact-dialog-backdrop');
      this.contactDialog = document.getElementById('ll-contact-dialog');
      this.contactDialogClose = document.getElementById('ll-contact-dialog-close');
      this.contactItemTitle = document.getElementById('ll-contact-item-title');
      this.contactItemMeta = document.getElementById('ll-contact-item-meta');
      this.contactRecipientName = document.getElementById('ll-contact-recipient-name');
      this.contactForm = document.getElementById('ll-contact-form');
      this.contactMessage = document.getElementById('ll-contact-message');
      this.contactChars = document.getElementById('ll-contact-chars');
      this.contactFeedback = document.getElementById('ll-contact-feedback');
      this.contactCancelBtn = document.getElementById('ll-contact-cancel-btn');
      this.contactSubmitBtn = document.getElementById('ll-contact-submit-btn');

      this.bindEvents();
      this.checkUrlForInitialItem();
    }

    /**
     * Bind all global, modal, and gallery listeners
     */
    bindEvents() {
      // Close button click
      this.closeBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.close();
      });

      // Backdrop click closes sheet
      this.backdrop?.addEventListener('click', (e) => {
        if (e.target === this.backdrop) {
          this.close();
        }
      });

      // Delegate close actions on buttons with [data-ll-close-sheet]
      this.sheet?.addEventListener('click', (e) => {
        if (e.target.closest('[data-ll-close-sheet]')) {
          e.preventDefault();
          this.close();
        }
      });

      // Contact Reporter button click
      this.contactReporterBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.handleContactReporterClick();
      });

      // Contact Dialog Close buttons
      this.contactDialogClose?.addEventListener('click', (e) => {
        e.preventDefault();
        this.closeContactDialog();
      });

      this.contactCancelBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.closeContactDialog();
      });

      // Close Contact dialog on its backdrop click
      this.contactDialogBackdrop?.addEventListener('click', (e) => {
        if (e.target === this.contactDialogBackdrop) {
          this.closeContactDialog();
        }
      });

      // Character counter for contact message
      this.contactMessage?.addEventListener('input', () => {
        if (this.contactChars && this.contactMessage) {
          this.contactChars.textContent = String(this.contactMessage.value.length);
        }
      });

      // Submit Contact Request form
      this.contactForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        this.submitContactRequest();
      });


      // Keyboard navigation (Escape to close, Arrows for gallery, Tab trapping)
      document.addEventListener('keydown', (e) => {
        if (!this.isOpen) return;

        if (e.key === 'Escape') {
          e.preventDefault();
          if (this.contactDialogBackdrop && !this.contactDialogBackdrop.hidden) {
            this.closeContactDialog();
            return;
          }
          this.close();
          return;
        }

        // Arrow keys for gallery when not typing in form inputs
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        if (activeTag !== 'textarea' && activeTag !== 'input') {
          if (e.key === 'ArrowLeft' && this.currentImages.length > 1) {
            e.preventDefault();
            this.prevImage();
          } else if (e.key === 'ArrowRight' && this.currentImages.length > 1) {
            e.preventDefault();
            this.nextImage();
          }
        }

        // Focus trap
        if (e.key === 'Tab') {
          this.handleFocusTrap(e);
        }
      });

      // Gallery Prev/Next buttons
      this.prevBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.prevImage();
      });

      this.nextBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.nextImage();
      });

      // Touch swipe gestures on mobile
      if (this.viewportEl) {
        this.viewportEl.addEventListener('touchstart', (e) => {
          this.touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        this.viewportEl.addEventListener('touchend', (e) => {
          this.touchEndX = e.changedTouches[0].screenX;
          this.handleSwipe();
        }, { passive: true });
      }

      // Claim form events
      const cancelClaimBtn = document.getElementById('ll-claim-cancel-btn');
      cancelClaimBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        this.hideClaimBox();
      });

      this.claimForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        this.submitClaim();
      });

      // Global Card Click Delegation — Bulletproof Interception
      document.addEventListener('click', (e) => {
        // 1. Explicitly ignore elements marked to prevent item detail
        if (e.target.closest('[data-prevent-item-detail]')) {
          return;
        }

        // 2. Locate detail trigger or card element
        const trigger = e.target.closest('[data-item-detail]');
        const card = e.target.closest('[data-item-id], .browse-item-card, .my-report-card, .item-row');

        if (!trigger && !card) return;

        // 3. Allow real secondary controls (e.g. delete button, status dropdown, form controls)
        // BUT do NOT block the card's main link (.browse-item-card-link, .card-image-link, or any <a> containing the item)
        const nestedAction = e.target.closest('button:not(#ll-item-close-btn):not(.ll-btn-close-icon), input, select, textarea, form, .dropdown, .dropdown-menu, .btn-delete, .action-btn, [data-action]');
        if (nestedAction && !nestedAction.hasAttribute('data-item-detail') && !nestedAction.hasAttribute('data-item-id')) {
          return;
        }

        // Check if clicked link is an external/unrelated link inside card
        const clickedLink = e.target.closest('a');
        if (clickedLink) {
          const isCardLink = clickedLink.classList.contains('browse-item-card-link') ||
                             clickedLink.classList.contains('card-image-link') ||
                             clickedLink.hasAttribute('data-item-id') ||
                             clickedLink.hasAttribute('data-item-detail') ||
                             clickedLink.closest('.browse-item-card, .my-report-card, .item-row') === card;
          if (!isCardLink && !clickedLink.hasAttribute('data-item-id') && !clickedLink.hasAttribute('data-item-detail')) {
            return;
          }
        }

        const targetEl = trigger || card;
        const itemId = targetEl.dataset.itemId || targetEl.getAttribute('data-item-id') ||
                       targetEl.querySelector('[data-item-id]')?.getAttribute('data-item-id');
        const context = targetEl.dataset.itemContext || targetEl.getAttribute('data-item-context') ||
                        targetEl.querySelector('[data-item-context]')?.getAttribute('data-item-context') || null;

        if (itemId) {
          e.preventDefault();
          e.stopPropagation();
          this.open(itemId, context);
        }
      });

      // Popstate navigation: handle browser Back and Forward without full reload
      window.addEventListener('popstate', (e) => {
        const urlParams = new URLSearchParams(window.location.search);
        const itemParam = urlParams.get('item');

        if (itemParam) {
          // Open or switch item from URL without pushing an additional history state
          if (itemParam !== this.currentItemId || !this.isOpen) {
            this.open(itemParam, null, false);
          }
        } else if (this.isOpen) {
          // Close sheet if open and item parameter was popped off
          this.close(false);
        }
      });
    }

    /**
     * Check current URL query string on initial page load
     */
    checkUrlForInitialItem() {
      const urlParams = new URLSearchParams(window.location.search);
      const itemParam = urlParams.get('item');
      if (itemParam) {
        // Auto-open on direct link load without pushing duplicate history
        this.open(itemParam, null, false);
      }
    }

    /**
     * Open Item Details Sheet
     * @param {string|number} itemId - Item ID or Item Code
     * @param {string|null} context - e.g. 'dashboard', 'browse', 'my_reports', 'claims'
     * @param {boolean} pushState - Whether to push state to history
     */
    open(itemId, context = null, pushState = true) {
      if (!this.sheet || !this.backdrop) return;

      const normalizedId = String(itemId).trim();
      if (!normalizedId) return;

      // Save focused element for accessibility restoration
      if (!this.isOpen) {
        this.previousActiveElement = document.activeElement;
        // Lock body scroll and save position
        this.savedScrollY = window.scrollY;
        document.body.style.overflow = 'hidden';
      }

      this.isOpen = true;
      this.currentItemId = normalizedId;

      // Update URL query parameter while strictly preserving other filters
      if (pushState) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('item', normalizedId);
        window.history.pushState({ lostlink_item: normalizedId }, '', currentUrl.toString());
      }

      // Update Context badge
      if (this.contextBadge) {
        if (context) {
          const formattedContext = context.replace(/[-_]/g, ' ');
          this.contextBadge.textContent = formattedContext;
          this.contextBadge.style.display = 'inline-block';
        } else {
          this.contextBadge.style.display = 'none';
        }
      }

      // Set temporary header badge
      if (this.codeBadge) {
        this.codeBadge.textContent = normalizedId.startsWith('ITM-') ? normalizedId : `#${normalizedId}`;
      }

      // Hide claim box
      this.hideClaimBox();

      // Show backdrop and sheet
      this.backdrop.hidden = false;
      this.sheet.hidden = false;

      // Force layout repaint then apply active class for smooth transition
      void this.sheet.offsetWidth;
      this.backdrop.classList.add('ll-open');
      this.sheet.classList.add('ll-open');
      this.sheet.setAttribute('aria-hidden', 'false');

      // Set initial focus to close button
      setTimeout(() => {
        this.closeBtn?.focus();
      }, 50);

      // Load item data
      this.loadItem(normalizedId, context);
    }

    /**
     * Close Item Details Sheet
     * @param {boolean} updateUrl - Whether to remove 'item' param from URL
     */
    close(updateUrl = true) {
      if (!this.isOpen || !this.sheet) return;

      this.isOpen = false;
      this.currentItemId = null;
      this.currentItemData = null;

      // Update URL if requested
      if (updateUrl) {
        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.has('item')) {
          currentUrl.searchParams.delete('item');
          window.history.pushState({}, '', currentUrl.toString());
        }
      }

      // Remove active classes to trigger exit transitions
      this.backdrop?.classList.remove('ll-open');
      this.sheet?.classList.remove('ll-open');
      this.sheet.setAttribute('aria-hidden', 'true');

      // Unlock body scroll and preserve previous scroll offset
      document.body.style.overflow = '';
      if (this.savedScrollY) {
        window.scrollTo(0, this.savedScrollY);
      }

      // Return focus to previously focused element
      if (this.previousActiveElement && typeof this.previousActiveElement.focus === 'function') {
        this.previousActiveElement.focus();
        this.previousActiveElement = null;
      }

      // Hide elements after transition completes
      setTimeout(() => {
        if (!this.isOpen) {
          if (this.backdrop) this.backdrop.hidden = true;
          if (this.sheet) this.sheet.hidden = true;
          this.hideClaimBox();
        }
      }, 240);
    }

    /**
     * Load Item Data from Backend API or In-Memory Cache
     * @param {string} itemId
     * @param {string|null} context
     */
    async loadItem(itemId, context = null) {
      // Reset views
      this.showState('loading');

      // Check cache
      if (this.cache.has(itemId)) {
        this.renderItem(this.cache.get(itemId), context);
        return;
      }

      try {
        const endpoint = `${this.baseUrl}/components/item-details/item-details.php?item=${encodeURIComponent(itemId)}&context=${encodeURIComponent(context || '')}`;
        const response = await fetch(endpoint, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!response.ok) {
          let errorMsg = 'The requested item could not be found.';
          try {
            const errData = await response.json();
            if (errData && errData.error) errorMsg = errData.error;
          } catch (_) {
            if (response.status === 404) errorMsg = 'Item not found or has been removed.';
          }
          throw new Error(errorMsg);
        }

        const resJson = await response.json();
        if (!resJson || !resJson.success || !resJson.data) {
          throw new Error(resJson?.error || 'Unable to retrieve item information.');
        }

        const data = resJson.data;

        // Cache item under current ID and canonical item code
        this.cache.set(itemId, data);
        if (data.item_code && data.item_code !== itemId) {
          this.cache.set(data.item_code, data);
        }
        if (data.id && String(data.id) !== itemId) {
          this.cache.set(String(data.id), data);
        }

        // If canonical code exists, quietly update URL to canonical code
        if (data.item_code) {
          const currentUrl = new URL(window.location.href);
          if (currentUrl.searchParams.get('item') !== data.item_code) {
            currentUrl.searchParams.set('item', data.item_code);
            window.history.replaceState({ lostlink_item: data.item_code }, '', currentUrl.toString());
          }
        }

        this.renderItem(data, context);

      } catch (err) {
        this.showError(err.message || 'Failed to load item details. Please check your network connection.');
      }
    }

    /**
     * Render Item Data into the Sheet DOM
     * @param {Object} item
     * @param {string|null} context
     */
    renderItem(item, context = null) {
      this.currentItemData = item;

      // Header Code Badge
      if (this.codeBadge) {
        this.codeBadge.textContent = item.item_code || `ITM-${item.id}`;
      }

      // Badges: Type & Status
      if (this.badgeType) {
        this.badgeType.className = `badge badge-${item.type === 'found' ? 'success' : 'danger'}`;
        this.badgeType.textContent = item.type ? item.type.toUpperCase() : 'UNKNOWN';
      }

      if (this.badgeStatus) {
        const statusMap = {
          'active': 'badge-success',
          'pending': 'badge-warning',
          'claimed': 'badge-primary',
          'returned': 'badge-info',
          'closed': 'badge-secondary'
        };
        const statusCls = statusMap[item.status] || 'badge-secondary';
        this.badgeStatus.className = `badge ${statusCls}`;
        this.badgeStatus.textContent = item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Unknown';
      }

      // Title
      if (this.titleEl) {
        this.titleEl.textContent = item.title || 'Untitled Item';
      }

      // Metadata
      if (this.valCategory) {
        const subcat = item.subcategory ? ` > ${item.subcategory}` : '';
        this.valCategory.textContent = `${item.category || 'Uncategorized'}${subcat}`;
      }

      if (this.valLocation) {
        this.valLocation.textContent = item.location || 'Not specified';
      }

      // Event Date (must display event_date, never substituted with created_at)
      if (this.valEventDate) {
        this.valEventDate.textContent = item.event_date_formatted || item.event_date || 'Unknown';
      }

      // Reported time
      if (this.valReported) {
        this.valReported.textContent = item.time_ago || item.created_at_formatted || 'Recently';
      }

      // Description
      if (this.valDescription) {
        this.valDescription.textContent = item.description || 'No description provided by the reporter.';
      }

      // Additional Notes
      if (this.notesBox && this.valNotes) {
        if (item.additional_note && item.additional_note.trim()) {
          this.valNotes.textContent = item.additional_note;
          this.notesBox.hidden = false;
        } else {
          this.notesBox.hidden = true;
        }
      }

      // Reporter Card
      if (this.reporterName) {
        this.reporterName.textContent = item.reporter?.name || 'Campus User';
      }
      if (this.reporterDept) {
        this.reporterDept.textContent = item.reporter?.department || 'Student';
      }
      if (this.reporterAvatar) {
        this.reporterAvatar.src = item.reporter?.avatar_url || this.defaultAvatar;
        this.reporterAvatar.onerror = () => {
          this.reporterAvatar.src = this.defaultAvatar;
        };
      }

      // Configure Contact Reporter Button
      if (this.contactReporterBtn) {
        if (item.permissions?.is_owner) {
          // Owner viewing own item: never show Contact Reporter
          this.contactReporterBtn.hidden = true;
        } else {
          this.contactReporterBtn.hidden = false;
          if (item.existing_conversation) {
            if (this.contactBtnText) this.contactBtnText.textContent = 'Open Conversation';
            this.contactReporterBtn.title = 'Open active conversation with reporter';
          } else if (item.existing_contact_request && item.existing_contact_request.status === 'pending') {
            if (this.contactBtnText) this.contactBtnText.textContent = 'Request Pending';
            this.contactReporterBtn.title = 'You have a pending contact request for this item';
          } else if (item.existing_contact_request && item.existing_contact_request.status === 'rejected') {
            if (this.contactBtnText) this.contactBtnText.textContent = 'Request Declined';
            this.contactReporterBtn.title = 'Your contact request was declined by the reporter';
          } else {
            if (this.contactBtnText) this.contactBtnText.textContent = 'Contact Reporter';
            this.contactReporterBtn.title = 'Send inquiry to reporter';
          }
        }
      }


      // Claims Notice
      if (this.claimsNotice) {
        if (item.existing_claim) {
          const claimStatus = item.existing_claim.status || 'pending';
          this.claimsNotice.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>You submitted a claim on this item (${item.existing_claim.created_at_formatted || 'recently'}) &mdash; <strong>Status: ${claimStatus.toUpperCase()}</strong></span>
          `;
          this.claimsNotice.hidden = false;
        } else if (item.permissions?.is_owner && item.pending_claims_count > 0) {
          this.claimsNotice.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span>This item has <strong>${item.pending_claims_count} pending claim(s)</strong> awaiting review.</span>
          `;
          this.claimsNotice.hidden = false;
        } else {
          this.claimsNotice.hidden = true;
        }
      }

      // Setup Image Gallery
      this.setupGallery(item.images || []);

      // Setup Action Footer
      this.setupActionFooter(item, context);

      // Show content
      this.showState('content');
    }

    /**
     * Setup Image Gallery and Thumbnails
     * @param {Array} images
     */
    setupGallery(images) {
      this.currentImages = images && images.length > 0 ? images : [{ url: this.defaultImage, is_default: true }];
      this.currentImageIndex = 0;

      const hasMultiple = this.currentImages.length > 1;

      // Set main image
      this.updateGalleryView(0);

      // Prev / Next button visibility
      if (this.prevBtn) this.prevBtn.hidden = !hasMultiple;
      if (this.nextBtn) this.nextBtn.hidden = !hasMultiple;
      if (this.counterEl) this.counterEl.hidden = !hasMultiple;

      // Render thumbnails
      if (this.thumbsTrack) {
        if (!hasMultiple) {
          this.thumbsTrack.hidden = true;
          this.thumbsTrack.innerHTML = '';
        } else {
          this.thumbsTrack.hidden = false;
          this.thumbsTrack.innerHTML = '';

          this.currentImages.forEach((img, idx) => {
            const thumbBtn = document.createElement('button');
            thumbBtn.type = 'button';
            thumbBtn.className = `ll-gallery-thumb ${idx === 0 ? 'active' : ''}`;
            thumbBtn.setAttribute('aria-label', `View image ${idx + 1}`);

            const thumbImg = document.createElement('img');
            thumbImg.src = img.url;
            thumbImg.alt = `Thumbnail ${idx + 1}`;
            thumbImg.loading = 'lazy';
            thumbImg.onerror = () => {
              thumbImg.src = this.defaultImage;
            };

            thumbBtn.appendChild(thumbImg);

            thumbBtn.addEventListener('click', (e) => {
              e.preventDefault();
              this.showImage(idx);
            });

            this.thumbsTrack.appendChild(thumbBtn);
          });
        }
      }
    }

    /**
     * Update Gallery Viewport with specific image index
     * @param {number} index
     */
    showImage(index) {
      if (index < 0 || index >= this.currentImages.length) return;

      this.currentImageIndex = index;
      this.updateGalleryView(index);

      // Update active thumbnail
      if (this.thumbsTrack) {
        const thumbs = this.thumbsTrack.querySelectorAll('.ll-gallery-thumb');
        thumbs.forEach((t, i) => {
          if (i === index) {
            t.classList.add('active');
            t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
          } else {
            t.classList.remove('active');
          }
        });
      }
    }

    updateGalleryView(index) {
      const imgData = this.currentImages[index];
      if (!imgData || !this.mainImg) return;

      // Smooth switch transition
      this.mainImg.classList.add('ll-img-switching');
      setTimeout(() => {
        this.mainImg.src = imgData.url;
        this.mainImg.onerror = () => {
          this.mainImg.src = this.defaultImage;
        };
        this.mainImg.classList.remove('ll-img-switching');
      }, 90);

      // Update counter
      if (this.currentNumEl) this.currentNumEl.textContent = String(index + 1);
      if (this.totalNumEl) this.totalNumEl.textContent = String(this.currentImages.length);
    }

    nextImage() {
      if (this.currentImages.length <= 1) return;
      const nextIdx = (this.currentImageIndex + 1) % this.currentImages.length;
      this.showImage(nextIdx);
    }

    prevImage() {
      if (this.currentImages.length <= 1) return;
      const prevIdx = (this.currentImageIndex - 1 + this.currentImages.length) % this.currentImages.length;
      this.showImage(prevIdx);
    }

    handleSwipe() {
      const diffX = this.touchEndX - this.touchStartX;
      if (Math.abs(diffX) > 40) {
        if (diffX < 0) {
          // Swiped left -> next image
          this.nextImage();
        } else {
          // Swiped right -> prev image
          this.prevImage();
        }
      }
    }

    /**
     * Setup Footer Actions according to User Role & Permissions
     * @param {Object} item
     * @param {string|null} context
     */
    setupActionFooter(item, context) {
      if (!this.actionLeft || !this.actionRight) return;

      // Left: Contextual info
      this.actionLeft.innerHTML = `
        <span>Reported ${this.escapeHtml(item.time_ago || item.created_at_formatted || 'recently')}</span>
      `;

      // Right: Context-aware actions
      this.actionRight.innerHTML = '';

      const perms = item.permissions || {};

      // 1. Claim button (if visitor can claim active item)
      if (perms.can_claim) {
        const claimBtn = document.createElement('button');
        claimBtn.type = 'button';
        claimBtn.className = 'btn btn-primary';
        claimBtn.innerHTML = `
          <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M9 11l3 3L22 4"></path>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
          </svg>
          Claim This Item
        `;
        claimBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.toggleClaimBox();
        });
        this.actionRight.appendChild(claimBtn);
      }

      // 2. Edit button (if owner or admin)
      if (perms.can_edit && item.urls?.edit_url) {
        const editLink = document.createElement('a');
        editLink.href = item.urls.edit_url;
        editLink.className = 'btn btn-outline';
        editLink.innerHTML = `
          <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
          Edit Item
        `;
        this.actionRight.appendChild(editLink);
      }

      // 3. View Claims list (if owner or admin and there are pending claims)
      if ((perms.is_owner || perms.is_admin) && item.pending_claims_count > 0 && perms.is_admin) {
        const claimsLink = document.createElement('a');
        claimsLink.href = item.urls?.claims_list_url || `${this.baseUrl}/admin/claims.php`;
        claimsLink.className = 'btn btn-outline';
        claimsLink.textContent = `Review Claims (${item.pending_claims_count})`;
        this.actionRight.appendChild(claimsLink);
      }

      // 4. Always provide Close Button
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'btn btn-secondary';
      closeBtn.setAttribute('data-ll-close-sheet', 'true');
      closeBtn.textContent = 'Close';
      this.actionRight.appendChild(closeBtn);
    }

    /**
     * Inline Claim Box Toggle
     */
    toggleClaimBox() {
      if (!this.claimBox) return;

      const isHidden = this.claimBox.hidden;
      this.claimBox.hidden = !isHidden;

      if (isHidden) {
        this.claimBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(() => {
          this.claimMessage?.focus();
        }, 100);
      }
    }

    hideClaimBox() {
      if (this.claimBox) {
        this.claimBox.hidden = true;
        if (this.claimMessage) this.claimMessage.value = '';
      }
    }

    /**
     * Submit ownership claim via AJAX
     */
    async submitClaim() {
      if (!this.currentItemData || !this.claimMessage) return;

      const message = this.claimMessage.value.trim();
      if (!message) {
        this.claimMessage.focus();
        return;
      }

      const submitBtn = document.getElementById('ll-claim-submit-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }

      try {
        const csrfToken = this.getCsrfToken() || this.currentItemData.csrf_token || '';
        const postUrl = this.currentItemData.urls?.claim_submit_url || `${this.baseUrl}/backend/claims/create.php`;

        const formData = new URLSearchParams();
        formData.append('item_id', String(this.currentItemData.id));
        formData.append('message', message);
        formData.append('csrf_token', csrfToken);

        const response = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const resJson = await response.json();

        if (!response.ok || !resJson.success) {
          throw new Error(resJson.error || 'Failed to submit claim');
        }

        // Show toast or alert
        if (window.LostLinkComponents?.Toast) {
          window.LostLinkComponents.Toast.success('Claim submitted successfully! The owner and admin have been notified.');
        } else {
          alert('Claim submitted successfully! The owner and admin have been notified.');
        }

        // Clear cache for this item to reflect the new claim
        this.cache.delete(String(this.currentItemData.id));
        if (this.currentItemData.item_code) {
          this.cache.delete(this.currentItemData.item_code);
        }

        // Reload item to refresh permissions and claims display
        await this.loadItem(this.currentItemData.id);

      } catch (err) {
        alert(err.message || 'Error submitting claim. Please try again.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Claim';
        }
      }
    }

    /**
     * Handle Contact Reporter button click
     */
    handleContactReporterClick() {
      if (!this.currentItemData) return;

      // If already has active conversation, navigate directly to it
      if (this.currentItemData.existing_conversation?.conversation_code) {
        const msgsUrl = this.currentItemData.urls?.messages_url || `${this.baseUrl}/messages/index.php`;
        window.location.href = `${msgsUrl}?c=${encodeURIComponent(this.currentItemData.existing_conversation.conversation_code)}`;
        return;
      }

      // If pending request, navigate to view request status
      if (this.currentItemData.existing_contact_request?.request_code && this.currentItemData.existing_contact_request.status === 'pending') {
        const msgsUrl = this.currentItemData.urls?.messages_url || `${this.baseUrl}/messages/index.php`;
        window.location.href = `${msgsUrl}?r=${encodeURIComponent(this.currentItemData.existing_contact_request.request_code)}`;
        return;
      }

      // Check if authenticated
      if (!this.currentItemData.permissions?.is_authenticated) {
        window.location.href = `${this.baseUrl}/auth/login.php`;
        return;
      }

      this.openContactDialog();
    }

    /**
     * Open the Contact Reporter Dialog
     */
    openContactDialog() {
      if (!this.contactDialogBackdrop || !this.currentItemData) return;

      const item = this.currentItemData;
      if (this.contactItemTitle) this.contactItemTitle.textContent = item.title || 'Item';
      if (this.contactItemMeta) {
        const typeStr = item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : 'Item';
        const locStr = item.location || 'Location unspecified';
        this.contactItemMeta.textContent = `${typeStr} • ${locStr}`;
      }
      if (this.contactRecipientName) {
        this.contactRecipientName.textContent = item.reporter?.name || 'Item Reporter';
      }
      if (this.contactMessage) {
        this.contactMessage.value = '';
      }
      if (this.contactChars) {
        this.contactChars.textContent = '0';
      }
      if (this.contactFeedback) {
        this.contactFeedback.hidden = true;
        this.contactFeedback.innerHTML = '';
        this.contactFeedback.className = 'll-contact-feedback';
      }
      if (this.contactSubmitBtn) {
        this.contactSubmitBtn.disabled = false;
        this.contactSubmitBtn.innerHTML = `
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          <span>Send Message</span>
        `;
      }

      this.contactDialogBackdrop.hidden = false;
      setTimeout(() => {
        this.contactMessage?.focus();
      }, 60);
    }

    /**
     * Close the Contact Reporter Dialog
     */
    closeContactDialog() {
      if (this.contactDialogBackdrop) {
        this.contactDialogBackdrop.hidden = true;
      }
    }

    /**
     * Submit Contact Request to backend API
     */
    async submitContactRequest() {
      if (!this.currentItemData || !this.contactMessage) return;

      const message = this.contactMessage.value.trim();
      if (!message) {
        this.showContactFeedback('Please type an initial message before sending.', 'error');
        this.contactMessage.focus();
        return;
      }

      if (this.contactSubmitBtn) {
        this.contactSubmitBtn.disabled = true;
        this.contactSubmitBtn.innerHTML = `
          <span class="ll-spinner-sm" aria-hidden="true"></span>
          <span>Sending...</span>
        `;
      }

      try {
        const csrfToken = this.getCsrfToken() || this.currentItemData.csrf_token || '';
        const postUrl = this.currentItemData.urls?.contact_submit_url || `${this.baseUrl}/backend/messages/contact_request.php?action=create`;

        const formData = new URLSearchParams();
        formData.append('action', 'create');
        formData.append('item_id', String(this.currentItemData.id));
        formData.append('item_code', String(this.currentItemData.item_code));
        formData.append('message', message);
        formData.append('csrf_token', csrfToken);

        const response = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const resJson = await response.json();

        if (!response.ok || !resJson.success) {
          throw new Error(resJson.error || 'Failed to send contact request.');
        }

        // Show clean success feedback
        this.showContactFeedback('Contact request sent successfully! The reporter has been notified.', 'success');

        // Update local item cache and state
        if (this.currentItemData) {
          this.currentItemData.existing_contact_request = {
            request_code: resJson.data?.request_code || '',
            status: 'pending'
          };
          if (this.contactBtnText) {
            this.contactBtnText.textContent = 'Request Pending';
          }
          if (this.contactReporterBtn) {
            this.contactReporterBtn.title = 'You have a pending contact request for this item';
          }
        }

        // Clear cached item so subsequent opens show updated state
        this.cache.delete(String(this.currentItemData.id));
        if (this.currentItemData.item_code) {
          this.cache.delete(this.currentItemData.item_code);
        }

        // Auto-close dialog after brief delay
        setTimeout(() => {
          this.closeContactDialog();
        }, 1600);

      } catch (err) {
        this.showContactFeedback(err.message || 'Error sending contact request. Please try again.', 'error');
        if (this.contactSubmitBtn) {
          this.contactSubmitBtn.disabled = false;
          this.contactSubmitBtn.innerHTML = `
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
            <span>Send Message</span>
          `;
        }
      }
    }

    showContactFeedback(msg, type = 'error') {
      if (!this.contactFeedback) return;
      this.contactFeedback.hidden = false;
      this.contactFeedback.className = `ll-contact-feedback ll-feedback-${type}`;
      this.contactFeedback.textContent = msg;
    }


    /**
     * Show/hide sheet views (loading, error, content)
     * @param {'loading'|'error'|'content'} state
     */
    showState(state) {
      if (this.loadingEl) this.loadingEl.hidden = state !== 'loading';
      if (this.errorEl) this.errorEl.hidden = state !== 'error';
      if (this.contentEl) this.contentEl.hidden = state !== 'content';
    }

    showError(message) {
      const errMsg = document.getElementById('ll-error-message');
      if (errMsg) errMsg.textContent = message;
      this.showState('error');
    }

    /**
     * Focus trap inside sheet
     */
    handleFocusTrap(e) {
      if (!this.sheet) return;

      const focusableElements = this.sheet.querySelectorAll(
        'button:not([hidden]):not([disabled]), [href]:not([hidden]), input:not([hidden]):not([disabled]), select:not([hidden]):not([disabled]), textarea:not([hidden]):not([disabled]), [tabindex]:not([tabindex="-1"]):not([hidden])'
      );

      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      }
    }

    getCsrfToken() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    escapeHtml(str) {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  }

  // Initialize and expose singleton
  function initItemDetails() {
    if (!window.LostLinkItemDetails) {
      window.LostLinkItemDetails = new ItemDetailsModal();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initItemDetails);
  } else {
    initItemDetails();
  }

})();
