<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    gis:        Object,
    counts:     Object,
    warehouses: Array,
    userRole:   String,
    filters:    Object,
})

const search    = ref(props.filters?.search    ?? '')
const statusF   = ref(props.filters?.status    ?? '')
const warehouseF= ref(props.filters?.warehouse ?? '')

let timer = null
watch([search, statusF, warehouseF], () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('gi.index'), {
            search:    search.value    || undefined,
            status:    statusF.value   || undefined,
            warehouse: warehouseF.value|| undefined,
        }, { preserveState: true, replace: true })
    }, 320)
})

const canCreate = ['admin_dept', 'super_admin'].includes(props.userRole)

function deleteGI(gi) {
    if (!confirm(`Hapus ${gi.gi_number}? Tindakan ini tidak bisa dibatalkan.`)) return
    router.delete(route('gi.destroy', gi.id), { preserveScroll: true })
}

const STATUS_MAP = {
    draft:               { lbl: 'Draft',          bg:'rgba(100,116,139,.15)', col:'#94a3b8' },
    pending_manager_dept:{ lbl: 'Mgr Dept',       bg:'rgba(59,130,246,.15)',  col:'#60a5fa' },
    pending_supervisor:  { lbl: 'WH Supervisor',   bg:'rgba(234,179,8,.15)',   col:'#fbbf24' },
    pending_manager_wh:  { lbl: 'WH Manager',     bg:'rgba(139,92,246,.15)',  col:'#a78bfa' },
    approved:            { lbl: 'Approved',        bg:'rgba(16,185,129,.15)',  col:'#34d399' },
    assigned:            { lbl: 'Assigned',        bg:'rgba(249,115,22,.15)',  col:'#fb923c' },
    in_picking:          { lbl: 'Picking',         bg:'rgba(249,115,22,.2)',   col:'#f97316' },
    ready_to_pickup:     { lbl: 'Ready',           bg:'rgba(16,185,129,.25)',  col:'#10b981' },
    completed:           { lbl: 'Completed',       bg:'rgba(16,185,129,.15)',  col:'#34d399' },
    rejected:            { lbl: 'Rejected',        bg:'rgba(239,68,68,.15)',   col:'#f87171' },
}
function statusStyle(s) { const m = STATUS_MAP[s]??{}; return { background:m.bg, color:m.col } }
function statusLabel(s) { return STATUS_MAP[s]?.lbl ?? s }

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', { day:'2-digit', month:'short', year:'numeric' })
}

