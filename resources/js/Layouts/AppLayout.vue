<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

// ── user ──────────────────────────────────────────────────────────────
const page     = usePage()
const user     = computed(() => page.props.auth?.user ?? { name: 'User', email: '', role: 'operator' })
const initials = computed(() => {
    const parts = (user.value.name || 'U').trim().split(' ')
    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')
})
const roleLabel = computed(() => t(`role.${user.value.role}`, user.value.role))

// ── impersonate ───────────────────────────────────────────────────────
const impersonating = computed(() => page.props.impersonating ?? false)
const role = computed(() => user.value.role ?? 'operator')

// ── DB-driven permissions ─────────────────────────────────────────────
// auth.permissions from HandleInertiaRequests:
//   super_admin → { __all: true }
//   others      → { moduleName: { view, create, edit, delete, approve } }
const permissions = computed(() => page.props.auth?.permissions ?? {})

function canDo(module, action = 'view') {
    if (permissions.value.__all) return true          // super_admin
    return permissions.value[module]?.[action] === true
}

// Sidebar section visibility helpers
const showSystem    = computed(() => canDo('users') || canDo('departments') || role.value === 'super_admin')
const showWarehouse = computed(() => canDo('warehouses') || canDo('locations') || canDo('vehicles') || canDo('employees'))
const showInventory = computed(() => canDo('itemMaster') || canDo('goodsReceipt') || canDo('goodsIssue'))
const showReports   = computed(() => canDo('inventoryReport') || canDo('transactionLog'))

function stopImpersonate() { router.post(route('users.stop-impersonate')) }

// ── theme ─────────────────────────────────────────────────────────────
const isDark = ref(localStorage.getItem('theme') !== 'light')
function toggleTheme() {
    isDark.value = !isDark.value
    const theme = isDark.value ? 'dark' : 'light'
    document.documentElement.setAttribute('data-theme', theme)
    localStorage.setItem('theme', theme)
}
onMounted(() => {
    const saved = localStorage.getItem('theme') ?? 'dark'
    isDark.value = saved !== 'light'
    document.documentElement.setAttribute('data-theme', saved)
})

// ── language ──────────────────────────────────────────────────────────
function switchLang(lang) {
    locale.value = lang
    localStorage.setItem('wbn_locale', lang)
}

// ── sidebar ───────────────────────────────────────────────────────────
const collapsed  = ref(localStorage.getItem('sb') === '1')
const mobileOpen = ref(false)
function toggleSidebar() {
    if (window.innerWidth < 760) { mobileOpen.value = !mobileOpen.value }
    else { collapsed.value = !collapsed.value; localStorage.setItem('sb', collapsed.value ? '1' : '0') }
}

// ── dropdowns ─────────────────────────────────────────────────────────
const bellOpen = ref(false)
const userOpen = ref(false)
function toggleBell(e) { e.stopPropagation(); userOpen.value = false; bellOpen.value = !bellOpen.value }
function toggleUser(e) { e.stopPropagation(); bellOpen.value = false; userOpen.value = !userOpen.value }
function closeDropdowns() { bellOpen.value = false; userOpen.value = false }
onMounted(()  => document.addEventListener('click', closeDropdowns))
onUnmounted(() => document.removeEventListener('click', closeDropdowns))

// ── notifications ─────────────────────────────────────────────────────
const localNotifs = ref([...(page.props.auth?.notifications ?? [])])
watch(() => page.props.auth?.notifications, (val) => {
    localNotifs.value = [...(val ?? [])]
}, { deep: true })
const unreadCount = computed(() => localNotifs.value.filter(n => !n.is_read).length)

function timeAgo(dateStr) {
    if (!dateStr) return ''
    const diff = Date.now() - new Date(dateStr).getTime()
    const m = Math.floor(diff / 60000)
    if (m < 1)  return 'just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h / 24)}d ago`
}

function notifItemClass(type) {
    if (type === 'gr_arrived')   return 'dd-info'   // blue  — goods arrived, inspect needed
    if (type === 'gr_inspected') return 'dd-amber'  // amber — awaiting supervisor approval
    if (type === 'gr_completed') return 'dd-ok'     // green — stock updated, all done
    return 'dd-info'
}

// Get CSRF token from meta tag (used for fetch-based API calls)
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

