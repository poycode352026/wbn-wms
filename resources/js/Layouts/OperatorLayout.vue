<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const page = usePage()
const user = computed(() => page.props.auth?.user ?? { name: 'Operator', role: 'operator' })

const initials = computed(() => {
    const parts = (user.value.name || 'O').trim().split(' ')
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase()
})

// ── theme ──────────────────────────────────────────────────────────────────
const isDark = ref(true)
onMounted(() => {
    const saved = localStorage.getItem('theme') ?? 'dark'
    isDark.value = saved !== 'light'
    document.documentElement.setAttribute('data-theme', saved)
})
function toggleTheme() {
    isDark.value = !isDark.value
    const th = isDark.value ? 'dark' : 'light'
    document.documentElement.setAttribute('data-theme', th)
    localStorage.setItem('theme', th)
}

// ── language ───────────────────────────────────────────────────────────────
const showLangMenu = ref(false)
function switchLang(lang) {
    locale.value = lang
    localStorage.setItem('wbn_locale', lang)
    showLangMenu.value = false
}

// ── logout ─────────────────────────────────────────────────────────────────
function logout() { router.post(route('logout')) }

// ── scan button — set flag then navigate so camera opens on scan-list ──────
function goToScan() {
    sessionStorage.setItem('open_camera', '1')
    router.visit(route('operator.scan-list'))
}

// ── active tab ─────────────────────────────────────────────────────────────
function isActive(path) {
    const p = window.location.pathname
    if (path === '/operator/scan') return p === '/operator/scan' || p.startsWith('/operator/scan/')
    if (path === '/operator/history') return p === '/operator/history'
    return p.startsWith(path)
}
</script>

<template>
  <div class="op-root" @click="showLangMenu = false">

    <!-- ── Sticky Top Header ─────────────────────────────────────────── -->
    <header class="op-header">
      <div class="op-header-inner">
        <!-- Brand -->
        <div class="op-brand">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink:0">
            <rect width="24" height="24" rx="6" fill="var(--orange-500)"/>
            <path d="M7 17V9l5-3 5 3v8" stroke="#fff" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="10" y="13" width="4" height="4" rx="1" fill="#fff"/>
          </svg>
          <span class="op-brand-text">WBN WMS</span>
          <span class="op-role-chip">Operator</span>
        </div>

        <!-- User + utils -->
        <div class="op-header-right">
          <div class="op-avatar">{{ initials }}</div>

          <!-- Lang -->
          <div style="position:relative">
            <button class="util-btn lang-btn" @click.stop="showLangMenu = !showLangMenu" type="button">
              {{ locale.toUpperCase() }}
            </button>
            <div v-if="showLangMenu" class="lang-menu">
              <button @click.stop="switchLang('id')" :class="{ active: locale === 'id' }">ID</button>
              <button @click.stop="switchLang('en')" :class="{ active: locale === 'en' }">EN</button>
              <button @click.stop="switchLang('zh')" :class="{ active: locale === 'zh' }">中文</button>
            </div>
          </div>

          <!-- Logout -->
          <button class="util-btn logout-btn" @click="logout" type="button" title="Logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2" width="15" height="15" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- ── Page title bar (from slot) ────────────────────────────────── -->
    <div v-if="$slots.title" class="op-titlebar">
      <slot name="title" />
    </div>

    <!-- ── Scrollable Content ─────────────────────────────────────────── -->
    <main class="op-main">
      <slot />
    </main>

    <!-- ── Sticky Bottom Nav ──────────────────────────────────────────── -->
    <nav class="op-footer-nav">
      <!-- Jobs list -->
      <a :href="route('operator.scan-list')"
         class="op-nav-tab"
         :class="{ active: isActive('/operator/scan') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          width="22" height="22" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 11l3 3L22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        <span>{{ $t('operator.navJobs') }}</span>
      </a>

      <!-- Scan (center floating) — sets sessionStorage flag then navigates to scan-list to open camera -->
      <a :href="route('operator.scan-list')"
         class="op-nav-tab op-scan-tab"
         :class="{ active: isActive('/operator/scan/') }"
         @click.prevent="goToScan">
        <div class="op-scan-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            width="24" height="24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>
        <span>{{ $t('operator.navScan') }}</span>
      </a>

      <!-- History -->
      <a :href="route('operator.history')"
         class="op-nav-tab"
         :class="{ active: isActive('/operator/history') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          width="22" height="22" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>{{ $t('operator.navHistory') }}</span>
      </a>
    </nav>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; }

