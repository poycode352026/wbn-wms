<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const page = usePage()
const user = computed(() => page.props.auth?.user ?? { name: 'Employee', role: 'employee' })

const initials = computed(() => {
    const parts = (user.value.name || 'E').trim().split(' ')
    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')
})

// ── theme ──────────────────────────────────────────────────────────────
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

// ── language ───────────────────────────────────────────────────────────
const showLangMenu = ref(false)
function switchLang(lang) {
    locale.value = lang
    localStorage.setItem('wbn_locale', lang)
    showLangMenu.value = false
}

// ── logout ─────────────────────────────────────────────────────────────
function logout() { router.post(route('logout')) }

// ── active nav detection ───────────────────────────────────────────────
function isActive(path) {
    if (path === '/portal') return window.location.pathname === '/portal'
    return window.location.pathname.startsWith(path)
}
</script>

<template>
    <div class="emp-root" @click="showLangMenu = false">

        <!-- ── Sticky Top Header ──────────────────────────────────────── -->
        <header class="emp-header">
            <div class="emp-header-inner">
                <!-- Brand -->
                <Link href="/portal" class="emp-brand">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink:0">
                        <rect width="24" height="24" rx="6" fill="var(--orange-500)"/>
                        <path d="M7 17V9l5-3 5 3v8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="10" y="13" width="4" height="4" rx="1" fill="#fff"/>
                    </svg>
                    <span class="emp-brand-text">WBN WMS</span>
                </Link>

                <!-- Employee info (compact) -->
                <div class="emp-header-user">
                    <div class="emp-avatar-sm">{{ initials }}</div>
                    <span class="emp-name-sm">{{ user.name }}</span>
                </div>

                <!-- Utilities -->
                <div class="emp-header-utils">
                    <!-- Theme toggle -->
                    <button class="util-btn" @click.stop="toggleTheme" type="button" :title="isDark ? 'Light mode' : 'Dark mode'">
                        <svg v-if="isDark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <!-- Lang toggle -->
                    <div class="lang-wrap" style="position:relative">
                        <button class="util-btn lang-btn" @click.stop="showLangMenu = !showLangMenu" type="button">
                            {{ locale.toUpperCase() }}
                        </button>
                        <div v-if="showLangMenu" class="lang-menu">
                            <button @click.stop="switchLang('id')" :class="{ active: locale === 'id' }">ID</button>
                            <button @click.stop="switchLang('en')" :class="{ active: locale === 'en' }">EN</button>
                            <button @click.stop="switchLang('zh')" :class="{ active: locale === 'zh' }">中文</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Scrollable Content ─────────────────────────────────────── -->
        <main class="emp-main">
            <slot />
        </main>

        <!-- ── Sticky Bottom Footer Nav ──────────────────────────────── -->
        <nav class="emp-footer-nav">
            <Link href="/portal" class="emp-nav-tab" :class="{ active: isActive('/portal') }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>
                    <path d="M9 21V12h6v9"/>
                </svg>
                <span>{{ $t('portal.navHome') }}</span>
            </Link>
            <Link href="/portal/history" class="emp-nav-tab" :class="{ active: isActive('/portal/history') }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8v4l3 3"/>
                    <circle cx="12" cy="12" r="9"/>
                </svg>
                <span>{{ $t('portal.navHistory') }}</span>
            </Link>
            <Link href="/portal/profile" class="emp-nav-tab" :class="{ active: isActive('/portal/profile') }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>{{ $t('portal.navProfile') }}</span>
            </Link>
        </nav>

    </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; }

.emp-root {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
    background: var(--surface-2);
    font-family: inherit;
}

/* ── Top Header ──────────────────────────────────────────────────────── */
.emp-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 1px 8px rgba(0,0,0,.08);
}
.emp-header-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: 480px;
    margin: 0 auto;
    padding: 10px 16px;
}

.emp-brand {
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    flex-shrink: 0;
}
.emp-brand-text {
    font-size: 13px;
    font-weight: 800;
    color: var(--fg);
    letter-spacing: -.01em;
}

.emp-header-user {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 7px;
    overflow: hidden;
}
.emp-avatar-sm {
    width: 28px;
    height: 28px;
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
}
.emp-name-sm {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--fg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.emp-header-utils {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.util-btn {
    appearance: none;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--fg-2);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 10.5px;
    font-weight: 700;
    font-family: inherit;
    transition: background 180ms, color 180ms;
}
.util-btn:hover { background: var(--hover); color: var(--fg); }
.lang-btn { width: auto; padding: 0 8px; letter-spacing: .04em; }
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
    min-width: 72px;
}
.lang-menu button {
    display: block;
    width: 100%;
    text-align: center;
    appearance: none;
    border: 0;
    background: transparent;
    color: var(--fg-2);
    font-size: 12px;
    font-weight: 600;
    padding: 9px 14px;
    cursor: pointer;
    font-family: inherit;
    transition: background 150ms, color 150ms;
}
.lang-menu button:hover { background: var(--hover); color: var(--fg); }
.lang-menu button.active { color: var(--orange-500); background: rgba(249,115,22,.08); }

/* ── Main Content ────────────────────────────────────────────────────── */
.emp-main {
    flex: 1;
    max-width: 480px;
    width: 100%;
    margin: 0 auto;
    padding: 16px 14px 80px; /* 80px bottom for footer nav */
}

/* ── Bottom Footer Nav ───────────────────────────────────────────────── */
.emp-footer-nav {
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
.emp-nav-tab {
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
    transition: color 180ms;
}
.emp-nav-tab svg { stroke: currentColor; transition: stroke 180ms; }
.emp-nav-tab.active { color: var(--orange-500); }
.emp-nav-tab:hover { color: var(--fg); }
</style>
