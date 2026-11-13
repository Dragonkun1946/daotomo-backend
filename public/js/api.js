/* api.js — Shared API + UI helpers + SoundFX + Confetti + Command Palette cho Đảo Tò Mò */
const getApiBase = () => {
  if (typeof window !== 'undefined' && window.location) {
    const { origin, protocol, hostname } = window.location;
    if (protocol === 'http:' || protocol === 'https:') {
      if (origin.includes(':5500') || origin.includes(':3000')) {
        return 'http://127.0.0.1:8000/api';
      }
      if (hostname === 'localhost' || hostname === '127.0.0.1' || origin.includes(':8000') || origin.includes(':5000')) {
        return '/api';
      }
      if (hostname === 'daotomo.site' || hostname.endsWith('.daotomo.site')) {
        return 'https://daotomo-backend.onrender.com/api';
      }
      return '/api';
    }
    if (protocol === 'file:') {
      return 'http://127.0.0.1:8000/api';
    }
  }
  return 'https://daotomo-backend.onrender.com/api';
};

const API = getApiBase();

/* ═══════════════════════════════════════════════════════════
   SOUND FX ENGINE (Web Audio API Synthesizer — Zero Audio Files)
═══════════════════════════════════════════════════════════ */
const SoundFX = {
  ctx: null,
  enabled: typeof localStorage !== 'undefined' ? localStorage.getItem('dtm_sound_enabled') !== 'false' : true,
  init() {
    if (!this.ctx && typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext)) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      this.ctx = new AudioCtx();
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  },
  toggle() {
    this.enabled = !this.enabled;
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('dtm_sound_enabled', this.enabled ? 'true' : 'false');
    }
    if (this.enabled) this.playSuccess();
    return this.enabled;
  },
  playClick() {
    if (!this.enabled) return;
    try {
      this.init();
      if (!this.ctx) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(820, this.ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(380, this.ctx.currentTime + 0.04);
      gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.04);
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start();
      osc.stop(this.ctx.currentTime + 0.04);
    } catch {}
  },
  playSuccess() {
    if (!this.enabled) return;
    try {
      this.init();
      if (!this.ctx) return;
      const now = this.ctx.currentTime;
      [523.25, 659.25, 783.99, 1046.50].forEach((freq, idx) => {
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(freq, now + idx * 0.06);
        gain.gain.setValueAtTime(0.12, now + idx * 0.06);
        gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.06 + 0.22);
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start(now + idx * 0.06);
        osc.stop(now + idx * 0.06 + 0.22);
      });
    } catch {}
  },
  playError() {
    if (!this.enabled) return;
    try {
      this.init();
      if (!this.ctx) return;
      const now = this.ctx.currentTime;
      [220, 180].forEach((freq, idx) => {
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(freq, now + idx * 0.1);
        gain.gain.setValueAtTime(0.07, now + idx * 0.1);
        gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.1 + 0.16);
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start(now + idx * 0.1);
        osc.stop(now + idx * 0.1 + 0.16);
      });
    } catch {}
  },
  playPing() {
    if (!this.enabled) return;
    try {
      this.init();
      if (!this.ctx) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(1150, this.ctx.currentTime);
      gain.gain.setValueAtTime(0.07, this.ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + 0.14);
      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start();
      osc.stop(this.ctx.currentTime + 0.14);
    } catch {}
  }
};
window.SoundFX = SoundFX;

/* ═══════════════════════════════════════════════════════════
   CONFETTI FX ENGINE (Pure Canvas Minecraft Pixels)
═══════════════════════════════════════════════════════════ */
const ConfettiFX = {
  canvas: null,
  ctx: null,
  particles: [],
  animId: null,
  init() {
    if (this.canvas) return;
    this.canvas = document.createElement('canvas');
    this.canvas.id = 'confetti-canvas';
    this.canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:99999;';
    document.body.appendChild(this.canvas);
    this.ctx = this.canvas.getContext('2d');
    this.resize();
    window.addEventListener('resize', () => this.resize());
  },
  resize() {
    if (!this.canvas) return;
    this.canvas.width = window.innerWidth;
    this.canvas.height = window.innerHeight;
  },
  fire(x = window.innerWidth / 2, y = window.innerHeight * 0.4, count = 65) {
    this.init();
    const colors = ['#7c3aed', '#22d3ee', '#f59e0b', '#10b981', '#f43f5e', '#ffffff'];
    for (let i = 0; i < count; i++) {
      const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.4;
      const speed = Math.random() * 9 + 4;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed - 3.5,
        size: Math.random() * 8 + 4,
        color: colors[Math.floor(Math.random() * colors.length)],
        rotation: Math.random() * 360,
        vr: (Math.random() - 0.5) * 12,
        life: 1,
        decay: Math.random() * 0.015 + 0.012
      });
    }
    if (!this.animId) this.loop();
  },
  loop() {
    if (!this.particles.length) {
      if (this.ctx && this.canvas) this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.animId = null;
      return;
    }
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.vy += 0.22;
      p.vx *= 0.98;
      p.rotation += p.vr;
      p.life -= p.decay;

      if (p.life <= 0 || p.y > this.canvas.height) {
        this.particles.splice(i, 1);
        continue;
      }

      this.ctx.save();
      this.ctx.translate(p.x, p.y);
      this.ctx.rotate((p.rotation * Math.PI) / 180);
      this.ctx.globalAlpha = p.life;
      this.ctx.fillStyle = p.color;
      this.ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
      this.ctx.restore();
    }
    this.animId = requestAnimationFrame(() => this.loop());
  }
};
window.ConfettiFX = ConfettiFX;

