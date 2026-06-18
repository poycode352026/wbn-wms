<script setup>
import { ref, computed } from 'vue'
import { router, Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    grq: Object,
})

const STATUS_MAP = {
    completed: { lbl: 'Completed', bg:'rgba(16,185,129,.15)',  col:'#34d399' },
    cancelled: { lbl: 'Cancelled', bg:'rgba(239,68,68,.15)',   col:'#f87171' },
}
function statusStyle(s) { const m = STATUS_MAP[s]??{}; return { background:m.bg, color:m.col } }
function statusLabel(s) { return STATUS_MAP[s]?.lbl ?? s }

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit',
    })
}

function itemName(v) {
    if (!v?.item) return v?.sku ?? '—'
    if (locale.value === 'zh' && v.item.name_zh) return v.item.name_zh
    if (locale.value === 'id' && v.item.name_id) return v.item.name_id
    return v.item.name_en || v.item.name_id || v.sku
}

// ── cancel ─────────────────────────────────────────────────────────────────
const showCancel = ref(false)
const cancelling = ref(false)

function confirmCancel() {
    if (!confirm(t('grq.confirmCancel'))) return
    cancelling.value = true
    router.post(route('grq.cancel', props.grq.id), {}, {
        onFinish: () => { cancelling.value = false },
    })
}

const canCancel = computed(() => props.grq.status === 'completed')
</script>

<template>
<AppLayout>
  <template #title>{{ grq.grq_number }}</template>
  <template #breadcrumb>
    <Link :href="route('grq.index')" class="bc-link">{{ $t('grq.title') }}</Link>
    <span class="bc-sep">›</span>
    {{ grq.grq_number }}
  </template>

  <div class="grq-show-page">

    <!-- Page header -->
    <div class="grq-ph">
      <div>
        <div class="grq-num-row">
          <span class="grq-num">{{ grq.grq_number }}</span>
          <span class="grq-status-badge" :style="statusStyle(grq.status)">
            {{ statusLabel(grq.status) }}
          </span>
        </div>
        <p class="grq-ps">{{ $t('grq.sub') }}</p>
      </div>
      <div class="grq-hdr-actions">
        <Link :href="route('grq.index')" class="grq-back-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          {{ $t('btn.back') }}
        </Link>
        <button v-if="canCancel" type="button" class="grq-cancel-btn" :disabled="cancelling" @click="confirmCancel">
          <span v-if="cancelling">Cancelling…</span>
          <span v-else>{{ $t('grq.cancelBtn') }}</span>
        </button>
      </div>
    </div>

    <!-- Info cards -->
    <div class="grq-info-grid">

      <div class="grq-card">
        <div class="grq-card-title">Requester</div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">{{ $t('grq.requesterName') }}</div>
          <div class="grq-meta-val">{{ grq.requester_name }}</div>
        </div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">{{ $t('grq.requesterEmpId') }}</div>
          <div class="grq-meta-val">{{ grq.requester_emp_id ?? '—' }}</div>
        </div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">{{ $t('grq.department') }}</div>
          <div class="grq-meta-val">{{ grq.department?.name ?? '—' }}</div>
        </div>
      </div>

      <div class="grq-card">
        <div class="grq-card-title">Request Details</div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">{{ $t('grq.warehouse') }}</div>
          <div class="grq-meta-val">
            <span class="grq-wh-badge">{{ grq.warehouse?.code ?? '—' }}</span>
            {{ grq.warehouse?.name }}
          </div>
        </div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">{{ $t('grq.recordedBy') }}</div>
          <div class="grq-meta-val">{{ grq.recorded_by?.name ?? '—' }}</div>
        </div>
        <div class="grq-meta-row">
          <div class="grq-meta-lbl">Date</div>
          <div class="grq-meta-val">{{ fmtDate(grq.created_at) }}</div>
        </div>
      </div>

      <div v-if="grq.remark || grq.cancelled_by" class="grq-card grq-card-full">
        <div v-if="grq.remark">
          <div class="grq-card-title">{{ $t('grq.remark') }}</div>
          <p class="grq-remark-text">{{ grq.remark }}</p>
        </div>
        <div v-if="grq.cancelled_by" class="grq-cancel-info">
          <span class="grq-meta-lbl">{{ $t('grq.cancelledBy') }}: </span>
          <span class="grq-meta-val">{{ grq.cancelled_by?.name }}</span>
          <span class="grq-sep"> · </span>
          <span class="grq-meta-lbl">{{ $t('grq.cancelledAt') }}: </span>
          <span class="grq-meta-val">{{ fmtDate(grq.cancelled_at) }}</span>
        </div>
      </div>

    </div>

    <!-- Items table -->
    <div class="grq-card">
      <div class="grq-card-title">Item List ({{ grq.items?.length ?? 0 }} items)</div>
      <div class="grq-items-wrap">
        <table class="grq-items-table">
          <thead>
            <tr>
              <th>#</th>
              <th>SKU</th>
              <th>Item</th>
              <th>Warehouse</th>
              <th>Location</th>
              <th class="tr">{{ $t('grq.colReqQty') }}</th>
              <th>{{ $t('grq.colUomUsed') }}</th>
              <th class="tr">{{ $t('grq.colBaseQty') }}</th>
              <th>Base UoM</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!grq.items?.length">
              <td colspan="9" class="grq-items-empty">No items.</td>
            </tr>
            <tr v-for="(item, idx) in grq.items" :key="item.id">
              <td class="grq-idx">{{ idx + 1 }}</td>
              <td class="grq-sku">{{ item.variant?.sku ?? '—' }}</td>
              <td class="grq-item-name">{{ itemName(item.variant) }}</td>
              <td>
                <span v-if="item.warehouse?.code" class="grq-wh-badge">{{ item.warehouse.code }}</span>
                <span v-else class="grq-dim">—</span>
              </td>
              <td class="grq-loc">{{ item.location?.code ?? '—' }}</td>
              <td class="tr grq-qty">{{ item.requested_qty?.toLocaleString() }}</td>
              <td class="grq-uom">{{ item.uom_used }}</td>
              <td class="tr grq-qty">{{ item.qty_in_base_uom?.toLocaleString() }}</td>
              <td class="grq-uom">{{ item.variant?.item?.base_uom ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</AppLayout>
</template>

<style scoped>
.grq-show-page { display:flex; flex-direction:column; gap:18px }

.grq-ph { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap }
.grq-num-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap }
.grq-num { font-size:22px; font-weight:800; color:var(--fg); font-family:monospace; letter-spacing:-.01em }
.grq-ps  { font-size:13px; color:var(--fg-2); margin-top:4px }

.bc-link { color:var(--fg-2); text-decoration:none }
.bc-link:hover { color:var(--fg) }
.bc-sep  { margin:0 6px; color:var(--fg-dim) }

.grq-status-badge { display:inline-flex; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; white-space:nowrap }

.grq-hdr-actions { display:flex; gap:8px; align-items:center }

.grq-back-btn {
  display:inline-flex; align-items:center; gap:6px; padding:8px 16px;
  border:1px solid var(--border-2); border-radius:8px; font-size:13px; font-weight:600;
  color:var(--fg-2); background:var(--surface); text-decoration:none;
}
.grq-back-btn:hover { background:var(--hover) }

.grq-cancel-btn {
  display:inline-flex; align-items:center; gap:6px; padding:8px 16px;
  border:1px solid rgba(239,68,68,.4); border-radius:8px; font-size:13px; font-weight:600;
  color:#f87171; background:rgba(239,68,68,.07); cursor:pointer; font-family:inherit;
}
.grq-cancel-btn:hover:not(:disabled) { background:rgba(239,68,68,.14) }
.grq-cancel-btn:disabled { opacity:.45; cursor:not-allowed }

/* ── info grid ─────────────────────────────────────────────────────── */
.grq-info-grid {
  display:grid; grid-template-columns:1fr 1fr; gap:14px;
}
.grq-card-full { grid-column:1/-1 }

/* ── card ─────────────────────────────────────────────────────────── */
.grq-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px }
.grq-card-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--fg-dim); margin-bottom:12px }

