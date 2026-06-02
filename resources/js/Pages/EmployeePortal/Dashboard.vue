<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    employee:          Object,
    drivenVehicles:    Array,
    today:             String,
    mandatoryStatus:   Array,   // employee PPE mandatory items
    lvMandatoryStatus: Array,   // LV equipment mandatory items (per vehicle)
    recentLogs:        Array,   // last 3 CooldownLog entries
})

// ── helpers ────────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function itemLocalName(item) {
    if (!item) return '—'
    if (locale.value === 'id' && item.name_id) return item.name_id
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    return item.name_en ?? '—'
}

// ── Mandatory item computed ─────────────────────────────────────────────
const mandatoryItems  = computed(() => props.mandatoryStatus ?? [])
const lvStatus        = computed(() => props.lvMandatoryStatus ?? [])

// Only items that are actually overdue are shown on the home dashboard
const overdueItems = computed(() => mandatoryItems.value.filter(ms => ms.overdue))

const overdueEmpCount = computed(() =>
    overdueItems.value.filter(ms => !ms.pending_request).length
)
const allEmpPending = computed(() =>
    overdueItems.value.length > 0 && overdueItems.value.every(ms => ms.pending_request)
)

// Any LV item overdue (for at least one vehicle)?
const hasAnyLvOverdue = computed(() =>
    lvStatus.value.some(lv => lv.items.some(ms => ms.overdue))
)

// ── Submit request ─────────────────────────────────────────────────────
function ajukan(itemId) {
    useForm({ items: [{ item_id: itemId, lv_id: null }] })
        .post(route('portal.submitRequest'), { preserveScroll: true })
}

function ajukanSemua() {
    const items = mandatoryItems.value
        .filter(ms => ms.overdue && !ms.pending_request)
        .map(ms => ({ item_id: ms.item.id, lv_id: null }))
    useForm({ items }).post(route('portal.submitRequest'), { preserveScroll: true })
}

function ajukanLv(itemId, lvId) {
    useForm({ items: [{ item_id: itemId, lv_id: lvId }] })
        .post(route('portal.submitRequest'), { preserveScroll: true })
}

// ── Recent logs ────────────────────────────────────────────────────────
const recentLogs = computed(() => props.recentLogs ?? [])

function logItemName(log) {
    const item = log.variant?.item
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? item.name_id ?? '—'
}

function logType(log) {
    if (log.lv) return log.lv.full_number ?? `LV-${log.lv.lv_number}`
    return t('portal.typePersonal')
}
</script>

