<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    grs:        Object,
    stats:      Object,
    warehouses: Array,
    filters:    Object,
    userRole:   String,
    userId:     Number,
})

const flash = computed(() => page.props.flash ?? {})

// ── filters ────────────────────────────────────────────────────────────────
const search      = ref(props.filters.search       ?? '')
const statusF     = ref(props.filters.status       ?? '')
const warehouseF  = ref(props.filters.warehouse_id ?? '')

let timer = null
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(applyFilters, 400)
})
watch([statusF, warehouseF], applyFilters)

function applyFilters() {
    router.get(route('gr.index'), {
        search:       search.value      || undefined,
        status:       statusF.value     || undefined,
        warehouse_id: warehouseF.value  || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function goPage(url) {
    if (!url) return
    router.get(url, {}, { preserveState: true, preserveScroll: true })
}

// ── helpers ─────────────────────────────────────────────────────────────
const STATUS_MAP = {
    draft:              { label: 'Draft',             color: 'rgba(100,116,139,.15)',  text: '#94a3b8' },
    arrived:            { label: 'Arrived',           color: 'rgba(59,130,246,.15)',   text: '#60a5fa' },
    pending_supervisor: { label: 'Pending Approval',  color: 'rgba(234,179,8,.15)',    text: '#fbbf24' },
    completed:          { label: 'Completed',         color: 'rgba(16,185,129,.15)',   text: '#34d399' },
}

function statusStyle(s) {
    const m = STATUS_MAP[s] ?? { color: 'rgba(100,116,139,.15)', text: '#94a3b8' }
    return { background: m.color, color: m.text }
}
function statusLabel(s) {
    return t('gr.status_' + s)
}

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: '2-digit', month: 'short', year: 'numeric',
    })
}

// Can this user create GR?
const canCreate = computed(() => ['procurement_admin', 'wh_admin', 'super_admin'].includes(props.userRole))

// Action button per row based on role + status
function rowAction(gr) {
    const r         = props.userRole
    const isCreator = gr.created_by?.id === props.userId
    // Only the creator (procurement_admin or wh_admin) can mark their own draft as arrived
    if (gr.status === 'draft' && isCreator && ['procurement_admin', 'wh_admin'].includes(r)) return { label: t('gr.markArrived'), type: 'arrived' }
    if (r === 'wh_admin' && gr.status === 'arrived') return { label: t('gr.inspect'), type: 'inspect' }
    if (r === 'wh_supervisor' && gr.status === 'pending_supervisor') return { label: t('gr.approve'), type: 'approve' }
    return null
}

const STAT_COLORS = [
    { bg: 'rgba(59,130,246,.12)',  icon: '#60a5fa' },
    { bg: 'rgba(100,116,139,.12)', icon: '#94a3b8' },
    { bg: 'rgba(234,179,8,.12)',   icon: '#fbbf24' },
    { bg: 'rgba(249,115,22,.12)',  icon: '#fb923c' },
    { bg: 'rgba(16,185,129,.12)',  icon: '#34d399' },
]
</script>

