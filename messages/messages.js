/**
 * ==============================================================================
 * LOSTLINK MESSAGES JAVASCRIPT CONTROLLER (messages.js)
 * ==============================================================================
 * Manages:
 * - Conversations list and Contact Requests list
 * - Live message threads, read receipts, and auto-scroll
 * - Real-time sending with optimistic insertion and error handling
 * - Accept/Reject contact requests with automatic conversation transition
 * - Polling for incoming messages
 * - Seamless integration with Item Details modal
 * - Mobile responsive navigation and state management
 */

(function () {
  'use strict';

  class MessagesManager {
    constructor() {
      this.appEl = document.getElementById('messages-app');
      if (!this.appEl) return;

      this.baseUrl = (this.appEl.dataset.baseUrl || '').replace(/\/+$/, '');
      this.currentUserId = parseInt(this.appEl.dataset.userId || '0', 10);
      this.initialConvCode = this.appEl.dataset.initialConv || '';
      this.initialReqCode = this.appEl.dataset.initialReq || '';

      this.layoutCard = document.getElementById('messages-layout');
      this.totalUnreadBadge = document.getElementById('messages-total-unread');
      this.tabConvBadge = document.getElementById('tab-conv-badge');
      this.tabReqBadge = document.getElementById('tab-req-badge');
      this.searchInput = document.getElementById('messages-search-input');

      // Tabs & Panels
      this.tabConvBtn = document.getElementById('tab-conversations');
      this.tabReqBtn = document.getElementById('tab-requests');
      this.panelConv = document.getElementById('panel-conversations');
      this.panelReq = document.getElementById('panel-requests');
      this.convUl = document.getElementById('conversations-ul');
      this.reqUl = document.getElementById('requests-ul');

      // Loading & Empty states
      this.convLoading = document.getElementById('conv-list-loading');
      this.convEmpty = document.getElementById('conv-list-empty');
      this.reqLoading = document.getElementById('req-list-loading');
      this.reqEmpty = document.getElementById('req-list-empty');
      this.countIncoming = document.getElementById('count-incoming');
      this.countOutgoing = document.getElementById('count-outgoing');

      // View Panels
      this.viewEmpty = document.getElementById('view-empty-selection');
      this.viewConv = document.getElementById('view-conversation');
      this.viewReq = document.getElementById('view-request');

      // Conversation View Elements
      this.convMobileBackBtn = document.getElementById('conv-mobile-back-btn');
      this.convItemThumb = document.getElementById('conv-item-thumb');
      this.convItemTitle = document.getElementById('conv-item-title');
      this.convBadgeType = document.getElementById('conv-badge-type');
      this.convItemCode = document.getElementById('conv-item-code');
      this.convOtherName = document.getElementById('conv-other-name');
      this.convOtherDept = document.getElementById('conv-other-dept');
      this.convOtherNameM = document.getElementById('conv-other-name-m');
      this.convOtherDeptM = document.getElementById('conv-other-dept-m');
      this.convStatusPill = document.getElementById('conv-status-pill');
      this.convViewItemBtn = document.getElementById('conv-view-item-btn');
      this.convThreadScroll = document.getElementById('conv-thread-scroll');
      this.convMessagesContainer = document.getElementById('conv-messages-container');
      this.convClosedAlert = document.getElementById('conv-closed-alert');
      this.convClosedText = document.getElementById('conv-closed-text');
      this.convBlockedAlert = document.getElementById('conv-blocked-alert');
      this.convBlockedText = document.getElementById('conv-blocked-text');
      this.convUnblockBtn = document.getElementById('conv-unblock-btn');
      this.convComposerBox = document.getElementById('conv-composer-box');
      this.convMessageForm = document.getElementById('conv-message-form');
      this.convMessageInput = document.getElementById('conv-message-input');
      this.convSendBtn = document.getElementById('conv-send-btn');

      // 3-Dot Options Dropdown Elements
      this.convOptionsBtn = document.getElementById('conv-options-btn');
      this.convOptionsMenu = document.getElementById('conv-options-menu');
      this.convMenuViewItem = document.getElementById('conv-menu-view-item');
      this.convMenuBlockBtn = document.getElementById('conv-menu-block-btn');
      this.convMenuBlockText = document.getElementById('conv-menu-block-text');

      // Block User Modal Elements
      this.blockModal = document.getElementById('block-confirm-modal');
      this.blockModalCloseBtn = document.getElementById('block-modal-close-btn');
      this.blockModalCancelBtn = document.getElementById('block-modal-cancel-btn');
      this.blockModalConfirmBtn = document.getElementById('block-modal-confirm-btn');
      this.blockModalUserName = document.getElementById('block-modal-user-name');

      // Request View Elements
      this.reqMobileBackBtn = document.getElementById('req-mobile-back-btn');
      this.reqStatusBadge = document.getElementById('req-status-badge');
      this.reqItemImg = document.getElementById('req-item-img');
      this.reqItemType = document.getElementById('req-item-type');
      this.reqItemCode = document.getElementById('req-item-code');
      this.reqItemTitle = document.getElementById('req-item-title');
      this.reqItemLocation = document.getElementById('req-item-location');
      this.reqViewItemLink = document.getElementById('req-view-item-link');
      this.reqUserAvatar = document.getElementById('req-user-avatar');
      this.reqUserRoleLabel = document.getElementById('req-user-role-label');
      this.reqUserName = document.getElementById('req-user-name');
      this.reqUserDept = document.getElementById('req-user-dept');
      this.reqCreatedTime = document.getElementById('req-created-time');
      this.reqMessageContent = document.getElementById('req-message-content');
      this.reqActionsPanel = document.getElementById('req-actions-panel');

      // Internal State
      this.activeTab = 'conversations';
      this.activeSubfilter = 'incoming';
      this.conversations = [];
      this.requests = { incoming: [], outgoing: [] };
      this.activeConvCode = null;
      this.activeReqCode = null;
      this.currentConvData = null;
      this.currentReqData = null;
      this.pollingTimer = null;
      this.isSubmitting = false;

      this.init();
    }

    init() {
      this.bindEvents();
      this.loadAllData().then(() => {
        if (this.initialConvCode) {
          this.openConversation(this.initialConvCode);
        } else if (this.initialReqCode) {
          this.switchTab('requests');
          this.openRequest(this.initialReqCode);
        }
      });

      // Background sync every 7s when idle
      setInterval(() => {
        if (!document.hidden && !this.activeConvCode) {
          this.loadConversations(true);
          this.loadRequests(true);
        }
      }, 7000);
    }

    bindEvents() {
      // Tab Switching
      this.tabConvBtn?.addEventListener('click', () => this.switchTab('conversations'));
      this.tabReqBtn?.addEventListener('click', () => this.switchTab('requests'));

      // Requests Subfilters (Incoming / Outgoing)
      document.querySelectorAll('.subfilter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const sub = e.target.dataset.subfilter;
          if (sub) {
            document.querySelectorAll('.subfilter-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            this.activeSubfilter = sub;
            this.renderRequestsList();
          }
        });
      });

      // Filter/Search Input
      this.searchInput?.addEventListener('input', () => {
        const q = this.searchInput.value.trim().toLowerCase();
        this.filterLists(q);
      });

      // Mobile Back Buttons
      this.convMobileBackBtn?.addEventListener('click', () => this.closeActiveView());
      this.reqMobileBackBtn?.addEventListener('click', () => this.closeActiveView());

      // Send Message Form
      this.convMessageForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        this.sendMessage();
      });

      // Auto-resize composer textarea and handle Enter to send (Shift+Enter for newline)
      this.convMessageInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      this.convMessageInput?.addEventListener('input', () => {
        if (this.convMessageInput) {
          this.convMessageInput.style.height = 'auto';
          this.convMessageInput.style.height = Math.min(this.convMessageInput.scrollHeight, 120) + 'px';
        }
      });

      // View Item in Item Details modal
      this.convViewItemBtn?.addEventListener('click', () => {
        if (this.currentConvData?.item?.item_code && window.LostLinkItemDetails) {
          window.LostLinkItemDetails.open(this.currentConvData.item.item_code);
        }
      });

      this.reqViewItemLink?.addEventListener('click', () => {
        if (this.currentReqData?.item?.item_code && window.LostLinkItemDetails) {
          window.LostLinkItemDetails.open(this.currentReqData.item.item_code);
        }
      });

      // 3-Dot Options Dropdown Toggle
      this.convOptionsBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = this.convOptionsMenu?.hidden ?? true;
        if (this.convOptionsMenu) {
          this.convOptionsMenu.hidden = !isHidden;
          this.convOptionsBtn.setAttribute('aria-expanded', String(isHidden));
          this.convOptionsBtn.classList.toggle('active', isHidden);
        }
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (this.convOptionsMenu && !this.convOptionsMenu.hidden) {
          if (!this.convOptionsMenu.contains(e.target) && !this.convOptionsBtn?.contains(e.target)) {
            this.convOptionsMenu.hidden = true;
            this.convOptionsBtn?.setAttribute('aria-expanded', 'false');
            this.convOptionsBtn?.classList.remove('active');
          }
        }
      });

      // View Item from 3-dot dropdown (Mobile)
      this.convMenuViewItem?.addEventListener('click', () => {
        if (this.convOptionsMenu) this.convOptionsMenu.hidden = true;
        if (this.currentConvData?.item?.item_code && window.LostLinkItemDetails) {
          window.LostLinkItemDetails.open(this.currentConvData.item.item_code);
        }
      });

      // Block / Unblock trigger from 3-dot dropdown
      this.convMenuBlockBtn?.addEventListener('click', () => {
        if (this.convOptionsMenu) this.convOptionsMenu.hidden = true;
        if (!this.currentConvData) return;

        if (this.currentConvData.is_blocked && this.currentConvData.blocked_by_current_user) {
          this.unblockUser(this.activeConvCode);
        } else if (this.currentConvData.is_blocked) {
          alert('You cannot unblock this conversation because the other user initiated the block.');
        } else {
          if (this.blockModalUserName) {
            this.blockModalUserName.textContent = this.currentConvData.other_party?.name || 'this user';
          }
          if (this.blockModal) this.blockModal.hidden = false;
        }
      });

      // Unblock button inside notice alert
      this.convUnblockBtn?.addEventListener('click', () => {
        if (this.activeConvCode) {
          this.unblockUser(this.activeConvCode);
        }
      });

      // Block confirmation modal actions
      this.blockModalCloseBtn?.addEventListener('click', () => {
        if (this.blockModal) this.blockModal.hidden = true;
      });
      this.blockModalCancelBtn?.addEventListener('click', () => {
        if (this.blockModal) this.blockModal.hidden = true;
      });
      this.blockModal?.addEventListener('click', (e) => {
        if (e.target === this.blockModal) {
          this.blockModal.hidden = true;
        }
      });
      this.blockModalConfirmBtn?.addEventListener('click', () => {
        if (this.activeConvCode) {
          this.blockUser(this.activeConvCode);
        }
      });

      // Keyboard & viewport resize support for mobile
      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
          if (this.activeConvCode && this.layoutCard?.classList.contains('viewing-thread')) {
            this.scrollToBottom();
          }
        });
      }

      // Popstate navigation
      window.addEventListener('popstate', (e) => {
        const params = new URLSearchParams(window.location.search);
        const c = params.get('c');
        const r = params.get('r');
        if (c) {
          this.openConversation(c, false);
        } else if (r) {
          this.openRequest(r, false);
        } else {
          this.closeActiveView(false);
        }
      });
    }

    getCsrfToken() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    switchTab(tab) {
      this.activeTab = tab;
      if (tab === 'conversations') {
        this.tabConvBtn?.classList.add('active');
        this.tabReqBtn?.classList.remove('active');
        if (this.panelConv) this.panelConv.hidden = false;
        if (this.panelReq) this.panelReq.hidden = true;
      } else {
        this.tabReqBtn?.classList.add('active');
        this.tabConvBtn?.classList.remove('active');
        if (this.panelReq) this.panelReq.hidden = false;
        if (this.panelConv) this.panelConv.hidden = true;
      }
    }

    async loadAllData() {
      await Promise.all([
        this.loadConversations(),
        this.loadRequests()
      ]);
    }

    // ========================================================================
    // CONVERSATIONS
    // ========================================================================
    async loadConversations(silent = false) {
      if (!silent && this.convLoading) this.convLoading.hidden = false;
      try {
        const res = await fetch(`${this.baseUrl}/backend/messages/conversation.php?action=list`, {
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (this.convLoading) this.convLoading.hidden = true;

        if (data.success && data.data) {
          this.conversations = data.data.conversations || [];
          const totalUnread = data.data.total_unread || 0;

          // Update unread count badge
          if (this.totalUnreadBadge) {
            if (totalUnread > 0) {
              this.totalUnreadBadge.textContent = `${totalUnread} unread`;
              this.totalUnreadBadge.hidden = false;
            } else {
              this.totalUnreadBadge.hidden = true;
            }
          }
          if (this.tabConvBadge) {
            if (totalUnread > 0) {
              this.tabConvBadge.textContent = String(totalUnread);
              this.tabConvBadge.hidden = false;
            } else {
              this.tabConvBadge.hidden = true;
            }
          }

          this.renderConversationsList();
        }
      } catch (err) {
        if (this.convLoading) this.convLoading.hidden = true;
        console.error('Failed to load conversations:', err);
      }
    }

    renderConversationsList(filterQuery = '') {
      if (!this.convUl) return;
      this.convUl.innerHTML = '';

      let items = this.conversations;
      if (filterQuery) {
        items = items.filter(c => 
          (c.other_party?.name || '').toLowerCase().includes(filterQuery) ||
          (c.item?.title || '').toLowerCase().includes(filterQuery) ||
          (c.item?.item_code || '').toLowerCase().includes(filterQuery)
        );
      }

      if (items.length === 0) {
        if (this.convEmpty) this.convEmpty.hidden = false;
        return;
      }

      if (this.convEmpty) this.convEmpty.hidden = true;

      items.forEach(c => {
        const li = document.createElement('li');
        const isActive = (c.conversation_code === this.activeConvCode);

        li.className = `conv-item-card ${isActive ? 'active' : ''}`;
        li.dataset.code = c.conversation_code;

        const isFound = (c.item?.type === 'found');
        const badgeCls = isFound ? 'is-found' : 'is-lost';
        const badgeText = isFound ? 'F' : 'L';

        li.innerHTML = `
          <div class="conv-card-avatar-wrap">
            <img src="${this.escapeHtml(c.other_party?.avatar_url || '')}" alt="${this.escapeHtml(c.other_party?.name || '')}" class="conv-card-avatar" onerror="this.src='${this.baseUrl}/profile/default_user.png'">
            <span class="conv-card-item-badge ${badgeCls}" title="${isFound ? 'Found' : 'Lost'} Item">${badgeText}</span>
          </div>
          <div class="conv-card-body">
            <div class="conv-card-top">
              <span class="conv-card-name">${this.escapeHtml(c.other_party?.name || 'User')}</span>
              <span class="conv-card-time">${this.escapeHtml(c.latest_message?.created_at_rel || '')}</span>
            </div>
            <span class="conv-card-item-title">${this.escapeHtml(c.item?.title || 'Item')}</span>
            <div class="conv-card-bottom">
              <p class="conv-card-preview">${c.latest_message?.is_outgoing ? 'You: ' : ''}${this.escapeHtml(c.latest_message?.text || '')}</p>
              ${c.unread_count > 0 ? `<span class="conv-card-unread-pill">${c.unread_count}</span>` : ''}
            </div>
          </div>
        `;

        li.addEventListener('click', () => {
          this.openConversation(c.conversation_code);
        });

        this.convUl.appendChild(li);
      });
    }

    async openConversation(convCode, updateUrl = true) {
      if (!convCode) return;
      this.activeConvCode = convCode;
      this.activeReqCode = null;

      // Update UI active card
      if (this.convUl) {
        this.convUl.querySelectorAll('.conv-item-card').forEach(card => {
          card.classList.toggle('active', card.dataset.code === convCode);
        });
      }

      // Switch to conversation view pane
      if (this.viewEmpty) this.viewEmpty.hidden = true;
      if (this.viewReq) this.viewReq.hidden = true;
      if (this.viewConv) this.viewConv.hidden = false;

      // Mobile slide into thread view
      this.layoutCard?.classList.add('viewing-thread');

      if (updateUrl) {
        const newUrl = `${this.baseUrl}/messages/index.php?c=${encodeURIComponent(convCode)}`;
        window.history.replaceState({ c: convCode }, '', newUrl);
      }

      // Fetch conversation thread
      await this.fetchConversationThread(convCode);

      // Start live polling
      this.startPolling(convCode);
    }

    async fetchConversationThread(convCode) {
      try {
        const res = await fetch(`${this.baseUrl}/backend/messages/conversation.php?action=get&code=${encodeURIComponent(convCode)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (!json.success || !json.data) {
          throw new Error(json.error || 'Failed to load conversation');
        }

        const conv = json.data;
        this.currentConvData = conv;

        // Populate Header
        if (this.convItemThumb) {
          this.convItemThumb.onerror = () => {
            this.convItemThumb.src = `${this.baseUrl}/assets/images/default_no_image.png`;
          };
          this.convItemThumb.src = conv.item?.thumbnail_url || `${this.baseUrl}/assets/images/default_no_image.png`;
        }
        if (this.convItemTitle) this.convItemTitle.textContent = conv.item?.title || 'Item';
        if (this.convItemCode) this.convItemCode.textContent = conv.item?.item_code || '';
        if (this.convBadgeType) {
          const isFound = (conv.item?.type === 'found');
          this.convBadgeType.textContent = isFound ? 'Found' : 'Lost';
          this.convBadgeType.className = `conv-badge-type ${isFound ? 'is-found' : 'is-lost'}`;
        }
        const otherName = conv.other_party?.name || 'User';
        const otherDept = conv.other_party?.department || 'Student';
        if (this.convOtherName) this.convOtherName.textContent = otherName;
        if (this.convOtherDept) this.convOtherDept.textContent = otherDept;
        if (this.convOtherNameM) this.convOtherNameM.textContent = otherName;
        if (this.convOtherDeptM) this.convOtherDeptM.textContent = otherDept;

        // Apply Conversation State (Active, Closed, or Blocked)
        this.applyConversationStatusUI(conv);

        // Render Messages
        this.renderMessagesThread(conv.messages || []);

        // Decrement unread counts locally
        const targetConv = this.conversations.find(c => c.conversation_code === convCode);
        if (targetConv && targetConv.unread_count > 0) {
          targetConv.unread_count = 0;
          this.renderConversationsList();
        }

      } catch (err) {
        console.error('Error fetching conversation thread:', err);
      }
    }

    applyConversationStatusUI(conv) {
      const isBlocked = Boolean(conv.is_blocked);
      const blockedByMe = Boolean(conv.blocked_by_current_user);
      const isClosed = Boolean(conv.is_closed);

      if (isBlocked) {
        // Blocked State
        if (this.convStatusPill) {
          this.convStatusPill.textContent = 'Blocked';
          this.convStatusPill.className = 'conv-status-pill is-blocked';
        }
        if (this.convClosedAlert) this.convClosedAlert.hidden = true;
        if (this.convBlockedAlert) {
          this.convBlockedAlert.hidden = false;
          if (this.convBlockedText) {
            this.convBlockedText.textContent = blockedByMe 
              ? 'You have blocked this user. Messaging is disabled.' 
              : 'This conversation is blocked. Messages cannot be sent.';
          }
          if (this.convUnblockBtn) {
            this.convUnblockBtn.hidden = !blockedByMe;
          }
        }
        if (this.convComposerBox) {
          this.convComposerBox.classList.add('is-blocked');
        }
        if (this.convMessageInput) {
          this.convMessageInput.disabled = true;
          this.convMessageInput.placeholder = blockedByMe 
            ? 'You have blocked this user.' 
            : 'Conversation is blocked.';
        }
        if (this.convSendBtn) {
          this.convSendBtn.disabled = true;
        }
        if (this.convMenuBlockText) {
          this.convMenuBlockText.textContent = blockedByMe ? 'Unblock User' : 'User Blocked';
        }
        if (this.convMenuBlockBtn) {
          this.convMenuBlockBtn.disabled = !blockedByMe;
        }

      } else if (isClosed) {
        // Closed State
        if (this.convStatusPill) {
          this.convStatusPill.textContent = 'Closed';
          this.convStatusPill.className = 'conv-status-pill is-closed';
        }
        if (this.convBlockedAlert) this.convBlockedAlert.hidden = true;
        if (this.convClosedAlert) this.convClosedAlert.hidden = false;
        if (this.convComposerBox) {
          this.convComposerBox.classList.add('is-blocked');
        }
        if (this.convMessageInput) {
          this.convMessageInput.disabled = true;
          this.convMessageInput.placeholder = 'This conversation is closed.';
        }
        if (this.convSendBtn) {
          this.convSendBtn.disabled = true;
        }
        if (this.convMenuBlockText) {
          this.convMenuBlockText.textContent = 'Block User';
        }
        if (this.convMenuBlockBtn) {
          this.convMenuBlockBtn.disabled = false;
        }

      } else {
        // Active State
        if (this.convStatusPill) {
          this.convStatusPill.textContent = 'Active';
          this.convStatusPill.className = 'conv-status-pill';
        }
        if (this.convBlockedAlert) this.convBlockedAlert.hidden = true;
        if (this.convClosedAlert) this.convClosedAlert.hidden = true;
        if (this.convComposerBox) {
          this.convComposerBox.classList.remove('is-blocked');
        }
        if (this.convMessageInput) {
          this.convMessageInput.disabled = false;
          this.convMessageInput.placeholder = 'Type a message...';
        }
        if (this.convSendBtn) {
          this.convSendBtn.disabled = false;
        }
        if (this.convMenuBlockText) {
          this.convMenuBlockText.textContent = 'Block User';
        }
        if (this.convMenuBlockBtn) {
          this.convMenuBlockBtn.disabled = false;
        }
      }
    }

    async blockUser(convCode) {
      if (!convCode || this.isSubmitting) return;
      this.isSubmitting = true;
      if (this.blockModalConfirmBtn) {
        this.blockModalConfirmBtn.disabled = true;
        this.blockModalConfirmBtn.textContent = 'Blocking...';
      }

      try {
        const csrfToken = this.getCsrfToken();
        const postUrl = `${this.baseUrl}/backend/messages/conversation.php?action=block`;
        const formData = new URLSearchParams();
        formData.append('conversation_code', convCode);
        formData.append('csrf_token', csrfToken);

        const res = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const json = await res.json();
        if (!res.ok || !json.success) {
          throw new Error(json.error || 'Failed to block user.');
        }

        if (this.blockModal) this.blockModal.hidden = true;

        // Update local conversation state immediately without reload
        if (this.currentConvData) {
          this.currentConvData.is_blocked = true;
          this.currentConvData.blocked_by_current_user = true;
          this.currentConvData.can_message = false;
          this.applyConversationStatusUI(this.currentConvData);
        }

        const convInList = this.conversations.find(c => c.conversation_code === convCode);
        if (convInList) {
          convInList.is_blocked = true;
          convInList.blocked_by_current_user = true;
        }

      } catch (err) {
        alert(err.message || 'Error blocking user.');
      } finally {
        this.isSubmitting = false;
        if (this.blockModalConfirmBtn) {
          this.blockModalConfirmBtn.disabled = false;
          this.blockModalConfirmBtn.textContent = 'Block User';
        }
      }
    }

    async unblockUser(convCode) {
      if (!convCode || this.isSubmitting) return;
      this.isSubmitting = true;

      try {
        const csrfToken = this.getCsrfToken();
        const postUrl = `${this.baseUrl}/backend/messages/conversation.php?action=unblock`;
        const formData = new URLSearchParams();
        formData.append('conversation_code', convCode);
        formData.append('csrf_token', csrfToken);

        const res = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const json = await res.json();
        if (!res.ok || !json.success) {
          throw new Error(json.error || 'Failed to unblock user.');
        }

        // Update local conversation state immediately without reload
        if (this.currentConvData) {
          this.currentConvData.is_blocked = Boolean(json.data?.is_blocked);
          this.currentConvData.blocked_by_current_user = Boolean(json.data?.blocked_by_current_user);
          this.currentConvData.can_message = Boolean(json.data?.can_message);
          this.applyConversationStatusUI(this.currentConvData);
        }

        const convInList = this.conversations.find(c => c.conversation_code === convCode);
        if (convInList) {
          convInList.is_blocked = Boolean(json.data?.is_blocked);
          convInList.blocked_by_current_user = Boolean(json.data?.blocked_by_current_user);
        }

      } catch (err) {
        alert(err.message || 'Error unblocking user.');
      } finally {
        this.isSubmitting = false;
      }
    }

    renderMessagesThread(messages) {
      if (!this.convMessagesContainer) return;
      this.convMessagesContainer.innerHTML = '';

      messages.forEach(m => {
        const bubble = document.createElement('div');
        const isOut = m.is_outgoing;
        bubble.className = `msg-bubble-row ${isOut ? 'outgoing' : 'incoming'}`;

        bubble.innerHTML = `
          ${!isOut ? `<img src="${this.escapeHtml(m.sender_avatar || '')}" alt="${this.escapeHtml(m.sender_name)}" class="msg-avatar" onerror="this.src='${this.baseUrl}/profile/default_user.png'">` : ''}
          <div class="msg-bubble-content">
            <div class="msg-text-bubble">${this.escapeHtml(m.message)}</div>
            <div class="msg-meta-row">
              <span class="msg-time">${this.escapeHtml(m.time_formatted || '')}</span>
              ${isOut ? `
                <span class="msg-read-icon" title="${m.is_read ? 'Read' : 'Delivered'}">
                  ${m.is_read ? '✓✓' : '✓'}
                </span>
              ` : ''}
            </div>
          </div>
        `;

        this.convMessagesContainer.appendChild(bubble);
      });

      this.scrollToBottom();
    }

    scrollToBottom() {
      if (this.convThreadScroll) {
        this.convThreadScroll.scrollTop = this.convThreadScroll.scrollHeight;
      }
    }

    async sendMessage() {
      if (!this.currentConvData || !this.convMessageInput || this.isSubmitting) return;

      const text = this.convMessageInput.value.trim();
      if (!text) return;

      this.isSubmitting = true;
      if (this.convSendBtn) {
        this.convSendBtn.disabled = true;
      }

      try {
        const csrfToken = this.getCsrfToken();
        const postUrl = `${this.baseUrl}/backend/messages/send_message.php`;

        const formData = new URLSearchParams();
        formData.append('conversation_code', this.currentConvData.conversation_code);
        formData.append('message', text);
        formData.append('csrf_token', csrfToken);

        const res = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const json = await res.json();

        if (!res.ok || !json.success) {
          throw new Error(json.error || 'Failed to send message.');
        }

        // Clear input
        this.convMessageInput.value = '';
        this.convMessageInput.style.height = 'auto';

        // Append message to UI
        if (json.data) {
          const newMsg = json.data;
          const bubble = document.createElement('div');
          bubble.className = 'msg-bubble-row outgoing';
          bubble.innerHTML = `
            <div class="msg-bubble-content">
              <div class="msg-text-bubble">${this.escapeHtml(newMsg.message)}</div>
              <div class="msg-meta-row">
                <span class="msg-time">${this.escapeHtml(newMsg.time_formatted || 'Just now')}</span>
                <span class="msg-read-icon" title="Delivered">✓</span>
              </div>
            </div>
          `;
          this.convMessagesContainer?.appendChild(bubble);
          this.scrollToBottom();

          // Update list preview
          const c = this.conversations.find(x => x.conversation_code === this.currentConvData.conversation_code);
          if (c) {
            c.latest_message = {
              text: newMsg.message,
              created_at_rel: 'Just now',
              is_outgoing: true
            };
            this.renderConversationsList();
          }
        }

      } catch (err) {
        alert(err.message || 'Error sending message. Please try again.');
      } finally {
        this.isSubmitting = false;
        if (this.convSendBtn) {
          this.convSendBtn.disabled = false;
        }
        this.convMessageInput?.focus();
      }
    }

    startPolling(convCode) {
      this.stopPolling();
      this.pollingTimer = setInterval(() => {
        if (!document.hidden) {
          if (this.activeConvCode === convCode) {
            this.fetchConversationThread(convCode);
          }
          this.loadConversations(true);
          this.loadRequests(true);
        }
      }, 5000);
    }

    stopPolling() {
      if (this.pollingTimer) {
        clearInterval(this.pollingTimer);
        this.pollingTimer = null;
      }
    }

    // ========================================================================
    // CONTACT REQUESTS
    // ========================================================================
    async loadRequests(silent = false) {
      if (!silent && this.reqLoading) this.reqLoading.hidden = false;
      try {
        const res = await fetch(`${this.baseUrl}/backend/messages/contact_request.php?action=list`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (this.reqLoading) this.reqLoading.hidden = true;

        if (json.success && json.data) {
          this.requests = {
            incoming: json.data.incoming || [],
            outgoing: json.data.outgoing || []
          };

          const pendingIncoming = json.data.pending_incoming_count || 0;
          if (this.tabReqBadge) {
            if (pendingIncoming > 0) {
              this.tabReqBadge.textContent = String(pendingIncoming);
              this.tabReqBadge.hidden = false;
            } else {
              this.tabReqBadge.hidden = true;
            }
          }

          if (this.countIncoming) this.countIncoming.textContent = String(this.requests.incoming.length);
          if (this.countOutgoing) this.countOutgoing.textContent = String(this.requests.outgoing.length);

          this.renderRequestsList();
        }
      } catch (err) {
        if (this.reqLoading) this.reqLoading.hidden = true;
        console.error('Failed to load contact requests:', err);
      }
    }

    renderRequestsList(filterQuery = '') {
      if (!this.reqUl) return;
      this.reqUl.innerHTML = '';

      const items = (this.activeSubfilter === 'incoming') ? this.requests.incoming : this.requests.outgoing;
      let filtered = items;

      if (filterQuery) {
        filtered = filtered.filter(r => 
          (r.other_party_name || '').toLowerCase().includes(filterQuery) ||
          (r.item_title || '').toLowerCase().includes(filterQuery) ||
          (r.item_code || '').toLowerCase().includes(filterQuery)
        );
      }

      if (filtered.length === 0) {
        if (this.reqEmpty) this.reqEmpty.hidden = false;
        return;
      }

      if (this.reqEmpty) this.reqEmpty.hidden = true;

      filtered.forEach(r => {
        const li = document.createElement('li');
        const isActive = (r.request_code === this.activeReqCode);
        li.className = `req-item-card ${isActive ? 'active' : ''}`;
        li.dataset.code = r.request_code;

        const isIncoming = (this.activeSubfilter === 'incoming');
        const statusMap = {
          'pending': { text: 'Pending', cls: 'status-pending' },
          'accepted': { text: 'Accepted', cls: 'status-accepted' },
          'rejected': { text: 'Declined', cls: 'status-rejected' }
        };
        const st = statusMap[r.status] || { text: r.status, cls: 'status-pending' };

        li.innerHTML = `
          <div class="req-card-header">
            <div class="req-card-user">
              <img src="${this.escapeHtml(r.other_party_avatar || '')}" alt="${this.escapeHtml(r.other_party_name || '')}" class="req-card-avatar" onerror="this.src='${this.baseUrl}/profile/default_user.png'">
              <span class="req-card-username">${this.escapeHtml(r.other_party_name || 'User')}</span>
            </div>
            <span class="req-card-status-badge ${st.cls}">${st.text}</span>
          </div>
          <div class="req-card-item">
            ${isIncoming ? 'Regarding your report:' : 'Regarding:'} <strong>${this.escapeHtml(r.item_title || 'Item')}</strong>
          </div>
          <p class="req-card-snippet">"${this.escapeHtml(r.message_snippet || '')}"</p>
        `;

        li.addEventListener('click', () => {
          this.openRequest(r.request_code);
        });

        this.reqUl.appendChild(li);
      });
    }

    async openRequest(requestCode, updateUrl = true) {
      if (!requestCode) return;
      this.activeReqCode = requestCode;
      this.activeConvCode = null;
      this.stopPolling();

      // Highlight card
      if (this.reqUl) {
        this.reqUl.querySelectorAll('.req-item-card').forEach(card => {
          card.classList.toggle('active', card.dataset.code === requestCode);
        });
      }

      // Switch view pane
      if (this.viewEmpty) this.viewEmpty.hidden = true;
      if (this.viewConv) this.viewConv.hidden = true;
      if (this.viewReq) this.viewReq.hidden = false;

      this.layoutCard?.classList.add('viewing-thread');

      if (updateUrl) {
        const newUrl = `${this.baseUrl}/messages/index.php?r=${encodeURIComponent(requestCode)}`;
        window.history.replaceState({ r: requestCode }, '', newUrl);
      }

      try {
        const res = await fetch(`${this.baseUrl}/backend/messages/contact_request.php?action=get&code=${encodeURIComponent(requestCode)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();

        if (!json.success || !json.data) {
          throw new Error(json.error || 'Failed to load request');
        }

        const req = json.data;
        this.currentReqData = req;

        // Item Card
        if (this.reqItemImg) this.reqItemImg.src = req.item?.image_url || `${this.baseUrl}/assets/images/default_no_image.png`;
        if (this.reqItemTitle) this.reqItemTitle.textContent = req.item?.title || 'Item';
        if (this.reqItemCode) this.reqItemCode.textContent = req.item?.item_code || '';
        if (this.reqItemLocation) this.reqItemLocation.textContent = req.item?.location || 'Location unspecified';
        if (this.reqItemType) {
          const isFound = (req.item?.type === 'found');
          this.reqItemType.textContent = isFound ? 'Found' : 'Lost';
          this.reqItemType.className = `req-badge-type ${isFound ? 'is-found' : 'is-lost'}`;
        }

        // Requester/Reporter User Card
        const displayUser = req.is_reporter ? req.sender : req.reporter;
        const roleTitle = req.is_reporter ? 'Requester' : 'Reporter';

        if (this.reqUserRoleLabel) this.reqUserRoleLabel.textContent = roleTitle;
        if (this.reqUserName) this.reqUserName.textContent = displayUser?.name || 'User';
        if (this.reqUserDept) this.reqUserDept.textContent = displayUser?.department || 'Student';
        if (this.reqUserAvatar) this.reqUserAvatar.src = displayUser?.avatar_url || `${this.baseUrl}/profile/default_user.png`;
        if (this.reqCreatedTime) this.reqCreatedTime.textContent = req.created_at_rel || 'Recently';

        // Message
        if (this.reqMessageContent) this.reqMessageContent.textContent = `"${req.initial_message || ''}"`;

        // Status Badge
        const statusMap = {
          'pending': { text: 'Pending Response', cls: 'status-pending' },
          'accepted': { text: 'Accepted', cls: 'status-accepted' },
          'rejected': { text: 'Declined', cls: 'status-rejected' }
        };
        const st = statusMap[req.status] || { text: req.status, cls: 'status-pending' };
        if (this.reqStatusBadge) {
          this.reqStatusBadge.textContent = st.text;
          this.reqStatusBadge.className = `badge ${st.cls}`;
        }

        // Actions Panel
        this.renderRequestActions(req);

      } catch (err) {
        console.error('Error fetching contact request details:', err);
      }
    }

    renderRequestActions(req) {
      if (!this.reqActionsPanel) return;
      this.reqActionsPanel.innerHTML = '';

      if (req.status === 'pending') {
        if (req.is_reporter) {
          // Reporter view: can accept or reject
          this.reqActionsPanel.innerHTML = `
            <p class="req-actions-prompt">Would you like to accept this contact request and open a private conversation?</p>
            <div class="req-action-buttons">
              <button type="button" class="btn btn-danger-outline" id="btn-reject-request">Decline Request</button>
              <button type="button" class="btn btn-success" id="btn-accept-request">Accept &amp; Open Chat</button>
            </div>
          `;

          document.getElementById('btn-accept-request')?.addEventListener('click', () => {
            this.respondToRequest(req.request_code, 'accept');
          });

          document.getElementById('btn-reject-request')?.addEventListener('click', () => {
            const reason = prompt('Optional: Reason for declining this request (will be shown to requester):', '');
            if (reason !== null) {
              this.respondToRequest(req.request_code, 'reject', reason);
            }
          });
        } else {
          // Requester view: pending
          this.reqActionsPanel.innerHTML = `
            <p class="req-actions-prompt">
              Your inquiry was sent to the reporter. You will receive a notification as soon as they accept or decline.
            </p>
          `;
        }
      } else if (req.status === 'accepted') {
        this.reqActionsPanel.innerHTML = `
          <p class="req-actions-prompt text-success">
            <strong>This contact request has been accepted.</strong> A private communication channel is active.
          </p>
          ${req.conversation_code ? `
            <div class="req-action-buttons">
              <button type="button" class="btn btn-primary" id="btn-goto-conv">Go to Conversation &rarr;</button>
            </div>
          ` : ''}
        `;

        document.getElementById('btn-goto-conv')?.addEventListener('click', () => {
          this.switchTab('conversations');
          this.openConversation(req.conversation_code);
        });
      } else if (req.status === 'rejected') {
        this.reqActionsPanel.innerHTML = `
          <p class="req-actions-prompt text-danger">
            <strong>This contact request was declined.</strong> Messaging is not permitted for this request.
          </p>
          ${req.rejection_reason ? `<p class="text-secondary" style="font-size: 13px; margin: 0;">Reason: ${this.escapeHtml(req.rejection_reason)}</p>` : ''}
        `;
      }
    }

    async respondToRequest(requestCode, decision, reason = '') {
      try {
        const csrfToken = this.getCsrfToken();
        const postUrl = `${this.baseUrl}/backend/messages/contact_request.php?action=respond`;

        const formData = new URLSearchParams();
        formData.append('request_code', requestCode);
        formData.append('decision', decision);
        if (reason) formData.append('rejection_reason', reason);
        formData.append('csrf_token', csrfToken);

        const res = await fetch(postUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData.toString()
        });

        const json = await res.json();

        if (!res.ok || !json.success) {
          throw new Error(json.error || 'Failed to process request.');
        }

        // Immediate in-memory state update
        const targetReq = this.requests.incoming.find(r => r.request_code === requestCode);
        if (targetReq) {
          targetReq.status = (decision === 'accept') ? 'accepted' : 'rejected';
          if (decision === 'accept' && json.data?.conversation_code) {
            targetReq.conversation_code = json.data.conversation_code;
          }
        }
        if (this.currentReqData && this.currentReqData.request_code === requestCode) {
          this.currentReqData.status = (decision === 'accept') ? 'accepted' : 'rejected';
          if (decision === 'reject') {
            this.currentReqData.rejection_reason = reason;
          }
          if (decision === 'accept' && json.data?.conversation_code) {
            this.currentReqData.conversation_code = json.data.conversation_code;
          }
        }

        // Decrement pending count badge immediately
        const remainingPending = this.requests.incoming.filter(r => r.status === 'pending').length;
        if (this.tabReqBadge) {
          if (remainingPending > 0) {
            this.tabReqBadge.textContent = String(remainingPending);
            this.tabReqBadge.hidden = false;
          } else {
            this.tabReqBadge.hidden = true;
          }
        }

        if (decision === 'accept' && json.data?.conversation_code) {
          // Immediately load updated conversations list and open conversation without page reload
          await this.loadConversations(true);
          this.switchTab('conversations');
          this.openConversation(json.data.conversation_code);
        } else {
          // Re-render requests list and active request view immediately without reload
          this.renderRequestsList();
          if (this.currentReqData) {
            this.renderRequestActions(this.currentReqData);
            if (this.reqStatusBadge) {
              this.reqStatusBadge.textContent = 'Declined';
              this.reqStatusBadge.className = 'badge status-rejected';
            }
          }
          await this.loadRequests(true);
        }

      } catch (err) {
        alert(err.message || 'Error processing response. Please try again.');
      }
    }

    closeActiveView(updateUrl = true) {
      this.activeConvCode = null;
      this.activeReqCode = null;
      this.stopPolling();

      this.layoutCard?.classList.remove('viewing-thread');

      if (this.viewConv) this.viewConv.hidden = true;
      if (this.viewReq) this.viewReq.hidden = true;
      if (this.viewEmpty) this.viewEmpty.hidden = false;

      if (this.convUl) {
        this.convUl.querySelectorAll('.conv-item-card').forEach(card => card.classList.remove('active'));
      }
      if (this.reqUl) {
        this.reqUl.querySelectorAll('.req-item-card').forEach(card => card.classList.remove('active'));
      }

      if (updateUrl) {
        window.history.replaceState({}, '', `${this.baseUrl}/messages/index.php`);
      }
    }

    filterLists(query) {
      if (this.activeTab === 'conversations') {
        this.renderConversationsList(query);
      } else {
        this.renderRequestsList(query);
      }
    }

    escapeHtml(str) {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.LostLinkMessages = new MessagesManager();
    });
  } else {
    window.LostLinkMessages = new MessagesManager();
  }

})();
