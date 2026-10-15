/**
 * admin-auth.js — Shared auth guard, layout builder & API client
 * Loaded by every admin page except login.html
 */

function getApiBaseAdmin() {
  const path = window.location.pathname;
  let baseFolder = '/';
  if (path.includes('/public/')) {
    baseFolder = path.substring(0, path.indexOf('/public/') + 8);
  }
  const cleanBase = baseFolder.endsWith('/') ? baseFolder : baseFolder + '/';
  return window.location.origin + cleanBase + 'api/admin';
}

const adminApi = axios.create({
  baseURL: getApiBaseAdmin(),
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
  timeout: 15000,
});

// Global request interceptor to adjust paths like '/api/...' or '/api/admin/...'
if (typeof axios !== 'undefined') {
  axios.interceptors.request.use(config => {
    if (config.url && config.url.startsWith('/api') && !config.url.startsWith('http')) {
      const path = window.location.pathname;
      let baseFolder = '/';
      if (path.includes('/public/')) {
        baseFolder = path.substring(0, path.indexOf('/public/') + 8);
      }
      const cleanBase = baseFolder.endsWith('/') ? baseFolder : baseFolder + '/';
      if (cleanBase !== '/') {
        config.url = cleanBase + config.url.substring(1);
      }
    }
    return config;
  });
}

adminApi.interceptors.request.use(config => {
  const token = localStorage.getItem('albi_admin_token');
  if (token) config.headers['Authorization'] = `Bearer ${token}`;
  return config;
});

adminApi.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) AdminAuth.logout();
    return Promise.reject(err);
  }
);

/* ===================================================
   AdminAuth — Auth guard and layout
   =================================================== */
const AdminAuth = {
  user() {
    try { return JSON.parse(localStorage.getItem('albi_admin_user')); } catch { return null; }
  },

  role() {
    try { return JSON.parse(localStorage.getItem('albi_admin_role')); } catch { return null; }
  },

  token() { return localStorage.getItem('albi_admin_token'); },

  isLoggedIn() { return !!this.token(); },

  /**
   * Returns true if the current user may access a given menu.
   * Super admins bypass all permission checks.
   */
  hasPermission(menu) {
    const role = this.role();
    if (!role) return true; // no role stored → legacy / first-login; allow all
    if (role.is_super) return true;
    return Array.isArray(role.permissions) && role.permissions.includes(menu);
  },

  requireAuth() {
    if (!this.isLoggedIn()) {
      location.href = 'login';
      return false;
    }
    return true;
  },

  logout() {
    adminApi.post('/auth/logout').catch(() => {});
    localStorage.removeItem('albi_admin_token');
    localStorage.removeItem('albi_admin_user');
    localStorage.removeItem('albi_admin_role');
    location.href = 'login';
  },

  /**
   * Inject sidebar + topbar into the page.
   * @param {string} pageTitle  — shown in topbar
   * @param {string} activeNav  — matches the nav item id
   */
  buildLayout(pageTitle, activeNav) {
    const user = this.user();

    const allNavItems = [
      { id: 'dashboard',   label: 'Dashboard',        icon: 'bi-grid-1x2',       href: 'dashboard', section: 'UTAMA' },
      { id: 'products',    label: 'Katalog Produk',   icon: 'bi-box-seam',       href: 'products',  section: 'KATALOG & STOK' },
      { id: 'categories',  label: 'Kategori Aroma',   icon: 'bi-tags',           href: 'categories',section: 'KATALOG & STOK' },
      { id: 'tiers',       label: 'Ukuran & Size',    icon: 'bi-layers',         href: 'tiers',     section: 'KATALOG & STOK' },
      { id: 'stock',       label: 'Stok Inventori',   icon: 'bi-clipboard-data', href: 'stock',     section: 'KATALOG & STOK' },
      { id: 'orders',      label: 'Pesanan Masuk',    icon: 'bi-receipt',        href: 'orders',    section: 'OPERASIONAL' },
      { id: 'cashier',     label: 'Kasir Toko (POS)', icon: 'bi-calculator',     href: 'cashier',   section: 'OPERASIONAL' },
      { id: 'customers',   label: 'Data Pelanggan',   icon: 'bi-people',         href: 'customers', section: 'OPERASIONAL' },
      { id: 'reports',     label: 'Laporan Finansial',icon: 'bi-bar-chart-line', href: 'reports',   section: 'OPERASIONAL' },
      { id: 'settings',    label: 'Pengaturan Toko',  icon: 'bi-gear',           href: 'settings',  section: 'SISTEM' },
      { id: 'users',       label: 'Manajemen Staf',   icon: 'bi-person-badge',   href: 'users',     section: 'SISTEM' },
      { id: 'roles',       label: 'Hak Akses Role',   icon: 'bi-shield-lock',    href: 'roles',     section: 'SISTEM' },
    ];

    // Filter nav items by permission and group them
    const navItems = allNavItems.filter(item => this.hasPermission(item.id));
    let lastSection = '';
    let navHtml = '';

    navItems.forEach(item => {
      if (item.section && item.section !== lastSection) {
        navHtml += `<div class="admin-sidebar-section">${item.section}</div>`;
        lastSection = item.section;
      }
      navHtml += `
        <a href="${item.href}" class="admin-nav-item${activeNav === item.id ? ' active' : ''}">
          <i class="bi ${item.icon}"></i>
          <span>${item.label}</span>
        </a>
      `;
    });

    document.getElementById('admin-sidebar').innerHTML = `
      <div class="admin-sidebar">
        <button class="admin-sidebar-close d-md-none" id="sidebar-close"><i class="bi bi-x-lg"></i></button>
        <div class="admin-sidebar-brand">Albi <span>Admin</span></div>
        <nav class="admin-nav">${navHtml}</nav>
        <div class="admin-sidebar-footer">
          <button class="admin-logout-btn" id="btn-logout">
            <i class="bi bi-box-arrow-right"></i> Logout
          </button>
        </div>
      </div>
    `;

    document.getElementById('admin-topbar').innerHTML = `
      <div class="admin-topbar">
        <button class="admin-mobile-toggle d-md-none" id="sidebar-toggle">
          <i class="bi bi-list"></i>
        </button>
        <h1 class="admin-page-title">${escA(pageTitle)}</h1>
        <div class="ms-auto d-flex align-items-center gap-3">
          <a href="/" class="admin-topbar-link d-none d-md-inline" target="_blank">
            <i class="bi bi-shop me-1"></i>Lihat Toko
          </a>
          <div class="admin-user-chip">
            <i class="bi bi-person-circle"></i>
            ${escA(user?.name || 'Admin')}
            ${this.role()?.name ? `<span class="badge bg-secondary ms-1" style="font-size:0.65rem">${escA(this.role().name)}</span>` : ''}
          </div>
        </div>
      </div>
    `;

    document.getElementById('btn-logout')?.addEventListener('click', () => AdminAuth.logout());

    const sidebarEl = document.querySelector('.admin-sidebar');

    // Backdrop helpers
    function createSidebarBackdrop() {
      if (document.getElementById('sidebar-backdrop')) return;
      const d = document.createElement('div');
      d.id = 'sidebar-backdrop';
      d.addEventListener('click', hideSidebar);
      document.body.appendChild(d);
      // slight delay to allow CSS to apply
      requestAnimationFrame(() => d.classList.add('visible'));
    }

    function removeSidebarBackdrop() {
      const d = document.getElementById('sidebar-backdrop');
      if (!d) return;
      d.removeEventListener('click', hideSidebar);
      d.remove();
    }

    function showSidebar() {
      if (!sidebarEl) return;
      sidebarEl.classList.add('open');
      document.body.classList.add('admin-sidebar-open');
      createSidebarBackdrop();
    }

    function hideSidebar() {
      if (!sidebarEl) return;
      sidebarEl.classList.remove('open');
      document.body.classList.remove('admin-sidebar-open');
      removeSidebarBackdrop();
    }

    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
      if (!sidebarEl) return;
      if (sidebarEl.classList.contains('open')) hideSidebar(); else showSidebar();
    });

    document.getElementById('sidebar-close')?.addEventListener('click', hideSidebar);

    // Close sidebar with Escape key on mobile
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') hideSidebar();
    });
  },
};

