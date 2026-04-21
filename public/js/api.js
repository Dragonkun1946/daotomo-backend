/* api.js — Shared API + UI helpers cho tất cả các trang */
const API = '/api';

const api = {
  getToken:   () => localStorage.getItem('dtm_token'),
  getUser:    () => JSON.parse(localStorage.getItem('dtm_user') || 'null'),
  isLoggedIn: () => !!localStorage.getItem('dtm_token'),
  setSession(token, user) {
    localStorage.setItem('dtm_token', token);
    localStorage.setItem('dtm_user', JSON.stringify(user));
  },
  clearSession() {
    localStorage.removeItem('dtm_token');
    localStorage.removeItem('dtm_user');
  },
  async request(endpoint, opts = {}) {
    const token = this.getToken();
    const headers = { 'Content-Type': 'application/json', ...opts.headers };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res  = await fetch(`${API}${endpoint}`, { ...opts, headers });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Lỗi không xác định');
    return data;
  },
  register: (username, email, password, mc='') =>
    api.request('/auth/register', { method:'POST', body: JSON.stringify({ username, email, password, minecraftUsername: mc }) }),
  login: (email, password) =>
    api.request('/auth/login', { method:'POST', body: JSON.stringify({ email, password }) }),
  getMe: () => api.request('/auth/me'),
  getProducts: (params={}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/products${qs ? '?'+qs : ''}`);
  },
  getProduct:  (id) => api.request(`/products/${id}`),
  createOrder: (items, mc='', note='') =>
    api.request('/orders', { method:'POST', body: JSON.stringify({ items, minecraftUsername: mc, note }) }),
  getMyOrders: () => api.request('/orders/my'),
};

/* ── Init navbar on every page ── */
document.addEventListener('DOMContentLoaded', () => {
  const btn  = document.getElementById('hamburger');
  const list = document.getElementById('nav-list');
  if (btn && list) {
    btn.addEventListener('click', () => {
      list.classList.toggle('open');
      btn.classList.toggle('open');
    });
    list.querySelectorAll('a').forEach(a =>
      a.addEventListener('click', () => { list.classList.remove('open'); btn.classList.remove('open'); })
    );
    document.addEventListener('click', e => {
      if (!btn.contains(e.target) && !list.contains(e.target)) {
        list.classList.remove('open'); btn.classList.remove('open');
      }
    });
  }
  // Active link
  const cur = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('#nav-list a').forEach(a => {
    if (a.getAttribute('href')?.includes(cur)) a.classList.add('active');
  });
  // Show username
  const navAcc = document.getElementById('nav-account-link');
  if (navAcc && api.isLoggedIn()) {
    const u = api.getUser();
    if (u) navAcc.textContent = '👤 ' + u.username;
  }
});