/* ═══════════════════════════════════════════════════════════
   TOAST SYSTEM (Enhanced with Progress Timer & Audio Cues)
═══════════════════════════════════════════════════════════ */
function showToast(message, type = 'info', duration = 3400) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: '✅',
    error: '⚠️',
    info: '💡'
  };

  if (type === 'success') SoundFX.playSuccess();
  else if (type === 'error') SoundFX.playError();
  else SoundFX.playPing();

  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${icons[type] || '✨'}</span>
    <span class="toast-msg">${api.escapeHtml(message)}</span>
    <div class="toast-timer-bar" style="transition: width ${duration}ms linear; width: 100%;"></div>
  `;
  container.appendChild(toast);

  requestAnimationFrame(() => {
    const bar = toast.querySelector('.toast-timer-bar');
    if (bar) bar.style.width = '0%';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'all 0.25s ease';
    setTimeout(() => toast.remove(), 260);
  }, duration);
}

const api = {
  getToken:   () => localStorage.getItem('dtm_token'),
  getUser:    () => JSON.parse(localStorage.getItem('dtm_user') || 'null'),
  resolveAssetUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      return trimmed;
    }
    if (trimmed.startsWith('/uploads/') && API.startsWith('http')) {
      return API.replace(/\/api\/?$/, '') + trimmed;
    }
    return trimmed;
  },
  getSkinCacheKey(user) {
    const uid = user && (user.id || user._id || user.username);
    return uid ? `dtm_custom_skin_${uid}` : null;
  },
  getUserCustomSkin(user) {
    const k = this.getSkinCacheKey(user);
    return k ? (localStorage.getItem(k) || '') : '';
  },
  setUserCustomSkin(user, skinData) {
    localStorage.removeItem('dtm_custom_skin');
    const k = this.getSkinCacheKey(user);
    if (k && skinData) {
      try { localStorage.setItem(k, skinData); } catch (e) {}
    }
  },
  clearUserCustomSkin(user) {
    localStorage.removeItem('dtm_custom_skin');
    const k = this.getSkinCacheKey(user);
    if (k) localStorage.removeItem(k);
  },
  normalizeUserAssets(user) {
    if (!user || typeof user !== 'object') return user;
    const resolvedSkin = this.resolveAssetUrl(user.skinUrl || user.skin_url || '');
    const resolvedAvatar = this.resolveAssetUrl(user.avatar || '');
    user.skinUrl = resolvedSkin;
    user.skin_url = resolvedSkin;
    user.avatar = resolvedAvatar;
    if (resolvedSkin && resolvedSkin.startsWith('data:image/')) {
      this.setUserCustomSkin(user, resolvedSkin);
    }
    return user;
  },
  setUser:    (user) => {
    if (user) {
      api.normalizeUserAssets(user);
      localStorage.setItem('dtm_user', JSON.stringify(user));
      try { window.dispatchEvent(new CustomEvent('daotomo:auth-changed', { detail: { user } })); } catch (e) {}
    }
  },
  isLoggedIn: () => {
    const t = localStorage.getItem('dtm_token');
    return !!t && t !== 'null' && t !== 'undefined' && t !== 'false' && t.trim() !== '';
  },
  isAdmin: () => {
    if (!api.isLoggedIn()) return false;
    const u = api.getUser();
    return !!(u && (u.role === 'admin' || u.is_admin || u.role === 'ADMIN'));
  },
  setSession(token, user) {
    // Always wipe legacy unscoped key so switching accounts never leaks skins across users
    localStorage.removeItem('dtm_custom_skin');
    if (token) localStorage.setItem('dtm_token', token);
    if (user) {
      this.normalizeUserAssets(user);
      localStorage.setItem('dtm_user', JSON.stringify(user));
    }
    try {
      const skinUrl = this.getActiveSkinUrl(user || null);
      window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl } }));
      window.dispatchEvent(new CustomEvent('daotomo:auth-changed', { detail: { user } }));
    } catch (e) {}
  },
  clearSession() {
    localStorage.removeItem('dtm_token');
    localStorage.removeItem('dtm_user');
    localStorage.removeItem('dtm_custom_skin');
    try {
      window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl: '/img/steve.png' } }));
      window.dispatchEvent(new CustomEvent('daotomo:auth-changed', { detail: { user: null } }));
    } catch (e) {}
  },
  showToast,
  playClick: () => SoundFX.playClick(),
  playSuccess: () => SoundFX.playSuccess(),
  playError: () => SoundFX.playError(),
  fireConfetti: (x, y, count) => ConfettiFX.fire(x, y, count),
  async request(endpoint, opts = {}) {
    const token = this.getToken();
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...opts.headers };
    if (token && token !== 'null' && token !== 'undefined') headers['Authorization'] = `Bearer ${token}`;
    const res  = await fetch(`${API}${endpoint}`, { ...opts, headers });
    let data;
    const contentType = res.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      data = await res.json();
    } else {
      const text = await res.text();
      let parsed = null;
      try { parsed = JSON.parse(text); } catch {}
      if (parsed) {
        data = parsed;
      } else {
        throw new Error(res.status === 500 ? 'Máy chủ đang khởi động hoặc kết nối cơ sở dữ liệu (500). Vui lòng thử lại sau giây lát!' : (text.slice(0, 120) || `Lỗi HTTP ${res.status}`));
      }
    }
    if (!res.ok) {
      if (res.status === 401) {
        if (!endpoint.includes('/auth/login') && !endpoint.includes('/auth/register')) {
          this.clearSession();
        }
        const friendlyMsg = (data.message === 'Unauthenticated.' || !data.message)
          ? 'Phiên đăng nhập đã hết hạn hoặc bạn chưa đăng nhập.'
          : data.message;
        const err = new Error(friendlyMsg);
        err.status = 401;
        throw err;
      }
      throw new Error(data.message || 'Lỗi không xác định');
    }
    return data;
  },
  register: (username, email, password, mc='') =>
    api.request('/auth/register', { method:'POST', body: JSON.stringify({ username, email, password, minecraftUsername: mc }) }),
  login: (email, password) =>
    api.request('/auth/login', { method:'POST', body: JSON.stringify({ email, password }) }),
  getMe: async () => {
    const res = await api.request('/auth/me');
    const u = res?.user || res?.data || res;
    if (u && typeof u === 'object' && (u.id || u.username)) {
      api.setUser(u);
    }
    return res;
  },
  updateMe: (data) =>
    api.request('/auth/me', { method: 'PUT', body: JSON.stringify(data) }),
  forgotPassword: (email) =>
    api.request('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) }),
  resetPassword: (token, password) =>
    api.request(`/auth/reset-password/${token}`, { method: 'POST', body: JSON.stringify({ password }) }),
  getProducts: (params={}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/products${qs ? '?'+qs : ''}`);
  },
  getProduct:  (id) => api.request(`/products/${id}`),
  createOrder: async function(items, mc='', note='', paymentMethod='qr', idempotencyKey='') {
    const headers = {};
    if (idempotencyKey) headers['X-Idempotency-Key'] = idempotencyKey;
    const payload = { items, minecraftUsername: mc, note, paymentMethod, idempotencyKey };

    // If user is logged in, try member order first
    if (this.isLoggedIn()) {
      try {
        return await this.request('/orders', {
          method: 'POST',
          headers,
          body: JSON.stringify(payload),
        });
      } catch (err) {
        // If token was invalid/expired (401), clear dead session and fallback to guest order seamlessly
        if (err.status === 401 || err.message?.includes('Unauthenticated') || err.message?.includes('đăng nhập')) {
          this.clearSession();
          return await this.request('/orders/guest', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
          });
        }
        throw err;
      }
    }

    // Guest checkout (user not logged in)
    return await this.request('/orders/guest', {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    });
  },
  getMyOrders: (params={}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/orders/my${qs ? '?'+qs : ''}`);
  },
  getOrder: (id) => api.request(`/orders/${id}`),
  getAllOrders: (params={}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/orders${qs ? '?'+qs : ''}`);
  },
  updateOrderStatus: (id, payload) =>
    api.request(`/orders/${id}/status`, { method: 'PUT', body: JSON.stringify(payload) }),
  adminMarkPaidAndDeliver: (orderId, force=false) =>
    api.request(`/admin/orders/${orderId}/deliver`, { method: 'POST', body: JSON.stringify({ force }) }),
  getAdminUsers: (params={}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/admin/users${qs ? '?'+qs : ''}`);
  },
  adminChangePassword: (userId, newPassword) =>
    api.request(`/admin/users/${userId}/password`, { method: 'PUT', body: JSON.stringify({ password: newPassword, new_password: newPassword }) }),
  adminUnlinkGame: (userId) =>
    api.request(`/admin/users/${userId}/unlink`, { method: 'POST' }),
  cancelOrder: (id, reason='') =>
    api.request(`/orders/${id}/cancel`, { method: 'PUT', body: JSON.stringify({ reason }) }),
  submitPaymentProof: (id, paymentProof, note='') =>
    api.request(`/orders/${id}/payment-proof`, { method: 'PUT', body: JSON.stringify({ paymentProof, note }) }),
  linkGame: (pinCode) =>
    api.request('/user/game-link', { method: 'POST', body: JSON.stringify({ pin: pinCode, pin_code: pinCode }) }),
  unlinkGame: () =>
    api.request('/user/game-link', { method: 'DELETE' }),
  checkinToday: () => api.request('/checkin', { method: 'POST' }),
  getMyCheckins: () => api.request('/checkin/me'),
  getCheckinStatus: () => api.request('/checkin/me'),
  updateSkin: (skinData, skinUrl) =>
    api.request('/auth/skin', { method: 'POST', body: JSON.stringify({ skinData, skinUrl }) }),
  getSupportConfig: () => api.request('/support/config'),
  createSupportTicket: (payload) =>
    api.request('/tickets', { method: 'POST', body: JSON.stringify(payload) }),
  getMyTickets: () => api.request('/tickets/my'),
  lookupTicket: (code) => api.request('/tickets/lookup?code=' + encodeURIComponent(code)),
  getAdminTickets: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return api.request(`/admin/tickets${qs ? '?' + qs : ''}`);
  },
  adminUpdateTicket: (id, payload) =>
    api.request(`/admin/tickets/${id}`, { method: 'PUT', body: JSON.stringify(payload) }),
  adminUpdateSupportSettings: (payload) =>
    api.request('/admin/support/settings', { method: 'PUT', body: JSON.stringify(payload) }),
  adminTestSupportWebhook: (discord_ticket_webhook_url = '') =>
    api.request('/admin/support/test-webhook', { method: 'POST', body: JSON.stringify({ discord_ticket_webhook_url }) }),
  escapeHtml: (str) => String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;'),
  safeUrl: (url, fallback = '#') => {
    const s = String(url ?? '').trim();
    if (!s) return fallback;
    if (s.startsWith('/') || s.startsWith('data:image/')) return s;
    try {
      const parsed = new URL(s);
      if (parsed.protocol === 'https:' || parsed.protocol === 'http:') return s;
    } catch (_) {}
    return fallback;
  },
  getActiveSkinUrl: (userOverride = null) => {
    if (!userOverride && !api.isLoggedIn()) return '/img/steve.png';
    const user = userOverride || api.getUser();
    if (!user) return '/img/steve.png';
    const rawSkin = api.resolveAssetUrl(user.skinUrl || user.skin_url || '');
    if (rawSkin.startsWith('data:image/')) {
      return rawSkin;
    }
    const scoped = api.getUserCustomSkin(user);
    if (scoped && (rawSkin.includes('/uploads/') || user._offlineSkin)) {
      return scoped;
    }
    if (rawSkin && rawSkin !== '/img/steve.png') {
      return rawSkin;
    }
    return '/img/steve.png';
  },
};
window.api = api;

/* ═══════════════════════════════════════════════════════════
   COMMAND PALETTE ENGINE (Ctrl+K / Cmd+K Quick Navigation)
═══════════════════════════════════════════════════════════ */
const COMMAND_ITEMS = [
  { icon: '🏠', title: 'Trang Chủ — Bảng tin & Khám phá Đảo', type: 'link', url: '/index.html' },
  { icon: '🛒', title: 'Cửa Hàng — Mua Rank VIP, Crate, Xu Đảo', type: 'link', url: '/html/Store.html' },
  { icon: '👑', title: 'Gói VIP Hoàng Gia — Đặc quyền /fly, x2 EXP', type: 'link', url: '/html/Store.html?cat=ranks' },
  { icon: '🗝️', title: 'Rương Thần Thoại — Mythic Key mở hòm quý', type: 'link', url: '/html/Store.html?cat=crates' },
  { icon: '💰', title: 'Nạp Xu Máy Chủ — Tỷ giá ưu đãi VietQR 24/7', type: 'link', url: '/html/Store.html?cat=currency' },
  { icon: '🎮', title: 'Sao Chép IP Server (play.daotomo.net)', type: 'action', action: 'copy_ip' },
  { icon: '🗺️', title: 'Bản Đồ Thế Giới & Tọa Độ Địa Danh', type: 'link', url: '/html/Map.html' },
  { icon: '💬', title: 'Cộng Đồng Discord — Voice, Chat & Event', type: 'link', url: '/html/Discord.html' },
  { icon: '🎫', title: 'Tạo Ticket Hỗ Trợ Web — Gửi vấn đề cần BQT xử lý', type: 'action', action: 'open_support_ticket' },
  { icon: '📱', title: 'Nhắn Tin Hỗ Trợ Qua Zalo Admin', type: 'action', action: 'open_zalo' },
  { icon: '📅', title: 'Điểm Danh Hằng Ngày — Tích chuỗi nhận quà', type: 'link', url: '/html/Checkin.html' },
  { icon: '📦', title: 'Lịch Sử Đơn Hàng & Tài Khoản Của Tôi', type: 'link', url: '/html/Account.html' },
  { icon: '🧍', title: 'Mô Hình Nhân Vật 3D — Xoay & tương tác skin Minecraft', type: 'action', action: 'open_skin_viewer' },
  { icon: '🛡️', title: 'Bảng Điều Khiển Admin (Quản trị)', type: 'link', url: '/html/Admin.html' },
  { icon: '🔊', title: 'Bật / Tắt Hiệu Ứng Âm Thanh', type: 'action', action: 'toggle_sound' }
];

function initCommandPalette() {
  if (document.getElementById('cmd-palette-backdrop')) return;

  const backdrop = document.createElement('div');
  backdrop.id = 'cmd-palette-backdrop';
  backdrop.className = 'cmd-palette-backdrop';
  backdrop.innerHTML = `
    <div class="cmd-palette-modal" role="dialog" aria-modal="true">
      <div class="cmd-palette-header">
        <span class="cmd-palette-search-icon">🔍</span>
        <input type="text" id="cmd-palette-input" class="cmd-palette-input" placeholder="Tìm kiếm nhanh trang, vật phẩm, lệnh... (Gõ để tìm)" autocomplete="off">
        <span class="cmd-palette-esc" id="cmd-palette-close-btn">ESC</span>
      </div>
      <div class="cmd-palette-body" id="cmd-palette-body"></div>
    </div>
  `;
  document.body.appendChild(backdrop);

  const input = document.getElementById('cmd-palette-input');
  const body = document.getElementById('cmd-palette-body');
  const closeBtn = document.getElementById('cmd-palette-close-btn');

  let activeIndex = 0;
  let filteredItems = [...COMMAND_ITEMS];

  function renderList() {
    if (!filteredItems.length) {
      body.innerHTML = `
        <div style="text-align:center; padding:2rem 1rem; color:var(--text-muted); font-size:0.9rem;">
          Không tìm thấy kết quả phù hợp cho "${api.escapeHtml(input.value)}"
        </div>
      `;
      return;
    }

    body.innerHTML = filteredItems.map((item, idx) => `
      <div class="cmd-palette-item ${idx === activeIndex ? 'active' : ''}" data-index="${idx}">
        <div class="cmd-item-left">
          <span class="cmd-item-icon">${item.icon}</span>
          <span>${item.title}</span>
        </div>
        <span class="cmd-item-shortcut">${item.type === 'action' ? 'Thực thi ↵' : 'Chuyển trang ↵'}</span>
      </div>
    `).join('');

    body.querySelectorAll('.cmd-palette-item').forEach(el => {
      el.addEventListener('click', () => {
        executeItem(filteredItems[parseInt(el.dataset.index, 10)]);
      });
      el.addEventListener('mouseenter', () => {
        activeIndex = parseInt(el.dataset.index, 10);
        updateActiveItem();
      });
    });
  }

  function updateActiveItem() {
    body.querySelectorAll('.cmd-palette-item').forEach((el, idx) => {
      el.classList.toggle('active', idx === activeIndex);
    });
  }

  function openPalette() {
    backdrop.classList.add('open');
    input.value = '';
    filteredItems = [...COMMAND_ITEMS];
    activeIndex = 0;
    renderList();
    SoundFX.playPing();
    setTimeout(() => input.focus(), 50);
  }

  function closePalette() {
    backdrop.classList.remove('open');
    input.blur();
  }

  function executeItem(item) {
    if (!item) return;
    SoundFX.playClick();
    closePalette();
    if (item.type === 'link') {
      window.location.href = item.url;
    } else if (item.action === 'copy_ip') {
      navigator.clipboard.writeText('play.daotomo.net').then(() => {
        SoundFX.playSuccess();
        ConfettiFX.fire(window.innerWidth / 2, window.innerHeight * 0.4, 60);
        showToast('Đã sao chép IP máy chủ: play.daotomo.net', 'success');
      });
    } else if (item.action === 'toggle_sound') {
      const state = SoundFX.toggle();
      showToast(state ? '🔊 Âm thanh đã BẬT' : '🔇 Âm thanh đã TẮT', 'info');
      updateSoundBtn();
    } else if (item.action === 'open_skin_viewer') {
      if (window.openSkinModal) window.openSkinModal();
    } else if (item.action === 'open_support_ticket') {
      if (typeof window.openSupportTicketModal === 'function') {
        window.openSupportTicketModal();
      } else {
        const ticketUrl = DTM_SUPPORT_STATE.ticketChannelUrl || DTM_SUPPORT_STATE.discordInviteUrl;
        window.open(ticketUrl, '_blank', 'noopener,noreferrer');
      }
    } else if (item.action === 'open_zalo') {
      const zaloLink = DTM_SUPPORT_STATE.zaloUrl || document.getElementById('dtm-fab-zalo-btn')?.getAttribute('href') || '/html/Discord.html';
      window.open(zaloLink, '_blank', 'noopener,noreferrer');
    }
  }

  input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    filteredItems = COMMAND_ITEMS.filter(item => item.title.toLowerCase().includes(q));
    activeIndex = 0;
    renderList();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % filteredItems.length;
      updateActiveItem();
      SoundFX.playClick();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + filteredItems.length) % filteredItems.length;
      updateActiveItem();
      SoundFX.playClick();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      executeItem(filteredItems[activeIndex]);
    } else if (e.key === 'Escape') {
      closePalette();
    }
  });

  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) closePalette();
  });
  if (closeBtn) closeBtn.addEventListener('click', closePalette);

  // Global hotkey: Ctrl+K or Cmd+K
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (backdrop.classList.contains('open')) closePalette();
      else openPalette();
    }
  });

  window.openCommandPalette = openPalette;
  window.closeCommandPalette = closePalette;
}

/* ═══════════════════════════════════════════════════════════
   NAVBAR & FLOATING ACTION DOCK (Sound, Skin 3D, Admin)
═══════════════════════════════════════════════════════════ */
const DTM_SUPPORT_STATE = {
  zaloUrl: '',
  discordInviteUrl: 'https://discord.gg/AQyTJ8Wu7',
  ticketChannelUrl: 'https://discord.com/channels/1381315355317899314/1485265083918713063',
  ticketMode: 'modal',
  webhookConfigured: false,
};

function getSoundSvg(enabled) {
  if (enabled) {
    return `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>`;
  }
  return `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>`;
}

function updateSoundBtn() {
  const btn = document.getElementById('btn-sound-toggle');
  if (btn) {
    btn.innerHTML = SoundFX.enabled ? '🔊' : '🔇';
    btn.title = SoundFX.enabled ? 'Tắt âm thanh hiệu ứng' : 'Bật âm thanh hiệu ứng';
    btn.classList.toggle('active', SoundFX.enabled);
  }
  const dockIcon = document.getElementById('dtm-fab-sound-icon');
  const dockBtn = document.getElementById('dtm-fab-sound-btn');
  if (dockIcon && dockBtn) {
    const label = SoundFX.enabled ? 'Âm thanh: BẬT' : 'Âm thanh: TẮT';
    dockIcon.innerHTML = getSoundSvg(SoundFX.enabled);
    dockBtn.setAttribute('data-tooltip', label);
    dockBtn.setAttribute('title', label);
    dockBtn.setAttribute('aria-label', label);
    dockBtn.classList.toggle('active', SoundFX.enabled);
    dockBtn.classList.toggle('muted', !SoundFX.enabled);
  }
}

function initNavbarActions() {
  const nav = document.querySelector('nav');
  if (!nav) return;

  let group = nav.querySelector('.nav-actions-group');
  if (!group) {
    group = document.createElement('div');
    group.className = 'nav-actions-group';

    // Search / Cmd palette trigger
    const cmdBtn = document.createElement('button');
    cmdBtn.id = 'btn-cmd-palette';
    cmdBtn.className = 'nav-action-btn cmd-btn';
    cmdBtn.setAttribute('aria-label', 'Tìm kiếm nhanh (Ctrl+K)');
    cmdBtn.innerHTML = `<span>🔍</span><span class="nav-cmd-badge">Ctrl K</span>`;
    cmdBtn.addEventListener('click', () => {
      if (window.openCommandPalette) window.openCommandPalette();
    });
    group.appendChild(cmdBtn);

    // Append to nav (order is controlled by CSS flexbox)
    nav.appendChild(group);
  }
}

/* ═══════════════════════════════════════════════════════════
   GLOBAL SUPPORT NOTICE BAR (Light-red card above Footer)
═══════════════════════════════════════════════════════════ */
function initSupportNoticeBar() {
  if (document.getElementById('dtm-support-notice')) return;
  const footer = document.querySelector('footer');
  if (!footer || !footer.parentNode) return;

  const wrap = document.createElement('div');
  wrap.className = 'dtm-support-notice-wrap';

  const bar = document.createElement('div');
  bar.id = 'dtm-support-notice';
  bar.className = 'dtm-support-notice';
  bar.setAttribute('role', 'region');
  bar.setAttribute('aria-label', 'Lưu ý hỗ trợ');

  const badge = document.createElement('span');
  badge.className = 'dtm-notice-badge';
  badge.textContent = '⚠️ Lưu ý';

  const text = document.createElement('span');
  text.className = 'dtm-notice-text';

  const dcLink = document.createElement('a');
  dcLink.id = 'dtm-notice-discord-link';
  dcLink.className = 'dtm-notice-link dtm-notice-discord-link';
  dcLink.href = DTM_SUPPORT_STATE.ticketChannelUrl;
  dcLink.target = '_blank';
  dcLink.rel = 'noopener noreferrer';
  dcLink.textContent = 'kênh Discord';

  const zaloLink = document.createElement('a');
  zaloLink.id = 'dtm-notice-zalo-link';
  zaloLink.className = 'dtm-notice-link dtm-notice-zalo-link';
  zaloLink.href = DTM_SUPPORT_STATE.zaloUrl;
  zaloLink.target = '_blank';
  zaloLink.rel = 'noopener noreferrer';
  zaloLink.textContent = 'Zalo';

  text.append('Tạo ticket tại ', dcLink, ' hoặc gửi tin nhắn qua ', zaloLink, ' để được hỗ trợ.');
  bar.append(badge, text);
  wrap.appendChild(bar);

  footer.parentNode.insertBefore(wrap, footer);
  document.body.classList.add('dtm-has-notice');
}

/* ═══════════════════════════════════════════════════════════
   UNIFIED MODERN FOOTER (Consistent across all 9 pages)
═══════════════════════════════════════════════════════════ */
function initGlobalFooter() {
  const footer = document.querySelector('footer');
  if (!footer || footer.dataset.dtmEnhanced === '1') return;
  footer.dataset.dtmEnhanced = '1';

  footer.innerHTML = `
    <div class="footer-grid">
      <div class="footer-col">
        <div class="footer-brand-head">
          <img class="footer-brand-logo" src="/img/logo.jpg" alt="Đảo Tò Mò Logo">
          <div>
            <div class="footer-brand-title">Đảo Tò Mò</div>
            <div class="footer-brand-sub">Survival • Làng Rắn Mới</div>
          </div>
        </div>
        <p>Cộng đồng Minecraft sinh tồn nơi mọi người cùng khám phá, xây dựng, giao thương và giải trí mỗi ngày.</p>
        <div class="social-row">
          <a class="social-btn discord" href="/html/Discord.html" title="Tham gia Discord" aria-label="Discord">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.27 5.33C17.94 4.71 16.5 4.26 15 4a.09.09 0 0 0-.07.03c-.18.33-.39.76-.53 1.09a16.09 16.09 0 0 0-4.8 0c-.14-.34-.35-.76-.54-1.09-.01-.02-.04-.03-.07-.03-1.5.26-2.93.71-4.27 1.33-.01 0-.02.01-.03.02-2.72 4.07-3.47 8.03-3.1 11.95 0 .02.01.04.03.05 1.8 1.32 3.53 2.12 5.24 2.65.03.01.06 0 .07-.02.4-.55.76-1.13 1.07-1.74.02-.04 0-.08-.04-.09-.57-.22-1.11-.48-1.64-.78-.04-.02-.04-.08-.01-.11.11-.08.22-.17.33-.25.02-.02.05-.02.07-.01 3.44 1.57 7.15 1.57 10.55 0 .02-.01.05-.01.07.01.11.09.22.17.33.26.04.03.04.09-.01.11-.52.31-1.07.56-1.64.78-.04.01-.05.06-.04.09.32.61.68 1.19 1.07 1.74.03.01.06.02.09.01 1.72-.53 3.45-1.33 5.25-2.65.02-.01.03-.03.03-.05.44-4.53-.73-8.46-3.1-11.95-.01-.01-.02-.02-.04-.02ZM8.52 14.91c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.84 2.12-1.89 2.12Zm6.97 0c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.83 2.12-1.89 2.12Z"/></svg>
          </a>
          <a class="social-btn facebook" href="https://www.facebook.com/DragonKun1946" target="_blank" rel="noopener noreferrer" title="Facebook Admin" aria-label="Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5Z"/></svg>
          </a>
          <a class="social-btn zalo dtm-footer-zalo-link" href="${DTM_SUPPORT_STATE.zaloUrl}" target="_blank" rel="noopener noreferrer" title="Nhắn Zalo Admin" aria-label="Zalo">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 3C6.75 3 2.5 6.8 2.5 11.5c0 2.56 1.27 4.86 3.27 6.41-.14 1.3-.64 2.54-1.42 3.46-.18.22 0 .56.29.51 1.58-.25 3.12-.85 4.32-1.69.98.26 2.01.4 3.04.4 5.25 0 9.5-3.8 9.5-8.5S17.25 3 12 3Z" fill="currentColor"/>
              <text x="12" y="14.2" text-anchor="middle" fill="#07090f" font-family="Arial, Helvetica, sans-serif" font-weight="900" font-size="7.4" letter-spacing="-0.2">Zalo</text>
            </svg>
          </a>
        </div>
      </div>

      <div class="footer-col">
        <h3>Khám Phá</h3>
        <div class="footer-links">
          <a href="/index.html">Trang chủ</a>
          <a href="/html/Server.html">Thông tin Server</a>
          <a href="/html/Map.html">Bản đồ Đảo</a>
          <a href="/html/Store.html">Cửa hàng VIP</a>
        </div>
      </div>

      <div class="footer-col">
        <h3>Tiện Ích & Hỗ Trợ</h3>
        <div class="footer-links">
          <a href="/html/Checkin.html">Điểm danh hằng ngày</a>
          <a href="/html/Account.html">Tài khoản & Skin 3D</a>
          <a class="dtm-footer-ticket-link" href="${DTM_SUPPORT_STATE.ticketChannelUrl}" target="_blank" rel="noopener noreferrer">Tạo Ticket Discord</a>
          <a class="dtm-footer-zalo-link" href="${DTM_SUPPORT_STATE.zaloUrl}" target="_blank" rel="noopener noreferrer">Nhắn Zalo hỗ trợ</a>
        </div>
      </div>

      <div class="footer-col">
        <h3>Kết Nối Server</h3>
        <div class="footer-server-card">
          <div class="footer-server-status">
            <span class="footer-status-dot"></span>
            <span>ONLINE • JAVA 1.21.4</span>
          </div>
          <button type="button" class="footer-ip-btn" data-copy="play.daotomo.net" data-copied-label="<span>play.daotomo.net</span><span class=&quot;footer-ip-pill&quot;>✓ Đã chép!</span>" title="Bấm để sao chép IP server">
            <span>play.daotomo.net</span>
            <span class="footer-ip-pill">Sao chép</span>
          </button>
          <p class="footer-server-note">Hỗ trợ nhanh qua kênh Ticket Discord & Zalo Admin.</p>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>© 2026 Đảo Tò Mò. All rights reserved.</span>
      <span>Server Minecraft Sinh Tồn Việt Nam</span>
    </div>
  `;
}

/* ═══════════════════════════════════════════════════════════
   UNIFIED FLOATING ACTION WIDGET (Bottom-Right FAB)
   - Circular Popup Stack: Âm thanh, Skin 3D, Zalo, Ticket Discord, Admin
═══════════════════════════════════════════════════════════ */
function initFloatingActionWidget() {
  if (document.getElementById('dtm-fab-widget')) return;

  // Clean up any legacy left-pinned skin button
  const oldBtn = document.getElementById('floating-skin-btn');
  if (oldBtn) oldBtn.remove();

  const widget = document.createElement('div');
  widget.id = 'dtm-fab-widget';
  widget.className = 'dtm-fab-widget';
  widget.setAttribute('aria-label', 'Tiện ích Đảo Tò Mò');
  widget.style.cssText = 'position:fixed !important;bottom:22px !important;right:22px !important;z-index:99990 !important;width:52px !important;height:52px !important;display:block !important;pointer-events:none !important;margin:0 !important;padding:0 !important;transform:none !important;';

  const soundLabel = SoundFX.enabled ? 'Âm thanh: BẬT' : 'Âm thanh: TẮT';

  widget.innerHTML = `
    <!-- Action Menu Popover (Circular icon buttons stacked above trigger) -->
    <div class="dtm-fab-menu" id="dtm-fab-menu" role="menu">
      <!-- Sound Toggle -->
      <button class="dtm-fab-item sound-item ${SoundFX.enabled ? 'active' : 'muted'}" id="dtm-fab-sound-btn" type="button" data-tooltip="${soundLabel}" title="${soundLabel}" aria-label="${soundLabel}" role="menuitem">
        <span class="dtm-fab-item-icon" id="dtm-fab-sound-icon">${getSoundSvg(SoundFX.enabled)}</span>
      </button>

      <!-- 3D Skin Viewer -->
      <button class="dtm-fab-item skin-item" id="dtm-fab-skin-btn" type="button" data-tooltip="Mô hình Skin 3D" title="Mô hình Skin 3D" aria-label="Mô hình Skin 3D" role="menuitem">
        <span class="dtm-fab-item-icon">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
        </span>
      </button>

      <!-- Zalo Direct Support -->
      <a href="${DTM_SUPPORT_STATE.zaloUrl || '/html/Discord.html'}" target="_blank" rel="noopener noreferrer" class="dtm-fab-item zalo-item" id="dtm-fab-zalo-btn" data-tooltip="Chat Zalo Admin" title="Chat Zalo Admin" aria-label="Chat Zalo" role="menuitem">
        <span class="dtm-fab-item-icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none">
            <path d="M12 2C6.2 2 1.5 6.18 1.5 11.34c0 2.82 1.4 5.36 3.6 7.06-.16 1.44-.7 2.8-1.56 3.82-.2.24 0 .62.32.56 1.74-.28 3.44-.94 4.76-1.86 1.08.28 2.22.44 3.38.44 5.8 0 10.5-4.18 10.5-9.34S17.8 2 12 2Z" fill="#ffffff"/>
            <text x="12" y="14.2" text-anchor="middle" fill="#0068ff" font-family="Arial, Helvetica, sans-serif" font-weight="900" font-size="8.2" letter-spacing="-0.2">Zalo</text>
          </svg>
        </span>
      </a>

      <!-- Support Ticket Trigger (Modal) -->
      <button type="button" class="dtm-fab-item ticket-item" id="dtm-fab-ticket-btn" data-tooltip="Tạo Ticket Hỗ Trợ" title="Tạo Ticket Hỗ Trợ Trực Tuyến" aria-label="Tạo Ticket Hỗ Trợ" role="menuitem">
        <span class="dtm-fab-item-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z" />
            <path d="M10 6v2" stroke-dasharray="1 2" />
            <path d="M10 11v2" stroke-dasharray="1 2" />
            <path d="M10 16v2" stroke-dasharray="1 2" />
          </svg>
        </span>
      </button>

      <!-- Admin Dashboard (Strictly for user.role === 'admin') -->
      <a href="/html/Admin.html" class="dtm-fab-item admin-item" id="dtm-fab-admin-btn" style="display:none;" data-tooltip="Trang Quản Trị (Admin)" title="Trang Quản Trị Hệ Thống (Dành riêng cho Admin)" aria-label="Trang Quản Trị" role="menuitem">
        <span class="dtm-fab-item-icon">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </span>
      </a>
    </div>

    <!-- Main Floating Trigger Button (Squircle matching user design) -->
    <button class="dtm-fab-trigger" id="dtm-fab-trigger" type="button" aria-expanded="false" aria-label="Mở tiện ích nhanh" title="Menu Tiện ích Đảo Tò Mò">
      <span class="dtm-fab-icon dtm-fab-icon-main">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#fbbf24" stroke="#d97706" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M13 2L3 14h8l-2 8 11-12h-8l3-8z"/>
        </svg>
      </span>
      <span class="dtm-fab-icon dtm-fab-icon-close">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </span>
      <span class="dtm-fab-pulse" title="Hệ thống Online"></span>
    </button>
  `;

  document.body.appendChild(widget);

  const trigger = document.getElementById('dtm-fab-trigger');
  const soundBtn = document.getElementById('dtm-fab-sound-btn');
  const skinBtn = document.getElementById('dtm-fab-skin-btn');
  const zaloBtn = document.getElementById('dtm-fab-zalo-btn');
  const ticketBtn = document.getElementById('dtm-fab-ticket-btn');
  const adminBtn = document.getElementById('dtm-fab-admin-btn');

  function toggleMenu(forceOpen = null) {
    const isOpen = forceOpen !== null ? forceOpen : !widget.classList.contains('open');
    widget.classList.toggle('open', isOpen);
    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    SoundFX.playClick();
    toggleMenu();
  });

  // Sound toggle button click
  soundBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const state = SoundFX.toggle();
    updateSoundBtn();
    showToast(state ? '🔊 Âm thanh giao diện đã BẬT' : '🔇 Âm thanh giao diện đã TẮT', 'info');
  });

  // Skin 3D button click
  skinBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    SoundFX.playClick();
    toggleMenu(false);
    if (typeof window.openSkinModal === 'function') {
      window.openSkinModal();
    } else {
      window.location.href = '/html/Account.html';
    }
  });

  // Zalo link click
  if (zaloBtn) {
    zaloBtn.addEventListener('click', () => {
      SoundFX.playClick();
      toggleMenu(false);
    });
  }

  // Support Ticket click: always opens web ticket modal directly
  if (ticketBtn) {
    ticketBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      SoundFX.playClick();
      toggleMenu(false);
      if (typeof window.openSupportTicketModal === 'function') {
        window.openSupportTicketModal();
      }
    });
  }

  // Close when clicking outside or pressing Escape
  document.addEventListener('click', (e) => {
    if (!widget.contains(e.target) && widget.classList.contains('open')) {
      toggleMenu(false);
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && widget.classList.contains('open')) {
      toggleMenu(false);
    }
  });

  // Admin button visibility check
  function updateAdminVisibility() {
    const u = api.getUser();
    const isAdmin = api.isLoggedIn() && u && (u.role === 'admin' || u.is_admin || u.role === 'ADMIN');
    if (adminBtn) {
      adminBtn.style.display = isAdmin ? 'flex' : 'none';
    }
  }

  updateAdminVisibility();

  // Sync public support config (Zalo link, Discord ticket channel URL & mode)
  api.getSupportConfig().then((cfg) => {
    if (!cfg || !cfg.success) return;
    if (cfg.zaloUrl) {
      DTM_SUPPORT_STATE.zaloUrl = cfg.zaloUrl;
      if (zaloBtn) zaloBtn.href = cfg.zaloUrl;
      const modalZalo = document.getElementById('dtm-ticket-quick-zalo');
      if (modalZalo) modalZalo.href = cfg.zaloUrl;
      document.querySelectorAll('.dtm-notice-zalo-link, .dtm-footer-zalo-link').forEach((el) => { el.href = cfg.zaloUrl; });
    }
    if (cfg.discordInviteUrl) {
      DTM_SUPPORT_STATE.discordInviteUrl = cfg.discordInviteUrl;
    }
    if (cfg.ticketChannelUrl) {
      DTM_SUPPORT_STATE.ticketChannelUrl = cfg.ticketChannelUrl;
      if (ticketBtn) ticketBtn.href = cfg.ticketChannelUrl;
      const modalDiscord = document.getElementById('dtm-ticket-quick-discord');
      if (modalDiscord) modalDiscord.href = cfg.ticketChannelUrl;
      document.querySelectorAll('.dtm-notice-discord-link, .dtm-footer-ticket-link').forEach((el) => { el.href = cfg.ticketChannelUrl; });
    }
    if (cfg.ticketMode === 'direct_channel' || cfg.ticketMode === 'modal') {
      DTM_SUPPORT_STATE.ticketMode = cfg.ticketMode;
    }
    DTM_SUPPORT_STATE.webhookConfigured = !!cfg.webhookConfigured;
  }).catch(() => {});

  // If logged in, also verify role via /auth/me to stay strictly synced
  if (api.isLoggedIn()) {
    api.getMe().then(({ user }) => {
      if (user) {
        api.setUser(user);
        updateAdminVisibility();
      }
    }).catch(() => {});
  }

  // React to login/logout/profile update events
  window.addEventListener('storage', updateAdminVisibility);
  window.addEventListener('daotomo:auth-changed', updateAdminVisibility);
}

/* ═══════════════════════════════════════════════════════════
   SUPPORT TICKET MODAL (Submit problem -> Backend & Discord)
═══════════════════════════════════════════════════════════ */
function initSupportTicketModal() {
  if (document.getElementById('dtm-ticket-backdrop')) return;

  const backdrop = document.createElement('div');
  backdrop.id = 'dtm-ticket-backdrop';
  backdrop.className = 'dtm-ticket-backdrop';
  backdrop.innerHTML = `
    <div class="dtm-ticket-card" role="dialog" aria-modal="true" aria-labelledby="dtm-ticket-title">
      <div class="dtm-ticket-header">
        <div>
          <h2 id="dtm-ticket-title">🎫 Hỗ Trợ & Ticket Đảo Tò Mò</h2>
          <p>Tạo ticket hỗ trợ mới hoặc tra cứu lời đáp từ Ban Quản Trị.</p>
        </div>
        <button type="button" class="dtm-ticket-close" id="dtm-ticket-close-btn" aria-label="Đóng">✕</button>
      </div>

      <!-- Navigation Tabs -->
      <div class="dtm-ticket-tabs" style="display:flex;gap:8px;margin-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:10px;">
        <button type="button" class="dtm-tk-tab-btn active" id="dtm-tk-tab-create" style="flex:1;padding:7px 12px;background:rgba(245,158,11,0.18);border:1px solid #f59e0b;border-radius:8px;color:#fde68a;font-weight:700;font-size:0.8rem;cursor:pointer;">
          ➕ Tạo Ticket Mới
        </button>
        <button type="button" class="dtm-tk-tab-btn" id="dtm-tk-tab-track" style="flex:1;padding:7px 12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.15);border-radius:8px;color:#94a3b8;font-weight:700;font-size:0.8rem;cursor:pointer;">
          📋 Lời Đáp BQT & Tra Cứu
        </button>
      </div>

      <div id="dtm-ticket-form-wrap">
        <div class="dtm-ticket-field">
          <label for="dtm-tk-category">Chủ đề cần hỗ trợ *</label>
          <select id="dtm-tk-category" class="dtm-ticket-select">
            <option value="payment">💳 Nạp Xu / Đơn Hàng / VietQR</option>
            <option value="bug">🐛 Báo Lỗi Game / Website</option>
            <option value="report">⚖️ Tố Cáo Vi Phạm / Hack / Phá Hoại</option>
            <option value="account">🔐 Tài Khoản / Liên Kết / Quên Mật Khẩu</option>
            <option value="other">💬 Thắc Mắc Khác / Góp Ý</option>
          </select>
        </div>

        <div class="dtm-ticket-grid-2">
          <div class="dtm-ticket-field">
            <label for="dtm-tk-mc">Tên Minecraft (IGN)</label>
            <input type="text" id="dtm-tk-mc" class="dtm-ticket-input" placeholder="VD: SteveVN (nếu có)">
          </div>
          <div class="dtm-ticket-field">
            <label for="dtm-tk-discord">Thông tin liên hệ (Discord / Zalo / SĐT)</label>
            <input type="text" id="dtm-tk-discord" class="dtm-ticket-input" placeholder="VD: @tenban hoặc SĐT">
          </div>
        </div>

        <div class="dtm-ticket-field">
          <label for="dtm-tk-subject">Tiêu đề vấn đề cần xử lý *</label>
          <input type="text" id="dtm-tk-subject" class="dtm-ticket-input" placeholder="Tóm tắt ngắn gọn vấn đề (VD: Đã chuyển khoản chưa nhận rank)">
        </div>

        <div class="dtm-ticket-field">
          <label for="dtm-tk-message">Nội dung chi tiết vấn đề *</label>
          <textarea id="dtm-tk-message" class="dtm-ticket-textarea" placeholder="Mô tả chi tiết sự cố cần xử lý, thời gian xảy ra, mã đơn hàng (#DTM-...) hoặc bằng chứng liên quan..."></textarea>
        </div>

        <div id="dtm-tk-error" style="margin-bottom:0.75rem;"></div>

        <button type="button" id="dtm-tk-submit-btn" class="btn btn-primary btn-full" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 6px 20px rgba(245,158,11,0.35);font-weight:700;">
          📨 Gửi Ticket Hỗ Trợ Ngay
        </button>

        <!-- Lưu ý thời gian xử lý & đề xuất Discord -->
        <div class="dtm-ticket-notice">
          <div class="dtm-ticket-notice-header">
            <span class="dtm-ticket-notice-badge">⚡ Lưu ý hỗ trợ:</span>
            <span class="dtm-ticket-notice-time">Web: 3h – 5h</span>
          </div>
          <p class="dtm-ticket-notice-text">
            Tham gia Discord tạo ticket để được hỗ trợ nhanh hơn, các ticket tại web sẽ được hỗ trợ sau <strong>3h – 5h</strong>.
          </p>
          <a id="dtm-ticket-discord-cta" class="dtm-ticket-discord-cta" href="${DTM_SUPPORT_STATE.discordInviteUrl || 'https://discord.gg/AQyTJ8Wu7'}" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M19.27 5.33C17.94 4.71 16.5 4.26 15 4a.09.09 0 0 0-.07.03c-.18.33-.39.76-.53 1.09a16.09 16.09 0 0 0-4.8 0c-.14-.34-.35-.76-.54-1.09-.01-.02-.04-.03-.07-.03-1.5.26-2.93.71-4.27 1.33-.01 0-.02.01-.03.02-2.72 4.07-3.47 8.03-3.1 11.95 0 .02.01.04.03.05 1.8 1.32 3.53 2.12 5.24 2.65.03.01.06 0 .07-.02.4-.55.76-1.13 1.07-1.74.02-.04 0-.08-.04-.09-.57-.22-1.11-.48-1.64-.78-.04-.02-.04-.08-.01-.11.11-.08.22-.17.33-.25.02-.02.05-.02.07-.01 3.44 1.57 7.15 1.57 10.55 0 .02-.01.05-.01.07.01.11.09.22.17.33.26.04.03.04.09-.01.11-.52.31-1.07.56-1.64.78-.04.01-.05.06-.04.09.32.61.68 1.19 1.07 1.74.03.01.06.02.09.01 1.72-.53 3.45-1.33 5.25-2.65.02-.01.03-.03.03-.05.44-4.53-.73-8.46-3.1-11.95-.01-.01-.02-.02-.04-.02ZM8.52 14.91c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.84 2.12-1.89 2.12Zm6.97 0c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.83 2.12-1.89 2.12Z"/>
            </svg>
            <span>Tham gia Discord Tạo Ticket (Hỗ Trợ Nhanh) ↗</span>
          </a>
        </div>
      </div>

      <!-- Ticket History & BQT Reply Lookup Tab -->
      <div id="dtm-ticket-track-wrap" style="display:none;">
        <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
          <input type="text" id="dtm-track-code-input" class="dtm-ticket-input" placeholder="Nhập mã #TK-XXXXXX tra cứu..." style="flex:1;font-family:var(--font-mono);text-transform:uppercase;">
          <button type="button" id="dtm-track-search-btn" class="btn btn-primary" style="padding:0.45rem 0.85rem;white-space:nowrap;font-size:0.8rem;background:linear-gradient(135deg,#38bdf8,#0284c7);">🔍 Tra Cứu</button>
        </div>
        <div id="dtm-track-result-msg" style="margin-bottom:0.6rem;"></div>
        <div id="dtm-track-tickets-list" style="max-height:310px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:2px;">
          <div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.82rem;">
            Đang tải dữ liệu...
          </div>
        </div>
      </div>

      <div id="dtm-ticket-success-wrap" style="display:none;text-align:center;padding:0.5rem 0;">
        <div style="font-size:2.6rem;margin-bottom:0.4rem;">🎫</div>
        <h3 style="color:#fff;font-family:var(--font-display);font-size:1.2rem;margin-bottom:0.35rem;">Đã Gửi Ticket Thành Công!</h3>
        <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.4rem 0.9rem;border-radius:9999px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.45);color:#fde68a;font-family:var(--font-mono);font-weight:800;margin:0.5rem 0;">
          <span id="dtm-tk-res-code">#TK-000000</span>
          <button type="button" id="dtm-tk-copy-code" style="background:none;border:none;color:#38bdf8;cursor:pointer;font-size:0.8rem;font-weight:700;">📋 Chép</button>
        </div>
        <p id="dtm-tk-res-desc" style="font-size:0.86rem;color:#cbd5e1;line-height:1.55;margin:0.6rem 0 1rem;"></p>

        <!-- Lưu ý cả trên màn hình kết quả -->
        <div class="dtm-ticket-notice" style="margin-bottom:1.1rem;text-align:left;">
          <div class="dtm-ticket-notice-header">
            <span class="dtm-ticket-notice-badge">⚡ Nhắc nhở thời gian:</span>
            <span class="dtm-ticket-notice-time">Web: 3h – 5h</span>
          </div>
          <p class="dtm-ticket-notice-text">
            Các ticket gửi tại web sẽ được hỗ trợ sau <strong>3h – 5h</strong>. Nếu cần hỗ trợ khẩn cấp hơn, hãy tham gia Discord tạo ticket để BQT phản hồi ngay lập tức!
          </p>
          <a class="dtm-ticket-discord-cta" href="${DTM_SUPPORT_STATE.discordInviteUrl || 'https://discord.gg/AQyTJ8Wu7'}" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M19.27 5.33C17.94 4.71 16.5 4.26 15 4a.09.09 0 0 0-.07.03c-.18.33-.39.76-.53 1.09a16.09 16.09 0 0 0-4.8 0c-.14-.34-.35-.76-.54-1.09-.01-.02-.04-.03-.07-.03-1.5.26-2.93.71-4.27 1.33-.01 0-.02.01-.03.02-2.72 4.07-3.47 8.03-3.1 11.95 0 .02.01.04.03.05 1.8 1.32 3.53 2.12 5.24 2.65.03.01.06 0 .07-.02.4-.55.76-1.13 1.07-1.74.02-.04 0-.08-.04-.09-.57-.22-1.11-.48-1.64-.78-.04-.02-.04-.08-.01-.11.11-.08.22-.17.33-.25.02-.02.05-.02.07-.01 3.44 1.57 7.15 1.57 10.55 0 .02-.01.05-.01.07.01.11.09.22.17.33.26.04.03.04.09-.01.11-.52.31-1.07.56-1.64.78-.04.01-.05.06-.04.09.32.61.68 1.19 1.07 1.74.03.01.06.02.09.01 1.72-.53 3.45-1.33 5.25-2.65.02-.01.03-.03.03-.05.44-4.53-.73-8.46-3.1-11.95-.01-.01-.02-.02-.04-.02ZM8.52 14.91c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.84 2.12-1.89 2.12Zm6.97 0c-1.03 0-1.89-.95-1.89-2.12s.84-2.12 1.89-2.12c1.06 0 1.9.96 1.89 2.12 0 1.17-.83 2.12-1.89 2.12Z"/>
            </svg>
            <span>Vào Discord Nhận Hỗ Trợ Nhanh ↗</span>
          </a>
        </div>

        <div style="display:flex;gap:0.65rem;justify-content:center;flex-wrap:wrap;">
          <button type="button" id="dtm-tk-new-btn" class="btn btn-ghost" style="font-size:0.84rem;">➕ Gửi Vấn Đề Khác</button>
          <button type="button" id="dtm-tk-done-btn" class="btn btn-primary" style="font-size:0.84rem;">Đóng Lại</button>
        </div>
      </div>

      <div class="dtm-ticket-quick-links">
        <a id="dtm-ticket-quick-discord" class="dtm-ticket-quick-btn discord" href="${DTM_SUPPORT_STATE.discordInviteUrl}" target="_blank" rel="noopener noreferrer">
          💬 Tham Gia Máy Chủ Discord
        </a>
        <a id="dtm-ticket-quick-zalo" class="dtm-ticket-quick-btn zalo" href="${DTM_SUPPORT_STATE.zaloUrl}" target="_blank" rel="noopener noreferrer">
          📱 Nhắn Zalo Admin
        </a>
      </div>
    </div>
  `;

  document.body.appendChild(backdrop);

  const closeBtn = document.getElementById('dtm-ticket-close-btn');
  const formWrap = document.getElementById('dtm-ticket-form-wrap');
  const trackWrap = document.getElementById('dtm-ticket-track-wrap');
  const successWrap = document.getElementById('dtm-ticket-success-wrap');
  const submitBtn = document.getElementById('dtm-tk-submit-btn');
  const errBox = document.getElementById('dtm-tk-error');
  const tabCreate = document.getElementById('dtm-tk-tab-create');
  const tabTrack = document.getElementById('dtm-tk-tab-track');
  const trackList = document.getElementById('dtm-track-tickets-list');
  const trackInput = document.getElementById('dtm-track-code-input');
  const trackSearchBtn = document.getElementById('dtm-track-search-btn');
  const trackMsg = document.getElementById('dtm-track-result-msg');

  function escapeTkHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  const statusBadges = {
    open: '<span style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);padding:2px 7px;border-radius:4px;font-size:0.72rem;font-weight:700">🟢 Mới gửi</span>',
    in_progress: '<span style="background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);padding:2px 7px;border-radius:4px;font-size:0.72rem;font-weight:700">🟡 Đang xử lý</span>',
    resolved: '<span style="background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);padding:2px 7px;border-radius:4px;font-size:0.72rem;font-weight:700">✅ Đã giải quyết</span>',
    closed: '<span style="background:rgba(148,163,184,0.15);color:#94a3b8;border:1px solid rgba(148,163,184,0.3);padding:2px 7px;border-radius:4px;font-size:0.72rem;font-weight:700">🔒 Đã đóng</span>',
  };

  function renderSingleTicketCard(tk) {
    const badge = statusBadges[tk.status] || `<span style="font-size:0.72rem;color:#cbd5e1">${escapeTkHtml(tk.status)}</span>`;
    const replyHtml = tk.admin_note
      ? `<div style="margin-top:8px;padding:8px 10px;background:rgba(56,189,248,0.12);border-left:3px solid #38bdf8;border-radius:4px;font-size:0.78rem;color:#f0f9ff;line-height:1.45;">
           <strong style="color:#38bdf8;display:flex;align-items:center;gap:4px;">💬 Phản hồi của BQT:</strong>
           <div style="margin-top:3px;white-space:pre-wrap;word-break:break-word">${escapeTkHtml(tk.admin_note)}</div>
         </div>`
      : `<div style="margin-top:6px;font-size:0.73rem;color:#94a3b8;font-style:italic">⏳ Đang chờ BQT tiếp nhận và phản hồi (thời gian dự kiến 3h–5h)</div>`;

    return `
      <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:10px 12px;text-align:left;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:4px">
          <span style="font-family:var(--font-mono);font-weight:800;color:#f59e0b;font-size:0.82rem">#${escapeTkHtml(tk.ticket_code)}</span>
          ${badge}
        </div>
        <div style="font-weight:700;color:#fff;font-size:0.84rem;margin-bottom:2px">${escapeTkHtml(tk.subject)}</div>
        <div style="font-size:0.77rem;color:var(--text-muted);white-space:pre-wrap;word-break:break-word">${escapeTkHtml(tk.message)}</div>
        ${replyHtml}
        <div style="font-size:0.7rem;color:#64748b;margin-top:6px;text-align:right">${new Date(tk.created_at).toLocaleString('vi-VN')}</div>
      </div>
    `;
  }

  async function loadUserTickets() {
    trackMsg.innerHTML = '';
    trackList.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.82rem;"><div class="spinner"></div> Đang tải danh sách ticket...</div>';
    try {
      if (api.isLoggedIn()) {
        const res = await api.getMyTickets();
        const tickets = res.data || res.tickets || [];
        if (!tickets.length) {
          trackList.innerHTML = `
            <div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.82rem;">
              Bạn chưa gửi ticket nào.<br>
              <button type="button" class="btn btn-ghost" style="margin-top:8px;font-size:0.75rem;" onclick="document.getElementById('dtm-tk-tab-create').click()">➕ Gửi ticket đầu tiên</button>
            </div>
          `;
          return;
        }
        trackList.innerHTML = tickets.map(renderSingleTicketCard).join('');
      } else {
        trackList.innerHTML = `
          <div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.82rem;">
            💡 Nhập mã ticket ở trên để tra cứu phản hồi, hoặc <a href="/html/Account.html" style="color:var(--primary);font-weight:700">Đăng nhập</a> để xem tất cả ticket của bạn.
          </div>
        `;
      }
    } catch (e) {
      trackList.innerHTML = `<div style="text-align:center;padding:1rem;color:#f87171;font-size:0.8rem">❌ ${escapeTkHtml(e.message)}</div>`;
    }
  }

  function switchModalTab(tab) {
    if (tab === 'create') {
      tabCreate.style.background = 'rgba(245,158,11,0.18)';
      tabCreate.style.borderColor = '#f59e0b';
      tabCreate.style.color = '#fde68a';
      tabTrack.style.background = 'rgba(255,255,255,0.04)';
      tabTrack.style.borderColor = 'rgba(255,255,255,0.15)';
      tabTrack.style.color = '#94a3b8';
      formWrap.style.display = 'block';
      trackWrap.style.display = 'none';
      successWrap.style.display = 'none';
    } else {
      tabTrack.style.background = 'rgba(56,189,248,0.18)';
      tabTrack.style.borderColor = '#38bdf8';
      tabTrack.style.color = '#7dd3fc';
      tabCreate.style.background = 'rgba(255,255,255,0.04)';
      tabCreate.style.borderColor = 'rgba(255,255,255,0.15)';
      tabCreate.style.color = '#94a3b8';
      formWrap.style.display = 'none';
      trackWrap.style.display = 'block';
      successWrap.style.display = 'none';
      loadUserTickets();
    }
    SoundFX.playClick();
  }

  tabCreate?.addEventListener('click', () => switchModalTab('create'));
  tabTrack?.addEventListener('click', () => switchModalTab('track'));

  trackSearchBtn?.addEventListener('click', async () => {
    const code = trackInput.value.trim();
    if (!code) {
      trackMsg.innerHTML = '<span style="color:#f87171;font-size:0.78rem">⚠️ Vui lòng nhập mã ticket.</span>';
      return;
    }
    trackMsg.innerHTML = '<span style="color:var(--lagoon);font-size:0.78rem">⏳ Đang tra cứu ticket...</span>';
    try {
      const res = await api.lookupTicket(code);
      const tk = res.ticket || res.data;
      trackMsg.innerHTML = `<span style="color:#34d399;font-size:0.78rem">✅ Đã tìm thấy Ticket #${escapeTkHtml(tk.ticket_code)}!</span>`;
      trackList.innerHTML = renderSingleTicketCard(tk);
      SoundFX.playSuccess();
    } catch (e) {
      SoundFX.playError();
      trackMsg.innerHTML = `<span style="color:#f87171;font-size:0.78rem">❌ ${escapeTkHtml(e.message)}</span>`;
    }
  });

  trackInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') trackSearchBtn.click();
  });

  function openModal(prefillCategory = null) {
    const user = api.getUser();
    const mcEl = document.getElementById('dtm-tk-mc');
    const dcEl = document.getElementById('dtm-tk-discord');
    const catEl = document.getElementById('dtm-tk-category');

    if (user) {
      if (mcEl && !mcEl.value) mcEl.value = user.minecraftUsername || user.minecraft_username || '';
      if (dcEl && !dcEl.value) dcEl.value = user.discordUsername || user.discord_username || '';
    }
    if (prefillCategory && catEl) catEl.value = prefillCategory;

    errBox.innerHTML = '';
    switchModalTab('create');
    backdrop.classList.add('open');
    SoundFX.playPing();
    setTimeout(() => document.getElementById('dtm-tk-subject')?.focus(), 80);
  }

  function closeModal() {
    backdrop.classList.remove('open');
  }

  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) closeModal();
  });
  closeBtn?.addEventListener('click', closeModal);
  document.getElementById('dtm-tk-done-btn')?.addEventListener('click', closeModal);
  document.getElementById('dtm-tk-new-btn')?.addEventListener('click', () => {
    document.getElementById('dtm-tk-subject').value = '';
    document.getElementById('dtm-tk-message').value = '';
    openModal();
  });

  document.getElementById('dtm-tk-copy-code')?.addEventListener('click', () => {
    const code = document.getElementById('dtm-tk-res-code')?.textContent || '';
    navigator.clipboard.writeText(code).then(() => {
      SoundFX.playSuccess();
      showToast('Đã sao chép mã ticket ' + code, 'success');
    }).catch(() => {});
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && backdrop.classList.contains('open')) {
      closeModal();
    }
  });

  submitBtn?.addEventListener('click', async () => {
    const category = document.getElementById('dtm-tk-category').value;
    const minecraftUsername = document.getElementById('dtm-tk-mc').value.trim();
    const discordUsername = document.getElementById('dtm-tk-discord').value.trim();
    const subject = document.getElementById('dtm-tk-subject').value.trim();
    const message = document.getElementById('dtm-tk-message').value.trim();

    errBox.innerHTML = '';
    if (subject.length < 4) {
      errBox.innerHTML = '<div class="alert alert-error" style="font-size:0.82rem;margin:0;">⚠️ Tiêu đề hỗ trợ phải có ít nhất 4 ký tự.</div>';
      SoundFX.playError();
      return;
    }
    if (message.length < 10) {
      errBox.innerHTML = '<div class="alert alert-error" style="font-size:0.82rem;margin:0;">⚠️ Vui lòng mô tả chi tiết sự cố (ít nhất 10 ký tự).</div>';
      SoundFX.playError();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Đang gửi Ticket...';

    try {
      const res = await api.createSupportTicket({
        category,
        minecraft_username: minecraftUsername,
        discord_username: discordUsername,
        subject,
        message,
      });

      const code = res.ticket_code || res.data?.ticket_code || 'TK-NEW';
      document.getElementById('dtm-tk-res-code').textContent = '#' + code;
      document.getElementById('dtm-tk-res-desc').textContent = res.discord_dispatched
        ? 'Thông tin Ticket của bạn đã được ghi nhận và đẩy vào hệ thống BQT! Các yêu cầu gửi qua web sẽ được hỗ trợ xử lý sau 3h – 5h. Bạn cũng có thể vào Discord tạo ticket để được BQT hỗ trợ nhanh nhất.'
        : 'Ticket của bạn đã được ghi nhận trên hệ thống! Đội ngũ BQT sẽ tiếp nhận và xử lý sau 3h – 5h. Bạn có thể tham gia Discord để được hỗ trợ ngay lập tức.';

      formWrap.style.display = 'none';
      successWrap.style.display = 'block';
      document.getElementById('dtm-tk-subject').value = '';
      document.getElementById('dtm-tk-message').value = '';

      SoundFX.playSuccess();
      ConfettiFX.fire(window.innerWidth / 2, window.innerHeight * 0.45, 50);
      showToast(res.message || 'Gửi Ticket thành công!', 'success');
    } catch (e) {
      SoundFX.playError();
      errBox.innerHTML = `<div class="alert alert-error" style="font-size:0.82rem;margin:0;">❌ ${e.message}</div>`;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = '📨 Gửi Ticket Hỗ Trợ Ngay';
    }
  });

  window.openSupportTicketModal = openModal;
  window.closeSupportTicketModal = closeModal;
}