const STAT_CARDS = [
    { key:'total',                bg:'rgba(99,102,241,.12)',  col:'#818cf8', svg:'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>' },
    { key:'draft',                bg:'rgba(100,116,139,.12)', col:'#94a3b8', svg:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>' },
    { key:'pending_manager_dept', bg:'rgba(59,130,246,.12)',  col:'#60a5fa', svg:'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
    { key:'pending_supervisor',   bg:'rgba(234,179,8,.12)',   col:'#fbbf24', svg:'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>' },
    { key:'pending_manager_wh',   bg:'rgba(139,92,246,.12)',  col:'#a78bfa', svg:'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>' },
    { key:'approved',             bg:'rgba(16,185,129,.12)',  col:'#34d399', svg:'<polyline points="20 6 9 17 4 12"/>' },
    { key:'in_progress',          bg:'rgba(249,115,22,.12)',  col:'#fb923c', svg:'<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>' },
    { key:'ready_to_pickup',      bg:'rgba(20,184,166,.12)',  col:'#2dd4bf', svg:'<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>' },
    { key:'completed',            bg:'rgba(16,185,129,.12)',  col:'#10b981', svg:'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' },
    { key:'rejected',             bg:'rgba(239,68,68,.12)',   col:'#f87171', svg:'<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>' },
]
</script>

<template>
<AppLayout>
  <template #title>{{ $t('gi.title') }}</template>
  <template #breadcrumb>{{ $t('gi.title') }}</template>

  <div class="gi-page">

    <!-- Page header -->
    <div class="gi-ph">
      <div>
        <h1 class="gi-pt">{{ $t('gi.title') }}</h1>
        <p class="gi-ps">{{ $t('gi.sub') }}</p>
      </div>
      <Link v-if="canCreate" :href="route('gi.create')" class="gi-create-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        {{ $t('gi.createBtn') }}
      </Link>
    </div>

    <!-- Stat cards -->
    <div class="gi-stats">
      <div v-for="s in STAT_CARDS" :key="s.key" class="gi-stat">
        <div class="gs-icon" :style="{ background: s.bg }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke="s.col"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"
            v-html="s.svg" />
        </div>
        <div class="gs-val">{{ counts?.[s.key] ?? 0 }}</div>
        <div class="gs-lbl">{{ $t(`gi.stat_${s.key}`) }}</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="gi-toolbar">
      <input class="gi-search" type="text" v-model="search" :placeholder="$t('gi.searchPh')" />
      <select class="gi-filter" v-model="statusF">
        <option value="">{{ $t('gi.filterStatus') }}</option>
        <option v-for="(v, k) in STATUS_MAP" :key="k" :value="k">{{ v.lbl }}</option>
      </select>
      <select class="gi-filter" v-model="warehouseF">
        <option value="">{{ $t('gi.filterWh') }}</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="gi-table-wrap">
      <table class="gi-table">
        <thead>
          <tr>
            <th>{{ $t('gi.colGiNo') }}</th>
            <th>{{ $t('gi.colDept') }}</th>
            <th>{{ $t('gi.colWarehouse') }}</th>
            <th>{{ $t('gi.colPurpose') }}</th>
            <th class="tc">{{ $t('gi.colItems') }}</th>
            <th>{{ $t('gi.colStatus') }}</th>
            <th>{{ $t('gi.colRequestedBy') }}</th>
            <th>{{ $t('gi.colDate') }}</th>
            <th class="tc">{{ $t('gi.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!gis.data.length">
            <td colspan="9" class="gi-empty">{{ $t('gi.noResults') }}</td>
          </tr>
          <tr v-for="gi in gis.data" :key="gi.id">
            <td><span class="gi-num">{{ gi.gi_number }}</span></td>
            <td>{{ gi.department?.code ?? '—' }}</td>
            <td><span class="gi-wh-badge">{{ gi.warehouse?.code ?? '—' }}</span></td>
            <td class="gi-purpose-cell">{{ gi.purpose }}</td>
            <td class="tc">{{ gi.items_count }}</td>
            <td>
              <span class="gi-status-badge" :style="statusStyle(gi.status)">
                {{ statusLabel(gi.status) }}
              </span>
            </td>
            <td>{{ gi.requested_by?.name ?? '—' }}</td>
            <td class="gi-date">{{ fmtDate(gi.created_at) }}</td>
            <td class="tc">
              <div class="gi-actions-cell">
                <Link :href="route('gi.show', gi.id)" class="gi-view-btn">
                  {{ $t('gi.view') }}
                </Link>
                <button
                  v-if="gi.status === 'draft' && (userRole === 'super_admin' || gi.requested_by?.id === $page.props.auth?.user?.id)"
                  type="button"
                  class="gi-del-btn"
                  @click="deleteGI(gi)"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="gis.last_page > 1" class="gi-pager">
      <Link v-if="gis.prev_page_url" :href="gis.prev_page_url" class="pg-btn">←</Link>
      <span class="pg-info">{{ gis.current_page }} / {{ gis.last_page }}</span>
      <Link v-if="gis.next_page_url" :href="gis.next_page_url" class="pg-btn">→</Link>
    </div>

  </div>
</AppLayout>
</template>

<style scoped>
.gi-page { display:flex; flex-direction:column; gap:18px }

.gi-ph  { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap }
.gi-pt  { font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.02em }
.gi-ps  { font-size:13px; color:var(--fg-2); margin-top:3px }
.gi-create-btn {
  display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:9px;
  font-size:13px; font-weight:600; background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  color:#fff; border:0; text-decoration:none; box-shadow:0 4px 10px -3px rgba(249,115,22,.45);
  transition:opacity 150ms; white-space:nowrap;
}
.gi-create-btn:hover { opacity:.88 }

/* ── stats ───────────────────────────────────────────────────────────── */
.gi-stats {
  display:grid; grid-template-columns:repeat(auto-fill, minmax(110px,1fr)); gap:10px;
}
.gi-stat {
  background:var(--surface); border:1px solid var(--border); border-radius:12px;
  padding:14px 16px; display:flex; flex-direction:column; gap:4px;
}
.gs-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; margin-bottom:4px; flex-shrink:0 }
.gs-icon svg { display:block }
.gs-val { font-size:22px; font-weight:800; color:var(--fg); line-height:1 }
.gs-lbl { font-size:11px; color:var(--fg-dim); font-weight:600; text-transform:uppercase; letter-spacing:.04em }

/* ── toolbar ─────────────────────────────────────────────────────────── */
.gi-toolbar { display:flex; gap:10px; flex-wrap:wrap }
.gi-search, .gi-filter {
  padding:8px 12px; font-size:13px; background:var(--surface);
  border:1px solid var(--border-2); border-radius:8px; color:var(--fg);
  outline:none; font-family:inherit; transition:border-color 180ms;
}
.gi-search { flex:1; min-width:200px }
.gi-search:focus, .gi-filter:focus { border-color:var(--orange-500) }

/* ── table ───────────────────────────────────────────────────────────── */
.gi-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:auto }
.gi-table { width:100%; border-collapse:collapse; font-size:13px }
.gi-table th {
  padding:10px 14px; text-align:left; font-size:10.5px; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em; color:var(--fg-dim);
  border-bottom:1px solid var(--border); white-space:nowrap; background:var(--surface);
}
.gi-table td { padding:11px 14px; border-bottom:1px solid var(--border); color:var(--fg-2); vertical-align:middle }
.gi-table tr:last-child td { border-bottom:0 }
.gi-table tr:hover td { background:var(--hover) }
.tc { text-align:center }

.gi-num { font-family:monospace; font-size:12.5px; font-weight:700; color:var(--fg) }
.gi-wh-badge {
  display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700;
  background:rgba(249,115,22,.1); color:var(--orange-500);
}
.gi-purpose-cell { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
.gi-status-badge { display:inline-flex; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:700; white-space:nowrap }
.gi-date { font-size:12px; white-space:nowrap; color:var(--fg-dim) }
.gi-empty { text-align:center; padding:48px; color:var(--fg-dim); font-size:13px }
.gi-view-btn {
  display:inline-flex; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600;
  background:var(--surface-2); border:1px solid var(--border-2); color:var(--fg-2);
  text-decoration:none; transition:background 150ms;
}
.gi-view-btn:hover { background:var(--hover); color:var(--fg) }
.gi-actions-cell { display:flex; align-items:center; gap:6px; justify-content:center }
.gi-del-btn {
  display:inline-flex; align-items:center; justify-content:center;
  width:28px; height:28px; border-radius:6px;
  background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.2);
  color:#f87171; cursor:pointer; transition:background 150ms;
  flex-shrink:0;
}
.gi-del-btn:hover { background:rgba(239,68,68,.2); }

/* ── pagination ─────────────────────────────────────────────────────── */
.gi-pager { display:flex; align-items:center; justify-content:center; gap:12px }
.pg-btn { display:inline-flex; padding:6px 14px; border-radius:8px; background:var(--surface); border:1px solid var(--border-2); color:var(--fg-2); text-decoration:none; font-size:13px; font-weight:600; transition:background 150ms }
.pg-btn:hover { background:var(--hover) }
.pg-info { font-size:13px; color:var(--fg-dim) }
</style>
