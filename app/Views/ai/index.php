<style>
/* =====================================================
   ACA - AI Assistant Styles (Modern & Responsive)
   ===================================================== */
:root {
    --aca-primary: #4f46e5;
    --aca-primary-dark: #3730a3;
    --aca-bg: #f8fafc;
    --aca-user-bg: linear-gradient(135deg, #4f46e5, #6366f1);
    --aca-bot-bg: #ffffff;
    --aca-text-dark: #1e293b;
}

.aca-wrapper {
    background: var(--aca-bg);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(79, 70, 229, 0.1);
    border: 1px solid #e2e8f0;
}

/* Header */
.aca-header {
    background: linear-gradient(135deg, var(--aca-primary), var(--aca-primary-dark));
    color: white;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.aca-avatar {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    border: 2px solid rgba(255,255,255,0.4);
    flex-shrink: 0;
}
.aca-title { font-size: 17px; font-weight: 700; margin: 0; }
.aca-subtitle { font-size: 12px; opacity: 0.85; margin: 0; }
.aca-status { display: flex; align-items: center; gap: 6px; font-size: 11px; margin-top: 2px; }
.aca-status-dot { width: 8px; height: 8px; background: #34d399; border-radius: 50%; animation: aca-pulse 2s infinite; }
@keyframes aca-pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

/* Chat Area */
.aca-chat {
    height: 480px;
    overflow-y: auto;
    padding: 20px;
    background: var(--aca-bg);
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.aca-chat::-webkit-scrollbar { width: 5px; }
.aca-chat::-webkit-scrollbar-track { background: transparent; }
.aca-chat::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

/* Messages */
.aca-msg { display: flex; align-items: flex-end; gap: 10px; max-width: 85%; animation: acaSlideUp .2s ease; }
@keyframes acaSlideUp { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }

.aca-msg.user { flex-direction: row-reverse; align-self: flex-end; }
.aca-msg.bot { align-self: flex-start; }

.aca-bubble {
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}
.aca-msg.user .aca-bubble {
    background: var(--aca-user-bg);
    color: white;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.aca-msg.bot .aca-bubble {
    background: var(--aca-bot-bg);
    color: var(--aca-text-dark);
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* Markdown styling inside bubbles */
.aca-bubble strong { font-weight: 700; color: inherit; }
.aca-bubble em { font-style: italic; }
.aca-bubble code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: #4f46e5;
}
.aca-bubble ul, .aca-bubble ol {
    padding-left: 20px;
    margin: 6px 0;
}
.aca-bubble li { margin: 3px 0; }
.aca-bubble p { margin: 4px 0; }
.aca-bubble .aca-md-table {
    font-size: 12.5px;
    margin: 6px 0;
    background: #fff;
}
.aca-bubble .aca-md-table th {
    background: #eef2ff;
    color: #3730a3;
    font-weight: 600;
    white-space: nowrap;
}
.aca-bubble .aca-md-table td, .aca-bubble .aca-md-table th {
    padding: 5px 8px;
    vertical-align: top;
}
.aca-bubble hr { margin: 8px 0; opacity: .15; }
.aca-bubble h3, .aca-bubble h4, .aca-bubble h5 { margin: 8px 0 4px; }

/* Bot Avatar inside chat */
.aca-bot-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 16px;
    flex-shrink: 0;
}

/* Quick Suggestion Chips */
.aca-suggestions {
    padding: 8px 16px 12px;
    background: var(--aca-bg);
    display: flex;
    gap: 8px;
    overflow-x: auto;
    white-space: nowrap;
    border-top: 1px dashed #e2e8f0;
}
.aca-suggestions::-webkit-scrollbar { height: 3px; }
.aca-suggestions::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

.aca-chip {
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.aca-chip:hover {
    background: var(--aca-primary);
    color: white;
    border-color: var(--aca-primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
}

/* Typing Indicator */
.aca-typing { align-self: flex-start; display: flex; align-items: flex-end; gap: 10px; }
.aca-typing-dots {
    background: white;
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    padding: 12px 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex; gap: 4px; align-items: center;
}
.aca-typing-dots span {
    width: 7px; height: 7px;
    background: #94a3b8;
    border-radius: 50%;
    animation: acaBounce 1.4s infinite ease-in-out both;
}
.aca-typing-dots span:nth-child(2) { animation-delay: .16s; }
.aca-typing-dots span:nth-child(3) { animation-delay: .32s; }
@keyframes acaBounce { 0%,80%,100%{transform:scale(0);} 40%{transform:scale(1);} }

/* Footer / Input Area */
.aca-footer {
    background: white;
    border-top: 1px solid #e2e8f0;
    padding: 14px 18px;
}
.aca-input-row { display: flex; gap: 10px; align-items: center; }
.aca-input {
    flex: 1;
    border: 2px solid #e2e8f0;
    border-radius: 24px;
    padding: 10px 18px;
    font-size: 14px;
    outline: none;
    transition: all .2s;
    background: #f8fafc;
}
.aca-input:focus { border-color: var(--aca-primary); background: white; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.aca-send-btn {
    width: 44px; height: 44px;
    background: var(--aca-user-bg);
    border: none;
    border-radius: 50%;
    color: white;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all .2s;
    flex-shrink: 0;
    font-size: 16px;
}
.aca-send-btn:hover { transform: scale(1.05); box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); }
.aca-send-btn:disabled { opacity: 0.5; transform: none; cursor: not-allowed; }

.aca-time { font-size: 10px; color: #94a3b8; margin-top: 3px; text-align: right; padding-right: 4px; }
.aca-msg.bot .aca-time { text-align: left; padding-left: 4px; }
</style>

<style>
/* ── Tema terang/gelap + peningkatan tampilan chat ── */
.aca-wrapper {
    background: var(--surface-2);
    border-color: var(--border);
    box-shadow: var(--shadow);
}
.aca-chat { background: var(--surface-2); }
.aca-header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.aca-header-btn {
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .28);
    color: #fff;
    border-radius: 9px;
    font-size: .76rem;
    padding: .38rem .72rem;
    backdrop-filter: blur(6px);
}
.aca-header-btn:hover { background: rgba(255, 255, 255, .26); color: #fff; }
.aca-header-btn-danger:hover { background: rgba(248, 113, 113, .35); border-color: rgba(248, 113, 113, .5); }
.aca-msg.bot .aca-bubble {
    background: var(--surface);
    color: var(--text-primary);
    border-color: var(--border);
    box-shadow: var(--shadow-xs);
}
.aca-msg.bot .aca-bubble strong { color: var(--primary); }
.aca-bubble code { background: var(--surface-3); color: var(--primary); }
.aca-bubble .aca-md-table { background: var(--surface); color: var(--text-primary); border-color: var(--border); }
.aca-bubble .aca-md-table th {
    background: var(--table-head-bg) !important;
    color: var(--primary) !important;
    border-color: var(--border) !important;
}
.aca-bubble .aca-md-table td { border-color: var(--border) !important; color: var(--text-primary); }
.aca-bubble hr { border-color: var(--border); }
.aca-suggestions { background: var(--surface-2); border-top-color: var(--border); }
.aca-chip { background: var(--surface); border-color: var(--border); color: var(--text-secondary); }
.aca-chip:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.aca-footer { background: var(--surface); border-top-color: var(--border); }
.aca-input { background: var(--surface-2); border-color: var(--border); color: var(--text-primary); }
.aca-input:focus { background: var(--surface); }
.aca-input::placeholder { color: var(--text-faint); }
.aca-typing-dots { background: var(--surface); border-color: var(--border); }
.aca-time { color: var(--text-faint); }
.aca-msg.bot > div:last-child { position: relative; }
.aca-copy-btn {
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    border-radius: 7px;
    font-size: .68rem;
    padding: 2px 7px;
    margin-left: 6px;
    vertical-align: middle;
    transition: all var(--transition);
}
.aca-copy-btn:hover { color: var(--primary); border-color: var(--primary); background: var(--primary-soft); }
.aca-copy-btn.copied { color: #10b981; border-color: rgba(16, 185, 129, .4); background: rgba(16, 185, 129, .1); }
.aca-mobile-hint { display: none; }
@media (max-width: 767px) {
    .aca-wrapper { border-radius: 14px; }
    .aca-header { padding: 13px 14px; gap: 10px; flex-wrap: wrap; }
    .aca-header-actions { width: 100%; justify-content: flex-start; margin-top: 4px; }
    .aca-chat { height: calc(100vh - 300px); min-height: 320px; padding: 14px; }
    .aca-msg { max-width: 94%; }
    .aca-suggestions { padding: 8px 12px 10px; }
    .aca-footer { padding: 10px 12px; }
    .aca-title { font-size: 15px; }
    .aca-subtitle { font-size: 11px; }
}
</style>

<div class="container-fluid mt-3 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="aca-wrapper">

                <!-- Header -->
                <div class="aca-header">
                    <div class="aca-avatar">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div>
                        <p class="aca-title">Aca — AI Assistant</p>
                        <p class="aca-subtitle">Asisten Cerdas Manajemen Kasir & Stok</p>
                        <div class="aca-status">
                            <div class="aca-status-dot"></div>
                            <span>Online & Terhubung ke Database Acounting</span>
                        </div>
                    </div>
                    <div class="aca-header-actions ms-auto">
                        <button class="btn btn-sm aca-header-btn" id="acaNewBtn" title="Mulai percakapan baru">
                            <i class="bi bi-plus-lg me-1"></i> Percakapan Baru
                        </button>
                        <button class="btn btn-sm aca-header-btn aca-header-btn-danger" id="acaClearBtn" title="Hapus riwayat chat">
                            <i class="bi bi-trash3 me-1"></i> Hapus Riwayat
                        </button>
                    </div>
                </div>

                <!-- Chat area -->
                <div class="aca-chat" id="acaChat">
                    <!-- Welcome message -->
                    <div class="aca-msg bot" id="welcomeMsg">
                        <div class="aca-bot-icon">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div>
                            <div class="aca-bubble">
                                Halo! Saya <strong>Aca</strong>, asisten AI untuk aplikasi <strong>Acounting</strong>.<br><br>
                                Saya membaca data aktual dari database dan menjawab sesuai hak akses akun Anda:<br>
                                📦 <strong>Barang, stok & nilai persediaan</strong><br>
                                🧾 <strong>Transaksi, detail nota & rekap kasir</strong><br>
                                📊 <strong>Omzet, laporan periode & produk terlaris</strong><br>
                                👥 <strong>Pengguna, peran & aktivitas aplikasi</strong><br>
                                💡 <strong>Panduan penggunaan fitur Acounting</strong><br><br>
                                Ketik pertanyaan atau pilih saran di bawah. Untuk data banyak, Aca akan menampilkan tabel agar mudah dibaca.
                            </div>
                            <div class="aca-time"><?= date('H:i') ?></div>
                        </div>
                    </div>

                    <!-- Conversation history -->
                    <?php if(!empty($data['history'])): ?>
                        <?php foreach($data['history'] as $chat): ?>
                            <div class="aca-msg user">
                                <div>
                                    <div class="aca-bubble"><?= htmlspecialchars($chat['question']); ?></div>
                                    <div class="aca-time"><?= date('H:i', strtotime($chat['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                            <div class="aca-msg bot">
                                <div class="aca-bot-icon">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <div>
                                    <div class="aca-bubble" data-md="1"><?= htmlspecialchars($chat['answer']); ?></div>
                                    <div class="aca-time"><?= date('H:i', strtotime($chat['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Typing indicator -->
                    <div class="aca-typing" id="acaTyping" style="display:none;">
                        <div class="aca-bot-icon">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="aca-typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>

                <!-- Quick suggestion chips -->
                <div class="aca-suggestions" id="acaChips">
                    <button class="aca-chip" data-q="Berapa total penjualan bulan ini?"><i class="bi bi-calendar3 text-success"></i> Penjualan Bulan Ini</button>
                    <button class="aca-chip" data-q="Berapa laba kotor?"><i class="bi bi-graph-up text-primary"></i> Laba Kotor</button>
                    <button class="aca-chip" data-q="Berapa saldo kas dan bank?"><i class="bi bi-cash-stack text-success"></i> Saldo Kas</button>
                    <button class="aca-chip" data-q="Barang apa saja yang stoknya hampir habis?"><i class="bi bi-exclamation-triangle text-warning"></i> Stok Menipis</button>
                    <button class="aca-chip" data-q="Siapa pelanggan dengan transaksi terbesar?"><i class="bi bi-trophy text-warning"></i> Transaksi Terbesar</button>
                    <button class="aca-chip" data-q="Berapa total pembelian bulan ini?"><i class="bi bi-cart-check text-info"></i> Pembelian</button>
                    <button class="aca-chip" data-q="Berapa total piutang?"><i class="bi bi-credit-card text-danger"></i> Piutang</button>
                    <button class="aca-chip" data-q="Berapa total utang?"><i class="bi bi-journal-x text-secondary"></i> Utang Usaha</button>
                    <button class="aca-chip" data-q="Tampilkan seluruh riwayat transaksi"><i class="bi bi-receipt text-info"></i> Riwayat Transaksi</button>
                    <button class="aca-chip" data-q="Produk apa yang paling laris?"><i class="bi bi-star-fill text-warning"></i> Produk Terlaris</button>
                </div>

                <!-- Input footer -->
                <div class="aca-footer">
                    <form id="acaForm" class="aca-input-row">
                        <input
                            type="text"
                            id="acaInput"
                            class="aca-input"
                            placeholder="Tanyakan ke Aca... (cth: Barang apa yang stoknya hampir habis?)"
                            autocomplete="off"
                            maxlength="1000"
                            required
                        >
                        <button type="submit" class="aca-send-btn" id="acaSendBtn" title="Kirim Pertanyaan">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                    <div style="text-align:center;margin-top:6px;font-size:11px;color:#94a3b8;">
                        Aca memahami bahasa Indonesia sehari-hari & menjawab berdasarkan data riil Acounting.
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="<?= BASEURL ?>/js/aca-markdown.js"></script>
<script>
(function() {
    'use strict';

    const chat     = document.getElementById('acaChat');
    const form     = document.getElementById('acaForm');
    const input    = document.getElementById('acaInput');
    const sendBtn  = document.getElementById('acaSendBtn');
    const typing   = document.getElementById('acaTyping');
    const chips    = document.getElementById('acaChips');
    const clearBtn = document.getElementById('acaClearBtn');
    const BASEURL  = '<?= BASEURL; ?>';
    const CSRF     = '<?= CSRF::token(); ?>';

    // ---- Markdown renderer (shared) ----
    function renderMarkdown(text) {
        return window.AcaMarkdown ? window.AcaMarkdown.render(text) : escapeHtml(text || '');
    }

    // Render historical bubbles that have data-md="1"
    document.querySelectorAll('.aca-bubble[data-md="1"]').forEach(el => {
        el.innerHTML = renderMarkdown(el.textContent || el.innerText);
    });

    // Tombol salin jawaban Aca
    function addCopyButton(bubble) {
        if (!bubble || bubble.querySelector('.aca-copy-btn')) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'aca-copy-btn';
        btn.setAttribute('data-copy', '1');
        btn.innerHTML = '<i class="bi bi-clipboard"></i> Salin';
        const timeEl = bubble.parentElement ? bubble.parentElement.querySelector('.aca-time') : null;
        if (timeEl) {
            timeEl.appendChild(btn);
        } else {
            bubble.insertAdjacentElement('afterend', btn);
        }
    }

    function legacyCopy(text) {
        return new Promise(function (resolve, reject) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                const ok = document.execCommand('copy');
                ok ? resolve() : reject(new Error('Copy command failed'));
            } catch (e) {
                reject(e);
            } finally {
                area.remove();
            }
        });
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                return legacyCopy(text);
            });
        }
        return legacyCopy(text);
    }

    document.addEventListener('click', function (event) {
        const copyBtn = event.target.closest('[data-copy]');
        if (!copyBtn) return;
        const bubble = copyBtn.closest('.aca-msg')?.querySelector('.aca-bubble');
        if (!bubble) return;
        copyToClipboard(bubble.innerText.trim()).then(function () {
            copyBtn.classList.add('copied');
            copyBtn.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
            setTimeout(function () {
                copyBtn.classList.remove('copied');
                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Salin';
            }, 1800);
        }).catch(function () {
            copyBtn.innerHTML = '<i class="bi bi-x"></i> Gagal';
        });
    });

    document.querySelectorAll('.aca-msg.bot .aca-bubble').forEach(addCopyButton);

    function now() {
        const d = new Date();
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }

    function scrollBottom() {
        chat.scrollTop = chat.scrollHeight;
    }

    function appendMsg(sender, text) {
        const msgEl = document.createElement('div');
        msgEl.className = 'aca-msg ' + sender;

        if (sender === 'user') {
            msgEl.innerHTML = `
                <div>
                    <div class="aca-bubble">${escapeHtml(text)}</div>
                    <div class="aca-time">${now()}</div>
                </div>`;
        } else {
            msgEl.innerHTML = `
                <div class="aca-bot-icon">
                    <i class="bi bi-robot"></i>
                </div>
                <div>
                    <div class="aca-bubble">${renderMarkdown(text)}</div>
                    <div class="aca-time">${now()}</div>
                </div>`;
        }

        chat.insertBefore(msgEl, typing);
        if (sender === 'bot') {
            addCopyButton(msgEl.querySelector('.aca-bubble'));
        }
        scrollBottom();
        return msgEl;
    }

    function escapeHtml(text) {
        return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function setLoading(loading) {
        input.disabled = loading;
        sendBtn.disabled = loading;
        typing.style.display = loading ? 'flex' : 'none';
        if (loading) scrollBottom();
    }

    let sending = false;

    function sendQuestion(question) {
        if (!question.trim() || sending) return;
        sending = true;

        appendMsg('user', question);
        input.value = '';
        setLoading(true);

        fetch(BASEURL + '/ai/ask', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: question, csrf_token: CSRF })
        })
        .then(r => r.json())
        .then(data => {
            setLoading(false);
            sending = false;
            if (data.success) {
                appendMsg('bot', data.answer);
            } else {
                appendMsg('bot', '⚠️ Terjadi kendala: ' + (data.message || 'Gagal memproses pertanyaan.'));
            }
            input.focus();
        })
        .catch(err => {
            setLoading(false);
            sending = false;
            appendMsg('bot', '⚠️ Maaf, gagal menghubungi server Acounting. Periksa koneksi lokal Anda.');
            input.focus();
        });
    }

    // Form submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        sendQuestion(input.value.trim());
    });

    // Quick chips
    chips.addEventListener('click', function(e) {
        const chip = e.target.closest('.aca-chip');
        if (!chip) return;
        const q = chip.dataset.q;
        if (q) {
            input.value = q;
            sendQuestion(q);
        }
    });

    // Clear / New Conversation
    function resetConversation(confirmText, successMessage) {
        if (!confirm(confirmText)) return;
        fetch(BASEURL + '/ai/clear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: CSRF })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Hapus semua pesan kecuali pesan sambutan.
                chat.querySelectorAll('.aca-msg').forEach(function (m, idx) {
                    if (idx > 0) m.remove();
                });
                alert(successMessage);
                input.focus();
            } else {
                alert(data.message || 'Gagal menghapus riwayat.');
            }
        })
        .catch(() => {
            alert('Gagal menghubungi server.');
        });
    }

    const newBtn = document.getElementById('acaNewBtn');
    if (newBtn) {
        newBtn.addEventListener('click', function () {
            resetConversation('Mulai percakapan baru? Riwayat percakapan saat ini akan dihapus.', 'Percakapan baru siap dimulai.');
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            resetConversation('Apakah Anda yakin ingin menghapus seluruh riwayat percakapan dengan Aca?', 'Riwayat percakapan berhasil dihapus.');
        });
    }

    scrollBottom();
})();
</script>
