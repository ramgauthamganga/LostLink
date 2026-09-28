/**
 * ==============================================================================
 * LOSTLINK ANNOUNCEMENTS SCRIPT (announcements.js)
 * ==============================================================================
 * Handles announcement interaction:
 * - Opening and submitting Create/Edit modal
 * - Character count
 * - Toggling pin status
 * - Archiving and restoring notices
 * - Delete confirmation modal
 * - Dismissible alerts
 */

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('announcements-main-wrapper');
  const baseUrl = root ? (root.getAttribute('data-base-url') || '') : '';
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

  const apiUrl = baseUrl + '/backend/announcements/actions.php';

  // UI Alert Container Elements
  const alertContainer = document.getElementById('anc-alert-container');

  function showAlert(message, isError = false) {
    if (!alertContainer) return;
    alertContainer.innerHTML = '';
    const banner = document.createElement('div');
    banner.className = 'anc-alert-banner ' + (isError ? 'is-error' : 'is-success');
    banner.setAttribute('role', 'alert');

    const icon = document.createElement('i');
    icon.className = 'fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle');
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('div');
    text.className = 'anc-alert-text';
    text.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'anc-alert-close-btn';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => {
      banner.remove();
    });

    banner.appendChild(icon);
    banner.appendChild(text);
    banner.appendChild(closeBtn);
    alertContainer.appendChild(banner);
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Handle dismiss on any initial server-rendered alerts
  if (alertContainer) {
    alertContainer.querySelectorAll('.anc-alert-close-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const banner = btn.closest('.anc-alert-banner');
        if (banner) banner.remove();
      });
    });
  }

  // ===========================================================================
  // ADMIN MODAL LOGIC
  // ===========================================================================
  const formModal = document.getElementById('anc-form-modal');
  const formModalClose = document.getElementById('anc-modal-close-btn');
  const formModalCancel = document.getElementById('anc-modal-cancel-btn');
  const formTitle = document.getElementById('anc-modal-title');
  const formSubtitle = document.getElementById('anc-modal-subtitle');
  const announcementForm = document.getElementById('anc-announcement-form');
  const formActionInput = document.getElementById('anc-form-action');
  const formCodeInput = document.getElementById('anc-form-code');
  const submitBtn = document.getElementById('anc-submit-btn');
  const submitBtnText = document.getElementById('anc-submit-btn-text');

  // Form Fields
  const titleInput = document.getElementById('anc-title-input');
  const categorySelect = document.getElementById('anc-category-select');
  const prioritySelect = document.getElementById('anc-priority-select');
  const contentInput = document.getElementById('anc-content-input');
  const pinnedInput = document.getElementById('anc-pinned-input');
  const charCount = document.getElementById('anc-char-count');
  const scheduleWrap = document.getElementById('anc-schedule-wrap');
  const scheduledAtInput = document.getElementById('anc-scheduled-at');
  const expiresAtInput = document.getElementById('anc-expires-at');

  const statusRadios = document.querySelectorAll('input[name="status"]');

  function openCreateModal() {
    if (!formModal || !announcementForm) return;
    announcementForm.reset();
    if (formActionInput) formActionInput.value = 'create';
    if (formCodeInput) formCodeInput.value = '';
    if (formTitle) formTitle.textContent = 'Create Announcement';
    if (formSubtitle) formSubtitle.textContent = 'Compose and publish an official campus announcement.';
    if (submitBtnText) submitBtnText.textContent = 'Publish Announcement';
    if (scheduleWrap) scheduleWrap.hidden = true;
    updateCharCount();
    formModal.hidden = false;
    if (titleInput) titleInput.focus();
  }

  function closeFormModal() {
    if (formModal) formModal.hidden = true;
  }

  const btnOpenCreate = document.getElementById('btn-open-create-modal');
  if (btnOpenCreate) {
    btnOpenCreate.addEventListener('click', openCreateModal);
  }

  if (formModalClose) formModalClose.addEventListener('click', closeFormModal);
  if (formModalCancel) formModalCancel.addEventListener('click', closeFormModal);

  // Status Radios toggle schedule wrap
  statusRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (scheduleWrap) {
        scheduleWrap.hidden = (radio.value !== 'scheduled');
      }
      if (submitBtnText) {
        if (radio.value === 'draft') submitBtnText.textContent = 'Save as Draft';
        else if (radio.value === 'scheduled') submitBtnText.textContent = 'Schedule Announcement';
        else submitBtnText.textContent = (formActionInput && formActionInput.value === 'update') ? 'Save Changes' : 'Publish Announcement';
      }
    });
  });

  // Character Counter
  function updateCharCount() {
    if (!contentInput || !charCount) return;
    const len = contentInput.value.length;
    charCount.textContent = len + ' character' + (len === 1 ? '' : 's');
  }

  if (contentInput) {
    contentInput.addEventListener('input', updateCharCount);
  }

  // ===========================================================================
  // EDIT ANNOUNCEMENT
  // ===========================================================================
  async function openEditModal(code) {
    if (!code) return;
    try {
      const res = await fetch(apiUrl + '?action=get&code=' + encodeURIComponent(code));
      const data = await res.json();
      if (!data.success || !data.announcement) {
        showAlert(data.error || 'Failed to fetch announcement details.', true);
        return;
      }
      const a = data.announcement;

      if (formActionInput) formActionInput.value = 'update';
      if (formCodeInput) formCodeInput.value = a.announcement_code;
      if (formTitle) formTitle.textContent = 'Edit Announcement';
      if (formSubtitle) formSubtitle.textContent = 'Update details, priority, or publication settings for this notice.';
      if (submitBtnText) submitBtnText.textContent = 'Save Changes';

      if (titleInput) titleInput.value = a.title || '';
      if (categorySelect) categorySelect.value = a.category || 'lostlink';
      if (prioritySelect) prioritySelect.value = a.priority || 'normal';
      if (contentInput) contentInput.value = a.content || '';
      if (pinnedInput) pinnedInput.checked = (parseInt(a.is_pinned, 10) === 1);

      // Status
      const targetRadio = document.querySelector('input[name="status"][value="' + a.status + '"]');
      if (targetRadio) {
        targetRadio.checked = true;
      }

      if (scheduleWrap) {
        scheduleWrap.hidden = (a.status !== 'scheduled');
      }

      // Format datetime strings for datetime-local input (YYYY-MM-DDTHH:MM)
      if (scheduledAtInput && a.scheduled_at) {
        scheduledAtInput.value = a.scheduled_at.replace(' ', 'T').substring(0, 16);
      } else if (scheduledAtInput) {
        scheduledAtInput.value = '';
      }

      if (expiresAtInput && a.expires_at) {
        expiresAtInput.value = a.expires_at.replace(' ', 'T').substring(0, 16);
      } else if (expiresAtInput) {
        expiresAtInput.value = '';
      }

      updateCharCount();
      if (formModal) formModal.hidden = false;
    } catch (e) {
      showAlert('Error loading announcement details.', true);
    }
  }

  document.querySelectorAll('.btn-edit-announcement').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      openEditModal(code);
    });
  });

  // ===========================================================================
  // SUBMIT FORM (Create / Update)
  // ===========================================================================
  if (announcementForm) {
    announcementForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const titleVal = titleInput ? titleInput.value.trim() : '';
      const contentVal = contentInput ? contentInput.value.trim() : '';

      if (!titleVal) {
        showAlert('Please enter an announcement title.', true);
        if (titleInput) titleInput.focus();
        return;
      }
      if (!contentVal) {
        showAlert('Please enter announcement content.', true);
        if (contentInput) contentInput.focus();
        return;
      }

      const formData = new FormData(announcementForm);
      if (submitBtn) submitBtn.disabled = true;

      try {
        const res = await fetch(apiUrl, {
          method: 'POST',
          body: formData
        });
        const result = await res.json();

        if (submitBtn) submitBtn.disabled = false;

        if (!result.success) {
          showAlert(result.error || 'Operation failed.', true);
          return;
        }

        closeFormModal();
        showAlert(result.message || 'Saved successfully.');
        setTimeout(() => {
          window.location.reload();
        }, 800);
      } catch (err) {
        if (submitBtn) submitBtn.disabled = false;
        showAlert('Network or server error occurred.', true);
      }
    });
  }

  // ===========================================================================
  // PUBLISH NOW (Draft / Scheduled -> Published)
  // ===========================================================================
  document.querySelectorAll('.btn-publish-announcement').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      if (!code) return;

      if (!confirm('Are you sure you want to publish this announcement immediately?')) {
        return;
      }

      btn.disabled = true;
      const fd = new FormData();
      fd.append('action', 'publish');
      fd.append('announcement_code', code);
      fd.append('csrf_token', csrfToken);

      try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const result = await res.json();
        btn.disabled = false;
        if (result.success) {
          showAlert(result.message || 'Announcement published successfully.');
          setTimeout(() => {
            window.location.reload();
          }, 600);
        } else {
          showAlert(result.error || 'Failed to publish announcement.', true);
        }
      } catch (err) {
        btn.disabled = false;
        showAlert('Network error while publishing.', true);
      }
    });
  });

  // ===========================================================================
  // PIN / UNPIN TOGGLE
  // ===========================================================================
  document.querySelectorAll('.btn-toggle-pin').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      if (!code) return;

      const fd = new FormData();
      fd.append('action', 'toggle_pin');
      fd.append('announcement_code', code);
      fd.append('csrf_token', csrfToken);

      try {
        const res = await fetch(apiUrl, {
          method: 'POST',
          body: fd
        });
        const result = await res.json();
        if (result.success) {
          window.location.reload();
        } else {
          showAlert(result.error || 'Failed to toggle pin state.', true);
        }
      } catch (err) {
        showAlert('Network error while toggling pin.', true);
      }
    });
  });

  // ===========================================================================
  // ARCHIVE / UNARCHIVE
  // ===========================================================================
  document.querySelectorAll('.btn-archive-announcement').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      if (!code) return;

      const fd = new FormData();
      fd.append('action', 'archive');
      fd.append('announcement_code', code);
      fd.append('csrf_token', csrfToken);

      try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const result = await res.json();
        if (result.success) {
          window.location.reload();
        } else {
          showAlert(result.error || 'Failed to archive.', true);
        }
      } catch (err) {
        showAlert('Network error while archiving.', true);
      }
    });
  });

  document.querySelectorAll('.btn-unarchive-announcement').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      if (!code) return;

      const fd = new FormData();
      fd.append('action', 'unarchive');
      fd.append('announcement_code', code);
      fd.append('csrf_token', csrfToken);

      try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const result = await res.json();
        if (result.success) {
          window.location.reload();
        } else {
          showAlert(result.error || 'Failed to restore announcement.', true);
        }
      } catch (err) {
        showAlert('Network error while restoring.', true);
      }
    });
  });

  // ===========================================================================
  // DELETE WITH CONFIRMATION MODAL
  // ===========================================================================
  const deleteModal = document.getElementById('anc-delete-modal');
  const deleteModalClose = document.getElementById('anc-delete-modal-close');
  const deleteModalCancel = document.getElementById('anc-delete-cancel-btn');
  const deleteConfirmBtn = document.getElementById('anc-delete-confirm-btn');
  const deleteTargetTitle = document.getElementById('anc-delete-target-title');
  let deleteTargetCode = null;

  function openDeleteModal(code, title) {
    deleteTargetCode = code;
    if (deleteTargetTitle) deleteTargetTitle.textContent = title || 'this announcement';
    if (deleteModal) deleteModal.hidden = false;
  }

  function closeDeleteModal() {
    deleteTargetCode = null;
    if (deleteModal) deleteModal.hidden = true;
  }

  if (deleteModalClose) deleteModalClose.addEventListener('click', closeDeleteModal);
  if (deleteModalCancel) deleteModalCancel.addEventListener('click', closeDeleteModal);

  document.querySelectorAll('.btn-delete-announcement').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const code = btn.getAttribute('data-code');
      const title = btn.getAttribute('data-title') || '';
      openDeleteModal(code, title);
    });
  });

  if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener('click', async () => {
      if (!deleteTargetCode) return;

      deleteConfirmBtn.disabled = true;
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('announcement_code', deleteTargetCode);
      fd.append('csrf_token', csrfToken);

      try {
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const result = await res.json();
        deleteConfirmBtn.disabled = false;

        if (result.success) {
          closeDeleteModal();
          // If we were on detail view of this announcement, go back to main list
          if (window.location.search.indexOf('id=') !== -1) {
            window.location.href = 'announcements.php';
          } else {
            window.location.reload();
          }
        } else {
          showAlert(result.error || 'Failed to delete announcement.', true);
        }
      } catch (err) {
        deleteConfirmBtn.disabled = false;
        showAlert('Network error while deleting.', true);
      }
    });
  }

  // Close modals on escape key or clicking backdrop
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeFormModal();
      closeDeleteModal();
    }
  });

  [formModal, deleteModal].forEach(modal => {
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          closeFormModal();
          closeDeleteModal();
        }
      });
    }
  });
});
