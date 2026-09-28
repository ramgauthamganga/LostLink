/**
 * ==========================================================================
 * LOSTLINK SETTINGS JAVASCRIPT (settings.js)
 * ==========================================================================
 * Interactions for the continuous scroll-spy Settings page:
 * - IntersectionObserver / Container scroll spy for 6 stacked sections
 * - Sticky navigation offset & sticky elevation shadow
 * - Mobile horizontal active-pill centering
 * - Smooth anchor navigation with URL hash synchronization
 * - Account information read-only view vs. Edit Profile workflow
 * - Avatar image selection, live client preview, save, and discard
 * - Security overview vs. Change Password form workflow
 * - Password visibility toggles & client-side validation
 * - Appearance theme selector integrated with theme.js and localStorage
 * - Dismissible alerts
 */

(function () {
  'use strict';

  const SECTION_IDS = ['account', 'security', 'notifications', 'appearance', 'privacy', 'about'];
  let isManualScrolling = false;
  let manualScrollTimeout = null;

  /**
   * Helper to select DOM elements safely.
   */
  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

  /**
   * Horizontally centers the active navigation pill inside the scrollable tabs bar.
   * Particularly vital on mobile viewports.
   * 
   * @param {string} sectionId Target section ID
   * @param {boolean} smooth Whether to use smooth animation
   */
  function centerActiveTabPill(sectionId, smooth = true) {
    const activeBtn = $(`.settings-tab-btn[data-tab="${sectionId}"]`);
    if (!activeBtn) return;

    const tabsContainer = activeBtn.closest('.settings-tabs');
    if (!tabsContainer) return;

    const containerWidth = tabsContainer.offsetWidth;
    const btnLeft = activeBtn.offsetLeft;
    const btnWidth = activeBtn.offsetWidth;
    const targetScrollLeft = btnLeft - (containerWidth / 2) + (btnWidth / 2);

    tabsContainer.scrollTo({
      left: Math.max(0, targetScrollLeft),
      behavior: smooth ? 'smooth' : 'auto'
    });
  }

  /**
   * Sets the active navigation pill and updates accessibility attributes.
   * 
   * @param {string} sectionId Active section ID
   * @param {boolean} updateUrlHash Whether to sync the URL hash
   */
  function setActivePill(sectionId, updateUrlHash = false) {
    if (!SECTION_IDS.includes(sectionId)) return;

    const tabButtons = $$('.settings-tab-btn');
    tabButtons.forEach(btn => {
      const matches = btn.getAttribute('data-tab') === sectionId;
      btn.classList.toggle('active', matches);
      btn.setAttribute('aria-selected', matches ? 'true' : 'false');
    });

    centerActiveTabPill(sectionId, true);

    if (updateUrlHash && window.history && window.history.replaceState) {
      window.history.replaceState(null, '', `#${sectionId}`);
    }
  }

  /**
   * Initialize Sticky Scroll-Spy Navigation
   */
  function initScrollSpy() {
    const scrollContainer = $('.app-main-content');
    const stickyNav = $('#settings-sticky-nav');
    const sections = SECTION_IDS.map(id => document.getElementById(id)).filter(Boolean);

    if (!sections.length) return;

    let lastActiveSection = '';

    const handleScroll = () => {
      const containerScrollTop = scrollContainer ? scrollContainer.scrollTop : 0;
      const windowScrollTop = window.scrollY || document.documentElement.scrollTop || 0;
      const scrollTop = Math.max(containerScrollTop, windowScrollTop);

      // 1. Sticky elevation shadow toggle
      if (stickyNav) {
        const isMobile = window.innerWidth <= 768;
        const isStuck = isMobile
          ? (stickyNav.getBoundingClientRect().top <= 65)
          : (scrollTop > 15);
        stickyNav.classList.toggle('is-sticky', isStuck);
      }

      if (isManualScrolling) return;

      // 2. Section detection
      const isWindowScroll = windowScrollTop > 0 || (scrollContainer && scrollContainer.scrollHeight <= window.innerHeight);
      const containerRect = (!isWindowScroll && scrollContainer)
        ? scrollContainer.getBoundingClientRect()
        : { top: 0, height: window.innerHeight };

      // Check if at the very bottom of scrollable content
      const maxScroll = isWindowScroll
        ? document.documentElement.scrollHeight - window.innerHeight
        : (scrollContainer ? scrollContainer.scrollHeight - scrollContainer.clientHeight : 0);

      if (maxScroll > 0 && scrollTop >= maxScroll - 30) {
        const lastId = sections[sections.length - 1].id;
        if (lastActiveSection !== lastId) {
          lastActiveSection = lastId;
          setActivePill(lastId, true);
        }
        return;
      }

      // Find current section by relative top position
      const isMobile = window.innerWidth <= 768;
      const offsetThreshold = isMobile ? 140 : 160; // sticky navbar + tabs + margin
      let currentSectionId = sections[0].id;

      for (let i = 0; i < sections.length; i++) {
        const section = sections[i];
        const rect = section.getBoundingClientRect();
        const relativeTop = rect.top - containerRect.top;

        if (relativeTop <= offsetThreshold) {
          currentSectionId = section.id;
        } else {
          break;
        }
      }

      if (lastActiveSection !== currentSectionId) {
        lastActiveSection = currentSectionId;
        setActivePill(currentSectionId, true);
      }
    };

    // Attach scroll listener with throttling to both container and window
    let ticking = false;
    const onScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          handleScroll();
          ticking = false;
        });
        ticking = true;
      }
    };

    if (scrollContainer) {
      scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    }
    window.addEventListener('scroll', onScroll, { passive: true });

    // Initial check
    handleScroll();
  }

  /**
   * Handle navigation click events
   */
  function initNavigationClicks() {
    const tabButtons = $$('.settings-tab-btn');
    tabButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = btn.getAttribute('data-tab');
        const targetSection = document.getElementById(targetId);

        if (!targetSection) return;

        isManualScrolling = true;
        if (manualScrollTimeout) clearTimeout(manualScrollTimeout);

        setActivePill(targetId, true);

        // Smooth scroll to the target section
        targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

        manualScrollTimeout = setTimeout(() => {
          isManualScrolling = false;
        }, 700);
      });
    });
  }

  /**
   * Initial URL Hash Handling
   */
  function handleInitialHash() {
    const hash = (window.location.hash || '').replace(/^#/, '').toLowerCase();
    const initialSectionId = SECTION_IDS.includes(hash) ? hash : 'account';

    setActivePill(initialSectionId, false);

    if (hash && SECTION_IDS.includes(hash) && hash !== 'account') {
      const targetSection = document.getElementById(hash);
      if (targetSection) {
        setTimeout(() => {
          targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          centerActiveTabPill(hash, false);
        }, 150);
      }
    } else {
      centerActiveTabPill(initialSectionId, false);
    }
  }

  /**
   * Escape HTML utility for safe string injection
   */
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /**
   * Displays dismissible success or error alert banners dynamically
   */
  function showSettingsAlert(type, message) {
    $$('.settings-alert').forEach(el => el.remove());

    const isSuccess = type === 'success';
    const alertDiv = document.createElement('div');
    alertDiv.className = `settings-alert settings-alert-${isSuccess ? 'success' : 'error'}`;
    alertDiv.setAttribute('role', 'alert');

    const iconSvg = isSuccess
      ? `<svg class="settings-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`
      : `<svg class="settings-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

    alertDiv.innerHTML = `
      <div class="settings-alert-content">
        ${iconSvg}
        <span>${escapeHtml(message)}</span>
      </div>
      <button type="button" class="settings-alert-close" aria-label="Dismiss alert">&times;</button>
    `;

    const closeBtn = alertDiv.querySelector('.settings-alert-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        alertDiv.style.transition = 'opacity 150ms ease, transform 150ms ease';
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateY(-6px)';
        setTimeout(() => alertDiv.remove(), 150);
      });
    }

    const wrapper = $('.settings-content-wrapper');
    const tabsWrapper = $('#settings-sticky-nav');
    if (tabsWrapper && tabsWrapper.nextSibling) {
      tabsWrapper.parentNode.insertBefore(alertDiv, tabsWrapper.nextSibling);
    } else if (wrapper) {
      wrapper.prepend(alertDiv);
    }

    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /**
   * Account Section: Read-Only ↔ Edit Mode Toggle & Form Submission
   */
  function initAccountEditToggle() {
    const viewContainer = $('#account-view-container');
    const editContainer = $('#account-edit-container');
    const btnEdit = $('#btn-edit-profile');
    const btnCancel = $('#btn-cancel-edit-profile');
    const formEdit = $('#form-edit-profile');

    if (!viewContainer || !editContainer) return;

    if (btnEdit) {
      btnEdit.addEventListener('click', () => {
        viewContainer.style.display = 'none';
        editContainer.style.display = 'block';

        // Focus first field
        const nameInput = $('#edit_profile_name');
        if (nameInput) {
          nameInput.focus();
          nameInput.select();
        }
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', () => {
        if (formEdit) formEdit.reset();
        editContainer.style.display = 'none';
        viewContainer.style.display = 'block';
      });
    }

    if (formEdit) {
      formEdit.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = formEdit.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
          submitBtn.disabled = true;
        }

        try {
          const formData = new FormData(formEdit);
          const targetUrl = formEdit.getAttribute('action') || window.location.href;
          const response = await fetch(targetUrl, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });

          const result = await response.json();

          if (result.success) {
            // Update displayed values
            if (result.user) {
              if (result.user.name) {
                const viewName = $('#view-user-name');
                if (viewName) viewName.textContent = result.user.name;
                const viewFieldName = $('#view-field-name');
                if (viewFieldName) viewFieldName.textContent = result.user.name;
                const navUserName = $('.user-name');
                if (navUserName) navUserName.textContent = result.user.name;
              }
              if (result.user.department !== undefined) {
                const viewDept = $('#view-field-department');
                if (viewDept) viewDept.textContent = result.user.department || 'Not specified';
              }
              if (result.user.phone !== undefined) {
                const viewPhone = $('#view-field-phone');
                if (viewPhone) viewPhone.textContent = result.user.phone || 'Not provided';
              }
            }

            // Display success feedback
            showSettingsAlert('success', result.message);

            // Automatically exit Edit Profile mode back to read-only presentation
            editContainer.style.display = 'none';
            viewContainer.style.display = 'block';
          } else {
            // Display error feedback and keep Edit Profile mode active with input values preserved
            showSettingsAlert('error', result.message || 'Failed to update profile.');
            editContainer.style.display = 'block';
            viewContainer.style.display = 'none';
          }
        } catch (err) {
          // Fallback to normal form submission if fetch or JSON parsing fails
          formEdit.submit();
          return;
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
          }
        }
      });
    }
  }

  /**
   * Profile Image / Avatar Edit Affordance & Live Preview
   */
  function initAvatarEditing() {
    const avatarContainer = $('#avatar-container');
    const btnAvatarEdit = $('#btn-avatar-edit');
    const fileInput = $('#direct-avatar-input');
    const avatarPreview = $('#profile-avatar-preview');
    const actionsBar = $('#avatar-actions-bar');
    const btnDiscard = $('#btn-discard-avatar');

    if (!fileInput || !avatarPreview) return;

    const defaultSrc = avatarPreview.getAttribute('data-default-src') || avatarPreview.src;

    const triggerUpload = (e) => {
      e.stopPropagation();
      fileInput.click();
    };

    if (avatarContainer) avatarContainer.addEventListener('click', triggerUpload);
    if (btnAvatarEdit) btnAvatarEdit.addEventListener('click', triggerUpload);

    // Handle file selection
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;

      // Validate size (max 5 MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('The selected image exceeds the 5 MB file size limit.');
        fileInput.value = '';
        return;
      }

      // Validate MIME type
      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        alert('Please choose a valid image file (JPG, PNG, or WebP).');
        fileInput.value = '';
        return;
      }

      // Show instant preview
      const reader = new FileReader();
      reader.onload = (e) => {
        avatarPreview.src = e.target.result;
        if (actionsBar) actionsBar.style.display = 'flex';
      };
      reader.readAsDataURL(file);
    });

    // Handle discard
    if (btnDiscard) {
      btnDiscard.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput.value = '';
        avatarPreview.src = defaultSrc;
        if (actionsBar) actionsBar.style.display = 'none';
      });
    }
  }

  /**
   * Security Section: Read-Only Overview ↔ Change Password Form
   */
  function initSecurityToggle() {
    const overviewContainer = $('#security-overview-container');
    const editContainer = $('#security-edit-container');
    const btnOpen = $('#btn-open-change-password');
    const btnCancel = $('#btn-cancel-password');
    const formPassword = $('#form-update-password');

    if (!overviewContainer || !editContainer) return;

    if (btnOpen) {
      btnOpen.addEventListener('click', () => {
        editContainer.style.display = 'block';
        editContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        const currentPwInput = $('#current_password');
        if (currentPwInput) currentPwInput.focus();
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', () => {
        if (formPassword) formPassword.reset();
        editContainer.style.display = 'none';

        const matchHint = $('#password-match-hint');
        if (matchHint) matchHint.textContent = 'Must match the new password.';
      });
    }

    if (formPassword) {
      formPassword.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = formPassword.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
          submitBtn.disabled = true;
        }

        try {
          const formData = new FormData(formPassword);
          const targetUrl = formPassword.getAttribute('action') || window.location.href;
          const response = await fetch(targetUrl, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });

          const result = await response.json();

          if (result.success) {
            formPassword.reset();
            const matchHint = $('#password-match-hint');
            if (matchHint) {
              matchHint.textContent = 'Must match the new password.';
              matchHint.style.color = 'var(--secondary-text)';
            }

            // Display success feedback
            showSettingsAlert('success', result.message);

            // Automatically return section to normal/read-only overview
            editContainer.style.display = 'none';
          } else {
            // Display error feedback and keep Change Password form open
            showSettingsAlert('error', result.message || 'Failed to update password.');
            editContainer.style.display = 'block';
          }
        } catch (err) {
          // Fallback to normal form submission if fetch or JSON parsing fails
          formPassword.submit();
          return;
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
          }
        }
      });
    }
  }

  /**
   * Password Visibility Toggles
   */
  function initPasswordToggles() {
    const toggleButtons = $$('.settings-pw-toggle-btn');
    toggleButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        const isPassword = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPassword ? 'text' : 'password');
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

        const eyeIcon = btn.querySelector('.icon-eye');
        const eyeOffIcon = btn.querySelector('.icon-eye-off');
        if (eyeIcon && eyeOffIcon) {
          eyeIcon.style.display = isPassword ? 'none' : 'block';
          eyeOffIcon.style.display = isPassword ? 'block' : 'none';
        }
      });
    });
  }

  /**
   * Real-Time Password Validation Hint
   */
  function initPasswordValidation() {
    const newPw = $('#new_password');
    const confirmPw = $('#confirm_password');
    const hint = $('#password-match-hint');

    if (!newPw || !confirmPw || !hint) return;

    function checkMatch() {
      if (!newPw.value && !confirmPw.value) {
        hint.textContent = 'Must match the new password.';
        hint.style.color = 'var(--secondary-text)';
        return;
      }
      if (newPw.value.length < 8 && newPw.value.length > 0) {
        hint.textContent = 'Password must be at least 8 characters long.';
        hint.style.color = 'var(--danger)';
        return;
      }
      if (confirmPw.value && newPw.value !== confirmPw.value) {
        hint.textContent = 'Passwords do not match.';
        hint.style.color = 'var(--danger)';
        return;
      }
      if (confirmPw.value && newPw.value === confirmPw.value) {
        hint.textContent = '✓ Passwords match.';
        hint.style.color = 'var(--success)';
        return;
      }
      hint.textContent = 'Must match the new password.';
      hint.style.color = 'var(--secondary-text)';
    }

    newPw.addEventListener('input', checkMatch);
    confirmPw.addEventListener('input', checkMatch);
  }

  /**
   * Appearance Tab Theme Selector (Integrates with theme.js)
   */
  function initThemeSelector() {
    const themeCards = $$('.settings-theme-card');
    if (!themeCards.length) return;

    const THEME_KEY = 'theme';

    const getStoredTheme = () => {
      try {
        return localStorage.getItem(THEME_KEY);
      } catch (e) {
        return null;
      }
    };

    const updateActiveThemeUI = () => {
      const stored = getStoredTheme();
      const activeChoice = stored ? stored : 'system';

      themeCards.forEach(card => {
        const choice = card.getAttribute('data-theme-choice');
        card.classList.toggle('active', choice === activeChoice);
      });
    };

    themeCards.forEach(card => {
      card.addEventListener('click', () => {
        const choice = card.getAttribute('data-theme-choice');

        if (choice === 'light') {
          try { localStorage.setItem(THEME_KEY, 'light'); } catch (e) {}
          document.documentElement.setAttribute('data-theme', 'light');
        } else if (choice === 'dark') {
          try { localStorage.setItem(THEME_KEY, 'dark'); } catch (e) {}
          document.documentElement.setAttribute('data-theme', 'dark');
        } else if (choice === 'system') {
          try { localStorage.removeItem(THEME_KEY); } catch (e) {}
          const isSystemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          document.documentElement.setAttribute('data-theme', isSystemDark ? 'dark' : 'light');
        }

        updateActiveThemeUI();
      });
    });

    updateActiveThemeUI();

    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!getStoredTheme()) {
          document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        }
      });
    }
  }

  /**
   * Dismissible Alert Banners
   */
  function initAlertDismiss() {
    const closeButtons = $$('.settings-alert-close');
    closeButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const alertBox = btn.closest('.settings-alert');
        if (alertBox) {
          alertBox.style.transition = 'opacity 150ms ease, transform 150ms ease';
          alertBox.style.opacity = '0';
          alertBox.style.transform = 'translateY(-6px)';
          setTimeout(() => alertBox.remove(), 150);
        }
      });
    });
  }

  /**
   * DOM Ready Initialization
   */
  document.addEventListener('DOMContentLoaded', () => {
    initScrollSpy();
    initNavigationClicks();
    handleInitialHash();
    initAccountEditToggle();
    initAvatarEditing();
    initSecurityToggle();
    initPasswordToggles();
    initPasswordValidation();
    initThemeSelector();
    initAlertDismiss();

    // Listen for browser back/forward buttons
    window.addEventListener('hashchange', () => {
      handleInitialHash();
    });
  });
})();