/* ═══════════════════════════════════════════════════════════
   AMBIENT HERO PARTICLES (Floating Minecraft embers canvas)
═══════════════════════════════════════════════════════════ */
function initHeroParticles() {
  const hero = document.querySelector('.hero, .discord-hero, .checkin-hero, .map-hero, .server-hero');
  if (!hero || hero.querySelector('.hero-particles-canvas')) return;

  const canvas = document.createElement('canvas');
  canvas.className = 'hero-particles-canvas';
  canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;opacity:0.55;';
  hero.style.position = 'relative';
  hero.insertBefore(canvas, hero.firstChild);

  const ctx = canvas.getContext('2d');
  let width = (canvas.width = hero.offsetWidth);
  let height = (canvas.height = hero.offsetHeight);

  window.addEventListener('resize', () => {
    if (!hero) return;
    width = canvas.width = hero.offsetWidth;
    height = canvas.height = hero.offsetHeight;
  });

  const particles = [];
  const pCount = Math.min(32, Math.floor(width / 38));
  const colors = ['rgba(124, 58, 237, ', 'rgba(34, 211, 238, ', 'rgba(245, 158, 11, '];

  for (let i = 0; i < pCount; i++) {
    particles.push({
      x: Math.random() * width,
      y: Math.random() * height,
      size: Math.random() * 4 + 2,
      color: colors[Math.floor(Math.random() * colors.length)],
      alpha: Math.random() * 0.45 + 0.2,
      vy: -(Math.random() * 0.5 + 0.2),
      vx: (Math.random() - 0.5) * 0.35
    });
  }

  function render() {
    ctx.clearRect(0, 0, width, height);
    for (const p of particles) {
      p.y += p.vy;
      p.x += p.vx;
      if (p.y < -10) { p.y = height + 10; p.x = Math.random() * width; }
      if (p.x < -10) p.x = width + 10;
      if (p.x > width + 10) p.x = -10;

      ctx.fillStyle = p.color + p.alpha + ')';
      ctx.fillRect(p.x, p.y, p.size, p.size);
    }
    requestAnimationFrame(render);
  }
  render();
}

