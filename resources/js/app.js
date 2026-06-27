import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ===========================
// SETUP LARAVEL ECHO (WebSocket)
// ===========================
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key:         import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:      import.meta.env.VITE_REVERB_HOST,
    wsPort:      import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort:     import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS:    (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// ===========================
// CHAT APP STATE
// ===========================
const appEl = document.getElementById('chat-app');
if (!appEl) process.exit?.(); // only run on chat page

const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢'];

const STATE = {
    currentUser:      JSON.parse(appEl.dataset.user      || '{}'),
    conversations:    JSON.parse(appEl.dataset.conversations || '[]'),
    users:            JSON.parse(appEl.dataset.users     || '[]'),
    activeConvId:     null,
    echoChannel:      null,
    replyTo:          null,   // { id, body, sender_name, isImage }
    pendingImage:     null,   // File object yang mau diupload
    typingTimeout:    null,
    lastTypingSentAt: 0,
};

// ===========================
// DOM ELEMENTS
// ===========================
const $ = id => document.getElementById(id);

const EL = {
    convList:       $('conversation-list'),
    emptyChats:     $('empty-chats'),
    usersList:      $('users-list'),
    chatWelcome:    $('chat-welcome'),
    chatWindow:     $('chat-window'),
    chatAvatar:     $('chat-avatar'),
    chatName:       $('chat-name'),
    chatStatus:     $('chat-status'),
    chatTypeBadge:  $('chat-type-badge'),
    messagesArea:   $('messages-area'),
    messagesList:   $('messages-list'),
    messagesLoading:$('messages-loading'),
    messageForm:    $('message-form'),
    messageInput:   $('message-input'),
    searchInput:    $('search-input'),
    tabChats:       $('tab-chats'),
    tabUsers:       $('tab-users'),
    tabContentChats:$('tab-content-chats'),
    tabContentUsers:$('tab-content-users'),
    modalGroup:     $('modal-group'),
    btnNewGroup:    $('btn-new-group'),
    modalGroupClose:$('modal-group-close'),
    modalGroupCancel:$('modal-group-cancel'),
    btnCreateGroup: $('btn-create-group'),
    groupNameInput: $('group-name-input'),
    memberList:     $('member-list'),
    btnAttach:      $('btn-attach'),
    fileInput:      $('file-input'),
    imagePreviewBar:$('image-preview-bar'),
    imagePreviewImg:$('image-preview-img'),
    imagePreviewRemove: $('image-preview-remove'),
    replyBar:       $('reply-bar'),
    replyBarName:   $('reply-bar-name'),
    replyBarText:   $('reply-bar-text'),
    replyBarClose:  $('reply-bar-close'),
};

// ===========================
// CSRF TOKEN HELPER
// ===========================
const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;

async function fetchJSON(url, options = {}) {
    const isFormData = options.body instanceof FormData;
    const res = await fetch(url, {
        headers: {
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
            ...options.headers,
        },
        ...options,
    });
    if (!res.ok) {
        const errBody = await res.json().catch(() => ({}));
        throw new Error(errBody.message || `HTTP ${res.status}`);
    }
    return res.json();
}

// ===========================
// RENDER: CONVERSATION LIST
// ===========================
function renderConversations(list) {
    EL.convList.innerHTML = '';

    if (list.length === 0) {
        EL.convList.appendChild(EL.emptyChats);
        EL.emptyChats.classList.remove('hidden');
        return;
    }

    list.forEach(conv => {
        const el = document.createElement('div');
        el.className = `conv-item ${conv.id === STATE.activeConvId ? 'active' : ''}`;
        el.dataset.id = conv.id;

        const avatarText = conv.type === 'group' ? '👥' : getInitials(conv.name);
        const otherParticipant = conv.participants?.find(p => p.id !== STATE.currentUser.id);
        const isOnline = conv.type === 'private' && otherParticipant?.is_online;

        el.innerHTML = `
            <div class="avatar ${isOnline ? 'online' : 'offline'}">${avatarText}</div>
            <div class="conv-info">
                <div class="conv-name">${escHtml(conv.name)}</div>
                <div class="conv-last">${escHtml(conv.last_message || '')}</div>
            </div>
            <div class="conv-meta">
                <span class="conv-time">${conv.last_time || ''}</span>
                ${conv.unread > 0 ? `<span class="conv-badge">${conv.unread}</span>` : ''}
            </div>
        `;

        el.addEventListener('click', () => openConversation(conv));
        EL.convList.appendChild(el);
    });
}

// ===========================
// RENDER: USERS LIST
// ===========================
function renderUsers(list) {
    EL.usersList.innerHTML = '';
    list.forEach(user => {
        const el = document.createElement('div');
        el.className = 'user-item';
        el.dataset.userId = user.id;
        el.innerHTML = `
            <div class="avatar ${user.is_online ? 'online' : 'offline'}">${user.initials}</div>
            <div class="user-info">
                <div class="user-name">${escHtml(user.name)}</div>
                <div class="user-status-text ${user.is_online ? 'online' : 'offline'}">
                    ${user.is_online ? '● Online' : user.last_seen || 'Offline'}
                </div>
            </div>
        `;
        el.addEventListener('click', () => startPrivateChat(user));
        EL.usersList.appendChild(el);
    });
}

// ===========================
// OPEN CONVERSATION
// ===========================
async function openConversation(conv) {
    STATE.activeConvId = conv.id;
    clearReply();
    clearImagePreview();
    hideTyping();

    const otherParticipant = conv.participants?.find(p => p.id !== STATE.currentUser.id);
    const isOnline = conv.type === 'private' && otherParticipant?.is_online;

    EL.chatWelcome.classList.add('hidden');
    EL.chatWindow.classList.remove('hidden');
    EL.chatName.textContent    = conv.name;
    EL.chatAvatar.textContent  = conv.type === 'group' ? '👥' : getInitials(conv.name);
    EL.chatAvatar.className    = `avatar ${isOnline ? 'online' : 'offline'}`;
    EL.chatStatus.textContent  = conv.type === 'private'
        ? (isOnline ? 'Online' : (otherParticipant?.last_seen || 'Offline'))
        : `${conv.participants?.length || 0} anggota`;
    EL.chatTypeBadge.textContent = conv.type === 'group' ? '👥 Group' : '🔒 Private';

    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    const activeEl = EL.convList.querySelector(`[data-id="${conv.id}"]`);
    if (activeEl) activeEl.classList.add('active');

    conv.unread = 0;
    updateUnreadBadge(conv.id);

    EL.messagesLoading.classList.remove('hidden');
    EL.messagesList.innerHTML = '';
    try {
        const messages = await fetchJSON(`/conversations/${conv.id}/messages`);
        renderMessages(messages);
    } catch (e) {
        console.error('Gagal load pesan:', e);
    } finally {
        EL.messagesLoading.classList.add('hidden');
    }

    subscribeToConversation(conv.id);
    scrollToBottom();

    // Tandai semua pesan masuk sebagai "read" karena chat ini sedang dibuka
    fetchJSON(`/conversations/${conv.id}/read`, { method: 'POST', body: '{}' }).catch(() => {});
}

// ===========================
// RENDER MESSAGES
// ===========================
function renderMessages(messages) {
    EL.messagesList.innerHTML = '';
    let lastDate = null;

    messages.forEach(msg => {
        if (msg.date !== lastDate) {
            lastDate = msg.date;
            const sep = document.createElement('div');
            sep.className = 'date-separator';
            sep.innerHTML = `<span>${msg.date}</span>`;
            EL.messagesList.appendChild(sep);
        }
        EL.messagesList.appendChild(createMessageEl(msg));
    });

    scrollToBottom();
}

function statusTicksHtml(status) {
    if (status === 'read') return '<span class="msg-ticks read" title="Dibaca">✓✓</span>';
    if (status === 'delivered') return '<span class="msg-ticks" title="Terkirim ke perangkat">✓✓</span>';
    return '<span class="msg-ticks" title="Terkirim">✓</span>';
}

function reactionsRowHtml(reactions) {
    if (!reactions || reactions.length === 0) return '';
    const grouped = {};
    reactions.forEach(r => {
        grouped[r.emoji] = grouped[r.emoji] || [];
        grouped[r.emoji].push(r.name);
    });
    const pills = Object.entries(grouped).map(([emoji, names]) => `
        <span class="reaction-pill" data-emoji="${emoji}" title="${escHtml(names.join(', '))}">
            ${emoji} <span class="reaction-count">${names.length}</span>
        </span>
    `).join('');
    return `<div class="reactions-row">${pills}</div>`;
}

function replyQuoteHtml(parent) {
    if (!parent) return '';
    const previewText = parent.body ? escHtml(parent.body) : '📷 Foto';
    return `
        <div class="reply-quote">
            <span class="reply-quote-name">${escHtml(parent.sender_name)}</span>
            <span class="reply-quote-text">${previewText}</span>
        </div>
    `;
}

function bubbleInnerHtml(msg) {
    if (msg.is_deleted) {
        return `<div class="msg-bubble deleted-bubble" data-msg-id="${msg.id}">
            <em>🚫 Pesan telah dihapus</em>
        </div>`;
    }

    const mediaHtml = msg.type === 'image' && msg.media_url
        ? `<img src="${msg.media_url}" class="msg-image" alt="gambar" loading="lazy">`
        : '';
    const bodyHtml = msg.body ? `<div class="msg-text">${escHtml(msg.body)}</div>` : '';
    const ticks = msg.is_mine ? statusTicksHtml(msg.status) : '';

    return `
        <div class="msg-bubble" data-msg-id="${msg.id}">
            ${replyQuoteHtml(msg.parent)}
            ${mediaHtml}
            ${bodyHtml}
            <div class="msg-time-row">
                <span class="msg-time">${msg.created_at}</span>
                ${ticks}
            </div>
        </div>
    `;
}

function actionsHtml(msg) {
    const deleteBtn = msg.is_mine
        ? `<button class="msg-action-btn delete-btn" title="Hapus pesan">🗑</button>`
        : '';
    return `
        <div class="msg-actions">
            <button class="msg-action-btn react-btn" title="Beri reaction">😊</button>
            <button class="msg-action-btn reply-btn" title="Balas">↩</button>
            ${deleteBtn}
        </div>
    `;
}

function createMessageEl(msg) {
    const wrapper = document.createElement('div');
    wrapper.className = `msg-wrapper ${msg.is_mine ? 'mine' : ''}`;
    wrapper.dataset.msgId = msg.id;
    wrapper.dataset.status = msg.status || 'sent';
    wrapper.dataset.isMine = msg.is_mine ? '1' : '0';
    wrapper.dataset.body = msg.body || '';
    wrapper.dataset.senderName = msg.sender.name;
    wrapper.dataset.isImage = msg.type === 'image' ? '1' : '0';

    const senderAvatar = !msg.is_mine
        ? `<div class="avatar avatar-sm msg-sender-avatar">${msg.sender.initials}</div>`
        : '';
    const senderName = !msg.is_mine
        ? `<div class="msg-sender-name">${escHtml(msg.sender.name)}</div>`
        : '';

    wrapper.innerHTML = `
        ${senderAvatar}
        <div class="msg-content">
            ${senderName}
            <div class="msg-bubble-wrap">
                ${bubbleInnerHtml(msg)}
                ${msg.is_deleted ? '' : actionsHtml(msg)}
            </div>
            ${msg.is_deleted ? '' : reactionsRowHtml(msg.reactions)}
        </div>
    `;

    if (!msg.is_deleted) attachMessageActionHandlers(wrapper, msg);

    return wrapper;
}

// ===========================
// MESSAGE ACTIONS: reply / react / delete
// ===========================
function attachMessageActionHandlers(wrapper, msg) {
    const replyBtn  = wrapper.querySelector('.reply-btn');
    const reactBtn   = wrapper.querySelector('.react-btn');
    const deleteBtn  = wrapper.querySelector('.delete-btn');

    replyBtn?.addEventListener('click', () => setReply(msg));
    reactBtn?.addEventListener('click', (e) => openReactionPicker(e.currentTarget, msg.id));
    deleteBtn?.addEventListener('click', () => deleteMessage(msg.id));

    wrapper.querySelectorAll('.reaction-pill').forEach(pill => {
        pill.addEventListener('click', () => sendReaction(msg.id, pill.dataset.emoji));
    });
}

function setReply(msg) {
    STATE.replyTo = {
        id: msg.id,
        body: msg.body,
        sender_name: msg.sender.name,
        isImage: msg.type === 'image',
    };
    EL.replyBarName.textContent = msg.sender.name;
    EL.replyBarText.textContent = msg.body ? msg.body : '📷 Foto';
    EL.replyBar.classList.remove('hidden');
    EL.messageInput.focus();
}

function clearReply() {
    STATE.replyTo = null;
    EL.replyBar.classList.add('hidden');
}

EL.replyBarClose.addEventListener('click', clearReply);

let activePicker = null;
function openReactionPicker(anchorBtn, messageId) {
    closeReactionPicker();

    const picker = document.createElement('div');
    picker.className = 'reaction-picker';
    picker.innerHTML = REACTION_EMOJIS.map(e => `<button type="button" data-emoji="${e}">${e}</button>`).join('');
    picker.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            sendReaction(messageId, btn.dataset.emoji);
            closeReactionPicker();
        });
    });

    anchorBtn.closest('.msg-actions').appendChild(picker);
    activePicker = picker;
}
function closeReactionPicker() {
    activePicker?.remove();
    activePicker = null;
}
document.addEventListener('click', (e) => {
    if (activePicker && !activePicker.contains(e.target) && !e.target.classList.contains('react-btn')) {
        closeReactionPicker();
    }
});