/* ===================================================
   Shared Utilities
   =================================================== */
function escA(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function fmtRp(n) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function fmtDate(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

function fmtDateTime(str) {
  if (!str) return '—';
  return new Date(str).toLocaleString('id-ID', {
    day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'
  });
}

function slugify(text) {
  return text.toString().toLowerCase().trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w-]+/g, '')
    .replace(/--+/g, '-');
}

function showAlert(msg, type = 'success') {
  const container = document.getElementById('alert-container');
  if (!container) return;
  container.innerHTML = `
    <div class="alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'warning'} alert-dismissible fade show" role="alert" style="font-size:0.88rem">
      ${escA(msg)}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
  setTimeout(() => { container.innerHTML = ''; }, 4000);
}

/* ===================================================
   Status helpers
   =================================================== */
const ORDER_STATUSES = {
  pending:    { label: 'Pending',      class: 'pending' },
  processing: { label: 'Diproses',     class: 'processing' },
  shipped:    { label: 'Dikirim',      class: 'shipped' },
  delivered:  { label: 'Diterima',     class: 'delivered' },
  cancelled:  { label: 'Dibatalkan',   class: 'cancelled' },
  confirmed:  { label: 'Dikonfirmasi', class: 'confirmed' },
};

const PAYMENT_STATUSES = {
  unpaid:   { label: 'Belum Bayar', class: 'unpaid' },
  pending:  { label: 'Menunggu',    class: 'pending' },
  paid:     { label: 'Lunas',       class: 'paid' },
  failed:   { label: 'Gagal',       class: 'failed' },
  refunded: { label: 'Dikembalikan',class: 'refunded' },
};

function statusBadge(status) {
  const s = ORDER_STATUSES[status] || { label: status, class: 'pending' };
  return `<span class="badge-status ${s.class}">${s.label}</span>`;
}

function paymentBadge(status) {
  const s = PAYMENT_STATUSES[status] || { label: status, class: 'unpaid' };
  return `<span class="badge-status ${s.class}">${s.label}</span>`;
}

function activeBadge(bool) {
  return bool
    ? `<span class="badge-status active">Aktif</span>`
    : `<span class="badge-status inactive">Nonaktif</span>`;
}

function resolveAssetUrl(src) {
  if (!src) return null;
  if (src.includes('/storage/images/')) {
    src = src.replace('/storage/images/', '/images/');
  }
  if (src.startsWith('http') || src.startsWith('data:') || src.startsWith('blob:')) return src;
  
  const path = window.location.pathname;
  let baseFolder = '/';
  if (path.includes('/public/')) {
    baseFolder = path.substring(0, path.indexOf('/public/') + 8);
  }
  const cleanBase = baseFolder.endsWith('/') ? baseFolder : baseFolder + '/';
  
  const cleanSrc = src.startsWith('/') ? src.substring(1) : src;
  return window.location.origin + cleanBase + cleanSrc;
}