<template>
  <EmployeeLayout>
    <template #title>{{ $t('portal.navHome') }}</template>

    <!-- ── Welcome card ──────────────────────────────────────────────── -->
    <div class="welcome-card">
      <div class="welcome-avatar">
        {{ (employee?.name ?? 'E').split(' ').slice(0,2).map(s => s[0]).join('').toUpperCase() }}
      </div>
      <div class="welcome-info">
        <div class="welcome-name">{{ employee?.name }}</div>
        <div class="welcome-meta">
          <span class="badge-mono">{{ employee?.employee_id }}</span>
          <span v-if="employee?.position" class="welcome-pos">{{ employee.position }}</span>
        </div>
        <div v-if="employee?.department" class="welcome-dept">{{ employee.department.name }}</div>
      </div>
    </div>

    <!-- ── Mandatory Items Overdue ───────────────────────────────────── -->
    <div v-if="overdueItems.length > 0" class="section-card">
      <div class="section-title">🦺 {{ $t('portal.mandatoryPpeTitle') }}</div>

      <div v-for="ms in overdueItems" :key="ms.item.id" class="mandatory-row">
        <div class="mandatory-item-name">{{ itemLocalName(ms.item) }}</div>
        <div class="mandatory-item-meta">
          <span v-if="ms.taken_at" class="meta-label">{{ $t('portal.taken') }}: {{ fmtDate(ms.taken_at) }}</span>
          <span v-else class="meta-label dim">{{ $t('portal.neverReceived') }}</span>
        </div>
        <div class="mandatory-status-row">
          <span class="chip chip-overdue">{{ $t('portal.overdueChip') }}</span>
          <button
            v-if="!ms.pending_request"
            class="btn-ajukan"
            @click="ajukan(ms.item.id)"
            type="button">
            {{ $t('portal.ajukan') }}
          </button>
          <span v-else class="chip chip-pending">
            {{ $t('portal.pendingChip') }}
          </span>
        </div>
      </div>

      <!-- Batch submit all overdue -->
      <div v-if="overdueEmpCount > 1 && !allEmpPending" class="batch-row">
        <button class="btn-ajukan-all" @click="ajukanSemua" type="button">
          {{ $t('portal.ajukanAll').replace('{n}', overdueEmpCount) }} →
        </button>
      </div>
    </div>

    <!-- ── LV Equipment Overdue (jika driver) ────────────────────────── -->
    <template v-if="hasAnyLvOverdue">
      <div v-for="lv in lvStatus" :key="lv.vehicle.id"
           v-show="lv.items.some(ms => ms.overdue)"
           class="section-card lv-card">
        <div class="section-title">🚗 {{ $t('portal.mandatoryLvTitle').replace('{lv}', lv.vehicle.full_number ?? lv.vehicle.lv_number) }}</div>

        <div v-for="ms in lv.items.filter(ms => ms.overdue)" :key="ms.item.id" class="mandatory-row">
          <div class="mandatory-item-name">{{ itemLocalName(ms.item) }}</div>
          <div class="mandatory-item-meta">
            <span v-if="ms.taken_at" class="meta-label">{{ $t('portal.installedDate') }}: {{ fmtDate(ms.taken_at) }}</span>
            <span v-else class="meta-label dim">{{ $t('portal.neverReceived') }}</span>
          </div>
          <div class="mandatory-status-row">
            <span class="chip chip-overdue">{{ $t('portal.needsReplacement') }}</span>
            <button
              v-if="!ms.pending_request"
              class="btn-ajukan"
              @click="ajukanLv(ms.item.id, lv.vehicle.id)"
              type="button">
              {{ $t('portal.ajukan') }}
            </button>
            <span v-else class="chip chip-pending">
              {{ $t('portal.pendingChip') }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <!-- ── All good: no overdue items ────────────────────────────────── -->
    <div v-if="overdueItems.length === 0 && !hasAnyLvOverdue && mandatoryItems.length > 0"
         class="section-card all-good-card">
      <div class="all-good-icon">✅</div>
      <div class="all-good-text">{{ $t('portal.allItemsOk') }}</div>
      <div class="all-good-sub">{{ $t('portal.allItemsOkSub') }}</div>
    </div>

    <!-- ── Riwayat Singkat ────────────────────────────────────────────── -->
    <div class="section-card">
      <div class="section-header">
        <div class="section-title">📦 {{ $t('portal.recentPickups') }}</div>
        <a href="/portal/history" class="view-all-link">{{ $t('portal.viewAllHistory') }}</a>
      </div>

      <div v-if="recentLogs.length === 0" class="empty-msg">{{ $t('portal.noRecentPickups') }}</div>

      <div v-for="log in recentLogs" :key="log.id" class="log-row">
        <div class="log-left">
          <div class="log-item-name">{{ logItemName(log) }}</div>
          <div class="log-meta">
            <span class="log-sku">{{ log.variant?.sku ?? '—' }}</span>
            <span class="log-type-badge">{{ logType(log) }}</span>
          </div>
        </div>
        <div class="log-right">
          <div class="log-date">{{ fmtDate(log.taken_at) }}</div>
          <div v-if="log.cooldown_until" class="log-until dim">s/d {{ fmtDate(log.cooldown_until) }}</div>
        </div>
      </div>
    </div>

  </EmployeeLayout>
</template>

<style scoped>
/* ── welcome card ─────────────────────────────────────────────────────── */
.welcome-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
.welcome-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--orange-400);
    color: #fff;
    font-size: 18px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
}
.welcome-info { flex: 1; min-width: 0; }
.welcome-name { font-size: 16px; font-weight: 700; color: var(--fg); line-height: 1.3; }
.welcome-meta { display: flex; align-items: center; gap: 7px; margin-top: 4px; flex-wrap: wrap; }
.badge-mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    background: rgba(249,115,22,.12);
    color: var(--orange-500);
    padding: 2px 8px;
    border-radius: 20px;
}
.welcome-pos { font-size: 12px; color: var(--fg-2); }
.welcome-dept { font-size: 12px; color: var(--fg-2); margin-top: 2px; }