function markNotifRead(notif) {
    bellOpen.value = false
    // Mark as read via fetch (not router.post — avoids JSON response issue)
    if (!notif.is_read) {
        notif.is_read = true
        fetch(route('notifications.read', notif.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        }).catch(() => {})
    }
    // Navigate to relevant page
    if (notif.data?.route && notif.data.route.startsWith('/')) {
        router.visit(notif.data.route)
    } else if (notif.data?.gi_id) {
        router.visit('/goods-issues/' + notif.data.gi_id)
    } else if (notif.data?.gr_id) {
        router.visit('/goods-receipts/' + notif.data.gr_id)
    }
}

function markAllRead() {
    localNotifs.value.forEach(n => (n.is_read = true))
    // Use fetch instead of router.post — controller returns JSON, not Inertia response
    fetch(route('notifications.read-all'), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
    }).catch(() => {})
}

// ── date ──────────────────────────────────────────────────────────────
const dateStr = computed(() => {
    const d  = new Date()
    const wk = ['SUN','MON','TUE','WED','THU','FRI','SAT'][d.getDay()]
    const mo = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'][d.getMonth()]
    return `${wk} · ${String(d.getDate()).padStart(2,'0')} ${mo} ${d.getFullYear()}`
})

function logout() { router.post(route('logout')) }
</script>
<template>
<div class="wms-app-wrap">
  <!-- ── IMPERSONATE BANNER ─────────────────────────────────────────── -->
  <div v-if="impersonating" class="impersonate-bar">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span>Viewing as <strong>{{ user.name }}</strong> ({{ roleLabel }})</span>
    <button class="imp-stop" type="button" @click="stopImpersonate">Return to Admin</button>
  </div>
