<?php
// Sembunyikan floating widget jika sedang berada di halaman AI Assistant penuh
if (isset($data['judul']) && $data['judul'] === 'AI Assistant') {
    return;
}
?>

<!-- ============================================================== -->
<!-- FLOATING AI CHAT BOX WIDGET                                    -->
<!-- ============================================================== -->

<style>
/* Floating Action Button (FAB) */
.ai-fab-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1045;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ai-fab-tooltip {
    background: #1e293b;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 500;
    padding: 6px 14px;
    border-radius: 20px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
    pointer-events: none;
    opacity: 0;
    transform: translateX(10px);
    transition: all 0.25s ease;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ai-fab-container:hover .ai-fab-tooltip {
    opacity: 1;
    transform: translateX(0);
}

.ai-fab-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    border: none;
    outline: none;
    box-shadow: 0 8px 24px rgba(79, 70, 229, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    position: relative;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
}

.ai-fab-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 12px 28px rgba(79, 70, 229, 0.5);
}

.ai-fab-btn:active {
    transform: scale(0.96);
}

.ai-fab-pulse {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 13px;
    height: 13px;
    background: #10b981;
    border: 2px solid #ffffff;
    border-radius: 50%;
}

.ai-fab-pulse::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #10b981;
    opacity: 0.6;
    animation: fab-ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes fab-ping {
    75%, 100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

/* Chat Box Window */
.ai-chatbox-window {
    position: fixed;
    bottom: 92px;
    right: 24px;
    width: 380px;
    height: 530px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 16px 48px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(226, 232, 240, 0.8);
    z-index: 1050;
    display: none;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}

.ai-chatbox-window.active {
    display: flex;
    transform: translateY(0) scale(1);
    opacity: 1;
}

/* Header */
.ai-chatbox-header {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.ai-chatbox-header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ai-chatbox-avatar {
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.2);
    border: 1.5px solid rgba(255, 255, 255, 0.35);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.ai-chatbox-info h6 {
    margin: 0;
    font-size: 0.94rem;
    font-weight: 600;
    color: #ffffff;
    letter-spacing: -0.01em;
}

.ai-chatbox-info small {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
    gap: 4px;
}

.ai-chatbox-status-dot {
    width: 7px;
    height: 7px;
    background: #4ade80;
    border-radius: 50%;
    display: inline-block;
}

.ai-chatbox-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.ai-chatbox-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    text-decoration: none;
    transition: background 0.18s;
}

.ai-chatbox-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    color: #ffffff;
}

/* Quick Suggestion Chips */
.ai-chatbox-suggestions {
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    padding: 8px 14px;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    white-space: nowrap;
    flex-shrink: 0;
}