<template>
<AppLayout>
  <template #title>{{ $t('gr.title') }}</template>
  <template #breadcrumb>{{ $t('gr.title') }}</template>

  <!-- flash -->
  <div v-if="flash.success" class="gr-flash gr-flash-ok">{{ flash.success }}</div>
  <div v-if="flash.error"   class="gr-flash gr-flash-err">{{ flash.error }}</div>

  <!-- ── STAT CARDS ─────────────────────────────────────────────────────── -->
  <div class="gr-stat-row">
    <div class="gr-stat" v-for="(s, i) in [
      { key: 'total',              lbl: $t('gr.statTotal') },
      { key: 'draft',              lbl: $t('gr.statDraft') },
      { key: 'arrived',            lbl: $t('gr.statArrived') },
      { key: 'pending_supervisor', lbl: $t('gr.statPending') },
      { key: 'completed',          lbl: $t('gr.statCompleted') },
    ]" :key="i">
      <div class="gr-stat-ico" :style="{ background: STAT_COLORS[i].bg }">
        <svg :style="{ color: STAT_COLORS[i].icon }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path v-if="i===0" d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect v-if="i===0" x="9" y="11" width="14" height="10" rx="1"/><path v-if="i===0" d="m12 15 3 3 5-5"/>
          <path v-if="i===1" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline v-if="i===1" points="14 2 14 8 20 8"/>
          <path v-if="i===2" d="M12 2L2 7l10 5 10-5-10-5z"/><path v-if="i===2" d="M2 17l10 5 10-5"/><path v-if="i===2" d="M2 12l10 5 10-5"/>
          <circle v-if="i===3" cx="12" cy="12" r="10"/><line v-if="i===3" x1="12" y1="8" x2="12" y2="12"/><line v-if="i===3" x1="12" y1="16" x2="12.01" y2="16"/>
          <path v-if="i===4" d="M20 6 9 17l-5-5"/>
        </svg>
      </div>
      <div class="gr-stat-body">
        <div class="gr-stat-val">{{ (stats[s.key] ?? 0).toLocaleString() }}</div>
        <div class="gr-stat-lbl">{{ s.lbl }}</div>
      </div>
    </div>
  </div>

  <!-- ── TOOLBAR ─────────────────────────────────────────────────────────── -->
  <div class="gr-toolbar">
    <div class="gr-search-wrap">
      <svg class="gr-search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="gr-search" type="text" v-model="search" :placeholder="$t('gr.searchPh')" />
    </div>
    <select class="gr-select" v-model="statusF">
      <option value="">{{ $t('gr.filterStatus') }}</option>
      <option v-for="(m, k) in STATUS_MAP" :key="k" :value="k">{{ t('gr.status_' + k) }}</option>
    </select>
    <select class="gr-select" v-model="warehouseF">
      <option value="">{{ $t('gr.filterWh') }}</option>
      <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} · {{ wh.name }}</option>
    </select>
    <Link v-if="canCreate" class="gr-btn-create" :href="route('gr.create')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
      {{ $t('gr.createBtn') }}
    </Link>
  </div>

  <!-- ── TABLE ──────────────────────────────────────────────────────────── -->
  <div class="gr-table-wrap">
    <table class="gr-table">
      <thead>
        <tr>
          <th>{{ $t('gr.colGrNo') }}</th>
          <th>{{ $t('gr.colPrPo') }}</th>
          <th>{{ $t('gr.colWarehouse') }}</th>
          <th class="tc">{{ $t('gr.colItems') }}</th>
          <th>{{ $t('gr.colStatus') }}</th>
          <th>{{ $t('gr.colCreatedBy') }}</th>
          <th>{{ $t('gr.colDate') }}</th>
          <th class="tc">{{ $t('gr.colActions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!grs.data.length">
          <td colspan="8" class="gr-empty">{{ $t('gr.noResults') }}</td>
        </tr>
        <tr v-for="gr in grs.data" :key="gr.id">
          <!-- GR Number -->
          <td>
            <Link :href="route('gr.show', gr.id)" class="gr-num-link">{{ gr.gr_number }}</Link>
          </td>
          <!-- PR / PO -->
          <td>
            <div v-if="gr.pr_number" class="gr-ref"><span class="gr-ref-lbl">PR</span>{{ gr.pr_number }}</div>
            <div v-if="gr.po_number" class="gr-ref"><span class="gr-ref-lbl">PO</span>{{ gr.po_number }}</div>
            <span v-if="!gr.pr_number && !gr.po_number" class="gr-dim">—</span>
          </td>
          <!-- Warehouse -->
          <td>
            <span v-if="gr.warehouse" class="gr-wh">{{ gr.warehouse.code }}</span>
            <span v-else class="gr-dim">—</span>
          </td>
          <!-- Items count -->
          <td class="tc">
            <span class="gr-item-ct">{{ gr.items_count }}</span>
          </td>
          <!-- Status -->
          <td>
            <span class="gr-status-badge" :style="statusStyle(gr.status)">{{ statusLabel(gr.status) }}</span>
            <div v-if="gr.auto_approved" class="gr-auto-tag">auto</div>
          </td>
          <!-- Created by -->
          <td>
            <span class="gr-person">{{ gr.created_by?.name ?? '—' }}</span>
          </td>
          <!-- Date -->
          <td>
            <div class="gr-date">{{ fmtDate(gr.created_at) }}</div>
          </td>
          <!-- Actions -->
          <td class="tc">
            <div class="gr-actions">
              <Link :href="route('gr.show', gr.id)" class="gr-btn-view">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                {{ $t('gr.view') }}
              </Link>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- pagination -->
    <div class="gr-pg" v-if="grs.last_page > 1">
      <span class="gr-pg-info">{{ $t('si.showing', { from: grs.from, to: grs.to, total: grs.total }) }}</span>
      <div class="gr-pg-btns">
        <button class="gr-pg-btn" :disabled="!grs.prev_page_url" @click="goPage(grs.prev_page_url)">‹</button>
        <template v-for="link in grs.links.slice(1, -1)" :key="link.label">
          <button class="gr-pg-btn" :class="{ active: link.active }" :disabled="!link.url" @click="goPage(link.url)" v-html="link.label" />
        </template>
        <button class="gr-pg-btn" :disabled="!grs.next_page_url" @click="goPage(grs.next_page_url)">›</button>
      </div>
    </div>
  </div>
</AppLayout>
</template>

<style scoped>
.gr-stat-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px }
@media(max-width:1100px){ .gr-stat-row{ grid-template-columns:repeat(3,1fr) } }
@media(max-width:700px){ .gr-stat-row{ grid-template-columns:repeat(2,1fr) } }
.gr-stat { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow-sm) }
.gr-stat-ico { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; flex-shrink:0 }
.gr-stat-ico svg { width:18px; height:18px }
.gr-stat-val { font-size:22px; font-weight:700; letter-spacing:-.02em; line-height:1 }
.gr-stat-lbl { font-size:11.5px; color:var(--fg-2); margin-top:3px; font-weight:500 }

.gr-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap }
.gr-search-wrap { position:relative; flex:1; min-width:200px }
.gr-search-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--fg-dim) }
.gr-search { width:100%; padding:9px 12px 9px 32px; font-size:13.5px; background:var(--surface); border:1px solid var(--border-2); border-radius:8px; color:var(--fg); outline:none; transition:border-color 180ms }
.gr-search:focus { border-color:var(--orange-500) }
.gr-select { padding:9px 12px; font-size:13.5px; background:var(--surface); border:1px solid var(--border-2); border-radius:8px; color:var(--fg); outline:none; cursor:pointer }
.gr-btn-create {
  display:inline-flex; align-items:center; gap:6px; padding:9px 16px;
  border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  color:#fff; border:none; text-decoration:none; white-space:nowrap;
  box-shadow:0 4px 10px -3px rgba(249,115,22,.4); transition:opacity 180ms;
}
.gr-btn-create:hover { opacity:.9 }
.gr-btn-create svg { width:14px; height:14px }