<div class="wms-app">

  <!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
  <aside class="wms-sidebar" :class="{ collapsed, 'mobile-open': mobileOpen }">
    <div class="sb-top">
      <div class="brand">
        <div class="brand-logo"><img src="/assets/wbn-logo.png" alt="WBN" /></div>
        <div class="brand-text">
          <div class="brand-name">Weda Bay Nickel<span class="wms-badge">WMS</span></div>
          <div class="brand-sub">{{ $t('nav.warehouseMgmt') }}</div>
        </div>
      </div>
      <button class="sb-toggle" @click="toggleSidebar" type="button" aria-label="Toggle sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      </button>
    </div>

    <nav class="sb-nav">
      <div class="nav-section">
        <div class="nav-section-label">{{ $t('sec.main') }}</div>
        <Link class="nav-item" :class="{ active: route().current('dashboard') }" :href="route('dashboard')" :data-tip="$t('menu.dashboard')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          <span class="lbl">{{ $t('menu.dashboard') }}</span>
        </Link>
      </div>

      <!-- System Management — users / departments / permissions -->
      <div v-if="showSystem" class="nav-section">
        <div class="nav-section-label">{{ $t('sec.system') }}</div>
        <Link v-if="canDo('users')" class="nav-item" :class="{ active: route().current('users.*') }" :href="route('users.index')" :data-tip="$t('menu.users')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span class="lbl">{{ $t('menu.users') }}</span>
        </Link>
        <Link v-if="canDo('departments')" class="nav-item" :class="{ active: route().current('departments.*') }" :href="route('departments.index')" :data-tip="$t('menu.departments')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01M9 12h.01M9 15h.01M9 18h.01M14 9h.01M14 12h.01M14 15h.01M14 18h.01"/></svg>
          <span class="lbl">{{ $t('menu.departments') }}</span>
        </Link>
        <!-- Permissions page — super_admin only -->
        <Link v-if="role === 'super_admin'" class="nav-item" :class="{ active: route().current('permissions.*') }" :href="route('permissions.index')" :data-tip="$t('menu.permissions')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          <span class="lbl">{{ $t('menu.permissions') }}</span>
        </Link>
      </div>

      <!-- Warehouse & Rack Management -->
      <div v-if="showWarehouse" class="nav-section">
        <div class="nav-section-label">{{ $t('sec.warehouse') }}</div>
        <Link v-if="canDo('warehouses')" class="nav-item" :class="{ active: route().current('warehouses.*') }" :href="route('warehouses.index')" :data-tip="$t('menu.warehouses')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg>
          <span class="lbl">{{ $t('menu.warehouses') }}</span>
        </Link>
        <Link v-if="canDo('locations')" class="nav-item" :class="{ active: route().current('locations.*') }" :href="route('locations.index')" :data-tip="$t('menu.rackManagement')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
          <span class="lbl">{{ $t('menu.rackManagement') }}</span>
        </Link>
        <Link v-if="canDo('vehicles')" class="nav-item" :class="{ active: route().current('vehicles.*') }" :href="route('vehicles.index')" :data-tip="$t('menu.vehicles')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          <span class="lbl">{{ $t('menu.vehicles') }}</span>
        </Link>
        <Link v-if="canDo('employees')" class="nav-item" :class="{ active: route().current('employees.*') }" :href="route('employees.index')" :data-tip="$t('menu.employees')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span class="lbl">{{ $t('menu.employees') }}</span>
        </Link>
      </div>

      <!-- Inventory -->
      <div v-if="showInventory" class="nav-section">
        <div class="nav-section-label">{{ $t('sec.inventory') }}</div>
        <Link v-if="canDo('itemMaster')" class="nav-item" :class="{ active: route().current('items.*') }" :href="route('items.index')" :data-tip="$t('menu.itemMaster')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8L12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
          <span class="lbl">{{ $t('menu.itemMaster') }}</span>
        </Link>
        <Link v-if="canDo('itemMaster')" class="nav-item" :class="{ active: route().current('stock-input.*') }" :href="route('stock-input.index')" :data-tip="$t('menu.stockInput')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 16 4-4 4 4 4-6"/></svg>
          <span class="lbl">{{ $t('menu.stockInput') }}</span>
        </Link>
        <Link v-if="canDo('goodsReceipt')" class="nav-item" :class="{ active: route().current('gr.*') }" :href="route('gr.index')" :data-tip="$t('menu.goodsReceipt')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="1"/><path d="m12 15 3 3 5-5"/></svg>
          <span class="lbl">{{ $t('menu.goodsReceipt') }}</span><span class="lbl-badge">GR</span>
        </Link>
        <Link v-if="canDo('goodsIssue')" class="nav-item" :class="{ active: route().current('gi.*') }" :href="route('gi.index')" :data-tip="$t('menu.goodsIssue')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="1"/><path d="m16 15-3 3-3-3"/></svg>
          <span class="lbl">{{ $t('menu.goodsIssue') }}</span><span class="lbl-badge">GI</span>
        </Link>
        <!-- Operator Scan — visible to operator + wh_admin -->
        <Link v-if="role === 'operator' || role === 'wh_admin'" class="nav-item" :class="{ active: route().current('operator.*') }" :href="route('operator.scan-list')" :data-tip="$t('menu.operatorScan')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
          <span class="lbl">{{ $t('menu.operatorScan') }}</span>
        </Link>
        <a class="nav-item" href="#" :data-tip="$t('menu.transfer')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3l4 4-4 4"/><path d="M21 7H8"/><path d="M7 21l-4-4 4-4"/><path d="M3 17h13"/></svg>
          <span class="lbl">{{ $t('menu.transfer') }}</span>
        </a>
      </div>

      <!-- Reports -->
      <div v-if="showReports" class="nav-section">
        <div class="nav-section-label">{{ $t('sec.reports') }}</div>
        <a v-if="canDo('inventoryReport')" class="nav-item" href="#" :data-tip="$t('menu.stockReport')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="7"/><rect x="12" y="7" width="3" height="11"/><rect x="17" y="13" width="3" height="5"/></svg>
          <span class="lbl">{{ $t('menu.stockReport') }}</span>
        </a>
        <a v-if="canDo('transactionLog')" class="nav-item" href="#" :data-tip="$t('menu.transactionLog')">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>
          <span class="lbl">{{ $t('menu.transactionLog') }}</span>
        </a>
      </div>
    </nav>

    <div class="sb-bottom">
      <div class="sb-avatar">{{ initials }}</div>
      <div class="sb-user-text">
        <div class="sb-user-name">{{ user.name }}</div>
        <div class="sb-user-role">{{ roleLabel }}</div>
      </div>
      <button v-if="!impersonating" class="icon-btn" @click="logout" :title="$t('nav.logout')" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </button>
    </div>
  </aside>

  <div v-if="mobileOpen" class="sb-overlay" @click="mobileOpen=false"></div>

  <!-- ── MAIN ─────────────────────────────────────────────────────────── -->

  <div class="wms-main">

    <header class="wms-navbar">
      <div class="nb-left">
        <button class="icon-btn" @click="toggleSidebar" type="button" :aria-label="$t('nav.search')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div>
          <div class="nb-title"><slot name="title">{{ $t('menu.dashboard') }}</slot></div>
          <div class="nb-crumbs">
            <span>{{ $t('nav.home') }}</span>
            <span class="sep">/</span>
            <span class="here"><slot name="breadcrumb">{{ $t('menu.dashboard') }}</slot></span>
          </div>
        </div>
      </div>

      <div class="nb-right">
        <button class="icon-btn" type="button" :aria-label="$t('nav.search')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </button>

        <div class="lang-switch" role="tablist">
          <button v-for="l in ['en','zh','id']" :key="l" type="button"
            :class="{ active: locale === l }" @click="switchLang(l)">
            {{ l === 'zh' ? '中文' : l.toUpperCase() }}
          </button>
        </div>

        <button class="theme-toggle" type="button" @click="toggleTheme" :aria-label="isDark ? 'Light mode' : 'Dark mode'">
          <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
        </button>

        <div class="dd-wrap" style="position:relative">
          <button class="icon-btn nb-bell" @click="toggleBell" type="button" :aria-label="$t('nav.notifications')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span v-if="unreadCount > 0" class="nb-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </button>
          <div class="dd-menu notif-dd" :class="{ open: bellOpen }" @click.stop>
            <div class="dd-head">
              <span class="dd-head-title">{{ $t('nav.notifications') }}</span>
              <div class="dd-head-right">
                <span v-if="unreadCount > 0" class="dd-pill">{{ $t('nav.nNew', { n: unreadCount }) }}</span>
                <button v-if="unreadCount > 0" class="dd-read-all-btn" type="button" @click.stop="markAllRead">{{ $t('nav.markAllRead') }}</button>
              </div>
            </div>
            <!-- empty state -->
            <div v-if="localNotifs.length === 0" class="dd-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
              <span>{{ $t('nav.noNotifications') }}</span>
            </div>
            <!-- notification list -->
            <div v-else class="notif-list">
              <div v-for="notif in localNotifs" :key="notif.id"
                   class="dd-item" :class="[notifItemClass(notif.type), { 'dd-unread': !notif.is_read }]"
                   @click="markNotifRead(notif)">
                <div class="dd-ico">
                  <!-- gr_arrived → package/box icon (blue) -->
                  <svg v-if="notif.type === 'gr_arrived'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8 12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
                  <!-- gr_inspected → clipboard check icon (amber) -->
                  <svg v-else-if="notif.type === 'gr_inspected'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
                  <!-- gr_completed → trending-up / stock icon (green) -->
                  <svg v-else-if="notif.type === 'gr_completed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 16 4-4 4 4 4-6"/></svg>
                  <!-- default → info icon -->
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                </div>
                <div class="notif-body">
                  <div class="dd-item-title">{{ notif.title }}</div>
                  <div class="dd-item-meta">{{ notif.message }}</div>
                  <div class="notif-time">{{ timeAgo(notif.created_at) }}</div>
                </div>
                <div v-if="!notif.is_read" class="notif-dot"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="nb-divider"></div>

        <div class="dd-wrap" style="position:relative">
          <button class="user-btn" @click="toggleUser" type="button">
            <div class="sb-avatar" style="width:28px;height:28px;border-radius:999px;font-size:11px">{{ initials }}</div>
            <span class="un">{{ user.name }}</span>
            <svg style="width:12px;height:12px;color:var(--fg-2);flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div class="dd-menu" :class="{ open: userOpen }" @click.stop>
            <div class="dd-user-head">
              <div class="sb-avatar" style="width:38px;height:38px;border-radius:10px;font-size:13px">{{ initials }}</div>
              <div><div class="dd-nm">{{ user.name }}</div><div class="dd-em">{{ user.email }}</div></div>
            </div>
            <div class="dd-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>{{ $t('nav.profile') }}</span>
            </div>
            <div class="dd-row">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
              <span>{{ $t('nav.settings') }}</span>
            </div>
            <template v-if="!impersonating">
              <div class="dd-sep"></div>
              <div class="dd-row dd-danger" @click="logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>{{ $t('nav.logout') }}</span>
              </div>
            </template>
          </div>
        </div>
      </div>
    </header>

    <main class="wms-content">
      <slot :dateStr="dateStr" />
    </main>
  </div>