async function sendReaction(messageId, emoji) {
    try {
        const data = await fetchJSON(`/messages/${messageId}/react`, {
            method: 'POST',
            body: JSON.stringify({ emoji }),
        });
        updateReactionsUI(messageId, data.reactions);
    } catch (e) {
        console.error('Gagal kirim reaction:', e);
    }
}

function updateReactionsUI(messageId, reactions) {
    const wrapper = EL.messagesList.querySelector(`[data-msg-id="${messageId}"]`);
    if (!wrapper) return;
    let row = wrapper.querySelector('.reactions-row');
    const html = reactionsRowHtml(reactions);
    if (row) {
        row.outerHTML = html;
    } else if (html) {
        wrapper.querySelector('.msg-content').insertAdjacentHTML('beforeend', html);
    }
    // re-attach click handler ke pill baru
    wrapper.querySelectorAll('.reaction-pill').forEach(pill => {
        pill.addEventListener('click', () => sendReaction(messageId, pill.dataset.emoji));
    });
}

async function deleteMessage(messageId) {
    if (!confirm('Hapus pesan ini?')) return;
    try {
        await fetchJSON(`/messages/${messageId}`, { method: 'DELETE' });
        replaceWithDeletedBubble(messageId);
    } catch (e) {
        console.error('Gagal hapus pesan:', e);
    }
}

