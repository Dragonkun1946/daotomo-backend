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
    <span class="toast-msg">${message}</span>
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
  setUser:    (user) => {
    if (user) {
      localStorage.setItem('dtm_user', JSON.stringify(user));
    }
  },
  isLoggedIn: () => {
    const t = localStorage.getItem('dtm_token');
    return !!t && t !== 'null' && t !== 'undefined' && t !== 'false' && t.trim() !== '';
  },
  setSession(token, user) {
    if (token) localStorage.setItem('dtm_token', token);
    if (user) {
      const customSkin = localStorage.getItem('dtm_custom_skin');
      if (customSkin && !user.skinUrl) {
        user.skinUrl = customSkin;
      }
      localStorage.setItem('dtm_user', JSON.stringify(user));
    }
    try {
      const skinUrl = (user && user.skinUrl) || localStorage.getItem('dtm_custom_skin') || '/img/steve.png';
      window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl } }));
    } catch (e) {}
  },
  clearSession() {
    localStorage.removeItem('dtm_token');
    localStorage.removeItem('dtm_user');
    localStorage.removeItem('dtm_custom_skin');
    try {
      window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl: '/img/steve.png' } }));
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
  getActiveSkinUrl: () => {
    const customSkin = localStorage.getItem('dtm_custom_skin');
    if (customSkin) return customSkin;
    const user = api.getUser();
    if (api.isLoggedIn() && user && user.skinUrl) {
      return user.skinUrl;
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
          Không tìm thấy kết quả phù hợp cho "${input.value}"
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
   NAVBAR ACTIONS INJECTION (Sound Toggle & Command Button)
═══════════════════════════════════════════════════════════ */
function updateSoundBtn() {
  const btn = document.getElementById('btn-sound-toggle');
  if (btn) {
    btn.innerHTML = SoundFX.enabled ? '🔊' : '🔇';
    btn.title = SoundFX.enabled ? 'Tắt âm thanh hiệu ứng' : 'Bật âm thanh hiệu ứng';
    btn.classList.toggle('active', SoundFX.enabled);
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

    // Sound toggle button
    const soundBtn = document.createElement('button');
    soundBtn.id = 'btn-sound-toggle';
    soundBtn.className = 'nav-action-btn';
    soundBtn.setAttribute('aria-label', 'Bật / tắt âm thanh');
    soundBtn.addEventListener('click', () => {
      const state = SoundFX.toggle();
      updateSoundBtn();
      showToast(state ? '🔊 Âm thanh giao diện đã BẬT' : '🔇 Âm thanh giao diện đã TẮT', 'info');
    });
    group.appendChild(soundBtn);

    // Append to nav (order is controlled by CSS flexbox)
    nav.appendChild(group);

    updateSoundBtn();
  }
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
