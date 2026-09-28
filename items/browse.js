/**
 * ==========================================================================
 * LOSTLINK — BROWSE ITEMS JAVASCRIPT (browse.js)
 * ==========================================================================
 * Contextual search adapter for top navbar search on Browse Items,
 * compact secondary filters, sort controls, and image error fallbacks.
 */

document.addEventListener('DOMContentLoaded', () => {
  const pageContext = document.querySelector('[data-search-context="browse"]');
  if (!pageContext) {
    return;
  }

  /* -------------------------------------------------------------------------
     1. TOP NAVBAR CONTEXTUAL SEARCH ADAPTER
     ------------------------------------------------------------------------- */
  const navbarSearchContainer = document.querySelector('.navbar-search-container');
  const navbarSearchInput = document.querySelector('.navbar-search-input');

  if (navbarSearchContainer && navbarSearchInput) {
    const currentQuery = pageContext.dataset.currentQuery || '';
    const searchTarget = pageContext.dataset.searchTarget || window.location.pathname;
    const currentType = pageContext.dataset.currentType || 'all';
    const currentStatus = pageContext.dataset.currentStatus || 'all';
    const currentCategory = pageContext.dataset.currentCategory || '';
    const currentLocation = pageContext.dataset.currentLocation || '';
    const currentDate = pageContext.dataset.currentDate || '';
    const currentSort = pageContext.dataset.currentSort || 'newest';

    // Set contextual UI attributes on the navbar search
    navbarSearchInput.value = currentQuery;
    navbarSearchInput.placeholder = 'Search lost & found items...';
    navbarSearchInput.setAttribute('aria-label', 'Search lost and found community items');

    // Create / Manage clear button inside navbar search
    let clearButton = navbarSearchContainer.querySelector('.navbar-search-clear-btn');
    if (!clearButton) {
      clearButton = document.createElement('button');
      clearButton.type = 'button';
      clearButton.className = 'navbar-search-clear-btn';
      clearButton.setAttribute('aria-label', 'Clear search');
      clearButton.setAttribute('title', 'Clear search');
      clearButton.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      `;
      navbarSearchContainer.appendChild(clearButton);
    }

    const updateClearButtonVisibility = () => {
      clearButton.style.display = navbarSearchInput.value.trim().length > 0 ? 'inline-flex' : 'none';
    };

    updateClearButtonVisibility();
    navbarSearchInput.addEventListener('input', updateClearButtonVisibility);

    // Contextual Search Execution preserving all active filters
    const executeSearch = (rawQuery) => {
      const trimmedQuery = rawQuery.trim();
      const targetUrl = new URL(searchTarget, window.location.origin);

      if (trimmedQuery) {
        targetUrl.searchParams.set('q', trimmedQuery);
      } else {
        targetUrl.searchParams.delete('q');
      }

      if (currentType && currentType !== 'all') {
        targetUrl.searchParams.set('type', currentType);
      } else {
        targetUrl.searchParams.delete('type');
      }

      if (currentStatus && currentStatus !== 'all') {
        targetUrl.searchParams.set('status', currentStatus);
      } else {
        targetUrl.searchParams.delete('status');
      }

      if (currentCategory) {
        targetUrl.searchParams.set('category', currentCategory);
      } else {
        targetUrl.searchParams.delete('category');
      }

      if (currentLocation) {
        targetUrl.searchParams.set('location', currentLocation);
      } else {
        targetUrl.searchParams.delete('location');
      }

      if (currentDate) {
        targetUrl.searchParams.set('date', currentDate);
      } else {
        targetUrl.searchParams.delete('date');
      }

      if (currentSort && currentSort !== 'newest') {
        targetUrl.searchParams.set('sort', currentSort);
      } else {
        targetUrl.searchParams.delete('sort');
      }

      // Reset page back to 1 on new search
      targetUrl.searchParams.delete('page');

      window.location.href = targetUrl.toString();
    };

    // Trigger search on Enter key
    navbarSearchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        executeSearch(navbarSearchInput.value);
      }
    });

    // Make search icon clickable
    const searchIcon = navbarSearchContainer.querySelector('.search-icon');
    if (searchIcon) {
      searchIcon.setAttribute('role', 'button');
      searchIcon.setAttribute('tabindex', '0');
      searchIcon.setAttribute('aria-label', 'Submit search');

      const triggerSearch = () => executeSearch(navbarSearchInput.value);

      searchIcon.addEventListener('click', triggerSearch);
      searchIcon.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          triggerSearch();
        }
      });
    }

    // Clear button functionality
    clearButton.addEventListener('click', () => {
      navbarSearchInput.value = '';
      updateClearButtonVisibility();
      navbarSearchInput.focus();

      // If there was an active search query in the current URL, reset it
      if (currentQuery) {
        executeSearch('');
      }
    });
  }

  /* -------------------------------------------------------------------------
     2. HELPER: UPDATE FILTER PARAMETER PRESERVING STATE
     ------------------------------------------------------------------------- */
  const updateFilterParam = (key, value) => {
    const targetUrl = new URL(window.location.href);

    if (value && value !== 'all' && value !== 'newest') {
      targetUrl.searchParams.set(key, value);
    } else {
      targetUrl.searchParams.delete(key);
    }

    // Always reset to page 1 on filter modification
    targetUrl.searchParams.delete('page');

    window.location.href = targetUrl.toString();
  };

  /* -------------------------------------------------------------------------
     3. SORT SELECTION CHANGE HANDLER
     ------------------------------------------------------------------------- */
  const sortSelect = document.getElementById('browse-sort');
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      updateFilterParam('sort', sortSelect.value);
    });
  }

  /* -------------------------------------------------------------------------
     4. SECONDARY FILTERS HANDLERS (CATEGORY, LOCATION, DATE)
     ------------------------------------------------------------------------- */
  const categorySelect = document.getElementById('browse-category');
  if (categorySelect) {
    categorySelect.addEventListener('change', () => {
      updateFilterParam('category', categorySelect.value);
    });
  }

  const dateInput = document.getElementById('browse-date');
  if (dateInput) {
    dateInput.addEventListener('change', () => {
      updateFilterParam('date', dateInput.value);
    });
  }

  const locationInput = document.getElementById('browse-location');
  if (locationInput) {
    locationInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateFilterParam('location', locationInput.value.trim());
      }
    });
  }

  /* -------------------------------------------------------------------------
     5. IMAGE ERROR FALLBACK HANDLER
     ------------------------------------------------------------------------- */
  document.querySelectorAll('.browse-item-image-wrap img[data-fallback]').forEach((image) => {
    image.addEventListener('error', () => {
      const fallback = image.dataset.fallback;
      if (!fallback || image.dataset.fallbackApplied === 'true') {
        return;
      }

      image.dataset.fallbackApplied = 'true';
      image.src = fallback;
      image.alt = 'No image available';
    });
  });

  /* -------------------------------------------------------------------------
     6. MOBILE COMPACT FILTER TOOLBAR (DROPDOWNS & FILTER ACTIONS)
     ------------------------------------------------------------------------- */
  const mobileToolbar = document.getElementById('browse-mobile-toolbar');
  if (mobileToolbar) {
    const dropdowns = mobileToolbar.querySelectorAll('.browse-mobile-dropdown');

    const closeAllDropdowns = () => {
      dropdowns.forEach((dd) => {
        dd.classList.remove('is-open');
        const trigger = dd.querySelector('.browse-mobile-trigger');
        if (trigger) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    };

    dropdowns.forEach((dd) => {
      const trigger = dd.querySelector('.browse-mobile-trigger');
      const menu = dd.querySelector('.browse-mobile-menu');
      if (!trigger || !menu) return;

      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dd.classList.contains('is-open');
        closeAllDropdowns();
        if (!isOpen) {
          dd.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');

          // Keep menu inside viewport boundaries
          const rect = menu.getBoundingClientRect();
          if (rect.right > window.innerWidth - 8) {
            menu.style.left = 'auto';
            menu.style.right = '0px';
          }
          if (rect.left < 8) {
            menu.style.left = '0px';
            menu.style.right = 'auto';
          }
        }
      });

      // Prevent clicks inside menu from bubbling to document click
      menu.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    });

    // Close on outside tap/click
    document.addEventListener('click', () => {
      closeAllDropdowns();
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAllDropdowns();
      }
    });

    // Mobile Filters "Apply Filters" handler
    const applyBtn = document.getElementById('mobile-apply-filters');
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        const mobCat = document.getElementById('mobile-category');
        const mobLoc = document.getElementById('mobile-location');
        const mobDate = document.getElementById('mobile-date');

        const targetUrl = new URL(window.location.href);

        if (mobCat && mobCat.value) {
          targetUrl.searchParams.set('category', mobCat.value);
        } else {
          targetUrl.searchParams.delete('category');
        }

        if (mobLoc && mobLoc.value.trim()) {
          targetUrl.searchParams.set('location', mobLoc.value.trim());
        } else {
          targetUrl.searchParams.delete('location');
        }

        if (mobDate && mobDate.value) {
          targetUrl.searchParams.set('date', mobDate.value);
        } else {
          targetUrl.searchParams.delete('date');
        }

        targetUrl.searchParams.delete('page');
        window.location.href = targetUrl.toString();
      });
    }

    // Allow Enter key inside mobile-location to trigger Apply
    const mobLocInput = document.getElementById('mobile-location');
    if (mobLocInput) {
      mobLocInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (applyBtn) {
            applyBtn.click();
          }
        }
      });
    }

    // Mobile Filters "Reset" handler
    const resetBtn = document.getElementById('mobile-reset-filters');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        const targetUrl = new URL(window.location.href);
        targetUrl.searchParams.delete('category');
        targetUrl.searchParams.delete('location');
        targetUrl.searchParams.delete('date');
        targetUrl.searchParams.delete('page');
        window.location.href = targetUrl.toString();
      });
    }
  }
});