function replaceWithDeletedBubble(messageId) {
    const wrapper = EL.messagesList.querySelector(`[data-msg-id="${messageId}"]`);
    if (!wrapper) return;
    const wrap = wrapper.querySelector('.msg-bubble-wrap');
    if (wrap) {
        wrap.innerHTML = `<div class="msg-bubble deleted-bubble" data-msg-id="${messageId}"><em>🚫 Pesan telah dihapus</em></div>`;
    }
    wrapper.querySelector('.reactions-row')?.remove();
}

// ===========================
// SEND MESSAGE (teks dan/atau gambar)
// ===========================
EL.messageForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = EL.messageInput.value.trim();
    if (!body && !STATE.pendingImage) return;
    if (!STATE.activeConvId) return;

    const replyId = STATE.replyTo?.id || null;

    EL.messageInput.value = '';
    EL.messageInput.focus();

    try {
        let msg;
        if (STATE.pendingImage) {
            const form = new FormData();
            if (body) form.append('body', body);
            form.append('image', STATE.pendingImage);
            if (replyId) form.append('parent_id', replyId);
            msg = await fetchJSON(`/conversations/${STATE.activeConvId}/messages`, {
                method: 'POST',
                body: form,
            });
        } else {
            msg = await fetchJSON(`/conversations/${STATE.activeConvId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body, parent_id: replyId }),
            });
        }

        appendMessage(msg);
        updateConvLastMessage(STATE.activeConvId, msg.type === 'image' ? '📷 Foto' : msg.body, msg.created_at);
        clearReply();
        clearImagePreview();
    } catch (e) {
        console.error('Gagal kirim pesan:', e);
        alert(e.message || 'Gagal kirim pesan');
    }
});

function appendMessage(msg) {
    const lastSep = EL.messagesList.querySelector('.date-separator:last-of-type span');
    const today = new Date().toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'});
    if (!lastSep || lastSep.textContent !== msg.date) {
        const sep = document.createElement('div');
        sep.className = 'date-separator';
        sep.innerHTML = `<span>${msg.date || today}</span>`;
        EL.messagesList.appendChild(sep);
    }

    EL.messagesList.appendChild(createMessageEl(msg));
    scrollToBottom();
}

// ===========================
// ATTACH GAMBAR
// ===========================
EL.btnAttach.addEventListener('click', () => EL.fileInput.click());

EL.fileInput.addEventListener('change', () => {
    const file = EL.fileInput.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        alert('Hanya bisa upload file gambar.');
        EL.fileInput.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran gambar maksimal 5MB.');
        EL.fileInput.value = '';
        return;
    }
    STATE.pendingImage = file;
    EL.imagePreviewImg.src = URL.createObjectURL(file);
    EL.imagePreviewBar.classList.remove('hidden');
});

EL.imagePreviewRemove.addEventListener('click', clearImagePreview);

function clearImagePreview() {
    STATE.pendingImage = null;
    EL.fileInput.value = '';
    EL.imagePreviewImg.src = '';
    EL.imagePreviewBar.classList.add('hidden');
}

// ===========================
// WEBSOCKET: SUBSCRIBE TO CONVERSATION
// ===========================
function subscribeToConversation(convId) {
    if (STATE.echoChannel) {
        window.Echo.leave(STATE.echoChannel);
    }

    STATE.echoChannel = `conversation.${convId}`;
    const channel = window.Echo.private(STATE.echoChannel);

    channel.listen('.message.sent', (data) => {
        if (STATE.activeConvId !== convId) return;
        if (data.sender?.id === STATE.currentUser.id) return;
        if (document.querySelector(`[data-msg-id="${data.id}"]`)) return;

        appendMessage({ ...data, is_mine: false, is_deleted: false });
        updateConvLastMessage(convId, data.type === 'image' ? '📷 Foto' : data.body, data.created_at);

        // Chat ini sedang dibuka -> langsung tandai "read"
        fetchJSON(`/conversations/${convId}/read`, { method: 'POST', body: '{}' }).catch(() => {});

        STATE.conversations.forEach(c => {
            if (c.id === convId) c.unread = 0;
        });
        hideTyping();
    });

    channel.listen('.message.deleted', (data) => {
        if (STATE.activeConvId !== convId) return;
        replaceWithDeletedBubble(data.id);
    });

    channel.listen('.message.status', (data) => {
        if (STATE.activeConvId !== convId) return;
        data.message_ids.forEach(id => {
            const wrapper = EL.messagesList.querySelector(`[data-msg-id="${id}"]`);
            if (!wrapper) return;
            const ticksEl = wrapper.querySelector('.msg-ticks');
            if (ticksEl) {
                const span = document.createElement('span');
                span.outerHTML = statusTicksHtml(data.status);
                ticksEl.replaceWith(span.firstChild ?? span);
            }
        });
    });

    channel.listen('.message.reaction', (data) => {
        if (STATE.activeConvId !== convId) return;
        updateReactionsUI(data.message_id, data.reactions);
    });

    // Typing indicator (client event / whisper, tidak lewat server)
    channel.listenForWhisper('typing', (data) => {
        if (STATE.activeConvId !== convId) return;
        if (data.user_id === STATE.currentUser.id) return;
        showTyping(data.name);
    });
}

// ===========================
// TYPING INDICATOR
// ===========================
let typingHideTimer = null;
function showTyping(name) {
    EL.chatStatus.textContent = `${name} sedang mengetik...`;
    EL.chatStatus.classList.add('typing');
    clearTimeout(typingHideTimer);
    typingHideTimer = setTimeout(hideTyping, 3000);
}
function hideTyping() {
    clearTimeout(typingHideTimer);
    EL.chatStatus.classList.remove('typing');
    const conv = STATE.conversations.find(c => c.id === STATE.activeConvId);
    if (conv) refreshChatStatusText(conv);
}
function refreshChatStatusText(conv) {
    const otherParticipant = conv.participants?.find(p => p.id !== STATE.currentUser.id);
    const isOnline = conv.type === 'private' && otherParticipant?.is_online;
    EL.chatStatus.textContent = conv.type === 'private'
        ? (isOnline ? 'Online' : (otherParticipant?.last_seen || 'Offline'))
        : `${conv.participants?.length || 0} anggota`;
}

EL.messageInput.addEventListener('input', () => {
    if (!STATE.activeConvId || !STATE.echoChannel) return;
    const now = Date.now();
    if (now - STATE.lastTypingSentAt < 1500) return; // throttle biar gak spam
    STATE.lastTypingSentAt = now;
    window.Echo.private(STATE.echoChannel).whisper('typing', {
        user_id: STATE.currentUser.id,
        name: STATE.currentUser.name,
    });
});

// ===========================
// PRESENCE TRACKING
// ===========================
function subscribeToPresence() {
    window.Echo.channel('presence')
        .listen('.user.presence', (data) => {
            updateUserPresence(data.user_id, data.is_online);
        });
}

function updateUserPresence(userId, isOnline) {
    const userEl = EL.usersList.querySelector(`[data-user-id="${userId}"]`);
    if (userEl) {
        const avatar    = userEl.querySelector('.avatar');
        const statusTxt = userEl.querySelector('.user-status-text');
        avatar.className    = `avatar ${isOnline ? 'online' : 'offline'}`;
        statusTxt.className = `user-status-text ${isOnline ? 'online' : 'offline'}`;
        statusTxt.textContent = isOnline ? '● Online' : 'Baru saja offline';
    }

    const stateUser = STATE.users.find(u => u.id === userId);
    if (stateUser) stateUser.is_online = isOnline;

    STATE.conversations.forEach(conv => {
        if (conv.type !== 'private') return;
        const other = conv.participants?.find(p => p.id === userId);
        if (!other) return;

        other.is_online = isOnline;
        const convEl = EL.convList.querySelector(`[data-id="${conv.id}"] .avatar`);
        if (convEl) {
            convEl.className = `avatar ${isOnline ? 'online' : 'offline'}`;
        }

        if (STATE.activeConvId === conv.id) {
            EL.chatAvatar.className  = `avatar ${isOnline ? 'online' : 'offline'}`;
            refreshChatStatusText(conv);
        }
    });
}

// ===========================
// START PRIVATE CHAT
// ===========================
async function startPrivateChat(user) {
    try {
        const data = await fetchJSON('/conversations/private', {
            method: 'POST',
            body: JSON.stringify({ user_id: user.id }),
        });

        const convId = data.conversation_id;

        let conv = STATE.conversations.find(c => c.id === convId);
        if (!conv) {
            conv = {
                id: convId, name: user.name, type: 'private',
                last_message: '', last_time: '', unread: 0,
                participants: [
                    STATE.currentUser,
                    { id: user.id, name: user.name, initials: user.initials, is_online: user.is_online }
                ],
            };
            STATE.conversations.unshift(conv);
            renderConversations(STATE.conversations);
        }

        switchTab('chats');
        openConversation(conv);
    } catch (e) {
        console.error('Gagal buat private chat:', e);
    }
}

// ===========================
// GROUP CHAT MODAL
// ===========================
EL.btnNewGroup.addEventListener('click', () => {
    EL.memberList.innerHTML = '';
    STATE.users.forEach(user => {
        const item = document.createElement('label');
        item.className = 'member-item';
        item.innerHTML = `
            <input type="checkbox" name="member" value="${user.id}">
            <div class="avatar avatar-sm">${user.initials}</div>
            <span class="member-item-name">${escHtml(user.name)}</span>
        `;
        EL.memberList.appendChild(item);
    });
    EL.modalGroup.classList.remove('hidden');
});

[EL.modalGroupClose, EL.modalGroupCancel].forEach(btn => {
    btn.addEventListener('click', () => EL.modalGroup.classList.add('hidden'));
});

EL.btnCreateGroup.addEventListener('click', async () => {
    const name = EL.groupNameInput.value.trim();
    if (!name) { EL.groupNameInput.focus(); return; }

    const memberIds = [...EL.memberList.querySelectorAll('input[name="member"]:checked')]
        .map(cb => parseInt(cb.value));

    if (memberIds.length === 0) {
        alert('Pilih minimal 1 anggota!'); return;
    }

    try {
        const data = await fetchJSON('/conversations/group', {
            method: 'POST',
            body: JSON.stringify({ name, member_ids: memberIds }),
        });

        const newConv = {
            id: data.conversation_id, name: data.name, type: 'group',
            last_message: '', last_time: '', unread: 0,
            participants: STATE.users.filter(u => memberIds.includes(u.id))
                .concat([STATE.currentUser]),
        };

        STATE.conversations.unshift(newConv);
        renderConversations(STATE.conversations);

        EL.modalGroup.classList.add('hidden');
        EL.groupNameInput.value = '';
        switchTab('chats');
        openConversation(newConv);
    } catch (e) {
        console.error('Gagal buat group:', e);
    }
});

// ===========================
// TABS
// ===========================
EL.tabChats.addEventListener('click', () => switchTab('chats'));
EL.tabUsers.addEventListener('click', () => switchTab('users'));

function switchTab(tab) {
    EL.tabChats.classList.toggle('active', tab === 'chats');
    EL.tabUsers.classList.toggle('active', tab === 'users');
    EL.tabContentChats.classList.toggle('active', tab === 'chats');
    EL.tabContentUsers.classList.toggle('active', tab === 'users');
}

// ===========================
// SEARCH
// ===========================
EL.searchInput.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(el => {
        const name = el.querySelector('.conv-name')?.textContent.toLowerCase() || '';
        el.style.display = name.includes(q) ? '' : 'none';
    });
    document.querySelectorAll('.user-item').forEach(el => {
        const name = el.querySelector('.user-name')?.textContent.toLowerCase() || '';
        el.style.display = name.includes(q) ? '' : 'none';
    });
});

// ===========================
// HELPERS
// ===========================
function scrollToBottom() {
    setTimeout(() => {
        EL.messagesArea.scrollTop = EL.messagesArea.scrollHeight;
    }, 50);
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() || '').join('');
}

function updateConvLastMessage(convId, body, time) {
    const conv = STATE.conversations.find(c => c.id === convId);
    if (conv) { conv.last_message = body; conv.last_time = time; }

    const el = EL.convList.querySelector(`[data-id="${convId}"] .conv-last`);
    if (el) el.textContent = body;
    const timeEl = EL.convList.querySelector(`[data-id="${convId}"] .conv-time`);
    if (timeEl) timeEl.textContent = time;
}

function updateUnreadBadge(convId) {
    const el = EL.convList.querySelector(`[data-id="${convId}"] .conv-badge`);
    el?.remove();
}

// ===========================
// HEARTBEAT (kirim ping setiap 30 detik)
// ===========================
async function sendHeartbeat() {
    try {
        await fetchJSON('/user/heartbeat', { method: 'POST', body: '{}' });
    } catch (e) {}
}

window.addEventListener('beforeunload', () => {
    navigator.sendBeacon('/user/offline', new URLSearchParams({ _token: csrfToken() }));
});

// ===========================
// INIT
// ===========================
function init() {
    renderConversations(STATE.conversations);
    renderUsers(STATE.users);
    subscribeToPresence();
    sendHeartbeat();
    setInterval(sendHeartbeat, 30000);
}

init();
