/**
 * cart.js — LocalStorage cart management for Toko Albi Parfum
 *
 * Cart item shape:
 * {
 *   id: string,          // unique key = productId + '-' + variantId (or just productId)
 *   product_id: number,
 *   variant_id: number|null,
 *   name: string,
 *   variant_name: string|null,
 *   price: number,       // unit price at time of add
 *   qty: number,
 *   image: string|null,
 *   stock: number,       // max stock available
 * }
 */

const CART_KEY = 'albi_cart';

const Cart = {
  /* ---- Read ---- */
  all() {
    try {
      return JSON.parse(localStorage.getItem(CART_KEY)) || [];
    } catch {
      return [];
    }
  },

  count() {
    return this.all().reduce((sum, i) => sum + i.qty, 0);
  },

  subtotal() {
    return this.all().reduce((sum, i) => sum + i.price * i.qty, 0);
  },

  find(id) {
    return this.all().find(i => i.id === id) || null;
  },

  /* ---- Write ---- */
  _save(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    Cart._notifyBadge();
  },

  add(item) {
    const items = this.all();
    const existing = items.find(i => i.id === item.id);
    if (existing) {
      const newQty = existing.qty + (item.qty || 1);
      existing.qty = Math.min(newQty, existing.stock);
    } else {
      items.push({ ...item, qty: item.qty || 1 });
    }
    this._save(items);
    Toast.show('Produk ditambahkan ke keranjang', 'success');
  },

  updateQty(id, qty) {
    const items = this.all();
    const item = items.find(i => i.id === id);
    if (!item) return;
    if (qty <= 0) {
      this.remove(id);
      return;
    }
    item.qty = Math.min(qty, item.stock);
    this._save(items);
  },

  remove(id) {
    const items = this.all().filter(i => i.id !== id);
    this._save(items);
    Toast.show('Produk dihapus dari keranjang', 'info');
  },

  clear() {
    localStorage.removeItem(CART_KEY);
    Cart._notifyBadge();
  },

  /* ---- UI badge update ---- */
  _notifyBadge() {
    const badges = document.querySelectorAll('[data-cart-badge]');
    const count = Cart.count();
    badges.forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'inline' : 'none';
    });
  },

  /* ---- Build checkout payload ---- */
  toOrderItems() {
    return this.all().map(i => ({
      product_id: i.product_id,
      product_variant_id: i.variant_id || null,
      quantity: i.qty,
    }));
  },
};

/* ===================================================
   Toast notification utility
   =================================================== */
const Toast = {
  _container() {
    let c = document.getElementById('toast-container-albi');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container-albi';
      c.className = 'toast-container-albi';
      document.body.appendChild(c);
    }
    return c;
  },

  show(message, type = 'info', duration = 3000) {
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const container = this._container();

    const el = document.createElement('div');
    el.className = `toast-albi ${type}`;
    el.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span>${message}</span>`;
    container.appendChild(el);

    setTimeout(() => {
      el.style.animation = 'fadeOut 0.3s ease forwards';
      setTimeout(() => el.remove(), 300);
    }, duration);
  },
};

/* ===================================================
   Format currency — Rupiah
   =================================================== */
function formatRupiah(amount) {
  return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

/* ===================================================
   Utility: get query param
   =================================================== */
function getParam(key) {
  return new URLSearchParams(window.location.search).get(key);
}

/* ===================================================
   Utility: debounce
   =================================================== */
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}