/* ═══════════════════════════════════════════════════════════
   3D TILT EFFECT ON CARDS
═══════════════════════════════════════════════════════════ */
function init3DTilt() {
  const cards = document.querySelectorAll('.tilt-card, .product-card, .stat-card-discord, .pillar-card, .spec-card');
  cards.forEach(card => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const midX = rect.width / 2;
      const midY = rect.height / 2;
      const rotateX = ((y - midY) / midY) * -5;
      const rotateY = ((x - midX) / midX) * 5;
      card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-4px)`;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });
}

/* ── Init navbar & utilities on every page ── */
document.addEventListener('DOMContentLoaded', () => {
  const btn  = document.getElementById('hamburger');
  const list = document.getElementById('nav-list');
  if (btn && list) {
    btn.addEventListener('click', () => {
      list.classList.toggle('open');
      btn.classList.toggle('open');
      SoundFX.playClick();
    });
    list.querySelectorAll('a').forEach(a =>
      a.addEventListener('click', () => {
        list.classList.remove('open');
        btn.classList.remove('open');
        SoundFX.playClick();
      })
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

  /* ── Unify modern footer before binding [data-copy] handlers ── */
  initGlobalFooter();

  /* ── Copy-to-clipboard: <button data-copy="play.daotomo.net"> ── */
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const val = btn.dataset.copy;
      const original = btn.innerHTML;
      try {
        await navigator.clipboard.writeText(val);
        btn.classList.add('copied');
        btn.dataset.copiedLabel && (btn.innerHTML = btn.dataset.copiedLabel);
        SoundFX.playSuccess();
        const rect = btn.getBoundingClientRect();
        ConfettiFX.fire(rect.left + rect.width / 2, rect.top + rect.height / 2, 45);
        showToast('Đã sao chép: ' + val, 'success');
        setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = original; }, 1600);
      } catch {
        /* clipboard unavailable — silently ignore */
      }
    });
  });

  // Sound triggers on standard buttons
  document.querySelectorAll('.btn, .join-btn, .carousel-nav-btn, .carousel-dot').forEach(btn => {
    btn.addEventListener('click', () => SoundFX.playClick());
  });

  /* ── Initialize engines ── */
  initNavbarActions();
  initSupportNoticeBar();
  initFloatingActionWidget();
  initSupportTicketModal();
  initCommandPalette();
  initHeroParticles();
  init3DTilt();
  initCarousels();
});

/* ═══════════════════════════════════════════════════════════
   Vanilla Carousel Engine (Touch swipe, indicators, auto-play)
═══════════════════════════════════════════════════════════ */
function initCarousels(root = document) {
  const containers = root.querySelectorAll('.carousel-container');
  containers.forEach(container => {
    if (container._carouselInit) return;
    const track = container.querySelector('.carousel-track');
    if (!track) return;
    const slides = Array.from(track.querySelectorAll('.carousel-slide'));
    const total = slides.length;
    if (total <= 1) return;

    container._carouselInit = true;
    let current = 0;
    let timer = null;
    const duration = parseInt(container.dataset.interval, 10) || 5000;

    // Controls container
    let dotsWrap = container.querySelector('.carousel-dots');
    if (!dotsWrap) {
      dotsWrap = document.createElement('div');
      dotsWrap.className = 'carousel-dots';
      container.appendChild(dotsWrap);
    }
    dotsWrap.innerHTML = '';
    const dots = [];
    for (let i = 0; i < total; i++) {
      const dot = document.createElement('button');
      dot.className = `carousel-dot ${i === 0 ? 'active' : ''}`;
      dot.setAttribute('aria-label', `Chuyển tới slide ${i + 1}`);
      dot.addEventListener('click', (e) => {
        e.stopPropagation();
        SoundFX.playClick();
        goTo(i);
        restartAutoplay();
      });
      dotsWrap.appendChild(dot);
      dots.push(dot);
    }

    // Prev / Next buttons
    let prevBtn = container.querySelector('.carousel-prev, .carousel-nav-btn.prev, button.prev');
    let nextBtn = container.querySelector('.carousel-next, .carousel-nav-btn.next, button.next');
    if (!prevBtn) {
      prevBtn = document.createElement('button');
      prevBtn.className = 'carousel-nav-btn carousel-prev';
      prevBtn.setAttribute('aria-label', 'Slide trước');
      prevBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>`;
      container.appendChild(prevBtn);
    } else {
      prevBtn.classList.add('carousel-nav-btn', 'carousel-prev');
    }
    if (!nextBtn) {
      nextBtn = document.createElement('button');
      nextBtn.className = 'carousel-nav-btn carousel-next';
      nextBtn.setAttribute('aria-label', 'Slide tiếp theo');
      nextBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>`;
      container.appendChild(nextBtn);
    } else {
      nextBtn.classList.add('carousel-nav-btn', 'carousel-next');
    }

    prevBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      SoundFX.playClick();
      goTo(current - 1);
      restartAutoplay();
    });
    nextBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      SoundFX.playClick();
      goTo(current + 1);
      restartAutoplay();
    });

    // Progress bar
    let progress = container.querySelector('.carousel-progress');
    if (!progress) {
      progress = document.createElement('div');
      progress.className = 'carousel-progress';
      container.appendChild(progress);
    }

    function animateProgress() {
      if (!progress) return;
      progress.style.transition = 'none';
      progress.style.width = '0%';
      requestAnimationFrame(() => {
        progress.style.transition = `width ${duration}ms linear`;
        progress.style.width = '100%';
      });
    }

    function goTo(idx) {
      if (idx < 0) current = total - 1;
      else if (idx >= total) current = 0;
      else current = idx;

      track.style.transform = `translateX(-${current * 100}%)`;
      dots.forEach((d, i) => d.classList.toggle('active', i === current));
      animateProgress();
    }

    function startAutoplay() {
      stopAutoplay();
      animateProgress();
      timer = setInterval(() => {
        goTo(current + 1);
      }, duration);
    }

    function stopAutoplay() {
      if (timer) clearInterval(timer);
      timer = null;
      if (progress) {
        progress.style.transition = 'none';
        progress.style.width = '0%';
      }
    }

    function restartAutoplay() {
      startAutoplay();
    }

    container.addEventListener('mouseenter', stopAutoplay);
    container.addEventListener('mouseleave', startAutoplay);
    container.addEventListener('focusin', stopAutoplay);
    container.addEventListener('focusout', startAutoplay);

    // Touch & swipe gestures
    let startX = 0;
    let isDragging = false;

    container.addEventListener('touchstart', (e) => {
      startX = e.touches[0].clientX;
      isDragging = true;
      stopAutoplay();
    }, { passive: true });

    container.addEventListener('touchend', (e) => {
      if (!isDragging) return;
      isDragging = false;
      const endX = e.changedTouches[0].clientX;
      const diffX = endX - startX;
      if (Math.abs(diffX) > 40) {
        SoundFX.playClick();
        if (diffX < 0) goTo(current + 1);
        else goTo(current - 1);
      }
      startAutoplay();
    }, { passive: true });

    // Initial start
    startAutoplay();
    container._carousel = { goTo, next: () => goTo(current + 1), prev: () => goTo(current - 1) };
  });
}
window.initCarousels = initCarousels;