.grq-meta-row { display:flex; gap:12px; padding:5px 0; border-bottom:1px solid var(--border) }
.grq-meta-row:last-child { border-bottom:0 }
.grq-meta-lbl { font-size:12px; color:var(--fg-dim); min-width:120px; flex-shrink:0 }
.grq-meta-val { font-size:13px; color:var(--fg); font-weight:500; display:flex; align-items:center; gap:6px }

.grq-wh-badge {
  display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700;
  background:rgba(249,115,22,.1); color:var(--orange-500);
}

.grq-remark-text { font-size:13px; color:var(--fg-2); line-height:1.5 }
.grq-cancel-info { margin-top:8px; font-size:12.5px }
.grq-sep { color:var(--fg-dim) }

/* ── items table ─────────────────────────────────────────────────── */
.grq-items-wrap { overflow:auto }
.grq-items-table { width:100%; border-collapse:collapse; font-size:13px }
.grq-items-table th {
  padding:9px 12px; text-align:left; font-size:10.5px; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em; color:var(--fg-dim);
  border-bottom:1px solid var(--border); white-space:nowrap;
}
.grq-items-table td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle }
.grq-items-table tr:last-child td { border-bottom:0 }
.grq-items-table tr:hover td { background:var(--hover) }
.tr { text-align:right }

.grq-idx  { color:var(--fg-dim); font-size:12px; width:30px }
.grq-sku  { font-family:monospace; font-size:12px; font-weight:700; color:var(--orange-500) }
.grq-item-name { color:var(--fg-2) }
.grq-qty  { font-weight:700; color:var(--fg) }
.grq-uom  { color:var(--fg-dim); font-size:12px }
.grq-loc  { font-family:monospace; font-size:11.5px; color:var(--fg-dim) }
.grq-dim  { color:var(--fg-dim) }
.grq-items-empty { text-align:center; padding:32px; color:var(--fg-dim); font-size:13px }

@media (max-width:640px) {
  .grq-info-grid { grid-template-columns:1fr }
}
</style>
