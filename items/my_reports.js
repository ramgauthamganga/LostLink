/**
 * ==========================================================================
 * LOSTLINK — MY REPORTS PAGE JAVASCRIPT (my_reports.js)
 * ==========================================================================
 * Contextual search adapter for top navbar search on My Reports,
 * sort controls, dynamic clear buttons, and image error fallbacks.
 */

document.addEventListener('DOMContentLoaded', () => {
  const pageContext = document.querySelector('[data-search-context="my_reports"]');
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
    const currentStatus = pageContext.dataset.currentStatus || 'all';
    const currentSort = pageContext.dataset.currentSort || 'newest';

    // Set contextual UI attributes
    navbarSearchInput.value = currentQuery;
    navbarSearchInput.placeholder = 'Search your reports...';
    navbarSearchInput.setAttribute('aria-label', 'Search your reports');

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

    // Contextual Search Execution
    const executeSearch = (rawQuery) => {
      const trimmedQuery = rawQuery.trim();
      const targetUrl = new URL(searchTarget, window.location.origin);

      if (trimmedQuery) {
        targetUrl.searchParams.set('q', trimmedQuery);
      } else {
        targetUrl.searchParams.delete('q');
      }

      if (currentStatus && currentStatus !== 'all') {
        targetUrl.searchParams.set('status', currentStatus);
      } else {
        targetUrl.searchParams.delete('status');
      }

      if (currentSort && currentSort !== 'newest') {
        targetUrl.searchParams.set('sort', currentSort);
      } else {
        targetUrl.searchParams.delete('sort');
      }

      // Reset page back to 1 on search
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
     2. SORT SELECTION CHANGE HANDLER
     ------------------------------------------------------------------------- */
  const sortSelect = document.getElementById('my-reports-sort');
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      const newSort = sortSelect.value;
      const targetUrl = new URL(window.location.href);

      if (newSort && newSort !== 'newest') {
        targetUrl.searchParams.set('sort', newSort);
      } else {
        targetUrl.searchParams.delete('sort');
      }

      // Reset page back to 1 on sort change
      targetUrl.searchParams.delete('page');

      window.location.href = targetUrl.toString();
    });
  }

  /* -------------------------------------------------------------------------
     3. IMAGE ERROR FALLBACK HANDLER
     ------------------------------------------------------------------------- */
  const reportImages = document.querySelectorAll('.card-image-wrapper img[data-fallback]');
  reportImages.forEach((img) => {
    img.addEventListener('error', () => {
      const fallbackSrc = img.dataset.fallback;
      if (!fallbackSrc || img.dataset.fallbackApplied === 'true') {
        return;
      }
      img.dataset.fallbackApplied = 'true';
      img.src = fallbackSrc;
      img.alt = 'Default item image';
    });
  });
});