</div>
</div>
</template>
<style>
/* ── impersonate banner ─────────────────────────────────────────────── */
.wms-app-wrap{display:flex;flex-direction:column;min-height:100vh}
.impersonate-bar{
  display:flex;align-items:center;gap:10px;
  background:linear-gradient(90deg,#92400e,#b45309);
  color:#fef3c7;font-size:13px;font-weight:600;
  padding:8px 20px;z-index:50;flex-shrink:0;
}
.impersonate-bar svg{flex-shrink:0;color:#fef3c7}
.impersonate-bar span{flex:1}
.imp-stop{
  margin-left:auto;appearance:none;border:1px solid rgba(254,243,199,.5);
  background:rgba(254,243,199,.12);color:#fef3c7;
  padding:5px 14px;border-radius:6px;cursor:pointer;
  font-size:12px;font-weight:700;font-family:inherit;
  transition:background 180ms ease;
}
.imp-stop:hover{background:rgba(254,243,199,.25)}

/* ── CSS variables ──────────────────────────────────────────────────── */
:root {
  --blue-900:#1e3a8a;--blue-700:#1d4ed8;--blue-500:#3b82f6;--blue-400:#60a5fa;
  --orange-500:#f97316;--orange-400:#fb923c;--amber-500:#f59e0b;--amber-400:#fbbf24;
  --emerald:#10b981;--rose:#ef4444;--yellow:#eab308;

  --bg:#0a0f1a;--surface:#0d1117;--surface-2:#111827;--surface-3:#1e2535;
  --border:#1e2535;--border-2:#2a3550;--border-soft:#1a2236;
  --fg:#e6edf7;--fg-2:#94a3b8;--fg-dim:#64748b;
  --hover:rgba(59,130,246,.08);--active-bg:rgba(249,115,22,.1);
  --shadow-sm:0 1px 2px rgba(0,0,0,.4);
  --shadow-md:0 6px 18px -8px rgba(0,0,0,.6);
  --shadow-lg:0 18px 40px -16px rgba(0,0,0,.6);
  --logo-bg:#ffffff;
}
:root[data-theme="light"] {
  --bg:#f9fafb;--surface:#ffffff;--surface-2:#f9fafb;--surface-3:#ffffff;
  --border:#e5e7eb;--border-2:#d1d5db;--border-soft:#f1f5f9;
  --fg:#0b1530;--fg-2:#475569;--fg-dim:#94a3b8;
  --hover:rgba(59,130,246,.06);--active-bg:rgba(249,115,22,.08);
  --shadow-sm:0 1px 2px rgba(15,23,42,.06);
  --shadow-md:0 6px 18px -8px rgba(15,23,42,.12);
  --shadow-lg:0 18px 40px -16px rgba(15,23,42,.18);
  --logo-bg:#f3f4f6;
}
.wms-app {
  display:flex;min-height:100vh;
  font-family:'Inter',system-ui,-apple-system,sans-serif;
  -webkit-font-smoothing:antialiased;
  background:var(--bg);color:var(--fg);
  transition:background 250ms ease,color 250ms ease;
}

/* ── scrollbar ─────────────────────────────────────────────────────── */
.wms-app ::-webkit-scrollbar{width:10px;height:10px}
.wms-app ::-webkit-scrollbar-track{background:transparent}
.wms-app ::-webkit-scrollbar-thumb{background:var(--border-2);border-radius:8px}
.wms-app ::-webkit-scrollbar-thumb:hover{background:var(--fg-dim)}

/* ── sidebar ───────────────────────────────────────────────────────── */
.wms-sidebar{
  width:240px;flex-shrink:0;
  background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  transition:width 250ms ease;
  position:sticky;top:0;height:100vh;overflow:hidden;z-index:30;
}
.wms-sidebar.collapsed{width:64px}
.wms-main{flex:1;display:flex;flex-direction:column;min-width:0}

.sb-top{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px;border-bottom:1px solid var(--border);
  height:64px;gap:8px;
}
.brand{display:flex;align-items:center;gap:10px;min-width:0;overflow:hidden}
.brand-logo{
  background:var(--logo-bg);border-radius:10px;
  padding:5px 7px;display:flex;align-items:center;flex-shrink:0;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.4);
}
.brand-logo img{height:24px;width:auto;display:block}
.brand-text{min-width:0;line-height:1.2;white-space:nowrap;overflow:hidden}
.brand-name{font-size:13px;font-weight:700;letter-spacing:-.01em}
.brand-sub{font-size:10px;color:var(--fg-2);font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin-top:2px}
.wms-badge{
  display:inline-flex;padding:2px 6px;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  color:#fff;font-size:9px;font-weight:700;border-radius:4px;letter-spacing:.06em;
  margin-left:6px;vertical-align:middle;
}
.sb-toggle{
  appearance:none;border:1px solid var(--border);background:var(--surface);
  width:28px;height:28px;border-radius:7px;cursor:pointer;
  color:var(--fg-2);display:grid;place-items:center;flex-shrink:0;
  transition:background 200ms ease,color 200ms ease,transform 250ms ease;
}
.sb-toggle:hover{color:var(--fg);background:var(--hover)}
.sb-toggle svg{width:14px;height:14px;transition:transform 250ms ease}
.wms-sidebar.collapsed .sb-toggle svg{transform:rotate(180deg)}
.wms-sidebar.collapsed .brand-text,.wms-sidebar.collapsed .wms-badge,.wms-sidebar.collapsed .brand-logo{display:none}
.wms-sidebar.collapsed .sb-top{padding:8px;height:48px;justify-content:center}

.sb-nav{flex:1;overflow-y:auto;overflow-x:hidden;padding:14px 10px}
.nav-section{margin-bottom:16px}
.nav-section-label{
  font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--fg-dim);padding:0 12px 8px;margin-top:4px;
  transition:opacity 200ms ease;white-space:nowrap;
}
.wms-sidebar.collapsed .nav-section-label{opacity:0;height:0;padding:0;margin:0;overflow:hidden}
.nav-item{
  display:flex;align-items:center;gap:12px;
  padding:9px 12px;border-radius:8px;
  color:var(--fg-2);font-size:13.5px;font-weight:500;
  text-decoration:none;cursor:pointer;position:relative;margin:1px 0;
  white-space:nowrap;transition:background 180ms ease,color 180ms ease;
}
.nav-item:hover{background:var(--hover);color:var(--fg)}
.nav-item .ico{width:18px;height:18px;flex-shrink:0;color:currentColor}
.nav-item .lbl{flex:1;overflow:hidden;text-overflow:ellipsis}
.nav-item .lbl-badge{
  font-size:9.5px;font-weight:700;background:var(--surface-3);
  color:var(--fg-2);padding:2px 6px;border-radius:4px;
  border:1px solid var(--border-2);letter-spacing:.04em;
}
.wms-sidebar.collapsed .lbl,.wms-sidebar.collapsed .lbl-badge{opacity:0;width:0;max-width:0;overflow:hidden;padding:0;border-width:0;margin:0}
.wms-sidebar.collapsed .nav-item{padding:10px 0;justify-content:center;gap:0}
.nav-item.active{color:var(--orange-500);background:var(--active-bg);font-weight:600}
.nav-item.active::before{
  content:"";position:absolute;left:-10px;top:6px;bottom:6px;
  width:3px;background:var(--orange-500);border-radius:0 3px 3px 0;
}
.wms-sidebar.collapsed .nav-item.active::before{left:0}
.nav-item[data-tip]:hover::after{
  content:attr(data-tip);
  position:absolute;left:calc(100% + 12px);top:50%;transform:translateY(-50%);
  background:var(--surface-3);color:var(--fg);
  border:1px solid var(--border-2);padding:5px 9px;
  border-radius:6px;font-size:12px;font-weight:500;
  white-space:nowrap;pointer-events:none;box-shadow:var(--shadow-md);z-index:50;
}
.wms-sidebar:not(.collapsed) .nav-item[data-tip]:hover::after{display:none}

