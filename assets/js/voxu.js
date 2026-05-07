/* ============================================================
   VOXU — Main Application JavaScript
   ============================================================ */

'use strict';

function getCsrfToken() {
  return document.querySelector('input[name="_csrf"]')?.value
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || document.querySelector('meta[name="csrf"]')?.getAttribute('content')
    || '';
}

/* ── API HELPER ──────────────────────────────────── */
const API = {
  base: '/api/v1',
  async request(method, endpoint, data = null) {
    const csrf = getCsrfToken();
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    };
    if (csrf && method !== 'GET') {
      opts.headers['X-CSRF-Token'] = csrf;
    }
    if (data && method !== 'GET') opts.body = JSON.stringify(data);
    try {
      const res = await fetch(this.base + endpoint, opts);
      const json = await res.json();
      if (!res.ok) throw new Error(json.message || 'Request failed');
      return json;
    } catch(e) {
      Toast.error(e.message);
      throw e;
    }
  },
  get: (ep) => API.request('GET', ep),
  post: (ep, d) => API.request('POST', ep, d),
  del: (ep) => API.request('DELETE', ep),
};

/* ── TOAST NOTIFICATIONS ─────────────────────────── */
const Toast = {
  container: null,
  init() {
    this.container = document.getElementById('toast-container') ||
      Object.assign(document.createElement('div'), { id: 'toast-container' });
    document.body.appendChild(this.container);
  },
  show(msg, type = 'info', duration = 3500) {
    if (!this.container) this.init();
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${icons[type] || ''}</span><span>${msg}</span>`;
    this.container.appendChild(el);
    setTimeout(() => {
      el.style.animation = 'toastOut 0.3s ease forwards';
      setTimeout(() => el.remove(), 300);
    }, duration);
  },
  success: (m) => Toast.show(m, 'success'),
  error:   (m) => Toast.show(m, 'error'),
  info:    (m) => Toast.show(m, 'info'),
};

/* ── MODAL ───────────────────────────────────────── */
const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
  },
  close(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(el => {
      el.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
};

/* ── THEME TOGGLE ───────────────────────────────── */
function toggleTheme() {
  const body = document.body;
  if (body.classList.contains('theme-light')) {
    body.classList.replace('theme-light', 'theme-dark');
    document.cookie = 'voxu_theme=dark;path=/;max-age=31536000';
  } else {
    if (body.classList.contains('theme-dark')) {
      body.classList.replace('theme-dark', 'theme-light');
    } else {
      body.classList.add('theme-light');
    }
    document.cookie = 'voxu_theme=light;path=/;max-age=31536000';
  }
}

/* ── VOICE RECORDER ──────────────────────────────── */
const VoiceRecorder = {
  mediaRecorder: null,
  chunks: [],
  startTime: null,
  timerInterval: null,
  blob: null,
  duration: 0,
  maxDuration: (window.VOXU_MAX_RECORD_SECS ?? 180), // set per user plan

  async init(circleId, timeId, wavId, submitBtnId) {
    this.circleEl = document.getElementById(circleId);
    this.timeEl   = document.getElementById(timeId);
    this.wavEl    = document.getElementById(wavId);
    this.submitEl = document.getElementById(submitBtnId);
    if (!this.circleEl) return;

    this.circleEl.addEventListener('click', () => this.toggle());
    this.generateWavePreview();
  },

  generateWavePreview() {
    if (!this.wavEl) return;
    this.wavEl.innerHTML = '';
    for (let i = 0; i < 48; i++) {
      const bar = document.createElement('div');
      bar.className = 'waveform-bar';
      bar.style.height = Math.random() * 80 + 10 + '%';
      this.wavEl.appendChild(bar);
    }
  },

  async toggle() {
    if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
      this.stop();
    } else {
      await this.start();
    }
  },

  async start() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
      this.chunks = [];
      this.blob = null;

      // Pick the best supported MIME type for this browser/device
      const candidates = [
        'audio/webm;codecs=opus', 'audio/webm',
        'audio/ogg;codecs=opus',  'audio/ogg',
        'audio/mp4',              '',
      ];
      const mimeType = candidates.find(m => !m || MediaRecorder.isTypeSupported(m)) ?? '';
      this._mimeType = mimeType || 'audio/webm';

      // Derive file extension from chosen format
      this._ext = mimeType.includes('mp4') ? 'm4a'
                : mimeType.includes('ogg') ? 'ogg'
                : 'webm';

      const opts = mimeType ? { mimeType } : {};
      this.mediaRecorder = new MediaRecorder(stream, opts);
      this.mediaRecorder.addEventListener('dataavailable', e => {
        if (e.data.size > 0) this.chunks.push(e.data);
      });
      this.mediaRecorder.addEventListener('stop', () => {
        this.blob = new Blob(this.chunks, { type: this._mimeType });
        stream.getTracks().forEach(t => t.stop());
        this.onStop();
      });
      this.mediaRecorder.start(100);
      this.startTime = Date.now();
      this.circleEl.classList.add('recording');
      this.circleEl.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="color:var(--danger);width:36px;height:36px"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';
      this.startTimer();
      this.animateWave();
    } catch {
      Toast.error('Microphone access denied. Please allow mic access.');
    }
  },

  stop() {
    if (this.mediaRecorder) this.mediaRecorder.stop();
    clearInterval(this.timerInterval);
    clearInterval(this.waveInterval);
    this.circleEl.classList.remove('recording');
    this.circleEl.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:36px;height:36px;color:var(--purple)"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
  },

  startTimer() {
    this.timerInterval = setInterval(() => {
      const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
      this.duration = elapsed;
      const m = String(Math.floor(elapsed / 60)).padStart(2, '0');
      const s = String(elapsed % 60).padStart(2, '0');
      if (this.timeEl) this.timeEl.textContent = `${m}:${s}`;
      if (elapsed >= this.maxDuration) this.stop();
    }, 1000);
  },

  animateWave() {
    if (!this.wavEl) return;
    const bars = this.wavEl.querySelectorAll('.waveform-bar');
    this.waveInterval = setInterval(() => {
      bars.forEach(b => {
        b.style.height = Math.random() * 80 + 10 + '%';
        b.classList.add('active');
      });
    }, 150);
  },

  onStop() {
    if (this.submitEl) this.submitEl.disabled = false;
    this.generateWavePreview();
    if (this.timeEl) {
      const m = String(Math.floor(this.duration / 60)).padStart(2, '0');
      const s = String(this.duration % 60).padStart(2, '0');
      this.timeEl.textContent = `${m}:${s} recorded`;
    }
    // Show replay controls if a preview element exists
    if (this.previewEl && this.blob) {
      const url = URL.createObjectURL(this.blob);
      this.previewEl.src = url;
      this.previewEl.style.display = 'block';
      // Show re-record button if it exists
      if (this.rerecordEl) this.rerecordEl.style.display = 'inline-flex';
    }
  },

  initPreview(previewAudioId, rerecordBtnId) {
    this.previewEl   = document.getElementById(previewAudioId);
    this.rerecordEl  = document.getElementById(rerecordBtnId);
    if (this.rerecordEl) {
      this.rerecordEl.addEventListener('click', () => {
        this.blob = null;
        this.duration = 0;
        if (this.submitEl) this.submitEl.disabled = true;
        if (this.previewEl) { this.previewEl.src = ''; this.previewEl.style.display = 'none'; }
        if (this.rerecordEl) this.rerecordEl.style.display = 'none';
        if (this.timeEl) this.timeEl.textContent = '0:00';
        this.generateWavePreview();
        Toast.info('Recording cleared. Tap the mic to record again.');
      });
    }
  },

  async upload(title, extraData = {}, endpoint = null, coverImageFile = null) {
    if (!this.blob) { Toast.error('No recording found. Please record first.'); return null; }
    const ext      = this._ext || 'webm';
    const filename = 'voice.' + ext;
    const fd = new FormData();
    fd.append('audio', this.blob, filename);
    if (title) fd.append('title', title);
    fd.append('duration', String(this.duration));
    if (coverImageFile) fd.append('cover_image', coverImageFile, coverImageFile.name);
    Object.entries(extraData).forEach(([k, v]) => fd.append(k, String(v)));
    const url  = endpoint || '/api/v1/voice/create';
    const csrf = getCsrfToken();
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrf ? { 'X-CSRF-Token': csrf } : {},
        body: fd,
      });
    } catch (networkErr) {
      Toast.error('Network error — check your connection and try again.');
      return { success: false, message: 'Network error' };
    }
    let data;
    try { data = await res.json(); } catch { data = {}; }
    if (!res.ok || !data.success) {
      const msg = data.message || ('Upload failed (HTTP ' + res.status + ')');
      Toast.error(msg);
      return { success: false, message: msg };
    }
    return data;
  }
};

/* ── VOICE PLAYER ────────────────────────────────── */
const VoicePlayer = {
  current: null,
  init() {
    document.querySelectorAll('[data-voice-player]').forEach(el => {
      this.setup(el);
    });
  },

  setup(card) {
    const src    = card.dataset.src;
    const playBtn= card.querySelector('.play-btn');
    const prog   = card.querySelector('.progress-fill');
    const timEl  = card.querySelector('.player-time');
    const progBar= card.querySelector('.progress-bar');
    const bars   = card.querySelectorAll('.waveform-bar');
    if (!src || !playBtn) return;

    const audio  = new Audio(src);
    let playing  = false;

    const fmtTime = s => `${String(Math.floor(s/60)).padStart(2,'0')}:${String(Math.floor(s%60)).padStart(2,'0')}`;

    audio.addEventListener('loadedmetadata', () => {
      if (timEl) timEl.textContent = `0:00 / ${fmtTime(audio.duration)}`;
    });

    audio.addEventListener('timeupdate', () => {
      const pct = audio.currentTime / audio.duration * 100 || 0;
      if (prog) prog.style.width = pct + '%';
      if (timEl) timEl.textContent = `${fmtTime(audio.currentTime)} / ${fmtTime(audio.duration)}`;
      const barIdx = Math.floor(pct / 100 * bars.length);
      bars.forEach((b, i) => {
        b.classList.toggle('played', i < barIdx);
        b.classList.toggle('active', i === barIdx);
      });
    });

    audio.addEventListener('ended', () => {
      playing = false;
      playBtn.innerHTML = this.playIcon();
      bars.forEach(b => b.classList.remove('active', 'played'));
      if (prog) prog.style.width = '0%';
    });

    if (progBar) {
      progBar.addEventListener('click', e => {
        const pct = e.offsetX / progBar.offsetWidth;
        audio.currentTime = pct * audio.duration;
      });
    }

    playBtn.addEventListener('click', () => {
      if (this.current && this.current !== audio) {
        this.current.pause();
        const prev = document.querySelector('[data-voice-player] .play-btn[data-playing]');
        if (prev) { prev.innerHTML = this.playIcon(); delete prev.dataset.playing; }
      }
      if (playing) {
        audio.pause();
        playBtn.innerHTML = this.playIcon();
        delete playBtn.dataset.playing;
        this.current = null;
      } else {
        audio.play();
        playBtn.innerHTML = this.pauseIcon();
        playBtn.dataset.playing = '1';
        this.current = audio;
        // track play
        const postId = card.dataset.postId;
        if (postId) {
          fetch(`/api/v1/voice/${postId}/play`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: getCsrfToken() ? { 'X-CSRF-Token': getCsrfToken() } : {}
          });
        }
      }
      playing = !playing;
    });
  },

  playIcon:  () => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
  pauseIcon: () => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
};

/* ── STATUS VIEWER ───────────────────────────────── */
/* ═══════════════════════════════════════════════════════
   STATUS VIEWER — Full-screen story-style viewer
   • Auto-advances after each status (image:6s, text:5s, video:on ended)
   • Progress bar animates via rAF loop — no CSS transitions to break
   • Swipe left/right, keyboard arrows, tap left/right half to navigate
   • Video auto-pauses when navigating away
   ═══════════════════════════════════════════════════════ */
const StatusViewer = {
  statuses  : [],
  current   : 0,
  _timer    : null,       // setTimeout handle for image/text auto-advance
  _rafId    : null,       // requestAnimationFrame handle
  _startTs  : 0,          // timestamp when current status started
  _duration : 6000,       // ms for image/text; video uses its own length
  _paused   : false,
  _curVideo : null,       // reference to active <video> element

  /* ── PUBLIC: open ────────────────────────────────── */
  open(statuses, startIndex = 0) {
    this.statuses = Array.isArray(statuses) ? statuses : [];
    if (!this.statuses.length) return;
    this.current = Math.max(0, Math.min(startIndex, this.statuses.length - 1));
    const el = document.getElementById('status-viewer');
    if (!el) return;
    el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    this._show();
  },

  /* ── PUBLIC: close ───────────────────────────────── */
  close() {
    this._stopAll();
    const el = document.getElementById('status-viewer');
    if (el) el.classList.add('hidden');
    document.body.style.overflow = '';
    // Stop any playing video
    if (this._curVideo) { this._curVideo.pause(); this._curVideo = null; }
  },

  /* ── PUBLIC: next / prev ─────────────────────────── */
  next() {
    this._stopAll();
    if (this._curVideo) { this._curVideo.pause(); this._curVideo = null; }
    if (this.current < this.statuses.length - 1) {
      this.current++;
      this._show();
    } else {
      this.close();
    }
  },

  prev() {
    this._stopAll();
    if (this._curVideo) { this._curVideo.pause(); this._curVideo = null; }
    if (this.current > 0) {
      this.current--;
      this._show();
    }
  },

  /* ── INTERNAL: render current status ────────────── */
  _show() {
    const s = this.statuses[this.current];
    if (!s) { this.close(); return; }

    // Determine duration for this status
    // Video duration is handled via ended event, capped at 30s
    const isVideo = (s.type === 'video' && s.media_url);
    this._duration = isVideo ? 30000 : (s.type === 'text' ? 5000 : 6000);

    // Render content (overrideable per-page)
    this.updateContent(s);

    // Rebuild progress bars for the current group
    this._buildProgressBars();

    // Track view
    this.trackView(s.id);

    // Start the rAF progress animation + auto-advance timer
    this._startTs = performance.now();
    this._paused  = false;
    this._animateProgress();

    // For non-video: set timeout
    if (!isVideo) {
      this._timer = setTimeout(() => this.next(), this._duration);
    }
    // For video: advance on 'ended' event (set in updateContent)
  },

  /* ── INTERNAL: rAF progress bar animator ─────────── */
  _animateProgress() {
    cancelAnimationFrame(this._rafId);
    const fill = document.getElementById(`sfill-${this.current}`);
    if (!fill) return;
    fill.style.width = '0%';

    const self = this;
    function step(ts) {
      if (self._paused) { self._rafId = requestAnimationFrame(step); return; }
      const elapsed = ts - self._startTs;
      const pct     = Math.min(elapsed / self._duration * 100, 100);
      if (fill) fill.style.width = pct + '%';
      if (pct < 100) {
        self._rafId = requestAnimationFrame(step);
      }
    }
    this._rafId = requestAnimationFrame(step);
  },

  /* ── INTERNAL: build/reset progress bar fills ────── */
  _buildProgressBars() {
    const barContainer = document.getElementById('status-prog-bars');
    if (!barContainer) return;

    // Ensure we have the right number of bars
    const needed = this.statuses.length;
    while (barContainer.children.length < needed) {
      const bar  = document.createElement('div');
      bar.className = 'status-prog';
      bar.innerHTML = '<div class="status-prog-fill"></div>';
      barContainer.appendChild(bar);
    }
    while (barContainer.children.length > needed) {
      barContainer.removeChild(barContainer.lastChild);
    }

    // Set IDs so rAF can find the active fill
    Array.from(barContainer.children).forEach((bar, i) => {
      const fill = bar.querySelector('.status-prog-fill');
      if (fill) {
        fill.id = `sfill-${i}`;
        fill.style.transition = 'none';
        fill.style.width      = i < this.current ? '100%' : '0%';
      }
    });
  },

  /* ── INTERNAL: stop timers ───────────────────────── */
  _stopAll() {
    clearTimeout(this._timer);
    cancelAnimationFrame(this._rafId);
    this._timer = null;
    this._rafId = null;
  },

  /* ── DEFAULT updateContent (overrideable per page) ── */
  updateContent(s) {
    const viewer = document.getElementById('status-viewer');
    if (!viewer) return;

    // Update user info
    const nameEls = viewer.querySelectorAll('.status-user-name');
    nameEls.forEach(el => { el.textContent = '@' + (s.username || ''); });

    // Update caption and source
    const capEl = viewer.querySelector('.status-caption');
    const srcEl = viewer.querySelector('.status-source');
    if (capEl) capEl.textContent = s.caption || '';
    if (srcEl) srcEl.textContent = s.source_label ? '📌 ' + s.source_label : '';

    // Update contact button
    const contactBtn = document.getElementById('status-contact-btn');
    if (contactBtn) {
      if (s.contact_link) {
        contactBtn.href = s.contact_link;
        contactBtn.dataset.statusId = s.id;
        contactBtn.rel = 'noopener noreferrer';
        contactBtn.classList.remove('hidden');
      } else {
        contactBtn.classList.add('hidden');
      }
    }

    // Update content area
    const content = document.getElementById('status-content');
    if (!content) return;

    // Remove previous media (keep nav overlay divs)
    Array.from(content.children).forEach(child => {
      if (child.id !== 'status-nav-prev' && child.id !== 'status-nav-next') {
        child.remove();
      }
    });

    this._curVideo = null;

    if (s.type === 'image' && s.media_url) {
      const img = document.createElement('img');
      img.src = s.media_url;
      img.alt = s.caption || '';
      img.style.cssText = 'width:100%;height:100%;object-fit:contain;position:absolute;inset:0';
      content.insertBefore(img, content.firstChild);

    } else if (s.type === 'video' && s.media_url) {
      const vid = document.createElement('video');
      vid.src      = s.media_url;
      vid.autoplay = true;
      vid.playsInline = true;
      vid.controls = false;
      vid.style.cssText = 'width:100%;height:100%;object-fit:contain;position:absolute;inset:0';
      // Auto-advance when video ends
      vid.addEventListener('ended', () => this.next());
      // Update duration once metadata is known
      vid.addEventListener('loadedmetadata', () => {
        this._duration = Math.min(vid.duration * 1000, 30000);
      });
      content.insertBefore(vid, content.firstChild);
      this._curVideo = vid;

    } else {
      // Text / voice status
      const div = document.createElement('div');
      div.className = 'text-status';
      div.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:40px 24px';
      div.style.background = s.bg_color || 'linear-gradient(135deg,#6C3BFF,#00D1FF)';
      div.textContent = s.text || s.caption || '';
      content.insertBefore(div, content.firstChild);
    }
  },

  /* ── Track view ──────────────────────────────────── */
  async trackView(statusId) {
    if (!statusId) return;
    try {
      await fetch(`/api/v1/status/${statusId}/view`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: getCsrfToken() ? { 'X-CSRF-Token': getCsrfToken() } : {}
      });
    } catch {}
  }
};

/* ── ENERGY SYSTEM ───────────────────────────────── */
const Energy = {
  async send(postId, amount = 1) {
    try {
      const res = await API.post(`/posts/${postId}/energy`, { amount });
      Toast.success(`+${amount} energy sent!`);
      const el = document.querySelector(`[data-post-id="${postId}"] .energy-display`);
      if (el) el.textContent = `⚡ ${res.total_energy}`;
      return res;
    } catch {}
  }
};

/* ── INFINITE SCROLL ─────────────────────────────── */
const InfiniteScroll = {
  setup(feedId, loadFn) {
    this.feed    = document.getElementById(feedId);
    this.loadFn  = loadFn;
    this.page    = 1;
    this.loading = false;
    this.done    = false;
    if (!this.feed) return;
    window.addEventListener('scroll', this.onScroll.bind(this), { passive: true });
  },
  onScroll() {
    if (this.loading || this.done) return;
    const bottom = document.documentElement.scrollHeight - window.scrollY - window.innerHeight;
    if (bottom < 200) this.load();
  },
  async load() {
    this.loading = true;
    this.page++;
    const items = await this.loadFn(this.page);
    if (!items || items.length === 0) {
      this.done = true;
      const loader = document.getElementById('load-more');
      if (loader) loader.textContent = 'No more posts';
    }
    this.loading = false;
  }
};

/* ── IMAGE PREVIEW ───────────────────────────────── */
function setupImagePreview(inputId, previewId) {
  const input   = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!input || !preview) return;
  input.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
      preview.src = ev.target.result;
      preview.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
  });
}

/* ── FORM HELPERS ────────────────────────────────── */
function formToJSON(form) {
  const data = {};
  new FormData(form).forEach((v, k) => data[k] = v);
  return data;
}

function setLoading(btn, loading) {
  btn.classList.toggle('btn-loading', loading);
  btn.disabled = loading;
}

/* ── COPY TO CLIPBOARD ───────────────────────────── */
function copyText(text, msg = 'Copied!') {
  navigator.clipboard.writeText(text).then(() => Toast.success(msg)).catch(() => {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    Toast.success(msg);
  });
}

/* ── INIT ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  Toast.init();
  VoicePlayer.init();

  // Modal close on overlay click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) Modal.closeAll();
    });
  });

  // Bottom nav active state
  const path = window.location.pathname;
  document.querySelectorAll('.bottom-nav-item[href]').forEach(a => {
    if (a.getAttribute('href') === path) a.classList.add('active');
  });

  // PWA install prompt
  // Store the event — do NOT call prompt() here, only on explicit user gesture
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault(); // suppress browser mini-infobar
    window._pwaPrompt = e;
    // Show the install banner/button
    const banner = document.getElementById('install-banner');
    if (banner) {
      banner.classList.remove('hidden');
      // Animate it in
      banner.style.animation = 'fadeIn 0.4s ease';
    }
  });

  // Wire ANY element with data-pwa-install to trigger the prompt on click
  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-pwa-install]');
    if (!trigger || !window._pwaPrompt) return;
    try {
      const result = await window._pwaPrompt.prompt();
      if (result?.outcome === 'accepted') {
        window._pwaPrompt = null;
        const banner = document.getElementById('install-banner');
        if (banner) banner.classList.add('hidden');
      }
    } catch (err) {
      console.warn('PWA install prompt error:', err);
    }
  });

  // Also wire the legacy install-btn id
  const legacyInstallBtn = document.getElementById('install-btn');
  if (legacyInstallBtn) {
    legacyInstallBtn.setAttribute('data-pwa-install', '1');
  }

  // App installed event
  window.addEventListener('appinstalled', () => {
    window._pwaPrompt = null;
    const banner = document.getElementById('install-banner');
    if (banner) banner.classList.add('hidden');
    Toast && Toast.success('App installed!');
  });

  // Swipe support for status viewer
  const viewer = document.getElementById('status-viewer');
  if (viewer) {
    let touchStartX = 0;
    let touchStartY = 0;
    viewer.addEventListener('touchstart', e => {
      touchStartX = e.changedTouches[0].clientX;
      touchStartY = e.changedTouches[0].clientY;
    }, { passive: true });
    viewer.addEventListener('touchend', e => {
      const dx = e.changedTouches[0].clientX - touchStartX;
      const dy = e.changedTouches[0].clientY - touchStartY;
      // Only navigate if horizontal swipe is dominant
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
        dx > 0 ? StatusViewer.prev() : StatusViewer.next();
      }
    }, { passive: true });
  }

  // Keyboard navigation for status viewer
  document.addEventListener('keydown', e => {
    const viewer = document.getElementById('status-viewer');
    if (!viewer || viewer.classList.contains('hidden')) return;
    if (e.key === 'ArrowRight' || e.key === ' ')  { e.preventDefault(); StatusViewer.next(); }
    if (e.key === 'ArrowLeft')                     { e.preventDefault(); StatusViewer.prev(); }
    if (e.key === 'Escape')                        { StatusViewer.close(); }
  });
});


/* ══════════════════════════════════════════════════════
   VOXU i18n — Client-side language engine
   ══════════════════════════════════════════════════════ */
const VoxuI18n = {
  strings: {},
  lang: 'en',
  rtl: false,

  // Get current lang from cookie
  getCookieLang() {
    const m = document.cookie.match(/(?:^|; )voxu_lang=([^;]+)/);
    return m ? m[1] : 'en';
  },

  // Apply translations to all [data-i18n] elements
  apply() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const val = this.strings[key];
      if (!val) return;
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        el.placeholder = val;
      } else {
        el.textContent = val;
      }
    });
    // Apply RTL
    if (this.rtl) {
      document.documentElement.setAttribute('dir','rtl');
      document.documentElement.style.fontFamily = "'Noto Sans Arabic', 'Inter', sans-serif";
    } else {
      document.documentElement.removeAttribute('dir');
    }
    // Update html lang attribute
    document.documentElement.setAttribute('lang', this.lang);
    // Update any flag button
    const langBtn = document.getElementById('langBtn');
    if (langBtn) {
      const FLAGS = {en:'🇺🇸',zh:'🇨🇳',es:'🇪🇸',ar:'🇸🇦',pt:'🇧🇷',fr:'🇫🇷',
                     sw:'🇰🇪',ha:'🇳🇬',de:'🇩🇪',no:'🇳🇴',ja:'🇯🇵',hi:'🇮🇳',
                     ko:'🇰🇷',ru:'🇷🇺',id:'🇮🇩'};
      langBtn.textContent = FLAGS[this.lang] || '🌐';
    }
  },

  // Load strings from API and apply
  async load(lang) {
    this.lang = lang || this.getCookieLang();
    try {
      const r = await fetch('/api/v1/i18n/strings?lang=' + encodeURIComponent(this.lang), {credentials:'same-origin'});
      const d = await r.json();
      if (d.success) {
        this.strings = d.strings;
        this.rtl     = d.rtl;
        this.apply();
      }
    } catch(e) {
      console.warn('i18n load failed:', e.message);
    }
  },

  // Auto-load on page ready
  init() {
    const lang = this.getCookieLang();
    if (lang && lang !== 'en') {
      this.load(lang);
    }
  }
};

// Override setLang to use this engine
function setLang(code) {
  const _d = new Date(); _d.setFullYear(_d.getFullYear()+1);
  document.cookie = 'voxu_lang='+encodeURIComponent(code)+';path=/;expires='+_d.toUTCString()+';SameSite=Lax';
  // Close the dropdown immediately
  const menu = document.getElementById('langMenu');
  if (menu) menu.classList.add('hidden');
  // Update page live without full reload for same-lang languages
  VoxuI18n.load(code).then(() => {
    // Also persist server-side
    fetch('/api/v1/user/set-lang', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type':'application/json','X-CSRF-Token': getCsrfToken()||''},
      body: JSON.stringify({lang: code})
    });
    Toast.success('Language changed!');
  });
}
