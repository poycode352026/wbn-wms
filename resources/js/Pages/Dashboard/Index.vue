<script setup>
import { computed, ref } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransactionChart from '@/Components/TransactionChart.vue'

const { t, locale } = useI18n()
const page  = usePage()
const user  = computed(() => page.props.auth?.user ?? { name: 'User' })
const firstName = computed(() => (user.value.name || 'User').split(' ')[0])

const props = defineProps({
    stats:            Object,
    warehouseStats:   Array,
    chartData:        Object,
    recentTx:         Array,
    lowStockItems:    Array,
    mandatoryOverdue: Object,  // null if role has no access
    adminDeptStats:   Object,  // only for admin_dept role
})

// Mandatory overdue widget helpers
const hasMandatoryOverdue = computed(() =>
    props.mandatoryOverdue &&
    (props.mandatoryOverdue.employees.length > 0 || props.mandatoryOverdue.vehicles.length > 0)
)
const mandatoryAllClear = computed(() =>
    props.mandatoryOverdue &&
    props.mandatoryOverdue.employees.length === 0 &&
    props.mandatoryOverdue.vehicles.length === 0
)
const mandatoryExpanded = ref({}) // track which <details> are open

const greeting = computed(() => {
    const h = new Date().getHours()
    if (h < 12) return t('dash.goodMorning')
    if (h < 17) return t('dash.goodAfternoon')
    return t('dash.goodEvening')
})

const dateStr = computed(() => {
    const d  = new Date()
    const wk = ['SUN','MON','TUE','WED','THU','FRI','SAT'][d.getDay()]
    const mo = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'][d.getMonth()]
    return `${wk} · ${String(d.getDate()).padStart(2,'0')} ${mo} ${d.getFullYear()}`
})

function timeAgo(iso) {
    if (!iso) return '—'
    const diff = Date.now() - new Date(iso).getTime()
    const m = Math.floor(diff / 60000)
    if (m < 1)  return 'just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h / 24)}d ago`
}

// Map GR/GI status to pill class
const STATUS_PILL = {
    // GR statuses
    draft:              'dr',
    arrived:            'pn',
    pending_supervisor: 'pn',
    completed:          'ok',
    // GI statuses
    pending:            'pn',
    pending_manager_dept: 'pn',
    pending_manager_wh: 'pn',
    pending_supervisor: 'pn',
    approved:           'ok',
    rejected:           'rej',
    cancelled:          'dr',
}
function statusPill(status) { return STATUS_PILL[status] ?? 'dr' }
function statusLabel(tx) {
    if (tx.type === 'GR') return t('gr.status_' + tx.status)
    // GI statuses map
    const map = {
        draft:                'Draft',
        pending:              'Pending',
        pending_manager_dept: 'Pending Mgr',
        pending_manager_wh:   'Pending WH',
        pending_supervisor:   'Pending Sup.',
        approved:             'Approved',
        rejected:             'Rejected',
        cancelled:            'Cancelled',
        completed:            'Completed',
    }
    return map[tx.status] ?? tx.status
}

function itemName(item) {
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}

// ── reorder ────────────────────────────────────────────────────────────────
const role = computed(() => page.props.auth?.user?.role ?? '')
const canReorder = computed(() => ['wh_admin', 'super_admin'].includes(role.value))

const selectedReorder = ref([])   // array of { variant_id, suggested_qty }

function toggleReorder(item) {
    const idx = selectedReorder.value.findIndex(s => s.variant_id === item.variant_id)
    if (idx >= 0) selectedReorder.value.splice(idx, 1)
    else selectedReorder.value.push({ variant_id: item.variant_id, suggested_qty: item.suggested_qty })
}

function isSelectedReorder(item) {
    return selectedReorder.value.some(s => s.variant_id === item.variant_id)
}

