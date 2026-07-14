/* api.js — Shared API + UI helpers cho tất cả các trang */
const API = 'https://daotomo-backend.onrender.com/api';

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
  forgotPassword: (email) =>
    api.request('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) }),
  resetPassword: (token, password) =>
    api.request(`/auth/reset-password/${token}`, { method: 'POST', body: JSON.stringify({ password }) }),
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
    const label = navAcc.querySelector('span:last-child');
    if (u && label) label.textContent = u.username;
    else if (u) navAcc.textContent = u.username;
  }

  /* ── Scroll-reveal for any .reveal element on any page ── */
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
      revealEls.forEach(el => io.observe(el));
    } else {
      revealEls.forEach(el => el.classList.add('is-visible'));
    }
  }

  /* ── Animated mono stat counters: <span data-count-to="1200">0</span> ── */
  const counters = document.querySelectorAll('[data-count-to]');
  if (counters.length && 'IntersectionObserver' in window) {
    const runCount = (el) => {
      const target = parseFloat(el.dataset.countTo);
      const suffix = el.dataset.countSuffix || '';
      const duration = 1100;
      const start = performance.now();
      const step = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(eased * target).toLocaleString('vi-VN') + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    const ioCount = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { runCount(entry.target); ioCount.unobserve(entry.target); }
      });
    }, { threshold: 0.5 });
    counters.forEach(el => ioCount.observe(el));
  }

  /* ── Copy-to-clipboard: <button data-copy="play.daotomo.net"> ── */
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const val = btn.dataset.copy;
      const original = btn.innerHTML;
      try {
        await navigator.clipboard.writeText(val);
        btn.classList.add('copied');
        btn.dataset.copiedLabel && (btn.innerHTML = btn.dataset.copiedLabel);
        setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = original; }, 1600);
      } catch { /* clipboard unavailable — silently ignore */ }
    });
  });
});
