<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    grqs:       Object,
    warehouses: Array,
    filters:    Object,
})

const search    = ref(props.filters?.search      ?? '')
const statusF   = ref(props.filters?.status      ?? '')
const warehouseF= ref(props.filters?.warehouse_id ?? '')

let timer = null
watch([search, statusF, warehouseF], () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('grq.index'), {
            search:       search.value      || undefined,
            status:       statusF.value     || undefined,
            warehouse_id: warehouseF.value  || undefined,
        }, { preserveState: true, replace: true })
    }, 320)
})

const STATUS_MAP = {
    completed: { lbl: 'Completed', bg:'rgba(16,185,129,.15)',  col:'#34d399' },
    cancelled: { lbl: 'Cancelled', bg:'rgba(239,68,68,.15)',   col:'#f87171' },
}
function statusStyle(s) { const m = STATUS_MAP[s]??{}; return { background:m.bg, color:m.col } }
function statusLabel(s) { return STATUS_MAP[s]?.lbl ?? s }

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', { day:'2-digit', month:'short', year:'numeric' })
}
</script>

<template>
<AppLayout>
  <template #title>{{ $t('grq.title') }}</template>
  <template #breadcrumb>{{ $t('grq.title') }}</template>

  <div class="grq-page">

    <!-- Page header -->
    <div class="grq-ph">
      <div>
        <h1 class="grq-pt">{{ $t('grq.title') }}</h1>
        <p class="grq-ps">{{ $t('grq.sub') }}</p>
      </div>
      <Link :href="route('grq.create')" class="grq-create-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        {{ $t('grq.createBtn') }}
      </Link>
    </div>

    <!-- Toolbar -->
    <div class="grq-toolbar">
      <input class="grq-search" type="text" v-model="search" :placeholder="$t('grq.searchPh')" />
      <select class="grq-filter" v-model="statusF">
        <option value="">{{ $t('grq.filterStatus') }}</option>
        <option v-for="(v, k) in STATUS_MAP" :key="k" :value="k">{{ v.lbl }}</option>
      </select>
      <select class="grq-filter" v-model="warehouseF">
        <option value="">{{ $t('grq.filterWh') }}</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="grq-table-wrap">
      <table class="grq-table">
        <thead>
          <tr>
            <th>{{ $t('grq.colGrqNo') }}</th>
            <th>{{ $t('grq.colRequester') }}</th>
            <th>{{ $t('grq.colDept') }}</th>
            <th>{{ $t('grq.colWarehouse') }}</th>
            <th class="tc">{{ $t('grq.colItems') }}</th>
            <th>{{ $t('grq.colStatus') }}</th>
            <th>{{ $t('grq.colRecordedBy') }}</th>
            <th>{{ $t('grq.colDate') }}</th>
            <th class="tc">{{ $t('grq.colActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!grqs.data.length">
            <td colspan="9" class="grq-empty">{{ $t('grq.noResults') }}</td>
          </tr>
          <tr v-for="grq in grqs.data" :key="grq.id">
            <td><span class="grq-num">{{ grq.grq_number }}</span></td>
            <td>
              <div class="grq-requester">{{ grq.requester_name }}</div>
              <div v-if="grq.requester_emp_id" class="grq-empid">{{ grq.requester_emp_id }}</div>
            </td>
            <td>{{ grq.department?.name ?? '—' }}</td>
            <td><span class="grq-wh-badge">{{ grq.warehouse?.code ?? '—' }}</span></td>
            <td class="tc">{{ grq.items_count }}</td>
            <td>
              <span class="grq-status-badge" :style="statusStyle(grq.status)">
                {{ statusLabel(grq.status) }}
              </span>
            </td>
            <td>{{ grq.recorded_by?.name ?? '—' }}</td>
            <td class="grq-date">{{ fmtDate(grq.created_at) }}</td>
            <td class="tc">
              <Link :href="route('grq.show', grq.id)" class="grq-view-btn">
                {{ $t('grq.view') }}
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="grqs.last_page > 1" class="grq-pager">
      <Link v-if="grqs.prev_page_url" :href="grqs.prev_page_url" class="pg-btn">←</Link>
      <span class="pg-info">{{ grqs.current_page }} / {{ grqs.last_page }}</span>
      <Link v-if="grqs.next_page_url" :href="grqs.next_page_url" class="pg-btn">→</Link>
    </div>

  </div>
</AppLayout>
</template>

<style scoped>
.grq-page { display:flex; flex-direction:column; gap:18px }

.grq-ph  { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap }
.grq-pt  { font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.02em }
.grq-ps  { font-size:13px; color:var(--fg-2); margin-top:3px }
.grq-create-btn {
  display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:9px;
  font-size:13px; font-weight:600; background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  color:#fff; border:0; text-decoration:none; box-shadow:0 4px 10px -3px rgba(249,115,22,.45);
  transition:opacity 150ms; white-space:nowrap;
}
.grq-create-btn:hover { opacity:.88 }

/* ── toolbar ─────────────────────────────────────────────────────────── */
.grq-toolbar { display:flex; gap:10px; flex-wrap:wrap }
.grq-search, .grq-filter {
  padding:8px 12px; font-size:13px; background:var(--surface);
  border:1px solid var(--border-2); border-radius:8px; color:var(--fg);
  outline:none; font-family:inherit; transition:border-color 180ms;
}
.grq-search { flex:1; min-width:200px }
.grq-search:focus, .grq-filter:focus { border-color:var(--orange-500) }

/* ── table ───────────────────────────────────────────────────────────── */
.grq-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:auto }
.grq-table { width:100%; border-collapse:collapse; font-size:13px }
.grq-table th {
  padding:10px 14px; text-align:left; font-size:10.5px; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em; color:var(--fg-dim);
  border-bottom:1px solid var(--border); white-space:nowrap; background:var(--surface);
}
.grq-table td { padding:11px 14px; border-bottom:1px solid var(--border); color:var(--fg-2); vertical-align:middle }
.grq-table tr:last-child td { border-bottom:0 }
.grq-table tr:hover td { background:var(--hover) }
.tc { text-align:center }

.grq-num { font-family:monospace; font-size:12.5px; font-weight:700; color:var(--fg) }
.grq-requester { font-weight:600; color:var(--fg) }
.grq-empid { font-size:11px; color:var(--fg-dim); font-family:monospace }
.grq-wh-badge {
  display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700;
  background:rgba(249,115,22,.1); color:var(--orange-500);
}
.grq-status-badge { display:inline-flex; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:700; white-space:nowrap }
.grq-date { font-size:12px; white-space:nowrap; color:var(--fg-dim) }
.grq-empty { text-align:center; padding:48px; color:var(--fg-dim); font-size:13px }
.grq-view-btn {
  display:inline-flex; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600;
  background:var(--surface-2); border:1px solid var(--border-2); color:var(--fg-2);
  text-decoration:none; transition:background 150ms;
}
.grq-view-btn:hover { background:var(--hover); color:var(--fg) }

/* ── pagination ─────────────────────────────────────────────────────── */
.grq-pager { display:flex; align-items:center; justify-content:center; gap:12px }
.pg-btn { display:inline-flex; padding:6px 14px; border-radius:8px; background:var(--surface); border:1px solid var(--border-2); color:var(--fg-2); text-decoration:none; font-size:13px; font-weight:600; transition:background 150ms }
.pg-btn:hover { background:var(--hover) }
.pg-info { font-size:13px; color:var(--fg-dim) }
</style>