.op-root {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  background: var(--surface-2);
  font-family: inherit;
}

/* ── Header ──────────────────────────────────────────────────────────── */
.op-header {
  position: sticky;
  top: 0;
  z-index: 50;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 1px 8px rgba(0,0,0,.08);
}

.op-header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  max-width: 560px;
  margin: 0 auto;
  padding: 10px 14px;
}

.op-brand {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-shrink: 0;
}

.op-brand-text {
  font-size: 13px;
  font-weight: 800;
  color: var(--fg);
  letter-spacing: -.01em;
}

.op-role-chip {
  font-size: 9.5px;
  font-weight: 700;
  background: rgba(249,115,22,.12);
  color: var(--orange-500);
  padding: 2px 7px;
  border-radius: 20px;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.op-header-right {
  display: flex;
  align-items: center;
  gap: 5px;
}

.op-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--orange-400);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  text-transform: uppercase;
  margin-right: 2px;
}

.util-btn {
  appearance: none;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--fg-2);
  border-radius: 7px;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 10px;
  font-weight: 700;
  font-family: inherit;
  transition: background 150ms, color 150ms;
}

.util-btn:hover { background: var(--hover); color: var(--fg); }
.lang-btn { width: auto; padding: 0 7px; letter-spacing: .04em; }
.logout-btn { color: #f87171; border-color: rgba(248,113,113,.3); }
.logout-btn:hover { background: rgba(239,68,68,.1); color: #f87171; }

.lang-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,.14);
  overflow: hidden;
  z-index: 200;
  min-width: 68px;
}
.lang-menu button {
  display: block; width: 100%; text-align: center;
  appearance: none; border: 0; background: transparent;
  color: var(--fg-2); font-size: 12px; font-weight: 600;
  padding: 8px 12px; cursor: pointer; font-family: inherit;
  transition: background 150ms, color 150ms;
}
.lang-menu button:hover { background: var(--hover); color: var(--fg); }
.lang-menu button.active { color: var(--orange-500); background: rgba(249,115,22,.08); }

/* ── Title bar ───────────────────────────────────────────────────────── */
.op-titlebar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 700;
  color: var(--fg-2);
  max-width: 560px;
  width: 100%;
  margin: 0 auto;
}

/* ── Main ────────────────────────────────────────────────────────────── */
.op-main {
  flex: 1;
  max-width: 560px;
  width: 100%;
  margin: 0 auto;
  padding: 0 0 80px;
}

/* ── Bottom Nav ──────────────────────────────────────────────────────── */
.op-footer-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  box-shadow: 0 -2px 12px rgba(0,0,0,.1);
  display: flex;
  justify-content: space-around;
  align-items: stretch;
  z-index: 50;
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

.op-nav-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  flex: 1;
  padding: 9px 8px 8px;
  text-decoration: none;
  color: var(--fg-2);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .02em;
  transition: color 150ms;
}

.op-nav-tab svg { stroke: currentColor; transition: stroke 150ms; }
.op-nav-tab.active { color: var(--orange-500); }
.op-nav-tab:hover  { color: var(--fg); }

/* Scan tab — highlighted circle button */
.op-scan-tab { position: relative; }

.op-scan-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--orange-500);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: -18px;
  box-shadow: 0 4px 14px rgba(249,115,22,.45);
  transition: opacity 150ms, box-shadow 150ms;
}

.op-scan-icon svg { stroke: #fff; }
.op-scan-tab:hover .op-scan-icon { opacity: .88; }
.op-scan-tab.active .op-scan-icon { box-shadow: 0 4px 18px rgba(249,115,22,.65); }
</style>
