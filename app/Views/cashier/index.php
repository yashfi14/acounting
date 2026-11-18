<?php
// Flash message dari selesai checkout
$flash_success = $_SESSION['flash_cashier_success'] ?? null;
$flash_error   = $_SESSION['flash_cashier_error']   ?? null;
unset($_SESSION['flash_cashier_success'], $_SESSION['flash_cashier_error']);
?>
<!-- CSRF token tersimpan di DOM untuk digunakan oleh JavaScript -->
<input type="hidden" id="csrfToken" value="<?= CSRF::token(); ?>">

<style>
    .cashier-wrapper { padding: 1rem; }
    /* Panel Cari */
    .search-panel .card-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none; }
    /* Panel Keranjang */
    .cart-panel .card-header  { background: linear-gradient(135deg, #16a34a, #15803d); border: none; }

    /* Tombol +/- qty */
    .qty-control { display: flex; align-items: center; gap: 4px; }
    .qty-control button {
        width: 28px; height: 28px; padding: 0; border-radius: 6px;
        font-size: 16px; line-height: 1; display: flex; align-items: center; justify-content: center;
    }
    .qty-control input {
        width: 52px; text-align: center; border-radius: 6px;
        font-weight: 600; font-size: 15px;
    }

    /* Item search result */
    #searchResults .product-item {
        cursor: pointer; border-radius: 10px; padding: 10px 14px;
        transition: all .15s ease; border: 1px solid #e5e7eb; margin-bottom: 6px;
        display: flex; align-items: center; justify-content: space-between;
    }
    #searchResults .product-item:hover { background: #eff6ff; border-color: #93c5fd; transform: translateX(3px); }
    #searchResults .product-item .item-name { font-weight: 600; color: #1e293b; }
    #searchResults .product-item .item-price { color: #16a34a; font-size: 14px; }
    #searchResults .product-item .stock-badge { 
        background: #dbeafe; color: #1d4ed8; 
        border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 600; 
    }
    #searchResults .product-item .stock-badge.low { background: #fef3c7; color: #92400e; }
    #searchResults .product-item .stock-badge.out  { background: #fee2e2; color: #991b1b; }

    /* Keranjang row */
    #cartTableBody tr td { vertical-align: middle; }
    #cartTableBody .remove-btn { width: 30px; height: 30px; padding: 0; }

    /* Total area */
    .total-box { background: #f0fdf4; border-radius: 12px; padding: 14px 18px; }
    .total-box .total-label { color: #4b5563; font-size: 15px; }
    .total-box .total-val   { color: #15803d; font-weight: 800; font-size: 26px; }

    /* Checkout btn */
    #btnCheckout { border-radius: 10px; letter-spacing: .5px; }
    #btnCheckout:not(:disabled):hover { filter: brightness(1.07); transform: translateY(-1px); }

    /* Loading/empty states */
    #searchHint, #searchLoading { color: #94a3b8; text-align: center; padding: 28px 0; }
    #searchLoading .spinner-border { width: 1.4rem; height: 1.4rem; }

    /* Flash */
    .flash-area { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 320px; }
    .flash-area .alert { animation: slideIn .3s ease; }
    @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<!-- Flash messages -->
<div class="flash-area" id="flashArea">
    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flash_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flash_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<div class="container-fluid cashier-wrapper mt-2">
    <div class="row g-3">

        <!-- ===== Panel Kiri: Cari Barang ===== -->
        <div class="col-lg-5 search-panel">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-search me-2"></i>Cari Barang</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0 fs-6"
                               placeholder="Ketik nama barang..." autocomplete="off" style="border-radius: 0 8px 8px 0;">
                    </div>

                    <div id="searchResults">
                        <div id="searchHint">
                            <i class="bi bi-bag-heart fs-2 d-block mb-2"></i>
                            Ketik nama barang untuk mulai mencari
                        </div>
                        <div id="searchLoading" style="display:none;">
                            <span class="spinner-border me-2"></span>Mencari...
                        </div>
                        <div id="productList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Panel Kanan: Keranjang ===== -->
        <div class="col-lg-7 cart-panel">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Keranjang Transaksi <span class="badge bg-white text-success ms-1" id="cartCount">0</span></h5>
                </div>
                <div class="card-body d-flex flex-column p-0">

                    <!-- Tabel Keranjang -->
                    <div class="table-responsive flex-grow-1" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top" style="font-size:13px;">
                                <tr>
                                    <th class="ps-3">Barang</th>
                                    <th class="text-end" style="width:18%">Harga</th>
                                    <th class="text-center" style="width:18%">Qty</th>
                                    <th class="text-end" style="width:18%">Subtotal</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody">
                                <tr id="emptyCartRow">
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="bi bi-cart-x fs-2 d-block mb-2"></i>
                                        Keranjang masih kosong
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total & Checkout -->
                    <div class="p-3 border-top">
                        <div class="total-box mb-3 d-flex justify-content-between align-items-center">
                            <span class="total-label fw-semibold"><i class="bi bi-receipt me-1"></i>Total Pembayaran</span>
                            <span class="total-val" id="totalAmount">Rp 0</span>
                        </div>
                        <button class="btn btn-success w-100 py-2 fw-bold fs-5 transition-all" id="btnCheckout" disabled>
                            <i class="bi bi-check2-circle me-2"></i>Selesaikan Pembayaran
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput  = document.getElementById('searchInput');
    const productList  = document.getElementById('productList');
    const searchHint   = document.getElementById('searchHint');
    const searchLoading= document.getElementById('searchLoading');
    const cartTableBody= document.getElementById('cartTableBody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const totalAmountEl= document.getElementById('totalAmount');
    const btnCheckout  = document.getElementById('btnCheckout');
    const cartCount    = document.getElementById('cartCount');

    let cart = [];
    let searchTimeout = null;

    /* ---------- Helpers ---------- */
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }
    function formatRupiah(n) {
        return new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 }).format(n);
    }
    function showFlash(msg, type = 'success') {
        const area = document.getElementById('flashArea');
        const div  = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible shadow-sm`;
        div.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-triangle-fill'} me-2"></i>${escapeHtml(msg)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        area.appendChild(div);
        setTimeout(() => { if (div.parentNode) { div.classList.add('fade'); setTimeout(()=>div.remove(), 300); } }, 4000);
    }

    /* ---------- Pencarian ---------- */
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const kw = this.value.trim();
        if (!kw) {
            productList.innerHTML = '';
            searchHint.style.display = '';
            searchLoading.style.display = 'none';
            return;
        }
        searchHint.style.display = 'none';
        searchLoading.style.display = '';
        productList.innerHTML = '';
        searchTimeout = setTimeout(() => fetchProducts(kw), 300);
    });

    function fetchProducts(kw) {
        fetch(`<?= BASEURL; ?>/cashier/search?q=${encodeURIComponent(kw)}`)
            .then(r => r.json())
            .then(data => {
                searchLoading.style.display = 'none';
                productList.innerHTML = '';
                if (!data.length) {
                    productList.innerHTML = `<div class="text-center text-muted py-4"><i class="bi bi-search fs-3 d-block mb-2"></i>Barang "<strong>${escapeHtml(kw)}</strong>" tidak ditemukan atau stok habis.</div>`;
                    return;
                }
                data.forEach(p => {
                    const stockClass = p.stock === 0 ? 'out' : (p.stock <= 5 ? 'low' : '');
                    const div = document.createElement('div');
                    div.className = 'product-item';
                    div.innerHTML = `
                        <div>
                            <div class="item-name">${escapeHtml(p.name)}</div>
                            <div class="item-price">${formatRupiah(p.price)}</div>
                        </div>
                        <span class="stock-badge ${stockClass}">Stok: ${escapeHtml(p.stock)}</span>
                    `;
                    div.addEventListener('click', () => addToCart(p));
                    productList.appendChild(div);
                });
            })
            .catch(() => {
                searchLoading.style.display = 'none';
                productList.innerHTML = `<div class="text-center text-danger py-3"><i class="bi bi-wifi-off me-2"></i>Gagal memuat data.</div>`;
            });
    }

    /* ---------- Keranjang ---------- */
    function addToCart(product) {
        const exist = cart.find(i => i.id === product.id);
        if (exist) {
            if (exist.qty < product.stock) { exist.qty++; renderCart(); }
            else showFlash(`Stok ${product.name} sudah maksimal (${product.stock})!`, 'warning');
        } else {
            cart.push({ id: product.id, name: product.name, price: parseFloat(product.price), stock: parseInt(product.stock), qty: 1 });
            renderCart();
        }
    }

    function changeQty(id, delta) {
        const item = cart.find(i => i.id === id);
        if (!item) return;
        const newQty = item.qty + delta;
        if (newQty < 1) { removeFromCart(id); return; }
        if (newQty > item.stock) { showFlash(`Stok ${item.name} hanya tersisa ${item.stock}!`, 'warning'); return; }
        item.qty = newQty;
        renderCart();
    }

    function setQty(id, val) {
        const item = cart.find(i => i.id === id);
        if (!item) return;
        let v = parseInt(val) || 1;
        if (v < 1) v = 1;
        if (v > item.stock) { v = item.stock; showFlash(`Stok ${item.name} hanya tersisa ${item.stock}!`, 'warning'); }
        item.qty = v;
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(i => i.id !== id);
        renderCart();
    }

    // Expose to inline handlers
    window._changeQty = changeQty;
    window._setQty    = setQty;
    window._removeFromCart = removeFromCart;

    function renderCart() {
        // Remove existing item rows (keep emptyCartRow)
        Array.from(cartTableBody.querySelectorAll('.cart-item-row')).forEach(r => r.remove());

        let total = 0;

        if (cart.length === 0) {
            emptyCartRow.style.display = '';
            btnCheckout.disabled = true;
            totalAmountEl.textContent = 'Rp 0';
            cartCount.textContent = '0';
            return;
        }

        emptyCartRow.style.display = 'none';
        btnCheckout.disabled = false;
        cartCount.textContent = cart.length;

        cart.forEach(item => {
            const subtotal = item.price * item.qty;
            total += subtotal;

            const tr = document.createElement('tr');
            tr.className = 'cart-item-row';
            tr.innerHTML = `
                <td class="ps-3">
                    <div class="fw-semibold" style="font-size:14px;">${escapeHtml(item.name)}</div>
                    <div class="text-muted" style="font-size:12px;">Stok: ${item.stock}</div>
                </td>
                <td class="text-end" style="font-size:14px;">${formatRupiah(item.price)}</td>
                <td class="text-center">
                    <div class="qty-control justify-content-center">
                        <button class="btn btn-outline-secondary btn-sm" onclick="_changeQty(${item.id}, -1)" title="Kurang">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" class="form-control form-control-sm"
                               value="${item.qty}" min="1" max="${item.stock}"
                               oninput="_setQty(${item.id}, this.value)"
                               style="width:52px; text-align:center; font-weight:600;">
                        <button class="btn btn-outline-primary btn-sm" onclick="_changeQty(${item.id}, 1)" title="Tambah">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </td>
                <td class="text-end fw-semibold" style="font-size:14px;">${formatRupiah(subtotal)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger remove-btn" onclick="_removeFromCart(${item.id})" title="Hapus">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            `;
            cartTableBody.appendChild(tr);
        });

        totalAmountEl.textContent = formatRupiah(total);
    }

    /* ---------- Checkout ---------- */
    btnCheckout.addEventListener('click', function () {
        if (!cart.length) return;
        if (!confirm(`Konfirmasi pembayaran\nTotal: ${totalAmountEl.textContent}\n\nLanjutkan transaksi?`)) return;

        const csrfToken = document.getElementById('csrfToken').value;
        if (!csrfToken) {
            showFlash('Token keamanan tidak ditemukan. Muat ulang halaman.', 'danger');
            return;
        }

        btnCheckout.disabled = true;
        btnCheckout.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

        fetch('<?= BASEURL; ?>/cashier/checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart: cart, csrf_token: csrfToken })
        })
        .then(r => {
            const contentType = r.headers.get('Content-Type') || '';
            if (!contentType.includes('application/json')) {
                // Server kembalikan HTML (mungkin error PHP)
                return r.text().then(html => {
                    throw new Error('Server error (bukan JSON). Kemungkinan ada error PHP. Cek console.');
                });
            }
            return r.json();
        })
        .then(res => {
            if (res.success) {
                showFlash('✅ Transaksi #' + res.transaction_id + ' berhasil! Total: ' + formatRupiah(res.total), 'success');
                cart = [];
                renderCart();
                searchInput.value = '';
                productList.innerHTML = '';
                searchHint.style.display = '';
                // Refresh CSRF token
                document.getElementById('csrfToken').value = res.new_csrf || csrfToken;
            } else {
                showFlash('❌ Gagal: ' + res.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Checkout error:', err);
            showFlash('Koneksi error: ' + err.message, 'danger');
        })
        .finally(() => {
            btnCheckout.disabled = cart.length === 0;
            btnCheckout.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Selesaikan Pembayaran';
        });
    });
});
</script>
