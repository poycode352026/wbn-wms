<script setup>
import { ref, computed } from 'vue'
import { router, Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    grq:       Object,
    operators: { type: Array, default: () => [] },
})

const STATUS_MAP = {
    pending:         { lbl: 'Pending',          bg:'rgba(156,163,175,.15)', col:'#9ca3af' },
    assigned:        { lbl: 'Assigned',         bg:'rgba(59,130,246,.15)',  col:'#3b82f6' },
    in_picking:      { lbl: 'In Picking',       bg:'rgba(249,115,22,.15)',  col:'#f97316' },
    ready_to_pickup: { lbl: 'Ready to Pickup',  bg:'rgba(234,179,8,.15)',   col:'#eab308' },
    completed:       { lbl: 'Completed',        bg:'rgba(16,185,129,.15)',  col:'#34d399' },
    cancelled:       { lbl: 'Cancelled',        bg:'rgba(239,68,68,.15)',   col:'#f87171' },
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

// ── assign ─────────────────────────────────────────────────────────────────
const assignForm = useForm({ operator_id: '' })

function doAssign() {
    if (!assignForm.operator_id) return
    assignForm.post(route('grq.assign', props.grq.id))
}

// ── cancel ─────────────────────────────────────────────────────────────────
const cancelling = ref(false)

function confirmCancel() {
    if (!confirm(t('grq.confirmCancel'))) return
    cancelling.value = true
    router.post(route('grq.cancel', props.grq.id), {}, {
        onFinish: () => { cancelling.value = false },
    })
}

const canCancel  = computed(() => ['pending', 'assigned'].includes(props.grq.status))
const canAssign  = computed(() => props.grq.status === 'pending')

// ── photos ─────────────────────────────────────────────────────────────────
const lightboxSrc = ref(null)
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

    <!-- Assign Operator Panel (pending only) -->
    <div v-if="canAssign" class="grq-card grq-assign-card">
      <div class="grq-card-title">{{ $t('grq.assignOperatorTitle') }}</div>
      <p class="grq-assign-sub">{{ $t('grq.assignOperatorSub') }}</p>
      <div class="grq-assign-row">
        <select class="grq-input grq-assign-select" v-model="assignForm.operator_id">
          <option value="">{{ $t('grq.selectOperator') }}</option>
          <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
        </select>
        <button type="button" class="grq-assign-btn"
          :disabled="!assignForm.operator_id || assignForm.processing"
          @click="doAssign">
          {{ assignForm.processing ? $t('common.saving') : $t('grq.assignBtn') }}
        </button>
      </div>
    </div>

    <!-- Operator Info (assigned+) -->
    <div v-if="grq.assigned_to" class="grq-card grq-operator-card">
      <div class="grq-card-title">{{ $t('grq.operatorInfo') }}</div>
      <div class="grq-meta-row">
        <div class="grq-meta-lbl">{{ $t('grq.operator') }}</div>
        <div class="grq-meta-val"><span class="grq-op-badge">👷</span> {{ grq.assigned_to?.name }}</div>
      </div>
      <div v-if="grq.assigned_at" class="grq-meta-row">
        <div class="grq-meta-lbl">{{ $t('grq.assignedAt') }}</div>
        <div class="grq-meta-val">{{ fmtDate(grq.assigned_at) }}</div>
      </div>
      <div v-if="grq.completed_at" class="grq-meta-row">
        <div class="grq-meta-lbl">{{ $t('grq.completedAt') }}</div>
        <div class="grq-meta-val">{{ fmtDate(grq.completed_at) }}</div>
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
              <th v-if="grq.status !== 'pending'" class="tr">{{ $t('grq.colActualQty') }}</th>
              <th v-if="grq.status !== 'pending'">{{ $t('grq.colItemStatus') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!grq.items?.length">
              <td :colspan="grq.status !== 'pending' ? 11 : 9" class="grq-items-empty">No items.</td>
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
              <td v-if="grq.status !== 'pending'" class="tr grq-qty">
                {{ item.actual_qty != null ? item.actual_qty.toLocaleString() : '—' }}
              </td>
              <td v-if="grq.status !== 'pending'">
                <span v-if="item.item_status === 'ready'"    class="grq-item-status grq-item-ready">Ready</span>
                <span v-else-if="item.item_status === 'rejected'" class="grq-item-status grq-item-rejected" :title="item.notes">Rejected</span>
                <span v-else class="grq-dim">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Photos (completed) -->
    <div v-if="grq.photos?.length" class="grq-card">
      <div class="grq-card-title">{{ $t('grq.photosPickup') }}</div>
      <div class="grq-photos-grid">
        <div v-for="(photo, i) in grq.photos" :key="photo.id" class="grq-photo-thumb"
          @click="lightboxSrc = '/storage/' + photo.path">
          <img :src="'/storage/' + photo.path" :alt="photo.original_name" />
        </div>
      </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="lightboxSrc" class="grq-lightbox" @click="lightboxSrc = null">
        <img :src="lightboxSrc" />
      </div>
    </Teleport>

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

/* ── assign panel ──────────────────────────────────────────────────── */
.grq-assign-card { border-color: rgba(59,130,246,.4); background:rgba(59,130,246,.03) }
.grq-assign-sub  { font-size:12.5px; color:var(--fg-2); margin-bottom:12px }
.grq-assign-row  { display:flex; gap:10px; align-items:center }
.grq-assign-select { flex:1; padding:9px 12px; font-size:13px; font-family:inherit; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; color:var(--fg); outline:none }
.grq-assign-btn {
  padding:9px 18px; border:none; border-radius:8px; font-size:13px; font-weight:700;
  font-family:inherit; background:#3b82f6; color:#fff; cursor:pointer; white-space:nowrap;
  transition:opacity 150ms;
}
.grq-assign-btn:disabled { opacity:.4; cursor:not-allowed }
.grq-assign-btn:not(:disabled):hover { opacity:.88 }
.grq-input { padding:9px 12px; font-size:13px; font-family:inherit; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; color:var(--fg); width:100%; outline:none }

/* ── operator card ──────────────────────────────────────────────────── */
.grq-operator-card { border-color:rgba(59,130,246,.3) }
.grq-op-badge { font-size:14px }

/* ── item status ───────────────────────────────────────────────────── */
.grq-item-status { display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700; text-transform:uppercase }
.grq-item-ready    { background:rgba(52,211,153,.15); color:#34d399 }
.grq-item-rejected { background:rgba(239,68,68,.15);  color:#f87171; cursor:help }

/* ── photos grid ───────────────────────────────────────────────────── */
.grq-photos-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px }
.grq-photo-thumb { width:100px; height:100px; border-radius:8px; overflow:hidden; cursor:zoom-in; border:1px solid var(--border) }
.grq-photo-thumb img { width:100%; height:100%; object-fit:cover }

/* ── lightbox ──────────────────────────────────────────────────────── */
.grq-lightbox { position:fixed; inset:0; background:rgba(0,0,0,.88); z-index:9999; display:flex; align-items:center; justify-content:center; cursor:zoom-out; padding:20px }
.grq-lightbox img { max-width:100%; max-height:100%; border-radius:8px; object-fit:contain }

@media (max-width:640px) {
  .grq-info-grid { grid-template-columns:1fr }
  .grq-assign-row { flex-direction:column }
  .grq-assign-select, .grq-assign-btn { width:100% }
}
</style>
