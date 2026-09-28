/**
 * ==========================================================================
 * SHARED UI COMPONENTS JAVASCRIPT
 * ==========================================================================
 * Handles reusable interactive elements across the authenticated shell.
 * Includes: Sidebar Toggle, Dropdowns.
 * Note: Theme toggling is handled by assets/js/theme.js
 */

document.addEventListener('DOMContentLoaded', () => {
    
    /* 
    |--------------------------------------------------------------------------
    | DROPDOWNS LOGIC
    |--------------------------------------------------------------------------
    | Handles opening/closing of profile, notification, and generic dropdowns.
    */
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const container = toggle.closest('.dropdown-container');
            const isOpen = container.classList.contains('open');
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-container').forEach(c => {
                c.classList.remove('open');
                c.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
            });

            // Toggle current
            if (!isOpen) {
                container.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('.dropdown-container').forEach(c => {
                c.classList.remove('open');
                c.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
            });
        }
    });

    /* 
    |--------------------------------------------------------------------------
    | SIDEBAR TOGGLE LOGIC
    |--------------------------------------------------------------------------
    | Controls the expanded/collapsed state on desktop,
    | and the slide-in drawer state on mobile.
    */
    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    const body = document.body;

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // Desktop: Toggle collapsed state
            // Mobile: Toggle open state
            if (window.innerWidth <= 768) {
                body.classList.toggle('sidebar-mobile-open');
                body.classList.remove('sidebar-collapsed'); // ensure desktop class is removed
            } else {
                body.classList.toggle('sidebar-collapsed');
                body.classList.remove('sidebar-mobile-open'); // ensure mobile class is removed
            }
        });
    }

    // Close sidebar on mobile when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                body.classList.remove('sidebar-mobile-open');
            }
        });
    }

    // Close sidebar on mobile when a nav link is clicked
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                body.classList.remove('sidebar-mobile-open');
            }
        });
    });

    // Handle window resize to clean up classes
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            body.classList.remove('sidebar-mobile-open');
        } else {
            body.classList.remove('sidebar-collapsed');
        }
    });

});