function doReorder() {
    if (!selectedReorder.value.length) return
    const reorderParam = selectedReorder.value
        .map(s => `${s.variant_id}:${s.suggested_qty}`)
        .join(',')
    router.get(route('gr.create'), { reorder: reorderParam })
}
</script>
<template>
  <AppLayout>
    <template #title>{{ $t('dash.title') }}</template>
    <template #breadcrumb>{{ $t('dash.title') }}</template>

    <!-- page header -->
    <div class="page-head">
      <div>
        <h1>{{ $t('dash.title') }}</h1>
        <div class="greeting">{{ greeting }}, {{ firstName }} 👋</div>
      </div>
      <div class="dt">{{ dateStr }}</div>
    </div>

    <!-- Row 1 (admin_dept): custom stat cards -->
    <section v-if="role === 'admin_dept' && props.adminDeptStats" class="dash-row cards5-wrap">
      <!-- pending requests chip -->
      <Link v-if="props.adminDeptStats.pendingRequests > 0"
        :href="route('employees.index')"
        class="dept-req-chip">
        📩 {{ $t('dash.deptPendingReq', { n: props.adminDeptStats.pendingRequests }) }}
      </Link>

      <div class="dash-row cards5">
        <div class="stat b-blue">
          <div class="stat-num">{{ props.adminDeptStats.total }}</div>
          <div class="stat-lbl">{{ $t('dash.deptTotalGI') }}</div>
          <div class="stat-sub">{{ $t('dash.deptTotalGISub') }}</div>
        </div>
        <div class="stat b-green">
          <div class="stat-num">{{ props.adminDeptStats.completed }}</div>
          <div class="stat-lbl">{{ $t('dash.deptCompleted') }}</div>
          <div class="stat-sub">{{ $t('dash.deptCompletedSub') }}</div>
        </div>
        <div class="stat b-orange">
          <div class="stat-num">{{ props.adminDeptStats.inProgress }}</div>
          <div class="stat-lbl">{{ $t('dash.deptInProgress') }}</div>
          <div class="stat-sub">{{ $t('dash.deptInProgressSub') }}</div>
        </div>
        <div class="stat b-amber">
          <div class="stat-num">{{ props.adminDeptStats.draft }}</div>
          <div class="stat-lbl">{{ $t('dash.deptDraft') }}</div>
          <div class="stat-sub">{{ $t('dash.deptDraftSub') }}</div>
        </div>
        <div class="stat b-red">
          <div class="stat-num">{{ props.adminDeptStats.rejected }}</div>
          <div class="stat-lbl">{{ $t('dash.deptRejected') }}</div>
          <div class="stat-sub">{{ $t('dash.deptRejectedSub') }}</div>
        </div>
      </div>
    </section>

    <!-- Row 1: stat cards (non admin_dept) -->
    <section v-else class="dash-row cards4">
      <div class="stat b-orange">
        <div class="stat-top">
          <div class="stat-ico orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8L12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg></div>
        </div>
        <div class="stat-num">{{ props.stats?.totalItems?.toLocaleString() ?? '0' }}</div>
        <div class="stat-lbl">{{ $t('dash.totalItems') }}</div>
        <div class="stat-sub">{{ $t('dash.totalItemsSub') }}</div>
      </div>

      <div class="stat b-blue">
        <div class="stat-top">
          <div class="stat-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg></div>
        </div>
        <div class="stat-num">{{ props.stats?.totalStockQty?.toLocaleString() ?? '0' }}<span class="stat-unit">{{ $t('dash.units') }}</span></div>
        <div class="stat-lbl">{{ $t('dash.totalStock') }}</div>
        <div class="stat-sub">{{ $t('dash.totalStockSub', { n: props.stats?.totalWarehouses ?? 0 }) }}</div>
      </div>

      <div class="stat b-amber">
        <div class="stat-top">
          <div class="stat-ico amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        </div>
        <div class="stat-num">{{ (props.stats?.grToday ?? 0) + (props.stats?.giToday ?? 0) }}</div>
        <div class="stat-lbl">{{ $t('dash.transToday') }}</div>
        <div class="stat-sub">{{ $t('dash.transTodaySub', { gr: props.stats?.grToday ?? 0, gi: props.stats?.giToday ?? 0, tr: 0 }) }}</div>
      </div>

      <div class="stat b-red">
        <div class="stat-top">
          <div class="stat-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        </div>
        <div class="stat-num">{{ props.stats?.lowStockCount ?? 0 }} <span class="stat-unit">{{ $t('dash.items') }}</span></div>
        <div class="stat-lbl">{{ $t('dash.lowStock') }}</div>
        <div class="stat-sub">{{ $t('dash.lowStockSub') }}</div>
      </div>
    </section>

    <!-- Row 2: chart + recent transactions -->
    <section class="dash-row split-60-40">
      <TransactionChart :chartData="props.chartData" />

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">{{ $t('dash.recentTx') }}</span>
        </div>
        <table class="tbl" v-if="props.recentTx?.length">
          <thead><tr>
            <th>{{ $t('dash.colType') }}</th>
            <th>{{ $t('dash.colDoc') }}</th>
            <th class="right">{{ $t('dash.colItems') }}</th>
            <th>{{ $t('dash.colStatus') }}</th>
            <th class="right">{{ $t('dash.colTime') }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="tx in props.recentTx" :key="tx.doc" class="tx-row" @click="router.visit(tx.route)" style="cursor:pointer">
              <td><span class="pill" :class="tx.type === 'GR' ? 'gr' : 'gi'">{{ tx.type }}</span></td>
              <td class="mono">{{ tx.doc }}</td>
              <td class="right num">{{ tx.items }}</td>
              <td><span class="pill" :class="statusPill(tx.status)">{{ statusLabel(tx) }}</span></td>
              <td class="right dim sm">{{ timeAgo(tx.date) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="wh-empty">{{ $t('dash.noWarehouses') }}</div>
      </div>
    </section>

    <!-- Row 3: warehouse capacity + low stock -->
    <section class="dash-row split-50">
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">{{ $t('dash.whCapacity') }}</span>
          <a class="panel-link" href="#">{{ $t('dash.manage') }}</a>
        </div>
        <template v-if="props.warehouseStats && props.warehouseStats.length">
          <div v-for="(wh, wi) in props.warehouseStats" :key="wh.id"
            class="wh-card" :style="wi === props.warehouseStats.length - 1 ? 'margin-bottom:0' : ''">
            <div class="wh-row">
              <div>
                <div class="wh-name">{{ wh.code }} · {{ wh.name }}</div>
                <div class="wh-loc">{{ wh.location ?? '—' }}</div>
              </div>
              <div class="wh-qty">{{ wh.totalQty.toLocaleString() }} {{ $t('dash.units') }}</div>
            </div>
            <div class="wh-racks">{{ $t('dash.racks') }}: {{ wh.rackCount }}</div>
          </div>
        </template>
        <div v-else class="wh-empty">{{ $t('dash.noWarehouses') }}</div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div style="display:flex;align-items:center;gap:8px">
            <span class="panel-title">{{ $t('dash.lowStockTable') }}</span>
            <span v-if="props.stats?.lowStockCount > 0" class="pill" style="background:rgba(239,68,68,.14);color:#ef4444">
              {{ props.stats.lowStockCount }} {{ $t('dash.items') }}
            </span>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <!-- Reorder button — wh_admin only -->
            <button v-if="canReorder" class="reorder-btn" :class="{ 'reorder-active': selectedReorder.length }" type="button"
              :disabled="!selectedReorder.length" @click="doReorder">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="1"/><path d="m12 15 3 3 5-5"/></svg>
              <span v-if="selectedReorder.length">{{ $t('dash.reorderSelected', { n: selectedReorder.length }) }}</span>
              <span v-else>{{ $t('dash.reorder') }}</span>
            </button>
            <!-- View All link -->
            <Link :href="route('low-stock.index')" class="panel-link">View All →</Link>
          </div>
        </div>
        <table class="tbl" v-if="props.lowStockItems?.length">
          <thead><tr>
            <th v-if="canReorder" style="width:32px"></th>
            <th>{{ $t('dash.colCode') }}</th>
            <th>{{ $t('dash.colItem') }}</th>
            <th>Details</th>
            <th class="right">{{ $t('dash.colSoh') }}</th>
            <th class="right">{{ $t('dash.colMin') }}</th>
            <th class="right">{{ $t('dash.colReorderQty') }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="item in props.lowStockItems" :key="item.variant_id"
                :class="{ 'row-selected': isSelectedReorder(item) }"
                style="cursor: default">
              <td v-if="canReorder" style="text-align:center">
                <input type="checkbox"
                  class="reorder-check"
                  :checked="isSelectedReorder(item)"
                  :disabled="!item.variant_id"
                  @change="toggleReorder(item)" />
              </td>
              <td class="mono" style="font-size:11px">{{ item.sku ?? '—' }}</td>
              <td>
                <div class="ls-name">{{ itemName(item) }}</div>
              </td>
              <td>
                <div v-if="item.details" class="ls-details">{{ item.details }}</div>
                <span v-else class="dim" style="font-size:11px">—</span>
              </td>
              <td class="right low-soh num">{{ item.soh.toLocaleString() }}</td>
              <td class="right num dim">{{ item.min.toLocaleString() }}</td>
              <td class="right num ls-reorder-qty">+{{ item.suggested_qty.toLocaleString() }} <span class="ls-uom">{{ item.uom }}</span></td>
            </tr>
          </tbody>
        </table>
        <div v-else style="padding:24px 0;font-size:13px;color:var(--fg-2);text-align:center">
          {{ props.stats?.lowStockCount === 0 ? '✓ All items above minimum stock' : $t('dash.noWarehouses') }}
        </div>
      </div>
    </section>

    <!-- ── Row 4: Mandatory Distribution Alert ──────────────────────────── -->
    <section v-if="props.mandatoryOverdue" class="dash-row" style="margin-top:18px">
      <div class="panel mandatory-panel">
        <div class="panel-head">
          <span class="panel-title">⚠️ {{ $t('dash.mandatoryTitle') }}</span>
        </div>

        <!-- All Clear -->
        <div v-if="mandatoryAllClear" class="mandatory-clear">
          {{ $t('dash.mandatoryAllClear') }}
        </div>

        <!-- Employee PPE Overdue -->
        <div v-if="props.mandatoryOverdue.employees.length > 0" class="mandatory-group">
          <div class="mandatory-group-title">👷 {{ $t('dash.mandatoryEmpPanel') }}</div>
          <div v-for="entry in props.mandatoryOverdue.employees" :key="entry.item.id" class="mandatory-item-row">
            <div class="mandatory-item-left">
              <span class="mandatory-item-name">{{ locale === 'id' ? (entry.item.name_id || entry.item.name_en) : entry.item.name_en }}</span>
              <span class="mandatory-overdue-count">{{ $t('dash.mandatoryOverdueEmp', { n: entry.employees.length }) }}</span>
            </div>
            <details class="mandatory-details">
              <summary class="mandatory-summary">{{ $t('dash.mandatoryViewList') }}</summary>
              <ul class="mandatory-list">
                <li v-for="emp in entry.employees" :key="emp.id">
                  {{ emp.name }} <span class="mono">({{ emp.employee_id }})</span>
                  <span v-if="emp.dept" class="mandatory-dept"> · {{ emp.dept }}</span>
                </li>
              </ul>
            </details>
          </div>
        </div>

        <!-- LV Equipment Overdue -->
        <div v-if="props.mandatoryOverdue.vehicles.length > 0" class="mandatory-group" :style="props.mandatoryOverdue.employees.length > 0 ? 'margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)' : ''">
          <div class="mandatory-group-title">🚗 {{ $t('dash.mandatoryLvPanel') }}</div>
          <div v-for="entry in props.mandatoryOverdue.vehicles" :key="entry.item.id" class="mandatory-item-row">
            <div class="mandatory-item-left">
              <span class="mandatory-item-name">{{ locale === 'id' ? (entry.item.name_id || entry.item.name_en) : entry.item.name_en }}</span>
              <span class="mandatory-overdue-count">{{ $t('dash.mandatoryOverdueLv', { n: entry.vehicles.length }) }}</span>
            </div>
            <details class="mandatory-details">
              <summary class="mandatory-summary">{{ $t('dash.mandatoryViewList') }}</summary>
              <ul class="mandatory-list">
                <li v-for="v in entry.vehicles" :key="v.id">
                  <span class="mono">{{ v.full_number ?? v.lv_number }}</span>
                  <span v-if="v.dept" class="mandatory-dept"> · {{ v.dept }}</span>
                </li>
              </ul>
            </details>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
<style scoped>
.page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:4px}
.page-head h1{margin:0;font-size:26px;font-weight:700;letter-spacing:-.025em;color:var(--fg)}
.greeting{font-size:14px;color:var(--fg-2);margin-top:6px}
.dt{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--fg-2);background:var(--surface);border:1px solid var(--border);padding:6px 12px;border-radius:8px;letter-spacing:.04em;white-space:nowrap}
.dash-row{display:grid;gap:18px}
.cards4{grid-template-columns:repeat(4,minmax(0,1fr))}
.split-60-40{grid-template-columns:minmax(0,3fr) minmax(0,2fr)}
.split-50{grid-template-columns:1fr 1fr}
.stat{background:var(--surface);border:1px solid var(--border);border-left:4px solid #3b82f6;border-radius:14px;padding:18px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;transition:transform 220ms ease,box-shadow 220ms ease}
.stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stat.b-orange{border-left-color:#f97316}
.stat.b-blue{border-left-color:#3b82f6}
.stat.b-green{border-left-color:#10b981}
.stat.b-amber{border-left-color:#f59e0b}
.stat.b-red{border-left-color:#ef4444;box-shadow:var(--shadow-sm),0 0 0 1px rgba(239,68,68,.15),0 12px 30px -12px rgba(239,68,68,.25)}
.cards5-wrap{display:flex;flex-direction:column;gap:12px}
.cards5{grid-template-columns:repeat(5,minmax(0,1fr))}
.dept-req-chip{display:inline-flex;align-items:center;gap:8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);color:#f97316;font-size:13px;font-weight:600;padding:8px 16px;border-radius:10px;text-decoration:none;transition:background 200ms ease;align-self:flex-start}
.dept-req-chip:hover{background:rgba(249,115,22,.18)}
.stat-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px}
.stat-ico{width:42px;height:42px;border-radius:10px;display:grid;place-items:center}
.stat-ico svg{width:20px;height:20px}
.stat-ico.orange{background:rgba(249,115,22,.12);color:#f97316}
.stat-ico.blue{background:rgba(59,130,246,.12);color:#3b82f6}
.stat-ico.amber{background:rgba(245,158,11,.14);color:#f59e0b}
.stat-ico.red{background:rgba(239,68,68,.12);color:#ef4444}
.stat-trend{font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px}
.stat-trend.up{background:rgba(16,185,129,.12);color:#10b981}
.stat-trend.dn{background:rgba(239,68,68,.12);color:#ef4444}
.stat-num{font-size:30px;font-weight:700;letter-spacing:-.025em;line-height:1;color:var(--fg)}
.stat-unit{font-size:14px;font-weight:600;color:var(--fg-2);margin-left:6px;letter-spacing:0}
.stat-lbl{font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--fg-2);margin-top:8px}
.stat-sub{font-size:12px;color:var(--fg-2);margin-top:6px}
.stat-sub strong{color:var(--fg)}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:var(--shadow-sm)}
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap}
.panel-title{font-size:15px;font-weight:700;letter-spacing:-.01em;color:var(--fg)}
.panel-meta{font-size:11.5px;color:var(--fg-2);font-weight:500;margin-left:8px}
.panel-link{color:#f97316;font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap}
.panel-link:hover{text-decoration:underline}
.tabs{display:inline-flex;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:3px;gap:2px}
.tabs button{appearance:none;border:0;background:transparent;color:var(--fg-2);padding:4px 10px;font-size:11.5px;font-weight:600;border-radius:6px;cursor:pointer;font-family:inherit;transition:color 200ms ease,background 200ms ease}
.tabs button.active{background:var(--surface);color:var(--fg);box-shadow:var(--shadow-sm)}
.tabs button:hover:not(.active){color:var(--fg)}
.legend{display:inline-flex;gap:14px;font-size:11.5px;color:var(--fg-2);align-items:center}
.legend span{display:flex;align-items:center;gap:5px}
.legend i{display:inline-block;width:10px;height:10px;border-radius:3px;flex-shrink:0}
.lg-blue{background:#3b82f6}
.lg-orange{background:#f97316}
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.tbl th{text-align:left;font-size:10.5px;font-weight:700;color:var(--fg-2);letter-spacing:.1em;text-transform:uppercase;padding:8px 10px;border-bottom:1px solid var(--border-soft);white-space:nowrap}
.tbl td{padding:11px 10px;border-bottom:1px solid var(--border-soft);color:var(--fg)}
.tbl tr:last-child td{border-bottom:0}
.tbl tr:hover td{background:var(--hover)}
.tbl .mono{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--fg-2)}
.tbl .dim{color:var(--fg-2)}
.tbl .num{font-variant-numeric:tabular-nums}
.tbl .right{text-align:right}
.tbl .sm{font-size:12px}
.pill{display:inline-flex;align-items:center;font-size:10.5px;font-weight:700;letter-spacing:.04em;padding:3px 8px;border-radius:999px;text-transform:uppercase}
.pill.gr{background:rgba(59,130,246,.14);color:#3b82f6}
.pill.gi{background:rgba(249,115,22,.14);color:#f97316}
.pill.trf{background:var(--surface-2);color:var(--fg-2);border:1px solid var(--border)}
.pill.ok{background:rgba(16,185,129,.14);color:#10b981}
.pill.pn{background:rgba(234,179,8,.18);color:#a16207}
.pill.dr{background:var(--surface-2);color:var(--fg-2);border:1px solid var(--border)}
.pill.rej{background:rgba(239,68,68,.14);color:#ef4444}
.wh-card{padding:14px;border:1px solid var(--border-soft);border-radius:10px;margin-bottom:10px;background:var(--surface)}
.wh-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.wh-name{font-size:13.5px;font-weight:700;color:var(--fg)}
.wh-loc{font-size:11.5px;color:var(--fg-2);margin-top:2px}
.wh-pct{font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
.wh-qty{font-size:12.5px;font-weight:700;color:var(--fg-2);font-variant-numeric:tabular-nums;white-space:nowrap}
.wh-bar{height:8px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:999px;overflow:hidden}
.wh-bar > i{display:block;height:100%;border-radius:999px;background:#10b981;transition:width 380ms ease}
.wh-bar.warn > i{background:#f59e0b}
.wh-bar.crit > i{background:#ef4444}
.wh-racks{margin-top:4px;font-size:11.5px;color:var(--fg-2);font-family:'JetBrains Mono',monospace;letter-spacing:.02em}
.wh-racks .crit{color:#ef4444}
.wh-racks .warn{color:#f59e0b}
.wh-empty{font-size:13px;color:var(--fg-2);text-align:center;padding:24px 0}
.low-soh{color:#ef4444;font-weight:700}
.ls-name{font-size:13px;font-weight:600;color:var(--fg);line-height:1.3}
.ls-details{font-size:11px;color:var(--fg-2);margin-top:1px;line-height:1.4}
.ls-reorder-qty{color:#f97316;font-weight:700}
.ls-uom{font-size:10px;font-weight:500;color:var(--fg-2);margin-left:2px}
.reorder-btn{appearance:none;border:1px solid var(--border);background:var(--surface-2);color:var(--fg-2);font-size:11px;font-weight:700;letter-spacing:.04em;padding:5px 12px;border-radius:6px;cursor:not-allowed;font-family:inherit;transition:background 200ms ease,color 200ms ease,border-color 200ms ease;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.reorder-btn.reorder-active{border-color:#f97316;color:#f97316;background:transparent;cursor:pointer}
.reorder-btn.reorder-active:hover{background:#f97316;color:#fff}
.reorder-check{width:14px;height:14px;cursor:pointer;accent-color:#f97316}
.row-selected td{background:rgba(249,115,22,.06)!important}
/* mandatory distribution widget */
.mandatory-panel{border-left:4px solid #f59e0b}
.mandatory-clear{font-size:13px;color:#10b981;font-weight:600;padding:8px 0}
.mandatory-group{}
.mandatory-group-title{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--fg-2);margin-bottom:10px}
.mandatory-item-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-soft);gap:12px;flex-wrap:wrap}
.mandatory-item-row:last-child{border-bottom:0}
.mandatory-item-left{display:flex;align-items:center;gap:10px;flex:1}
.mandatory-item-name{font-size:13.5px;font-weight:600;color:var(--fg)}
.mandatory-overdue-count{background:rgba(239,68,68,.12);color:#ef4444;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px}
.mandatory-details{font-size:12px}
.mandatory-summary{cursor:pointer;color:#f97316;font-weight:600;font-size:12px;user-select:none;padding:4px 0}
.mandatory-list{margin:6px 0 0 16px;padding:0;list-style:disc;color:var(--fg-2);font-size:12px;line-height:1.9}
.mandatory-dept{color:var(--fg-2)}
.mono{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--fg-2)}
@media(max-width:1100px){
  .cards4,.cards5{grid-template-columns:repeat(2,1fr)}
  .split-60-40,.split-50{grid-template-columns:1fr}
}
@media(max-width:760px){
  .cards4,.cards5{grid-template-columns:1fr 1fr}
  .page-head{flex-direction:column;align-items:flex-start}
}
</style>