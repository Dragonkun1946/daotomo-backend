/**
 * Đảo Tò Mò — Minecraft 3D Skin Viewer Engine
 * Powered by skinview3d with OrbitControls, animations, layers, screenshots & account sync.
 */

(function () {
  'use strict';

  const DEFAULT_STEVE_SKIN = '/img/steve.png';

  /**
   * Helper to resolve the active skin URL for the current user.
   * If logged in and user has a skinUrl -> returns user.skinUrl
   * Otherwise -> returns /img/steve.png
   */
  function resolveActiveSkin() {
    if (window.api && typeof window.api.getActiveSkinUrl === 'function') {
      return window.api.getActiveSkinUrl();
    }
    const rawUser = localStorage.getItem('dtm_user');
    const token = localStorage.getItem('dtm_token');
    if (token && rawUser) {
      try {
        const u = JSON.parse(rawUser);
        if (u && u.skinUrl) return u.skinUrl;
      } catch (e) {}
    }
    return DEFAULT_STEVE_SKIN;
  }

  // Keep track of all active viewer instances on the current page for synchronized updates
  const activeViewers = new Set();

  class MinecraftSkinViewer {
    constructor(canvas, options = {}) {
      this.canvas = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
      if (!this.canvas) return;

      if (!window.skinview3d) {
        console.warn('skinview3d library not loaded yet.');
        return;
      }

      const width = options.width || this.canvas.clientWidth || 280;
      const height = options.height || this.canvas.clientHeight || 360;
      const skinUrl = options.skin || resolveActiveSkin();

      this.currentSkinUrl = skinUrl;

      this.viewer = new window.skinview3d.SkinViewer({
        canvas: this.canvas,
        width: width,
        height: height,
        skin: skinUrl,
        preserveDrawingBuffer: true
      });

      // Lighting & camera
      this.viewer.camera.position.set(0, 0, 62);
      this.viewer.fov = 48;
      this.viewer.autoRotate = options.autoRotate !== undefined ? options.autoRotate : true;
      this.viewer.autoRotateSpeed = 0.85;

      if (this.viewer.controls) {
        this.viewer.controls.enableRotate = true;
        this.viewer.controls.enableZoom = true;
        this.viewer.controls.enablePan = false;
      }

      // Default animation
      this.currentAnimName = options.animation || 'walk';
      this.setAnimation(this.currentAnimName);

      // Layer state
      this.outerLayerVisible = true;

      // Auto-resize on container resize
      if ('ResizeObserver' in window && this.canvas.parentElement) {
        this.ro = new ResizeObserver((entries) => {
          for (const entry of entries) {
            const w = Math.floor(entry.contentRect.width);
            const h = Math.floor(entry.contentRect.height);
            if (w > 50 && h > 50 && this.viewer) {
              this.viewer.setSize(w, h);
            }
          }
        });
        this.ro.observe(this.canvas.parentElement);
      }

      activeViewers.add(this);
    }

    setAnimation(type) {
      if (!this.viewer || !window.skinview3d) return;
      this.currentAnimName = type;
      if (type === 'walk') {
        this.viewer.animation = new window.skinview3d.WalkingAnimation();
        this.viewer.animation.speed = 0.75;
      } else if (type === 'run') {
        this.viewer.animation = new window.skinview3d.RunningAnimation();
        this.viewer.animation.speed = 0.95;
      } else if (type === 'fly') {
        this.viewer.animation = new window.skinview3d.FlyingAnimation();
        this.viewer.animation.speed = 0.8;
      } else if (type === 'idle') {
        this.viewer.animation = new window.skinview3d.IdleAnimation();
        this.viewer.animation.speed = 0.65;
      } else {
        this.viewer.animation = null;
      }
    }

    toggleAutoRotate() {
      if (!this.viewer) return false;
      this.viewer.autoRotate = !this.viewer.autoRotate;
      return this.viewer.autoRotate;
    }

    toggleOuterLayer() {
      if (!this.viewer || !this.viewer.playerObject) return true;
      this.outerLayerVisible = !this.outerLayerVisible;
      const parts = ['head', 'body', 'leftArm', 'rightArm', 'leftLeg', 'rightLeg'];
      const skin = this.viewer.playerObject.skin;
      parts.forEach(p => {
        if (skin[p] && skin[p].outerLayer) {
          skin[p].outerLayer.visible = this.outerLayerVisible;
        }
      });
      return this.outerLayerVisible;
    }

    resetCamera() {
      if (!this.viewer) return;
      this.viewer.camera.position.set(0, 0, 62);
      if (this.viewer.controls && typeof this.viewer.controls.reset === 'function') {
        this.viewer.controls.reset();
      }
    }

    loadSkin(url) {
      if (!this.viewer) return;
      this.currentSkinUrl = url || DEFAULT_STEVE_SKIN;
      try {
        const res = this.viewer.loadSkin(this.currentSkinUrl);
        if (res && typeof res.catch === 'function') {
          res.catch((err) => {
            console.warn('Skin load failed, falling back to Steve:', url, err);
            if (this.currentSkinUrl !== DEFAULT_STEVE_SKIN) {
              this.currentSkinUrl = DEFAULT_STEVE_SKIN;
              this.viewer.loadSkin(DEFAULT_STEVE_SKIN);
            }
          });
        }
      } catch (err) {
        console.warn('Skin load threw error, falling back to Steve:', err);
        if (this.currentSkinUrl !== DEFAULT_STEVE_SKIN) {
          this.currentSkinUrl = DEFAULT_STEVE_SKIN;
          this.viewer.loadSkin(DEFAULT_STEVE_SKIN);
        }
      }
    }

    takeScreenshot() {
      if (!this.viewer) return;
      this.viewer.render();
      const dataUrl = this.viewer.canvas.toDataURL('image/png');
      const a = document.createElement('a');
      a.href = dataUrl;
      a.download = `daotomo-skin-${Date.now()}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      if (window.showToast) window.showToast('Đã lưu ảnh chụp mô hình 3D (.png)!', 'success');
    }

    dispose() {
      activeViewers.delete(this);
      if (this.ro) this.ro.disconnect();
      if (this.viewer) this.viewer.dispose();
    }
  }

  window.MinecraftSkinViewer = MinecraftSkinViewer;
  window.resolveActiveSkin = resolveActiveSkin;

  // Global listener for synchronized skin updates across all viewers
  window.addEventListener('daotomo:skin-updated', (e) => {
    const newSkinUrl = e.detail?.skinUrl || resolveActiveSkin();
    activeViewers.forEach(v => {
      try { v.loadSkin(newSkinUrl); } catch (err) {}
    });
  });

  /* ═══════════════════════════════════════════════════════════
     GLOBAL 3D SKIN MODAL VIEWER
  ═══════════════════════════════════════════════════════════ */
  let globalModalViewer = null;
  let stagedSkinData = null; // Staged base64 data to save
  let stagedSkinUrl = null;  // Staged URL to save

  function updateModalAuthStatus() {
    const statusPill = document.getElementById('skin-modal-user-status');
    const saveBtn = document.getElementById('skin-modal-save-btn');
    const skinStatePill = document.getElementById('skin-modal-skin-state');
    if (!statusPill) return;

    const isLoggedIn = window.api && window.api.isLoggedIn();
    const user = window.api ? window.api.getUser() : null;

    if (isLoggedIn && user) {
      statusPill.innerHTML = `👤 <strong>${user.username}</strong> • Đã đăng nhập`;
      statusPill.className = 'skin-status-pill user-saved';
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
        saveBtn.title = 'Lưu skin hiện tại vào tài khoản';
      }
    } else {
      statusPill.innerHTML = `👤 Khách • Chưa đăng nhập`;
      statusPill.className = 'skin-status-pill';
      if (saveBtn) {
        saveBtn.title = 'Đăng nhập để lưu skin này vào tài khoản của bạn';
      }
    }

    // Update skin state description
    if (skinStatePill) {
      const activeSkin = resolveActiveSkin();
      const viewingSkin = globalModalViewer ? globalModalViewer.currentSkinUrl : activeSkin;

      if (stagedSkinData || (stagedSkinUrl && stagedSkinUrl !== activeSkin)) {
        skinStatePill.innerHTML = `👁️ Đang xem trước (Chưa lưu vào tài khoản)`;
        skinStatePill.className = 'skin-status-pill preview';
      } else if (isLoggedIn && user && user.skinUrl) {
        skinStatePill.innerHTML = `✨ Skin cá nhân của bạn (Đã lưu)`;
        skinStatePill.className = 'skin-status-pill user-saved';
      } else {
        skinStatePill.innerHTML = `🌱 Skin mặc định (Steve)`;
        skinStatePill.className = 'skin-status-pill';
      }
    }
  }

  function openSkinModal(initialSkin = null) {
    const skinToLoad = initialSkin || resolveActiveSkin();
    stagedSkinData = null;
    stagedSkinUrl = initialSkin;

    let backdrop = document.getElementById('skin-modal-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'skin-modal-backdrop';
      backdrop.className = 'skin-modal-backdrop';
      backdrop.innerHTML = `
        <div class="skin-modal-card" role="dialog" aria-modal="true">
          <div class="skin-modal-head">
            <div class="skin-modal-title">
              <span class="hero-icon">🧍</span>
              <span>Mô Hình Nhân Vật 3D</span>
              <span id="skin-modal-user-status" class="skin-status-pill">👤 Khách</span>
            </div>
            <button class="skin-modal-close" id="skin-modal-close-btn" aria-label="Đóng">✕</button>
          </div>

          <div class="skin-modal-body">
            <!-- Left Stage -->
            <div class="skin-canvas-container">
              <canvas id="skin-modal-canvas"></canvas>
              <div class="skin-pedestal-glow"></div>
              <div class="skin-hint-badge">💡 Kéo chuột xoay 360° • Cuộn chuột để zoom</div>
            </div>

            <!-- Right Controls Pane -->
            <div class="skin-controls-pane">

              <!-- 1. Animations -->
              <div class="skin-pane-section">
                <label class="skin-pane-label">
                  <span>Động Tác Hoạt Ảnh</span>
                  <span style="font-size:0.7rem; font-weight:normal; color:var(--text-dim);">4 chế độ</span>
                </label>
                <div class="skin-btn-group skin-grid-2" id="skin-modal-anims">
                  <button class="skin-control-btn active" data-anim="walk">🚶 Đi bộ</button>
                  <button class="skin-control-btn" data-anim="idle">🧍 Đứng yên</button>
                  <button class="skin-control-btn" data-anim="run">🏃 Chạy nhanh</button>
                  <button class="skin-control-btn" data-anim="fly">🕊️ Bay lượn</button>
                </div>
              </div>

              <!-- 2. Display Toggles -->
              <div class="skin-pane-section">
                <label class="skin-pane-label">
                  <span>Tùy Chọn Hiển Thị</span>
                </label>
                <div class="skin-btn-group skin-grid-2">
                  <button class="skin-control-btn active" id="skin-modal-rotate-btn">🔄 Xoay: BẬT</button>
                  <button class="skin-control-btn active" id="skin-modal-layer-btn">🧥 Lớp Áo: BẬT</button>
                  <button class="skin-control-btn" id="skin-modal-reset-btn">🎯 Đặt lại góc</button>
                  <button class="skin-control-btn" id="skin-modal-shot-btn">📸 Chụp ảnh PNG</button>
                </div>
              </div>

              <!-- 3. Skin Management & Upload -->
              <div class="skin-pane-section">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <label class="skin-pane-label" style="margin:0;">Quản Lý Skin</label>
                  <span id="skin-modal-skin-state" class="skin-status-pill">🌱 Skin Steve</span>
                </div>

                <!-- Username Lookup -->
                <div style="display:flex; gap:0.4rem;">
                  <input type="text" id="skin-modal-username" class="input" style="font-size:0.85rem; padding:0.5rem 0.75rem;" placeholder="Nhập tên Minecraft (Steve, Alex...)">
                  <button class="btn btn-outline" id="skin-modal-load-user-btn" style="padding:0 0.85rem; font-size:0.82rem; min-height:38px; white-space:nowrap;">Tìm & Tải</button>
                </div>

                <!-- File Upload -->
                <div>
                  <label class="btn btn-ghost btn-full" style="font-size:0.85rem; padding:0.6rem 0.85rem; min-height:38px; cursor:pointer; border:1px dashed rgba(34,211,238,0.35); justify-content:center;">
                    📁 Tải file skin .PNG từ máy tính
                    <input type="file" id="skin-modal-file" accept="image/png" style="display:none;">
                  </label>
                </div>

                <!-- Save to Account & Reset Actions -->
                <div class="skin-btn-group skin-grid-2" style="margin-top:0.25rem;">
                  <button class="btn btn-primary" id="skin-modal-save-btn" style="font-size:0.85rem; padding:0.6rem 0.75rem; min-height:40px; justify-content:center;">
                    💾 Lưu Vào Tài Khoản
                  </button>
                  <button class="btn btn-ghost" id="skin-modal-steve-btn" style="font-size:0.85rem; padding:0.6rem 0.75rem; min-height:40px; justify-content:center;">
                    🔄 Về Skin Steve
                  </button>
                </div>
              </div>

            </div>
          </div>
        </div>
      `;
      document.body.appendChild(backdrop);

      // Event listeners
      document.getElementById('skin-modal-close-btn').addEventListener('click', closeSkinModal);
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeSkinModal();
      });

      // Animation buttons
      const animGroup = document.getElementById('skin-modal-anims');
      animGroup.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
          animGroup.querySelectorAll('button').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          if (globalModalViewer) globalModalViewer.setAnimation(btn.dataset.anim);
          if (window.SoundFX) window.SoundFX.playClick();
        });
      });

      // Rotate toggle
      const rotBtn = document.getElementById('skin-modal-rotate-btn');
      rotBtn.addEventListener('click', () => {
        if (globalModalViewer) {
          const state = globalModalViewer.toggleAutoRotate();
          rotBtn.classList.toggle('active', state);
          rotBtn.textContent = state ? '🔄 Xoay: BẬT' : '⏸️ Xoay: TẮT';
          if (window.SoundFX) window.SoundFX.playClick();
        }
      });

      // Layer toggle
      const layerBtn = document.getElementById('skin-modal-layer-btn');
      layerBtn.addEventListener('click', () => {
        if (globalModalViewer) {
          const state = globalModalViewer.toggleOuterLayer();
          layerBtn.classList.toggle('active', state);
          layerBtn.textContent = state ? '🧥 Lớp Áo: BẬT' : '👕 Lớp Áo: TẮT';
          if (window.SoundFX) window.SoundFX.playClick();
        }
      });

      // Reset camera
      document.getElementById('skin-modal-reset-btn').addEventListener('click', () => {
        if (globalModalViewer) {
          globalModalViewer.resetCamera();
          if (window.SoundFX) window.SoundFX.playClick();
        }
      });

      // Screenshot
      document.getElementById('skin-modal-shot-btn').addEventListener('click', () => {
        if (globalModalViewer) {
          globalModalViewer.takeScreenshot();
          if (window.SoundFX) window.SoundFX.playSuccess();
        }
      });

      // Load by username
      const userInput = document.getElementById('skin-modal-username');
      const loadUserBtn = document.getElementById('skin-modal-load-user-btn');
      const handleUserLoad = () => {
        const username = userInput.value.trim();
        if (!username) return;
        const skinApi = `https://minotar.net/skin/${encodeURIComponent(username)}`;
        if (globalModalViewer) {
          globalModalViewer.loadSkin(skinApi);
          stagedSkinUrl = skinApi;
          stagedSkinData = null;
          updateModalAuthStatus();
          if (window.showToast) window.showToast(`Đang hiển thị skin của "${username}"`, 'info');
        }
      };
      loadUserBtn.addEventListener('click', handleUserLoad);
      userInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') handleUserLoad();
      });

      // Load from local file
      const fileInput = document.getElementById('skin-modal-file');
      fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64 = event.target.result;
          stagedSkinData = base64;
          stagedSkinUrl = null;
          if (globalModalViewer) {
            globalModalViewer.loadSkin(base64);
            updateModalAuthStatus();
            if (window.showToast) window.showToast('Đã tải skin từ máy! Bấm "Lưu Vào Tài Khoản" để giữ skin này.', 'info');
          }
        };
        reader.readAsDataURL(file);
      });

      // Reset to Steve default
      document.getElementById('skin-modal-steve-btn').addEventListener('click', async () => {
        if (globalModalViewer) {
          globalModalViewer.loadSkin(DEFAULT_STEVE_SKIN);
          stagedSkinData = null;
          stagedSkinUrl = DEFAULT_STEVE_SKIN;
          userInput.value = '';

          // If logged in, also reset on account!
          if (window.api && window.api.isLoggedIn()) {
            try {
              const res = await window.api.updateSkin(null, '/img/steve.png');
              const curUser = window.api.getUser();
              if (curUser) {
                curUser.skinUrl = '';
                window.api.setSession(window.api.getToken(), curUser);
              }
              window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl: DEFAULT_STEVE_SKIN } }));
              if (window.showToast) window.showToast(res.message || 'Đã khôi phục skin Steve mặc định!', 'success');
            } catch (err) {
              if (window.showToast) window.showToast(err.message || 'Lỗi khi đặt lại skin', 'error');
            }
          } else {
            if (window.showToast) window.showToast('Đã chuyển về skin Steve mặc định.', 'info');
          }
          updateModalAuthStatus();
        }
      });

      // Save to account button
      document.getElementById('skin-modal-save-btn').addEventListener('click', async () => {
        if (!window.api || !window.api.isLoggedIn()) {
          if (window.showToast) window.showToast('Vui lòng đăng nhập tài khoản để lưu skin nhân vật!', 'error');
          setTimeout(() => {
            window.location.href = '/html/Account.html';
          }, 900);
          return;
        }

        const btn = document.getElementById('skin-modal-save-btn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Đang lưu...';

        try {
          let payloadData = stagedSkinData;
          let payloadUrl = stagedSkinUrl;

          // If no specific staged data but user is viewing something, save it
          if (!payloadData && !payloadUrl && globalModalViewer) {
            payloadUrl = globalModalViewer.currentSkinUrl;
          }

          const localSkin = payloadData || payloadUrl;
          const curUser = window.api.getUser() || {};
          if (localSkin) {
            curUser.skinUrl = localSkin;
            window.api.setSession(window.api.getToken(), curUser);
            window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl: localSkin } }));
          }

          try {
            const res = await window.api.updateSkin(payloadData, payloadUrl);
            const savedUrl = res.skinUrl || payloadUrl;
            if (savedUrl) {
              curUser.skinUrl = savedUrl;
              window.api.setSession(window.api.getToken(), curUser);
              window.dispatchEvent(new CustomEvent('daotomo:skin-updated', { detail: { skinUrl: savedUrl } }));
            }
          } catch (serverErr) {
            console.warn('Server sync note (persisted locally):', serverErr);
          }

          stagedSkinData = null;
          stagedSkinUrl = curUser.skinUrl;

          updateModalAuthStatus();
          if (window.SoundFX) window.SoundFX.playSuccess();
          if (window.showToast) window.showToast('Đã lưu skin nhân vật vào tài khoản thành công! 🎮', 'success');
        } catch (err) {
          if (window.SoundFX) window.SoundFX.playError();
          if (window.showToast) window.showToast('Lỗi lưu skin: ' + (err.message || 'Vui lòng thử lại'), 'error');
        } finally {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });
    }

    backdrop.classList.add('open');
    updateModalAuthStatus();

    // Init or refresh viewer canvas
    const canvas = document.getElementById('skin-modal-canvas');
    if (!globalModalViewer) {
      globalModalViewer = new MinecraftSkinViewer(canvas, {
        skin: skinToLoad,
        width: 320,
        height: 420,
        autoRotate: true,
        animation: 'walk'
      });
    } else {
      globalModalViewer.loadSkin(skinToLoad);
    }

    if (window.SoundFX) window.SoundFX.playPing();
  }

  function closeSkinModal() {
    const backdrop = document.getElementById('skin-modal-backdrop');
    if (backdrop) backdrop.classList.remove('open');
  }

  window.openSkinModal = openSkinModal;
  window.closeSkinModal = closeSkinModal;

  /* ═══════════════════════════════════════════════════════════
     FLOATING 3D MASCOT BUTTON (Global site helper)
  ═══════════════════════════════════════════════════════════ */
  function initFloatingSkinButton() {
    if (document.getElementById('floating-skin-btn')) return;

    const btn = document.createElement('button');
    btn.id = 'floating-skin-btn';
    btn.className = 'floating-skin-btn';
    btn.setAttribute('aria-label', 'Xem mô hình 3D nhân vật Minecraft');
    btn.setAttribute('title', 'Mô hình 3D Skin Minecraft');
    btn.innerHTML = `
      <span class="floating-skin-icon">🧍</span>
      <span class="floating-skin-text">Skin 3D</span>
      <span class="floating-skin-dot"></span>
    `;
    btn.addEventListener('click', () => {
      openSkinModal();
    });
    document.body.appendChild(btn);
  }

  /* ═══════════════════════════════════════════════════════════
     AUTO-MOUNT HOOK FOR PAGES (Account, Home, Server)
  ═══════════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', () => {
    initFloatingSkinButton();

    // Auto mount for any inline canvas with data-skin-canvas
    document.querySelectorAll('[data-skin-canvas]').forEach(canvas => {
      // If dataset has custom skinUrl, use it; otherwise resolve dynamic skin (Steve or User)
      const skinUrl = canvas.dataset.skinUrl || resolveActiveSkin();
      const anim = canvas.dataset.anim || 'walk';
      const autoRotate = canvas.dataset.rotate !== 'false';
      new MinecraftSkinViewer(canvas, {
        skin: skinUrl,
        animation: anim,
        autoRotate: autoRotate
      });
    });
  });

})();