/* ── section card ─────────────────────────────────────────────────────── */
.section-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 14px;
    margin-bottom: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.lv-card { border-left: 3px solid #3b82f6; }
.section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--fg);
    margin-bottom: 12px;
}
.lv-name-dim { font-size: 12px; font-weight: 500; color: var(--fg-2); margin-left: 4px; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.view-all-link { font-size: 12px; color: var(--orange-500); font-weight: 600; text-decoration: none; }
.view-all-link:hover { text-decoration: underline; }
.empty-msg { font-size: 13px; color: var(--fg-2); text-align: center; padding: 12px 0; }

/* ── mandatory rows ───────────────────────────────────────────────────── */
.mandatory-row {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-soft);
}
.mandatory-row:last-child { border-bottom: 0; }
.mandatory-item-name { font-size: 14px; font-weight: 600; color: var(--fg); margin-bottom: 3px; }
.mandatory-item-meta { margin-bottom: 6px; }
.meta-label { font-size: 11.5px; color: var(--fg-2); }
.meta-label.dim { opacity: .6; }
.mandatory-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── chips ────────────────────────────────────────────────────────────── */
.chip {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
}
.chip-ok { background: rgba(16,185,129,.12); color: #10b981; }
.chip-overdue { background: rgba(239,68,68,.12); color: #ef4444; }
.chip-pending { background: var(--surface-2); color: var(--fg-2); border: 1px solid var(--border); }

/* ── buttons ──────────────────────────────────────────────────────────── */
.btn-ajukan {
    appearance: none;
    border: 1px solid #f97316;
    background: transparent;
    color: #f97316;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    transition: background 180ms, color 180ms;
    white-space: nowrap;
}
.btn-ajukan:hover { background: #f97316; color: #fff; }
.batch-row { padding-top: 10px; text-align: center; }
.btn-ajukan-all {
    appearance: none;
    border: none;
    background: #f97316;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
    transition: opacity 180ms;
}
.btn-ajukan-all:hover { opacity: .88; }

/* ── all good card ────────────────────────────────────────────────────── */
.all-good-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 28px 16px;
    text-align: center;
    gap: 6px;
}
.all-good-icon { font-size: 32px; }
.all-good-text { font-size: 15px; font-weight: 700; color: #10b981; }
.all-good-sub  { font-size: 12px; color: var(--fg-2); }

/* ── recent log rows ──────────────────────────────────────────────────── */
.log-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid var(--border-soft);
}
.log-row:last-child { border-bottom: 0; }
.log-left { flex: 1; min-width: 0; }
.log-item-name { font-size: 13px; font-weight: 600; color: var(--fg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.log-meta { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.log-sku { font-size: 11px; color: var(--fg-2); font-family: 'JetBrains Mono', monospace; }
.log-type-badge { font-size: 10px; font-weight: 700; background: var(--surface-2); color: var(--fg-2); border: 1px solid var(--border); padding: 1px 6px; border-radius: 20px; }
.log-right { text-align: right; flex-shrink: 0; }
.log-date { font-size: 12px; font-weight: 600; color: var(--fg); white-space: nowrap; }
.log-until { font-size: 10.5px; margin-top: 1px; }
.dim { color: var(--fg-2); }
</style>