.sb-bottom{
  border-top:1px solid var(--border);padding:12px 10px;
  display:flex;align-items:center;gap:10px;
}
.sb-avatar{
  width:32px;height:32px;border-radius:8px;
  background:linear-gradient(135deg,var(--blue-500),var(--blue-900));
  color:#fff;font-weight:700;font-size:12px;
  display:grid;place-items:center;flex-shrink:0;
}
.sb-user-text{flex:1;min-width:0;line-height:1.2;overflow:hidden;white-space:nowrap}
.sb-user-name{font-size:12.5px;font-weight:600}
.sb-user-role{font-size:9.5px;color:var(--fg-2);font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-top:2px}
.wms-sidebar.collapsed .sb-user-text,.wms-sidebar.collapsed .sb-bottom .icon-btn{display:none}
.wms-sidebar.collapsed .sb-bottom{justify-content:center}

.sb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:25}

/* ── navbar ────────────────────────────────────────────────────────── */
.wms-navbar{
  height:64px;background:var(--surface);border-bottom:1px solid var(--border);
  box-shadow:var(--shadow-sm);display:flex;align-items:center;
  padding:0 24px;gap:16px;position:sticky;top:0;z-index:20;
}
.nb-left{display:flex;align-items:center;gap:14px;flex:1;min-width:0}
.nb-title{font-size:18px;font-weight:700;letter-spacing:-.015em}
.nb-crumbs{font-size:12px;color:var(--fg-2);display:flex;align-items:center;gap:6px;flex-wrap:nowrap;white-space:nowrap}
.nb-crumbs .sep{opacity:.5}
.nb-crumbs .here{color:var(--fg);display:flex;align-items:center;gap:6px}
.nb-right{display:flex;align-items:center;gap:8px}
.nb-divider{width:1px;height:22px;background:var(--border);margin:0 4px}