.ai-chatbox-suggestions::-webkit-scrollbar {
    height: 3px;
}
.ai-chatbox-suggestions::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.ai-chatbox-chip {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #4f46e5;
    font-size: 0.74rem;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.ai-chatbox-chip:hover {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

/* Message Stream Container */
.ai-chatbox-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #f8fafc;
    scroll-behavior: smooth;
}

.ai-chatbox-body::-webkit-scrollbar {
    width: 4px;
}
.ai-chatbox-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Chat Messages */
.ai-widget-msg {
    display: flex;
    flex-direction: column;
    max-width: 86%;
    animation: aiMsgIn 0.22s ease-out;
}

@keyframes aiMsgIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.ai-widget-msg.user {
    align-self: flex-end;
}

.ai-widget-msg.bot {
    align-self: flex-start;
}

.ai-widget-bubble {
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 0.86rem;
    line-height: 1.55;
    word-break: break-word;
}

.ai-widget-msg.user .ai-widget-bubble {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
}

.ai-widget-msg.bot .ai-widget-bubble {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.ai-widget-msg.bot .ai-widget-bubble strong {
    color: #4338ca;
}

.ai-widget-msg.bot .ai-widget-bubble code {
    background: #f1f5f9;
    padding: 1px 4px;
    border-radius: 4px;
    color: #0f172a;
    font-size: 0.82em;
}

.ai-widget-msg.bot .ai-widget-bubble ul,
.ai-widget-msg.bot .ai-widget-bubble ol {
    padding-left: 18px;
    margin: 4px 0;
}
.ai-widget-msg.bot .ai-widget-bubble p { margin: 3px 0; }
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table {
    font-size: 0.76rem;
    margin: 4px 0;
    background: #fff;
}
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table th {
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
    white-space: nowrap;
}
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table th,
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table td {
    padding: 3px 6px;
}
.ai-widget-msg.bot .ai-widget-bubble hr { margin: 6px 0; opacity: .15; }

.ai-widget-meta {
    font-size: 0.70rem;
    color: #94a3b8;
    margin-top: 3px;
    padding: 0 4px;
}

.ai-widget-msg.user .ai-widget-meta {
    text-align: right;
}

/* Typing Indicator */
.ai-widget-typing {
    display: none;
    align-self: flex-start;
    align-items: center;
    gap: 4px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 8px 14px;
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.ai-widget-typing span {
    width: 6px;
    height: 6px;
    background: #94a3b8;
    border-radius: 50%;
    animation: aiBounce 1.3s infinite ease-in-out both;
}

.ai-widget-typing span:nth-child(1) { animation-delay: -0.32s; }
.ai-widget-typing span:nth-child(2) { animation-delay: -0.16s; }

@keyframes aiBounce {
    0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
    40% { transform: scale(1); opacity: 1; }
}

/* Footer & Input */
.ai-chatbox-footer {
    padding: 12px 14px;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    flex-shrink: 0;
}

.ai-chatbox-form {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-chatbox-input {
    flex: 1;
    border: 1.5px solid #e2e8f0;
    border-radius: 22px;
    padding: 9px 16px;
    font-size: 0.86rem;
    outline: none;
    transition: border-color 0.18s, box-shadow 0.18s;
    font-family: inherit;
}

.ai-chatbox-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.ai-chatbox-send-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    transition: transform 0.15s, opacity 0.15s;
    flex-shrink: 0;
}

.ai-chatbox-send-btn:hover:not(:disabled) {
    transform: scale(1.06);
}

.ai-chatbox-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 480px) {
    .ai-chatbox-window {
        bottom: 80px;
        right: 12px;
        width: calc(100vw - 24px);
        height: calc(100vh - 100px);
    }
    .ai-fab-container {
        bottom: 16px;
        right: 16px;
    }
}
</style>

<style>
/* ── Tema terang/gelap widget Aca ── */
.ai-chatbox-window {
    background: var(--surface);
    box-shadow: var(--shadow-lg), 0 0 0 1px var(--border);
}
.ai-chatbox-suggestions { background: var(--surface-2); border-bottom-color: var(--border); }
.ai-chatbox-chip { background: var(--surface); border-color: var(--border); color: var(--primary); }
.ai-chatbox-chip:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.ai-chatbox-body { background: var(--surface-2); }
.ai-widget-msg.bot .ai-widget-bubble {
    background: var(--surface);
    color: var(--text-primary);
    border-color: var(--border);
    box-shadow: var(--shadow-xs);
}
.ai-widget-msg.bot .ai-widget-bubble strong { color: var(--primary); }
.ai-widget-msg.bot .ai-widget-bubble code { background: var(--surface-3); color: var(--primary); }
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table { background: var(--surface); color: var(--text-primary); border-color: var(--border); }
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table th {
    background: var(--table-head-bg) !important;
    color: var(--primary) !important;
    border-color: var(--border) !important;
}
.ai-widget-msg.bot .ai-widget-bubble .aca-md-table td { border-color: var(--border) !important; }
.ai-widget-meta { color: var(--text-faint); }
.ai-chatbox-footer { background: var(--surface); border-top-color: var(--border); }
.ai-chatbox-input {
    background: var(--surface-2);
    color: var(--text-primary);
    border-color: var(--border);
}
.ai-chatbox-input:focus { background: var(--surface); }
.ai-chatbox-input::placeholder { color: var(--text-faint); }
.ai-widget-typing { background: var(--surface); border-color: var(--border); }
.ai-fab-tooltip { background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); }
.ai-widget-actions { display: flex; align-items: center; gap: 6px; margin-top: 3px; }
.ai-widget-copy {
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    border-radius: 6px;
    font-size: .64rem;
    padding: 1px 6px;
    cursor: pointer;
    transition: all var(--transition);
}
.ai-widget-copy:hover { color: var(--primary); border-color: var(--primary); }
.ai-widget-copy.copied { color: #10b981; border-color: rgba(16, 185, 129, .4); }
@media (max-width: 480px) {
    .ai-chatbox-window { border-radius: 14px; }
}
</style>

<!-- Hidden CSRF token untuk widget -->
<input type="hidden" id="aiWidgetCsrfToken" value="<?= CSRF::token(); ?>">

<!-- FAB Button -->
<div class="ai-fab-container">
    <div class="ai-fab-tooltip">
        <i class="bi bi-chat-dots-fill text-warning"></i> Tanya Aca AI Projek
    </div>
    <button class="ai-fab-btn" id="aiFabBtn" title="Buka AI Chat Box" aria-label="Buka Chat AI">
        <i class="bi bi-robot" id="aiFabIcon"></i>
        <span class="ai-fab-pulse"></span>
    </button>
</div>

<!-- Chat Box Window -->
<div class="ai-chatbox-window" id="aiChatboxWindow" role="dialog" aria-labelledby="chatboxTitle">
    <!-- Header -->
    <div class="ai-chatbox-header">
        <div class="ai-chatbox-header-title">
            <div class="ai-chatbox-avatar">🤖</div>
            <div class="ai-chatbox-info">
                <h6 id="chatboxTitle">Aca — AI Assistant</h6>
                <small><span class="ai-chatbox-status-dot"></span> Tanya seputar projek Acounting</small>
            </div>
        </div>
        <div class="ai-chatbox-actions">
            <a href="<?= BASEURL; ?>/ai" class="ai-chatbox-btn" title="Buka Halaman AI Penuh">
                <i class="bi bi-arrows-angle-expand"></i>
            </a>
            <button class="ai-chatbox-btn" id="aiWidgetClearBtn" title="Hapus Riwayat">
                <i class="bi bi-trash3"></i>
            </button>
            <button class="ai-chatbox-btn" id="aiWidgetCloseBtn" title="Tutup Chat Box">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <!-- Quick Suggestions -->
    <div class="ai-chatbox-suggestions" id="aiWidgetSuggestions">
        <button class="ai-chatbox-chip" data-ask="Ringkasan seluruh aktivitas aplikasi">📊 Ringkasan Aplikasi</button>
        <button class="ai-chatbox-chip" data-ask="Apa produk paling laku atau best seller?">🏆 Produk Terlaris</button>
        <button class="ai-chatbox-chip" data-ask="Berapa total transaksi bulan ini?">💡 Transaksi Bulan Ini</button>
        <button class="ai-chatbox-chip" data-ask="Barang apa saja yang stoknya hampir habis?">⚠️ Stok Menipis</button>
        <button class="ai-chatbox-chip" data-ask="Tampilkan seluruh riwayat transaksi">🧾 Riwayat Transaksi</button>
        <button class="ai-chatbox-chip" data-ask="Siapa pengguna yang melakukan transaksi?">👤 Kasir Transaksi</button>
    </div>

    <!-- Message Body -->
    <div class="ai-chatbox-body" id="aiWidgetBody">
        <!-- Welcome Message -->
        <div class="ai-widget-msg bot" id="aiWidgetWelcome">
            <div class="ai-widget-bubble">
                Halo! 👋 Saya <strong>Aca</strong>, asisten AI untuk projek <strong>Acounting</strong>.<br><br>
                Silakan tanyakan apapun tentang fitur kasir, manajemen stok barang, riwayat transaksi, atau data toko Anda! 😊
            </div>
            <div class="ai-widget-meta">Aca &bull; Online</div>
        </div>

        <!-- Typing Indicator -->
        <div class="ai-widget-typing" id="aiWidgetTyping">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Input Footer -->
    <div class="ai-chatbox-footer">
        <form class="ai-chatbox-form" id="aiWidgetForm">
            <input 
                type="text" 
                class="ai-chatbox-input" 
                id="aiWidgetInput" 
                placeholder="Tanyakan tentang projek ini..." 
                autocomplete="off" 
                maxlength="1000" 
                required
            >
            <button type="submit" class="ai-chatbox-send-btn" id="aiWidgetSendBtn" title="Kirim">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>
</div>

<script src="<?= BASEURL; ?>/js/aca-markdown.js"></script>
<script>
(function() {
    'use strict';

    const BASE_URL  = '<?= BASEURL; ?>';
    const ASK_URL   = BASE_URL + '/ai/ask';
    const HIST_URL  = BASE_URL + '/ai/history';
    const CLEAR_URL = BASE_URL + '/ai/clearHistory';

    const fabBtn      = document.getElementById('aiFabBtn');
    const fabIcon     = document.getElementById('aiFabIcon');
    const chatWindow  = document.getElementById('aiChatboxWindow');
    const closeBtn    = document.getElementById('aiWidgetCloseBtn');
    const clearBtn    = document.getElementById('aiWidgetClearBtn');
    const chatBody    = document.getElementById('aiWidgetBody');
    const chatForm    = document.getElementById('aiWidgetForm');
    const chatInput   = document.getElementById('aiWidgetInput');
    const sendBtn     = document.getElementById('aiWidgetSendBtn');
    const typingDots  = document.getElementById('aiWidgetTyping');
    const csrfInput   = document.getElementById('aiWidgetCsrfToken');
    const chips       = document.querySelectorAll('.ai-chatbox-chip');

    let historyLoaded = false;

    // Toggle chatbox window
    function toggleChatbox(forceOpen = null) {
        const isOpen = forceOpen !== null ? forceOpen : !chatWindow.classList.contains('active');
        if (isOpen) {
            chatWindow.classList.add('active');
            fabIcon.className = 'bi bi-x-lg';
            if (!historyLoaded) {
                loadHistory();
            }
            setTimeout(() => {
                chatInput.focus();
                scrollToBottom();
            }, 100);
        } else {
            chatWindow.classList.remove('active');
            fabIcon.className = 'bi bi-robot';
        }
    }

    fabBtn.addEventListener('click', () => toggleChatbox());
    closeBtn.addEventListener('click', () => toggleChatbox(false));

    // Markdown renderer (shared with halaman penuh)
    function renderMarkdown(text) {
        if (window.AcaMarkdown) return window.AcaMarkdown.render(text);
        return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // Append message to body
    function appendMessage(role, text, timeStr = null) {
        const time = timeStr || new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const msgDiv = document.createElement('div');
        msgDiv.className = 'ai-widget-msg ' + role;

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'ai-widget-bubble';

        if (role === 'user') {
            bubbleDiv.textContent = text;
        } else {
            bubbleDiv.innerHTML = renderMarkdown(text);
        }

        const metaDiv = document.createElement('div');
        metaDiv.className = 'ai-widget-meta';
        metaDiv.innerHTML = (role === 'user' ? 'Anda' : 'Aca') + ' &bull; ' + time;

        if (role === 'bot') {
            const actions = document.createElement('div');
            actions.className = 'ai-widget-actions';
            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'ai-widget-copy';
            copyBtn.setAttribute('data-copy', '1');
            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Salin';
            actions.appendChild(copyBtn);
            msgDiv.appendChild(bubbleDiv);
            msgDiv.appendChild(metaDiv);
            msgDiv.appendChild(actions);
        } else {
            msgDiv.appendChild(bubbleDiv);
            msgDiv.appendChild(metaDiv);
        }

        chatBody.insertBefore(msgDiv, typingDots);
        scrollToBottom();
    }

    // Set loading status
    function setLoading(isLoading) {
        chatInput.disabled = isLoading;
        sendBtn.disabled   = isLoading;
        typingDots.style.display = isLoading ? 'flex' : 'none';
        if (isLoading) scrollToBottom();
    }

    // Send question to backend
    let sending = false;

    async function sendQuestion(question) {
        const q = question.trim();
        if (!q || sending) return;
        sending = true;

        appendMessage('user', q);
        chatInput.value = '';
        setLoading(true);

        try {
            const token = csrfInput ? csrfInput.value : '';
            const res = await fetch(ASK_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: q, csrf_token: token })
            });

            const data = await res.json().catch(function () { return {}; });
            setLoading(false);

            if (res.ok && data.success) {
                appendMessage('bot', data.answer);
            } else {
                appendMessage('bot', '⚠️ ' + (data.message || 'Terjadi kesalahan sistem.'));
            }
        } catch (err) {
            setLoading(false);
            appendMessage('bot', '⚠️ Gagal terhubung ke AI Assistant. Silakan coba lagi.');
            console.error('[AI Chatbox]', err);
        } finally {
            sending = false;
        }
    }

    // Load recent history from server
    async function loadHistory() {
        historyLoaded = true;
        try {
            const res = await fetch(HIST_URL);
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && Array.isArray(data.history) && data.history.length > 0) {
                data.history.forEach(item => {
                    appendMessage('user', item.question);
                    appendMessage('bot', item.answer);
                });
            }
        } catch (e) {
            console.error('[AI Load History]', e);
        }
    }

    // Handle Form Submit
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendQuestion(chatInput.value);
    });

    // Handle Suggestion Chips
    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            const q = this.getAttribute('data-ask');
            if (q) {
                sendQuestion(q);
            }
        });
    });

    // Salin jawaban Aca
    chatBody.addEventListener('click', function (event) {
        const copyBtn = event.target.closest('[data-copy]');
        if (!copyBtn) return;
        const bubble = copyBtn.closest('.ai-widget-msg').querySelector('.ai-widget-bubble');
        if (!bubble) return;
        const text = bubble.innerText.trim();
        const done = function () {
            copyBtn.classList.add('copied');
            copyBtn.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
            setTimeout(function () {
                copyBtn.classList.remove('copied');
                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Salin';
            }, 1800);
        };
        const legacyCopy = function () {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                const ok = document.execCommand('copy');
                if (ok) { done(); return Promise.resolve(); }
            } catch (e) { /* fall through */ }
            finally { area.remove(); }
            return Promise.reject(new Error('Copy failed'));
        };
        const failed = function () {
            copyBtn.innerHTML = '<i class="bi bi-x"></i> Gagal';
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                legacyCopy().catch(failed);
            });
        } else {
            legacyCopy().catch(failed);
        }
    });

    // Clear History Button
    clearBtn.addEventListener('click', async function() {
        if (!confirm('Hapus seluruh riwayat percakapan AI Anda?')) return;
        try {
            const token = csrfInput ? csrfInput.value : '';
            const res = await fetch(CLEAR_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: token })
            });
            const data = await res.json();
            if (data.success) {
                // Hapus semua pesan kecuali welcome dan typing
                const msgs = chatBody.querySelectorAll('.ai-widget-msg:not(#aiWidgetWelcome)');
                msgs.forEach(m => m.remove());
            }
        } catch (e) {
            console.error('[AI Clear History]', e);
        }
    });

})();
</script>