.gr-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:var(--shadow-sm) }
.gr-table { width:100%; border-collapse:collapse }
.gr-table th { padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-2); background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap }
.gr-table td { padding:12px 14px; font-size:13.5px; border-bottom:1px solid var(--border-soft); vertical-align:middle }
.gr-table tbody tr:last-child td { border-bottom:none }
.gr-table tbody tr:hover td { background:var(--hover) }
.tc { text-align:center }
.gr-empty { text-align:center; color:var(--fg-dim); padding:40px }

.gr-num-link { font-family:monospace; font-weight:700; color:var(--orange-500); font-size:13px; text-decoration:none }
.gr-num-link:hover { text-decoration:underline }
.gr-ref { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--fg-2); margin-bottom:2px }
.gr-ref-lbl { font-size:9.5px; font-weight:800; letter-spacing:.08em; padding:1px 5px; border-radius:3px; background:var(--surface-3); border:1px solid var(--border-2); color:var(--fg-dim) }
.gr-dim { color:var(--fg-dim); font-size:12px }
.gr-wh { font-family:monospace; font-weight:700; font-size:12px; color:var(--fg); background:var(--surface-3); padding:2px 7px; border-radius:5px; border:1px solid var(--border-2) }
.gr-item-ct { font-weight:700; font-size:14px }
.gr-status-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11.5px; font-weight:700; letter-spacing:.03em }
.gr-auto-tag { font-size:9.5px; color:var(--fg-dim); font-weight:700; letter-spacing:.08em; margin-top:2px; text-transform:uppercase }
.gr-person { font-size:12.5px; color:var(--fg-2) }
.gr-date { font-size:12px; color:var(--fg-dim) }
.gr-actions { display:flex; gap:6px; justify-content:center }
.gr-btn-view {
  display:inline-flex; align-items:center; gap:5px; padding:5px 10px;
  border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;
  background:rgba(249,115,22,.1); color:var(--orange-500);
  border:1px solid rgba(249,115,22,.2); text-decoration:none; transition:background 150ms;
}
.gr-btn-view:hover { background:rgba(249,115,22,.2) }
.gr-btn-view svg { width:12px; height:12px }

.gr-pg { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-top:1px solid var(--border); gap:12px }
.gr-pg-info { font-size:12.5px; color:var(--fg-2) }
.gr-pg-btns { display:flex; gap:4px }
.gr-pg-btn { min-width:32px; height:32px; padding:0 8px; border-radius:7px; border:1px solid var(--border-2); background:var(--surface); font-size:13px; font-weight:600; color:var(--fg-2); cursor:pointer; transition:background 180ms,color 180ms }
.gr-pg-btn:hover:not(:disabled) { background:var(--hover); color:var(--fg) }
.gr-pg-btn.active { background:var(--orange-500); color:#fff; border-color:var(--orange-500) }
.gr-pg-btn:disabled { opacity:.4; cursor:default }

.gr-flash { padding:12px 16px; border-radius:8px; font-size:13.5px; font-weight:600 }
.gr-flash-ok  { background:rgba(16,185,129,.12); color:#34d399; border:1px solid rgba(16,185,129,.25) }
.gr-flash-err { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.25) }
</style>