/* icon btn */
.icon-btn{
  appearance:none;border:1px solid var(--border);background:transparent;
  width:32px;height:32px;border-radius:7px;cursor:pointer;
  color:var(--fg-2);display:grid;place-items:center;flex-shrink:0;
  transition:background 180ms ease,color 180ms ease,border-color 180ms ease;
}
.icon-btn:hover{background:var(--hover);color:var(--fg)}
.icon-btn svg{width:15px;height:15px}

/* bell badge */
.nb-bell{position:relative}
.nb-badge{
  position:absolute;top:-3px;right:-3px;
  background:var(--orange-500);color:#fff;
  font-size:9.5px;font-weight:700;
  width:16px;height:16px;border-radius:999px;
  display:grid;place-items:center;
  border:2px solid var(--surface);line-height:1;
}

/* lang switch */
.lang-switch{
  display:inline-flex;background:var(--surface-2);
  border:1px solid var(--border);border-radius:999px;padding:3px;gap:2px;
}
.lang-switch button{
  appearance:none;border:0;background:transparent;color:var(--fg-2);
  padding:4px 10px;font-size:11px;font-weight:600;letter-spacing:.02em;
  border-radius:999px;cursor:pointer;font-family:inherit;
  transition:color 200ms ease,background 200ms ease,box-shadow 200ms ease;
}
.lang-switch button.active{
  color:#fff;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  box-shadow:0 4px 10px -3px rgba(249,115,22,.55),inset 0 1px 0 rgba(255,255,255,.25);
}
.lang-switch button:hover:not(.active){color:var(--fg)}

