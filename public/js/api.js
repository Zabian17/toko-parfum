function getApiBase() {
  const path = window.location.pathname;
  let baseFolder = '/';
  if (path.includes('/public/')) {
    baseFolder = path.substring(0, path.indexOf('/public/') + 8);
  }
  const cleanBase = baseFolder.endsWith('/') ? baseFolder : baseFolder + '/';
  return window.location.origin + cleanBase + 'api';
}

const API_BASE = getApiBase();
const IS_VERCEL_PREVIEW = location.hostname.endsWith('.vercel.app')
  && (location.hostname.includes('-git-') || location.hostname === 'albi-parfum.vercel.app');
const PREVIEW_SESSION_KEY = 'albi_preview_session';
const PREVIEW_NAVIGATION_KEY = 'albi_preview_navigation';

function getPreviewSessionId() {
  if (!IS_VERCEL_PREVIEW) return null;
  try {
    let id = sessionStorage.getItem(PREVIEW_SESSION_KEY);
    if (!id) {
      id = crypto.randomUUID();
      sessionStorage.setItem(PREVIEW_SESSION_KEY, id);
    }
    return id;
  } catch {
    return null;
  }
}

console.log('%c[API] Base URL: ' + API_BASE, 'color:#C9A84C;font-weight:bold');

// Global axios interceptor to redirect absolute '/api' requests in subdirectories
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

// Check axios loaded
if (typeof axios === 'undefined') {
  console.error('[API] ✘ axios is NOT defined! CDN failed to load. Check network tab.');
} else {
  console.log('%c[API] ✔ axios loaded: ' + axios.VERSION, 'color:#27ae60');
}

const api = axios.create({
  baseURL: API_BASE,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

// Attach Sanctum token if stored
api.interceptors.request.use(config => {
  const token = localStorage.getItem('albi_token');
  if (token) config.headers['Authorization'] = `Bearer ${token}`;
  const previewSessionId = getPreviewSessionId();
  if (previewSessionId) config.headers['X-Preview-Session'] = previewSessionId;

  const params = config.params ? ' ' + JSON.stringify(config.params) : '';
  console.log(`%c[API] ▶ ${config.method?.toUpperCase()} ${config.baseURL}${config.url}${params}`, 'color:#4a90d9');
  return config;
});

if (IS_VERCEL_PREVIEW) {
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const destination = new URL(link.href, location.href);
    if (destination.origin === location.origin
      && (destination.pathname !== location.pathname || destination.search !== location.search)) {
      sessionStorage.setItem(PREVIEW_NAVIGATION_KEY, '1');
    }
  }, true);

  window.addEventListener('pagehide', () => {
    const sessionId = sessionStorage.getItem(PREVIEW_SESSION_KEY);
    if (!sessionId) return;
    if (sessionStorage.getItem(PREVIEW_NAVIGATION_KEY) === '1') {
      sessionStorage.removeItem(PREVIEW_NAVIGATION_KEY);
      return;
    }

    const body = new Blob([JSON.stringify({ session_id: sessionId })], { type: 'application/json' });
    const accepted = navigator.sendBeacon(`${API_BASE}/preview/session/reset`, body);
    if (!accepted) {
      fetch(`${API_BASE}/preview/session/reset`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId }),
        keepalive: true,
      });
    }
  });
}

// Global response + error logger
api.interceptors.response.use(
  res => {
    const count = Array.isArray(res.data?.data) ? ` (${res.data.data.length} items)` : '';
    console.log(`%c[API] ✔ ${res.status} ${res.config.url}${count}`, 'color:#27ae60');
    return res;
  },
  err => {
    if (err.response) {
      console.error(`[API] ✘ ${err.response.status} ${err.config?.url}`, err.response.data);
    } else if (err.request) {
      console.error(`[API] ✘ No response received (network error / CORS / timeout)`, err.message, err.config?.url);
    } else {
      console.error(`[API] ✘ Request setup error`, err.message);
    }
    if (err.response?.status === 401) {
      localStorage.removeItem('albi_token');
      localStorage.removeItem('albi_user');
    }
    return Promise.reject(err);
  }
);

/* ---- Product endpoints ---- */
const ProductAPI = {
  list: (params = {}) => api.get('/products', { params }),
  show: (id)          => api.get(`/products/${id}`),
  bySlug: (slug)      => api.get(`/products/slug/${slug}`),
};

/* ---- Category endpoints ---- */
const CategoryAPI = {
  list: () => api.get('/categories'),
  show: (id) => api.get(`/categories/${id}`),
};

/* ---- Shipping endpoints ---- */
const ShippingAPI = {
  provinces: ()            => api.get('/shipping/provinces'),
  cities: (provinceId)     => api.get('/shipping/cities', { params: { province_id: provinceId } }),
  destinations: (search)   => api.get('/shipping/destinations', { params: { search } }),
  cost: (payload)          => api.post('/shipping/cost', payload),
};

/* ---- Payment endpoints ---- */
const PaymentAPI = {
  config: ()                    => api.get('/payment/config'),
  snapToken: (orderNumber)      => api.get(`/payment/snap/${orderNumber}`),
  sandboxQris: (orderNumber, paymentReference) => api.post(`/payment/qris-sandbox/${orderNumber}`, { payment_reference: paymentReference }),
  details: (orderNumber, snapToken, transactionId) => api.get(`/payment/details/${orderNumber}`, { params: { snap_token: snapToken, transaction_id: transactionId || undefined } }),
  notification: (payload)       => api.post('/payment/notification', payload),
};

/* ---- Checkout ---- */
const CheckoutAPI = {
  place: (payload) => api.post('/checkout', payload, { timeout: 45000 }),
};

/* ---- Auth (Breeze) ---- */
const AuthAPI = {
  login:    (email, password) => api.post('/login', { email, password }),
  logout:   ()                => api.post('/logout'),
  register: (data)            => api.post('/register', data),
  user:     ()                => api.get('/user'),
};

/* ---- Resolve Asset URL helper for subdirectory installs ---- */
function resolveAssetUrl(src) {
  if (!src) return null;
  if (src.startsWith('http') || src.startsWith('data:') || src.startsWith('blob:')) return src;
  
  const path = window.location.pathname;
  let baseFolder = '/';
  if (path.includes('/public/')) {
    baseFolder = path.substring(0, path.indexOf('/public/') + 8);
  }
  const cleanBase = baseFolder.endsWith('/') ? baseFolder : baseFolder + '/';
  
  // strip leading slash from src if present to join cleanly
  const cleanSrc = src.startsWith('/') ? src.substring(1) : src;
  return window.location.origin + cleanBase + cleanSrc;
}