/* theme toggle */
.theme-toggle{
  appearance:none;border:1px solid var(--border);background:var(--surface-2);
  border-radius:999px;width:50px;height:28px;position:relative;cursor:pointer;
  transition:background 200ms ease,border-color 200ms ease;
}
.theme-toggle::before{
  content:"";position:absolute;top:2px;left:2px;width:22px;height:22px;
  border-radius:999px;background:linear-gradient(180deg,#fff,#d8def0);
  box-shadow:0 2px 6px rgba(0,0,0,.25);
  transition:transform 320ms cubic-bezier(.5,1.4,.4,1),background 320ms ease;
}
:root[data-theme="light"] .theme-toggle::before{
  transform:translateX(22px);
  background:linear-gradient(180deg,var(--amber-400),var(--orange-500));
}
.theme-toggle .icon-moon,.theme-toggle .icon-sun{
  position:absolute;top:50%;transform:translateY(-50%);
  width:11px;height:11px;color:var(--fg-2);
}
.theme-toggle .icon-moon{right:6px}
.theme-toggle .icon-sun{left:6px}

/* dropdowns */
.dd-menu{
  position:absolute;right:0;top:calc(100% + 8px);
  min-width:280px;background:var(--surface);
  border:1px solid var(--border);border-radius:12px;
  box-shadow:var(--shadow-lg);padding:8px;z-index:40;
  opacity:0;transform:translateY(-6px) scale(.98);pointer-events:none;
  transition:opacity 180ms ease,transform 180ms ease;
}
.dd-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
.dd-head{
  padding:8px 10px 10px;border-bottom:1px solid var(--border-soft);
  margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;
}
.dd-head-title{font-size:13px;font-weight:700}
.dd-pill{
  font-size:10px;font-weight:700;color:#fff;
  background:var(--orange-500);padding:2px 7px;border-radius:999px;
}
.dd-item{
  display:flex;align-items:flex-start;gap:10px;
  padding:9px 10px;border-radius:8px;cursor:pointer;
  transition:background 180ms ease;
}
.dd-item:hover{background:var(--hover)}
.dd-ico{
  width:32px;height:32px;border-radius:8px;
  display:grid;place-items:center;flex-shrink:0;
}
.dd-ico svg{width:16px;height:16px}
.dd-warn   .dd-ico{background:rgba(239,68,68,.12);color:var(--rose)}
.dd-info   .dd-ico{background:rgba(59,130,246,.12);color:var(--blue-500)}
.dd-ok     .dd-ico{background:rgba(16,185,129,.12);color:var(--emerald)}
.dd-amber  .dd-ico{background:rgba(245,158,11,.12);color:var(--amber-500)}
.dd-item-title{font-size:13px;font-weight:600;line-height:1.35}
.dd-item-meta{font-size:11.5px;color:var(--fg-2);margin-top:2px}
.dd-foot{border-top:1px solid var(--border-soft);margin-top:6px;padding-top:8px;text-align:center}
.dd-link{color:var(--orange-500);font-size:12.5px;font-weight:600;text-decoration:none}
.dd-link:hover{text-decoration:underline}
.dd-sep{height:1px;background:var(--border-soft);margin:6px 0}
.dd-row{
  display:flex;align-items:center;gap:10px;
  padding:9px 10px;border-radius:8px;cursor:pointer;
  font-size:13px;font-weight:500;color:var(--fg);
  transition:background 180ms ease;
}
.dd-row:hover{background:var(--hover)}
.dd-row svg{width:14px;height:14px;color:var(--fg-2)}
.dd-danger{color:var(--rose)}
.dd-danger svg{color:var(--rose)}
.dd-user-head{
  display:flex;align-items:center;gap:10px;
  padding:6px 10px 12px;border-bottom:1px solid var(--border-soft);margin-bottom:6px;
}
.dd-nm{font-size:13.5px;font-weight:700;line-height:1.25}
.dd-em{font-size:11.5px;color:var(--fg-2)}

/* user button */
.user-btn{
  display:flex;align-items:center;gap:10px;
  padding:4px 10px 4px 4px;
  background:transparent;border:1px solid transparent;
  border-radius:999px;cursor:pointer;color:var(--fg);
  transition:background 200ms ease,border-color 200ms ease;
}
.user-btn:hover{background:var(--hover);border-color:var(--border)}
.user-btn .un{font-size:13px;font-weight:600;white-space:nowrap}

/* ── content area ──────────────────────────────────────────────────── */
.wms-content{flex:1;padding:24px;background:var(--bg);display:flex;flex-direction:column;gap:20px}

/* ── responsive ────────────────────────────────────────────────────── */
@media(max-width:760px){
  .wms-sidebar{position:fixed;left:-260px;transition:left 250ms ease,width 250ms ease}
  .wms-sidebar.mobile-open{left:0}
  .nb-title{font-size:15px}
  .nb-crumbs{display:none}
  .lang-switch{display:none}
  .user-btn .un{display:none}
}

/* ── notification dropdown ─────────────────────────────────────────── */
.notif-dd{min-width:320px}
.dd-head-right{display:flex;align-items:center;gap:6px;flex-shrink:0}
.dd-read-all-btn{
  appearance:none;border:0;background:transparent;
  color:var(--orange-500);font-size:11px;font-weight:600;
  cursor:pointer;font-family:inherit;padding:2px 4px;border-radius:4px;
  transition:background 180ms ease;white-space:nowrap;
}
.dd-read-all-btn:hover{background:var(--hover)}
.notif-list{max-height:360px;overflow-y:auto}
.notif-body{flex:1;min-width:0}
.notif-time{font-size:10.5px;color:var(--fg-dim);margin-top:3px}
.notif-dot{
  width:8px;height:8px;border-radius:999px;
  background:var(--orange-500);flex-shrink:0;align-self:center;
}
.dd-unread{background:rgba(249,115,22,.04)}
.dd-unread:hover{background:rgba(249,115,22,.08)}
.dd-empty{
  display:flex;flex-direction:column;align-items:center;gap:10px;
  padding:28px 16px;color:var(--fg-dim);font-size:12.5px;
}
.dd-empty svg{width:32px;height:32px;opacity:.4}
</style>