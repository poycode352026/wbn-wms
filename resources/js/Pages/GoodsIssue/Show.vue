<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import QRCode from 'qrcode'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    gi:                 Object,
    userRole:           String,
    userId:             Number,
    operators:          Array,
    locationMap:        { type: Object, default: () => ({}) },
    warehouseStockMap:  { type: Object, default: () => null },
})

const flash = computed(() => page.props.flash ?? {})

function itemName(variant) {
    if (!variant?.item) return '—'
    const item = variant.item
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}

function fmtDateOnly(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: '2-digit', month: 'short', year: 'numeric',
    })
}
function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function storageUrl(path) { return `/storage/${path}` }

function fmtQty(v) {
    if (v === null || v === undefined) return '—'
    const n = parseFloat(v)
    if (isNaN(n)) return String(v)
    return Number.isInteger(n) ? String(n) : parseFloat(n.toFixed(4)).toString()
}

// ── Progress stepper (mirrors GR style) ───────────────────────────────────────
const STATUS_TO_IDX = {
    draft: 0, pending_manager_dept: 1, pending_supervisor: 2,
    pending_manager_wh: 3, approved: 4, assigned: 5, in_picking: 5,
    ready_to_pickup: 6, completed: 7,
}

const giSteps = computed(() => {
    const gi  = props.gi
    const s   = gi.status
    const idx = STATUS_TO_IDX[s] ?? 0
    const isRej = s === 'rejected'

    const apprls = gi.approvals ?? []
    const findAppr = (step, action) => apprls.find(a => a.step === step && a.action === action)

    // which approval step index was rejected at?
    const rejStepMap = { manager_dept: 1, wh_supervisor: 2, wh_manager: 3 }
    const lastRej = [...apprls].reverse().find(a => a.action === 'rejected')
    const rejIdx  = isRej ? (rejStepMap[lastRej?.step] ?? 1) : -1

    const mkStep = (i, key, label, sub, time) => {
        const done     = isRej ? i < rejIdx : i < idx
        const active   = !isRej && i === idx
        const rejected = isRej && i === rejIdx
        return { key, label, sub, time, done, active, rejected }
    }

    const mgr = findAppr('manager_dept', 'approved') ?? findAppr('manager_dept', 'rejected')
    const spv = findAppr('wh_supervisor', 'approved') ?? findAppr('wh_supervisor', 'rejected')
    const whm = findAppr('wh_manager',   'approved') ?? findAppr('wh_manager',   'rejected')

    return [
        mkStep(0, 'draft',                'Draft',        gi.requested_by?.name ?? '—',       gi.created_at),
        mkStep(1, 'pending_manager_dept', 'Dept Manager', mgr?.acted_by?.name ?? 'Pending',   mgr?.acted_at),
        mkStep(2, 'pending_supervisor',   'WH Supervisor',spv?.acted_by?.name ?? 'Pending',   spv?.acted_at),
        mkStep(3, 'pending_manager_wh',   'WH Manager',   whm?.acted_by?.name ?? 'Pending',   whm?.acted_at),
        mkStep(4, 'approved',    'Assign Operator', gi.assigned_to?.name ?? 'Pending', null),
        mkStep(5, 'in_progress', 'Picking',         gi.picked_by?.name ?? gi.assigned_to?.name ?? 'Pending', gi.picked_at),
        mkStep(6, 'ready_to_pickup',      'Ready',        'Staging area',                     null),
        mkStep(7, 'completed',            'Completed',    s === 'completed' ? 'Done' : 'Pending', gi.completed_at),
    ]
})

const showLocations = computed(() =>
    ['wh_admin', 'wh_supervisor', 'wh_manager', 'super_admin'].includes(props.userRole))

const STATUS_MAP = {
    draft:                { lbl: 'Draft',        bg: 'rgba(100,116,139,.15)', col: '#94a3b8' },
    pending_manager_dept: { lbl: 'Mgr Dept',     bg: 'rgba(59,130,246,.15)',  col: '#60a5fa' },
    pending_supervisor:   { lbl: 'WH Supervisor', bg: 'rgba(234,179,8,.15)',   col: '#fbbf24' },
    pending_manager_wh:   { lbl: 'WH Manager',   bg: 'rgba(139,92,246,.15)',  col: '#a78bfa' },
    approved:             { lbl: 'Approved',     bg: 'rgba(16,185,129,.15)',  col: '#34d399' },
    assigned:             { lbl: 'Assigned',     bg: 'rgba(249,115,22,.15)',  col: '#fb923c' },
    in_picking:           { lbl: 'Picking',      bg: 'rgba(249,115,22,.2)',   col: '#f97316' },
    ready_to_pickup:      { lbl: 'Ready',        bg: 'rgba(16,185,129,.25)',  col: '#10b981' },
    completed:            { lbl: 'Completed',    bg: 'rgba(16,185,129,.15)',  col: '#34d399' },
    rejected:             { lbl: 'Rejected',     bg: 'rgba(239,68,68,.15)',   col: '#f87171' },
}
const statusStyle = (s) => { const m = STATUS_MAP[s] ?? {}; return { background: m.bg, color: m.col } }
const statusLabel = (s) => STATUS_MAP[s]?.lbl ?? s

const STEP_LABELS = {
    manager_dept:  () => t('gi.stepManagerDept'),
    wh_supervisor: () => t('gi.stepSupervisor'),
    wh_manager:    () => t('gi.stepWarehouseManager'),
}

const isAssignedToMe = computed(() => props.gi.assigned_to?.id === props.userId)
const isPickedByMe   = computed(() => props.gi.picked_by?.id === props.userId)

const canSubmit = computed(() =>
    props.gi.status === 'draft' && ['admin_dept','super_admin'].includes(props.userRole))

const canApproveManagerDept = computed(() =>
    props.gi.status === 'pending_manager_dept' && ['manager_dept','super_admin'].includes(props.userRole))
const canApproveSupervisor = computed(() =>
    props.gi.status === 'pending_supervisor' && ['wh_supervisor','super_admin'].includes(props.userRole))
const canApproveManagerWH = computed(() =>
    props.gi.status === 'pending_manager_wh' && ['wh_manager','super_admin'].includes(props.userRole))
const canApprove = computed(() =>
    canApproveManagerDept.value || canApproveSupervisor.value || canApproveManagerWH.value)

const canAssign = computed(() =>
    props.gi.status === 'approved' && ['wh_admin','super_admin'].includes(props.userRole))
const canSelfAssign = computed(() =>
    props.gi.status === 'approved' && props.userRole === 'operator')
const canStartPicking = computed(() =>
    props.gi.status === 'assigned' && (isAssignedToMe.value || props.userRole === 'super_admin'))
const canPickItems = computed(() =>
    props.gi.status === 'in_picking' && (isPickedByMe.value || props.userRole === 'super_admin'))
const canPickup = computed(() =>
    props.gi.status === 'ready_to_pickup' &&
    ['wh_supervisor','wh_manager','wh_admin','super_admin'].includes(props.userRole))
const showBarcode = computed(() => props.gi.status === 'ready_to_pickup')

const requestPhotos = computed(() => (props.gi.photos ?? []).filter(p => p.stage === 'request'))
const pickingPhotos = computed(() => (props.gi.photos ?? []).filter(p => p.stage === 'picking'))
const pickupPhotos  = computed(() => (props.gi.photos ?? []).filter(p => p.stage === 'pickup'))

const qrCanvas = ref(null)
async function renderQR() {
    await nextTick()
    if (!qrCanvas.value) return
    try {
        await QRCode.toCanvas(qrCanvas.value, props.gi.gi_number, {
            width: 200, margin: 2, color: { dark: '#000000', light: '#ffffff' },
        })
    } catch (_) {}
}
watch(showBarcode, (val) => { if (val) renderQR() }, { immediate: true })
watch(qrCanvas, (el) => { if (el && showBarcode.value) renderQR() })

function downloadQR() {
    if (!qrCanvas.value) return
    const link = document.createElement('a')
    link.download = `${props.gi.gi_number}.png`
    link.href = qrCanvas.value.toDataURL('image/png')
    link.click()
}

const submitting = ref(false)
function submitGI() {
    if (!confirm(t('gi.confirmSubmit'))) return
    submitting.value = true
    router.post(route('gi.submit', props.gi.id), {}, { onFinish: () => { submitting.value = false } })
}

const approving = ref(false)
function approveGI() {
    if (!confirm('Approve this Goods Issue?')) return
    approving.value = true
    router.post(route('gi.approve', props.gi.id), {}, { onFinish: () => { approving.value = false } })
}

const showRejectPanel = ref(false)
const rejectReason    = ref('')
const rejecting       = ref(false)
function rejectGI() {
    if (!rejectReason.value.trim()) return
    rejecting.value = true
    router.post(route('gi.reject', props.gi.id), { reason: rejectReason.value }, {
        onFinish: () => { rejecting.value = false; showRejectPanel.value = false },
    })
}

const selectedOperator = ref('')
const assigning        = ref(false)

// Per-item warehouse overrides (wh_admin during assign)
const itemWarehouses = ref({})
watch(() => props.gi?.items, (items) => {
    if (!items) return
    const map = {}
    items.forEach(item => { map[item.id] = item.item_warehouse_id ?? null })
    itemWarehouses.value = map
}, { immediate: true })

function assignOperator() {
    if (!selectedOperator.value) return
    assigning.value = true
    const item_warehouses = (props.gi?.items ?? []).map(item => ({
        item_id:      item.id,
        warehouse_id: itemWarehouses.value[item.id] ?? null,
    }))
    router.post(route('gi.assign', props.gi.id), { operator_id: selectedOperator.value, item_warehouses }, {
        onFinish: () => { assigning.value = false },
    })
}
function selfAssign() {
    assigning.value = true
    const item_warehouses = (props.gi?.items ?? []).map(item => ({
        item_id:      item.id,
        warehouse_id: itemWarehouses.value[item.id] ?? null,
    }))
    router.post(route('gi.assign', props.gi.id), { operator_id: props.userId, item_warehouses }, {
        onFinish: () => { assigning.value = false },
    })
}

const startingPick = ref(false)
function startPicking() {
    startingPick.value = true
    router.post(route('gi.start-picking', props.gi.id), {}, {
        onFinish: () => { startingPick.value = false },
    })
}

const pickingRows = ref(
    (props.gi.items ?? []).map(item => {
        // Strip .00 decimals: 1.00 → 1, 2.50 → 2.5
        const rawQty = parseFloat(item.actual_qty ?? item.requested_qty ?? 0)
        return {
            id:         item.id,
            actual_qty: Number.isInteger(rawQty) ? rawQty : rawQty,
            status:     (item.status === 'rejected') ? 'rejected' : 'ready',
            notes:      item.notes ?? '',
        }
    })
)
const pickingPhotoFiles    = ref([])
const pickingPhotoPreviews = ref([])
const submittingPick       = ref(false)

// All items must have qty > 0 (if ready) OR notes filled (if rejected)
const canSubmitPicking = computed(() =>
    !submittingPick.value &&
    pickingRows.value.length > 0 &&
    pickingRows.value.every(row => {
        if (row.status === 'rejected') {
            return (row.notes ?? '').trim().length > 0
        }
        return parseFloat(row.actual_qty ?? 0) > 0
    })
)

function onPickPhotoChange(e) {
    const files = Array.from(e.target.files)
    if (files.length + pickingPhotoFiles.value.length > 10) { alert('Max 10 photos.'); return }
    files.forEach(file => {
        pickingPhotoFiles.value.push(file)
        const reader = new FileReader()
        reader.onload = ev => pickingPhotoPreviews.value.push(ev.target.result)
        reader.readAsDataURL(file)
    })
    e.target.value = ''
}
function removePickPhoto(i) {
    pickingPhotoFiles.value.splice(i, 1)
    pickingPhotoPreviews.value.splice(i, 1)
}
function submitPicking() {
    submittingPick.value = true
    const fd = new FormData()
    pickingRows.value.forEach((row, i) => {
        fd.append(`items[${i}][id]`,        row.id)
        fd.append(`items[${i}][actual_qty]`, row.actual_qty)
        fd.append(`items[${i}][status]`,     row.status)
        fd.append(`items[${i}][notes]`,      row.notes ?? '')
    })
    pickingPhotoFiles.value.forEach(f => fd.append('photos[]', f))
    router.post(route('gi.submit-picking', props.gi.id), fd, {
        forceFormData: true,
        onFinish: () => { submittingPick.value = false },
    })
}

const pickupGiNumber   = ref('')
const confirmingPickup = ref(false)
function confirmPickup() {
    if (!pickupGiNumber.value.trim()) return
    confirmingPickup.value = true
    router.post(route('gi.pickup', props.gi.id), { gi_number: pickupGiNumber.value }, {
        onFinish: () => { confirmingPickup.value = false },
    })
}

const lightboxSrc = ref(null)
function openLightbox(path) { lightboxSrc.value = storageUrl(path) }
function closeLightbox()    { lightboxSrc.value = null }

// Can delete photos if admin_dept (own GI, draft/pending) or super_admin
const canDeletePhoto = computed(() => {
    if (props.userRole === 'super_admin') return true
    if (props.userRole === 'admin_dept') {
        return props.gi.requested_by?.id === props.userId
            && ['draft', 'pending_manager_dept'].includes(props.gi.status)
    }
    return false
})

function deletePhoto(photoId) {
    if (!confirm('Hapus foto ini?')) return
    router.delete(route('gi.photos.destroy', photoId), { preserveScroll: true })
}
</script>
<template>
<AppLayout>
  <template #title>{{ gi.gi_number }}</template>
  <template #breadcrumb>
    <Link :href="route('gi.index')" class="bc-link">{{ $t('gi.title') }}</Link>
    <span class="bc-sep">›</span>
    {{ gi.gi_number }}
  </template>

  <div class="show-page">

    <!-- Flash -->
    <div v-if="flash.success" class="flash flash-ok">{{ flash.success }}</div>
    <div v-if="flash.error"   class="flash flash-err">{{ flash.error }}</div>

    <!-- Header -->
    <div class="sh-header">
      <div class="sh-header-left">
        <div class="sh-gi-number">{{ gi.gi_number }}</div>
        <span class="sh-status-badge" :style="statusStyle(gi.status)">{{ statusLabel(gi.status) }}</span>
        <span v-if="gi.department" class="sh-dept-chip">{{ gi.department.code }}</span>
      </div>
      <div class="sh-header-meta">
        <span class="sh-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          {{ fmtDate(gi.created_at) }}
        </span>
        <span v-if="gi.submitted_at" class="sh-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
          {{ $t('gi.submittedAt') }}: {{ fmtDate(gi.submitted_at) }}
        </span>
        <span v-if="gi.completed_at" class="sh-meta-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="13" height="13"><path d="M20 6L9 17l-5-5"/></svg>
          {{ $t('gi.completedAt') }}: {{ fmtDate(gi.completed_at) }}
        </span>
      </div>
    </div>

    <!-- Progress stepper -->
    <div class="sh-stepper">
      <template v-for="(step, i) in giSteps" :key="step.key">
        <!-- connector line (before every step except the first) -->
        <div v-if="i > 0" class="sh-connector"
          :class="{ 'sh-con-done': step.done || step.active, 'sh-con-err': step.rejected }">
        </div>

        <div class="sh-step" :class="{
          'sh-step-done':     step.done,
          'sh-step-active':   step.active,
          'sh-step-future':   !step.done && !step.active && !step.rejected,
          'sh-step-rejected': step.rejected,
        }">
          <div class="sh-step-dot">
            <svg v-if="step.done"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            <svg v-else-if="step.rejected"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
            <div v-else-if="step.active" class="sh-dot-pulse"></div>
            <span v-else class="sh-dot-num">{{ i + 1 }}</span>
          </div>

          <div class="sh-step-body">
            <div class="sh-step-label">{{ step.label }}</div>
            <div class="sh-step-sub">{{ step.sub }}</div>
            <div v-if="step.time && step.done" class="sh-step-time">{{ fmtDate(step.time) }}</div>
            <div v-else-if="step.active" class="sh-step-time sh-step-in-progress">
              <span class="sh-prog-blink"></span> In Progress
            </div>
            <div v-else-if="step.rejected" class="sh-step-time sh-step-rej-note">Rejected</div>
          </div>
        </div>
      </template>
    </div>

    <!-- Action: Submit (draft) -->
    <div v-if="canSubmit" class="sh-action-panel sh-action-submit">
      <div class="sh-action-content">
        <div class="sh-action-title">Submit for Approval</div>
        <div class="sh-action-sub">Submit to your department manager for review. Stock will be reserved.</div>
      </div>
      <button class="sh-btn-primary" :disabled="submitting" @click="submitGI">
        {{ submitting ? '…' : $t('gi.submit') }}
      </button>
    </div>

    <!-- Action: Approve / Reject -->
    <div v-if="canApprove && !showRejectPanel" class="sh-action-panel sh-action-approve">
      <div class="sh-action-content">
        <div class="sh-action-title">
          <span v-if="canApproveManagerDept">Dept Manager Review</span>
          <span v-else-if="canApproveSupervisor">WH Supervisor Review</span>
          <span v-else>WH Manager Final Approval</span>
        </div>
        <div class="sh-action-sub">Review the request items and approve or reject with reason.</div>
      </div>
      <div class="sh-action-btns">
        <button class="sh-btn-primary" :disabled="approving" @click="approveGI">
          {{ approving ? '…' : $t('gi.approveBtn') }}
        </button>
        <button class="sh-btn-danger" @click="showRejectPanel = !showRejectPanel">
          {{ $t('gi.rejectBtn') }}
        </button>
      </div>
    </div>

    <!-- Reject panel -->
    <div v-if="showRejectPanel && canApprove" class="sh-reject-panel">
      <label class="sh-fld-lbl">{{ $t('gi.rejectReason') }} <span class="req">*</span></label>
      <textarea class="sh-reject-ta" v-model="rejectReason"
        :placeholder="$t('gi.rejectReasonPh')" rows="3" />
      <div class="sh-reject-actions">
        <button class="sh-btn-ghost" @click="showRejectPanel = false">Cancel</button>
        <button class="sh-btn-danger" :disabled="!rejectReason.trim() || rejecting" @click="rejectGI">
          {{ rejecting ? '…' : $t('gi.rejectBtn') }}
        </button>
      </div>
    </div>

    <!-- Action: Assign Operator (wh_admin, approved) -->
    <div v-if="canAssign" class="sh-action-panel sh-action-assign">
      <div class="sh-action-content">
        <div class="sh-action-title">{{ $t('gi.assignOperatorTitle') }}</div>
        <div class="sh-action-sub">{{ $t('gi.assignOperatorSub') }}</div>

        <!-- Per-item warehouse adjustment -->
        <details v-if="props.warehouseStockMap" class="sh-wh-adjust">
          <summary class="sh-wh-adjust-summary">⚙ Sesuaikan Gudang per Item</summary>
          <table class="sh-wh-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Gudang Sumber</th>
                <th class="right">Tersedia</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in gi.items" :key="item.id">
                <td class="sh-wh-item-name">{{ itemName(item.variant) }}</td>
                <td>
                  <select class="sh-wh-select" v-model="itemWarehouses[item.id]">
                    <option :value="null">— default ({{ gi.warehouse?.code }}) —</option>
                    <option
                      v-for="w in (props.warehouseStockMap?.[item.item_variant_id] ?? [])"
                      :key="w.warehouse_id"
                      :value="w.warehouse_id"
                    >
                      {{ w.warehouse_code }} — {{ w.warehouse_name }}
                    </option>
                  </select>
                </td>
                <td class="right sh-wh-avail">
                  <template v-if="itemWarehouses[item.id]">
                    {{ (props.warehouseStockMap?.[item.item_variant_id] ?? []).find(w => w.warehouse_id === itemWarehouses[item.id])?.available?.toLocaleString() ?? '—' }}
                  </template>
                  <template v-else>
                    {{ (props.warehouseStockMap?.[item.item_variant_id] ?? []).reduce((s, w) => s + w.available, 0).toLocaleString() }}
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </details>

        <div class="sh-assign-row">
          <select class="sh-select" v-model="selectedOperator">
            <option value="">{{ $t('gi.selectOperator') }}</option>
            <option v-for="op in (operators ?? [])" :key="op.id" :value="op.id">{{ op.name }}</option>
          </select>
          <button class="sh-btn-primary" :disabled="!selectedOperator || assigning" @click="assignOperator">
            {{ assigning ? '…' : $t('gi.assignBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Action: Self-Assign (operator, approved) -->
    <div v-if="canSelfAssign" class="sh-action-panel sh-action-selfassign">
      <div class="sh-action-content">
        <div class="sh-action-title">{{ $t('gi.selfAssignTitle') }}</div>
        <div class="sh-action-sub">{{ $t('gi.selfAssignSub') }}</div>
      </div>
      <button class="sh-btn-orange" :disabled="assigning" @click="selfAssign">
        {{ assigning ? '…' : $t('gi.selfAssignBtn') }}
      </button>
    </div>

    <!-- Action: Start Picking (assigned operator) -->
    <div v-if="canStartPicking" class="sh-action-panel sh-action-picking">
      <div class="sh-action-content">
        <div class="sh-action-title">{{ $t('gi.startPickingTitle') }}</div>
        <div class="sh-action-sub">{{ $t('gi.startPickingSub') }}</div>
      </div>
      <button class="sh-btn-orange" :disabled="startingPick" @click="startPicking">
        {{ startingPick ? '…' : $t('gi.startPickingBtn') }}
      </button>
    </div>

    <!-- Picking Form (in_picking operator) -->
    <div v-if="canPickItems" class="sh-picking-card">
      <div class="sh-sec-lbl">{{ $t('gi.pickingFormTitle') }}</div>
      <div class="sh-action-sub" style="margin-bottom:4px">{{ $t('gi.pickingFormSub') }}</div>

      <div class="sh-pick-table-wrap">
        <table class="sh-table sh-pick-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>{{ $t('gi.colVariant') }}</th>
              <th>📍 Location</th>
              <th class="tc">{{ $t('gi.colReqQty') }}</th>
              <th class="tc">{{ $t('gi.colActualQty') }}</th>
              <th>{{ $t('gi.colItemStatus') }}</th>
              <th>{{ $t('gi.colNotes') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in pickingRows" :key="row.id">
              <td><span class="sh-sku">{{ gi.items[i]?.variant?.sku ?? '—' }}</span></td>
              <td>
                <div class="sh-item-name">{{ itemName(gi.items[i]?.variant) }}</div>
                <div v-if="gi.items[i]?.item_warehouse_id" class="sh-wh-chip" style="margin-bottom:2px">
                  🏭 {{ gi.items[i]?.itemWarehouse?.code ?? '—' }}
                </div>
                <div v-if="gi.items[i]?.store_to" class="sh-item-sub">→ {{ gi.items[i].store_to }}</div>
              </td>
              <!-- Rack/location column so operator knows exactly where to go -->
              <td class="sh-pick-loc-cell">
                <template v-if="locationMap[gi.items[i]?.item_variant_id]?.length">
                  <div v-for="loc in locationMap[gi.items[i].item_variant_id]" :key="loc.location_code"
                       class="sh-pick-loc-row">
                    <span class="sh-wh-chip" style="margin-bottom:2px">🏭 {{ loc.warehouse_code ?? gi.items[i]?.itemWarehouse?.code ?? gi.warehouse?.code ?? '—' }}</span>
                    <span class="sh-loc-chip">📍 {{ loc.location_code ?? '—' }}</span>
                    <span class="sh-loc-avail">{{ fmtQty(loc.available) }} avail</span>
                  </div>
                </template>
                <span v-else class="sh-fld-empty sh-pick-nostock">⚠ No stock</span>
              </td>
              <td class="tc sh-qty">{{ fmtQty(gi.items[i]?.requested_qty) }}<br><span class="sh-uom">{{ gi.items[i]?.requested_uom }}</span></td>
              <td class="tc">
                <input type="number" class="sh-qty-input" v-model="row.actual_qty" min="0" step="0.01" />
              </td>
              <td>
                <div class="sh-status-select-wrap">
                  <label class="sh-radio-lbl" :class="{ active: row.status === 'ready' }">
                    <input type="radio" :name="'st_'+row.id" value="ready" v-model="row.status" />
                    {{ $t('gi.itemReady') }}
                  </label>
                  <label class="sh-radio-lbl sh-radio-danger" :class="{ active: row.status === 'rejected' }">
                    <input type="radio" :name="'st_'+row.id" value="rejected" v-model="row.status" />
                    {{ $t('gi.itemRejected') }}
                  </label>
                </div>
              </td>
              <td>
                <textarea class="sh-notes-ta" v-model="row.notes" rows="1"
                  :placeholder="row.status === 'rejected' ? 'Reason for rejection…' : 'Optional…'" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Picking photos -->
      <div class="sh-pick-photos">
        <div class="sh-pick-photos-lbl">{{ $t('gi.photosPicking') }}</div>
        <div class="sh-photo-preview-row" v-if="pickingPhotoPreviews.length">
          <div v-for="(src, i) in pickingPhotoPreviews" :key="i" class="sh-photo-thumb-wrap">
            <img :src="src" class="sh-photo-thumb" />
            <button class="sh-photo-remove" @click="removePickPhoto(i)">×</button>
          </div>
        </div>
        <label class="sh-upload-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Upload Photos
          <input type="file" multiple accept="image/*" class="hidden-input" @change="onPickPhotoChange" />
        </label>
      </div>

      <div class="sh-pick-submit-row">
        <div v-if="!canSubmitPicking && !submittingPick" class="sh-pick-submit-hint">
          ⚠ Semua item harus diisi qty (Ready) atau diberi catatan (Rejected)
        </div>
        <button class="sh-btn-primary" :disabled="!canSubmitPicking" @click="submitPicking">
          {{ submittingPick ? '…' : $t('gi.submitPickingBtn') }}
        </button>
      </div>
    </div>

    <!-- Barcode + Pickup confirmation (ready_to_pickup) -->
    <div v-if="showBarcode" class="sh-barcode-card">
      <div class="sh-barcode-top">
        <div class="sh-barcode-header-info">
          <span class="sh-barcode-icon">📦</span>
          <div>
            <div class="sh-barcode-title">{{ $t('gi.readyForPickupTitle') }}</div>
            <div class="sh-barcode-sub">{{ $t('gi.readyForPickupSub') }}</div>
          </div>
        </div>
        <div class="sh-qr-wrap">
          <canvas ref="qrCanvas" class="sh-qr-canvas"></canvas>
          <div class="sh-qr-label">{{ gi.gi_number }}</div>
          <button class="sh-qr-download" @click="downloadQR" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download QR
          </button>
        </div>
      </div>

      <!-- Warehouse team pickup confirmation -->
      <div v-if="canPickup" class="sh-pickup-form">
        <div class="sh-pickup-divider"></div>
        <div class="sh-pickup-title">{{ $t('gi.confirmPickupTitle') }}</div>
        <div class="sh-pickup-sub">{{ $t('gi.confirmPickupSub') }}</div>
        <div class="sh-pickup-row">
          <input class="sh-pickup-input" v-model="pickupGiNumber"
            :placeholder="$t('gi.scanGiPh')" @keyup.enter="confirmPickup" />
          <button class="sh-btn-success"
            :disabled="!pickupGiNumber.trim() || confirmingPickup" @click="confirmPickup">
            {{ confirmingPickup ? '…' : $t('gi.confirmPickupBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Body grid -->
    <div class="sh-body">

      <!-- Left: Info + Approval history + Request photos -->
      <div class="sh-left-col">

        <div class="sh-card">
          <div class="sh-sec-lbl">{{ $t('gi.sectionInfo') }}</div>

          <div class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.department') }}</div>
            <div class="sh-fld-val">
              <span class="sh-dept-badge">{{ gi.department?.code ?? '—' }}</span>
              {{ gi.department?.name ?? '' }}
            </div>
          </div>

          <div class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.warehouse') }}</div>
            <div class="sh-fld-val">
              <span v-if="gi.warehouse" class="sh-wh-badge">{{ gi.warehouse.code }}</span>
              {{ gi.warehouse?.name ?? '—' }}
            </div>
          </div>

          <div v-if="gi.project" class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.project') }}</div>
            <div class="sh-fld-val">{{ gi.project }}</div>
          </div>

          <div class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.purpose') }}</div>
            <div class="sh-fld-val sh-fld-multiline">{{ gi.purpose }}</div>
          </div>

          <div class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.usageLocationLbl') }}</div>
            <div class="sh-fld-val">{{ gi.usage_location }}</div>
          </div>

          <div v-if="gi.notes" class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.notesLbl') }}</div>
            <div class="sh-fld-val sh-fld-multiline">{{ gi.notes }}</div>
          </div>

          <div class="sh-divider"></div>

          <div class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.requestedBy') }}</div>
            <div class="sh-fld-val">
              <span class="sh-avatar">{{ (gi.requested_by?.name ?? '?')[0].toUpperCase() }}</span>
              {{ gi.requested_by?.name ?? '—' }}
            </div>
          </div>

          <div v-if="gi.assigned_to" class="sh-field">
            <div class="sh-fld-lbl">{{ $t('gi.assignedTo') }}</div>
            <div class="sh-fld-val">
              <span class="sh-avatar sh-avatar-op">{{ (gi.assigned_to?.name ?? '?')[0].toUpperCase() }}</span>
              {{ gi.assigned_to?.name ?? '—' }}
            </div>
          </div>

          <div v-if="gi.picked_by" class="sh-field">
            <div class="sh-fld-lbl">Picked By</div>
            <div class="sh-fld-val">
              <span class="sh-avatar sh-avatar-op">{{ (gi.picked_by?.name ?? '?')[0].toUpperCase() }}</span>
              {{ gi.picked_by?.name ?? '—' }}
            </div>
          </div>
        </div>

        <!-- Approval history -->
        <div v-if="gi.approvals && gi.approvals.length" class="sh-card">
          <div class="sh-sec-lbl">{{ $t('gi.approvalHistory') }}</div>
          <div class="sh-timeline">
            <div v-for="appr in gi.approvals" :key="appr.id" class="sh-timeline-item">
              <div class="sh-timeline-dot" :class="appr.action === 'approved' ? 'dot-ok' : 'dot-err'">
                <svg v-if="appr.action === 'approved'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><path d="M20 6L9 17l-5-5"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </div>
              <div class="sh-timeline-content">
                <div class="sh-timeline-header">
                  <span class="sh-step-badge">{{ STEP_LABELS[appr.step]?.() ?? appr.step }}</span>
                  <span class="sh-act-badge" :class="appr.action === 'approved' ? 'act-ok' : 'act-err'">
                    {{ appr.action === 'approved' ? $t('gi.actApproved') : $t('gi.actRejected') }}
                  </span>
                </div>
                <div class="sh-timeline-by">{{ appr.acted_by?.name ?? '—' }}</div>
                <div class="sh-timeline-at">{{ fmtDate(appr.acted_at) }}</div>
                <div v-if="appr.reason" class="sh-timeline-reason">"{{ appr.reason }}"</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Request photos -->
        <div v-if="requestPhotos.length" class="sh-card">
          <div class="sh-sec-lbl">{{ $t('gi.photosRequest') }}</div>
          <div class="sh-photo-grid">
            <div v-for="photo in requestPhotos" :key="photo.id"
              class="sh-photo-cell" @click="openLightbox(photo.path)">
              <img :src="storageUrl(photo.path)" :alt="photo.original_name" class="sh-photo-img" />
              <div class="sh-photo-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
              </div>
              <button v-if="canDeletePhoto" type="button"
                class="sh-photo-del-btn" @click.stop="deletePhoto(photo.id)"
                title="Delete photo">×</button>
            </div>
          </div>
          <div v-if="requestPhotos[0]?.uploader" class="sh-photo-meta">
            Uploaded by {{ requestPhotos[0].uploader?.name }}
          </div>
        </div>

      </div>

      <!-- Right: Items table + Picking photos -->
      <div class="sh-right-col">

        <div class="sh-card">
          <div class="sh-sec-lbl">{{ $t('gi.sectionItems') }}</div>
          <div class="sh-table-scroll">
            <table class="sh-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>{{ $t('gi.colVariant') }}</th>
                  <th class="tc">{{ $t('gi.colReqQty') }}</th>
                  <th>{{ $t('gi.colStoreTo') }}</th>
                  <th>{{ $t('gi.colTracking') }}</th>
                  <th v-if="showLocations">Location</th>
                  <th>{{ $t('gi.colItemReason') }}</th>
                  <th v-if="['in_picking','ready_to_pickup','completed'].includes(gi.status)" class="tc">
                    {{ $t('gi.colActualQty') }}
                  </th>
                  <th>{{ $t('gi.colNotes') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in gi.items" :key="item.id">
                  <td><span class="sh-sku">{{ item.variant?.sku ?? '—' }}</span></td>
                  <td>
                    <div class="sh-item-name">{{ itemName(item.variant) }}</div>
                    <div v-if="item.variant?.brand || item.variant?.model" class="sh-item-sub">
                      {{ [item.variant?.brand, item.variant?.model].filter(Boolean).join(' · ') }}
                    </div>
                    <div v-if="item.item_warehouse_id" class="sh-wh-chip">
                      🏭 {{ item.itemWarehouse?.code ?? '—' }}
                    </div>
                    <div v-if="item.cooldown_until" class="sh-cooldown-chip">
                      🔒 cooldown until {{ fmtDateOnly(item.cooldown_until) }}
                    </div>
                  </td>
                  <td class="tc sh-qty">
                    {{ fmtQty(item.requested_qty) }}<br><span class="sh-uom">{{ item.requested_uom }}</span>
                  </td>
                  <td class="sh-store-to">{{ item.store_to ?? '—' }}</td>
                  <td class="sh-tracking-cell">
                    <span v-if="item.lv" class="sh-lv-chip">🚗 {{ item.lv.full_number ?? item.lv.lv_number }}</span>
                    <span v-else-if="item.employee" class="sh-emp-chip">👤 {{ item.employee.employee_id }}</span>
                    <span v-else class="sh-fld-empty">—</span>
                    <div v-if="item.employee" class="sh-emp-name">{{ item.employee.name }}</div>
                  </td>
                  <td v-if="showLocations" class="sh-loc-cell">
                    <template v-if="locationMap[item.item_variant_id]?.length">
                      <div v-for="loc in locationMap[item.item_variant_id]" :key="loc.location_code"
                        class="sh-loc-row">
                        <span class="sh-wh-chip" style="margin-bottom:2px">🏭 {{ loc.warehouse_code ?? item.itemWarehouse?.code ?? gi.warehouse?.code ?? '—' }}</span>
                        <span class="sh-loc-chip">📍 {{ loc.location_code ?? '—' }}</span>
                        <span class="sh-loc-avail">{{ fmtQty(loc.available) }} avail</span>
                      </div>
                    </template>
                    <span v-else class="sh-fld-empty">No stock</span>
                  </td>
                  <td class="sh-reason-cell">
                    <span v-if="item.item_reason" :title="item.item_reason" class="sh-reason-text">
                      {{ item.item_reason.length > 55 ? item.item_reason.slice(0,55)+'…' : item.item_reason }}
                    </span>
                    <span v-else class="sh-fld-empty">—</span>
                  </td>
                  <td v-if="['in_picking','ready_to_pickup','completed'].includes(gi.status)" class="tc">
                    <span v-if="item.actual_qty !== null && item.actual_qty !== undefined"
                      class="sh-actual-qty"
                      :class="{ 'qty-ok': item.status === 'ready', 'qty-err': item.status === 'rejected' }">
                      {{ fmtQty(item.actual_qty) }}
                    </span>
                    <span v-else class="sh-fld-empty">—</span>
                    <div class="sh-item-status-badge"
                      :class="item.status === 'ready' ? 'ib-ok' : item.status === 'rejected' ? 'ib-err' : 'ib-pending'">
                      {{ item.status }}
                    </div>
                  </td>
                  <td class="sh-notes-cell">{{ item.notes ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Picking photos gallery -->
        <div v-if="pickingPhotos.length" class="sh-card">
          <div class="sh-sec-lbl">{{ $t('gi.photosPickingGallery') }}</div>
          <div class="sh-photo-grid">
            <div v-for="photo in pickingPhotos" :key="photo.id"
              class="sh-photo-cell" @click="openLightbox(photo.path)">
              <img :src="storageUrl(photo.path)" :alt="photo.original_name" class="sh-photo-img" />
              <div class="sh-photo-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
              </div>
              <button v-if="userRole === 'super_admin'" type="button"
                class="sh-photo-del-btn" @click.stop="deletePhoto(photo.id)"
                title="Delete photo">×</button>
            </div>
          </div>
        </div>

        <!-- Pickup / Handover photos gallery -->
        <div v-if="pickupPhotos.length" class="sh-card sh-card-handover">
          <div class="sh-sec-lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              width="14" height="14" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Foto Bukti Serah Terima
          </div>
          <div class="sh-photo-grid">
            <div v-for="photo in pickupPhotos" :key="photo.id"
              class="sh-photo-cell" @click="openLightbox(photo.path)">
              <img :src="storageUrl(photo.path)" :alt="photo.original_name" class="sh-photo-img" />
              <div class="sh-photo-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
              </div>
              <button v-if="userRole === 'super_admin'" type="button"
                class="sh-photo-del-btn" @click.stop="deletePhoto(photo.id)"
                title="Delete photo">×</button>
            </div>
          </div>
          <div v-if="pickupPhotos[0]?.uploader" class="sh-photo-meta">
            Diserahkan oleh {{ pickupPhotos[0].uploader?.name }}
            <span v-if="pickupPhotos[0]?.created_at"> · {{ new Date(pickupPhotos[0].created_at).toLocaleString() }}</span>
          </div>
        </div>

      </div>
    </div>

    <!-- Completed banner -->
    <div v-if="gi.status === 'completed'" class="sh-banner sh-banner-ok">
      <span class="sh-banner-icon">✅</span>
      <div>
        <div class="sh-banner-title">Goods Issue Completed</div>
        <div class="sh-banner-sub">Completed on {{ fmtDate(gi.completed_at) }}</div>
      </div>
    </div>

    <!-- Rejected banner -->
    <div v-if="gi.status === 'rejected'" class="sh-banner sh-banner-err">
      <span class="sh-banner-icon">❌</span>
      <div>
        <div class="sh-banner-title">Goods Issue Rejected</div>
        <div v-if="gi.rejection_reason" class="sh-banner-sub">Reason: {{ gi.rejection_reason }}</div>
      </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="lightboxSrc" class="lb-overlay" @click.self="closeLightbox">
        <img :src="lightboxSrc" class="lb-img" />
        <button class="lb-close" @click="closeLightbox">×</button>
      </div>
    </Teleport>

  </div>
</AppLayout>
</template>

<style scoped>
.show-page { display:flex; flex-direction:column; gap:16px }
.bc-link { color:var(--fg-2); text-decoration:none; transition:color 150ms }
.bc-link:hover { color:var(--fg) }
.bc-sep  { color:var(--fg-dim); margin:0 6px }
.flash { padding:10px 16px; border-radius:10px; font-size:13px; font-weight:600 }
.flash-ok  { background:rgba(16,185,129,.12); color:#34d399; border:1px solid rgba(16,185,129,.25) }
.flash-err { background:rgba(239,68,68,.12);  color:#f87171; border:1px solid rgba(239,68,68,.25) }

/* Header */
.sh-header {
  display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;
  background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px 24px;
}
.sh-header-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap }
.sh-gi-number   { font-family:monospace; font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.01em }
.sh-status-badge { display:inline-flex; padding:4px 12px; border-radius:8px; font-size:12px; font-weight:700 }
.sh-dept-chip   { display:inline-flex; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:700; background:rgba(59,130,246,.1); color:#60a5fa }
.sh-header-meta { display:flex; flex-wrap:wrap; gap:14px; align-items:center }
.sh-meta-item   { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--fg-dim) }

/* Shared card */
.sh-card {
  background:var(--surface); border:1px solid var(--border); border-radius:14px;
  padding:20px; display:flex; flex-direction:column; gap:14px;
}
.sh-sec-lbl {
  font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-dim);
  padding-bottom:6px; border-bottom:1px solid var(--border);
}
.sh-field       { display:flex; flex-direction:column; gap:4px }
.sh-fld-lbl     { font-size:11px; font-weight:600; color:var(--fg-dim); text-transform:uppercase; letter-spacing:.04em }
.sh-fld-val     { font-size:13px; color:var(--fg-2); display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.sh-fld-multiline { white-space:pre-wrap; word-break:break-word; line-height:1.6; font-size:12.5px; display:block }
.sh-fld-empty   { color:var(--fg-dim) }
.sh-divider     { height:1px; background:var(--border); margin:2px 0 }
.sh-dept-badge  { display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700; background:rgba(59,130,246,.1); color:#60a5fa }
.sh-wh-badge    { display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700; background:rgba(249,115,22,.1); color:var(--orange-500) }
.sh-avatar      { display:inline-flex; width:22px; height:22px; border-radius:50%; align-items:center; justify-content:center; font-size:11px; font-weight:700; background:rgba(59,130,246,.15); color:#60a5fa; flex-shrink:0 }
.sh-avatar-op   { background:rgba(249,115,22,.15); color:#f97316 }

/* Body layout */
.sh-body { display:grid; grid-template-columns:280px 1fr; gap:16px; align-items:start }
@media (max-width:900px) { .sh-body { grid-template-columns:1fr } }
.sh-left-col, .sh-right-col { display:flex; flex-direction:column; gap:16px }

/* Items table */
.sh-table-scroll { overflow-x:auto }
.sh-table { width:100%; border-collapse:collapse; font-size:13px }
.sh-table th {
  padding:8px 12px; text-align:left; font-size:10.5px; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em; color:var(--fg-dim);
  border-bottom:1px solid var(--border); white-space:nowrap;
}
.sh-table td { padding:9px 12px; border-bottom:1px solid var(--border); color:var(--fg-2); vertical-align:top }
.sh-table tr:last-child td { border-bottom:0 }
.sh-table tr:hover td { background:var(--hover) }
.tc { text-align:center }
.sh-sku       { font-family:monospace; font-size:12px; font-weight:700; color:var(--fg) }
.sh-item-name { font-size:13px; color:var(--fg); font-weight:500 }
.sh-item-sub  { font-size:11px; color:var(--fg-dim); margin-top:2px }
.sh-qty       { font-weight:700; color:var(--fg) }
.sh-uom       { font-size:11px; color:var(--fg-dim) }
.sh-notes-cell   { font-size:12px; color:var(--fg-dim); max-width:150px }
.sh-store-to     { font-size:12px; color:var(--fg-2); max-width:130px }
.sh-reason-cell  { font-size:12px; color:var(--fg-2); max-width:170px }
.sh-reason-text  { cursor:help }
.sh-tracking-cell { white-space:nowrap }
.sh-lv-chip  { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:600; background:rgba(249,115,22,.1); color:#f97316 }
.sh-emp-chip { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:600; background:rgba(139,92,246,.1); color:#a78bfa }
.sh-wh-chip  { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:600; background:rgba(16,185,129,.1); color:#34d399; margin-top:3px }
.sh-emp-name { font-size:11px; color:var(--fg-dim); margin-top:2px }
.sh-cooldown-chip { display:inline-flex; align-items:center; gap:3px; margin-top:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:600; background:rgba(239,68,68,.1); color:#f87171 }
.sh-actual-qty { font-size:14px; font-weight:700 }
.qty-ok  { color:#34d399 }
.qty-err { color:#f87171 }
.sh-item-status-badge { display:inline-flex; margin-top:3px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.04em }
.ib-ok      { background:rgba(16,185,129,.1); color:#34d399 }
.ib-err     { background:rgba(239,68,68,.1); color:#f87171 }
.ib-pending { background:rgba(100,116,139,.1); color:#94a3b8 }

/* Action panels */
.sh-action-panel {
  display:flex; align-items:center; gap:16px; flex-wrap:wrap;
  padding:18px 20px; border-radius:14px; border:1px solid var(--border);
}
.sh-action-submit    { background:rgba(249,115,22,.06); border-color:rgba(249,115,22,.3) }
.sh-action-approve   { background:rgba(59,130,246,.06); border-color:rgba(59,130,246,.3) }
.sh-action-assign    { background:rgba(16,185,129,.06); border-color:rgba(16,185,129,.3) }
.sh-action-selfassign{ background:rgba(249,115,22,.05); border-color:rgba(249,115,22,.25) }
.sh-action-picking   { background:rgba(249,115,22,.08); border-color:rgba(249,115,22,.35) }
.sh-action-icon    { font-size:24px; flex-shrink:0 }
.sh-action-content { flex:1; min-width:200px }
.sh-action-title   { font-size:14px; font-weight:700; color:var(--fg); margin-bottom:3px }
.sh-action-sub     { font-size:12px; color:var(--fg-dim) }
.sh-action-btns    { display:flex; gap:10px; flex-shrink:0; flex-wrap:wrap }
.sh-assign-row { display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap }
.sh-select {
  flex:1; min-width:180px; padding:8px 12px; border-radius:9px;
  border:1px solid var(--border-2); background:var(--surface-2); color:var(--fg);
  font-size:13px; outline:none; cursor:pointer;
}
.sh-select:focus { border-color:rgba(249,115,22,.5) }
.sh-self-assign-btn {
  appearance:none; border:1px solid rgba(249,115,22,.5); background:transparent;
  color:#f97316; font-size:12px; font-weight:700; padding:8px 14px; border-radius:9px;
  cursor:pointer; font-family:inherit; white-space:nowrap; transition:background 180ms,color 180ms;
}
.sh-self-assign-btn:hover { background:#f97316; color:#fff; border-color:#f97316 }
.sh-self-assign-btn:disabled { opacity:.5; cursor:not-allowed }
.sh-wh-adjust { margin-top:12px; border:1px solid var(--border); border-radius:10px; overflow:hidden }
.sh-wh-adjust-summary { padding:8px 14px; cursor:pointer; font-size:12px; font-weight:700; color:var(--fg-2); user-select:none; background:var(--surface-2); }
.sh-wh-adjust-summary:hover { color:var(--fg) }
.sh-wh-table { width:100%; border-collapse:collapse; font-size:12.5px }
.sh-wh-table th { text-align:left; padding:7px 12px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-2); border-bottom:1px solid var(--border) }
.sh-wh-table td { padding:8px 12px; border-bottom:1px solid var(--border-soft); vertical-align:middle }
.sh-wh-table tr:last-child td { border-bottom:0 }
.sh-wh-item-name { font-weight:600; color:var(--fg); max-width:200px }
.sh-wh-select { width:100%; padding:5px 8px; border-radius:7px; border:1px solid var(--border); background:var(--surface-2); color:var(--fg); font-size:12.5px; outline:none; cursor:pointer }
.sh-wh-select:focus { border-color:rgba(249,115,22,.5) }
.sh-wh-avail { color:#10b981; font-weight:700; font-variant-numeric:tabular-nums; text-align:right }
.right { text-align:right }

/* Buttons */
.sh-btn-primary {
  display:inline-flex; align-items:center; padding:9px 20px; border-radius:9px; border:0; cursor:pointer;
  font-size:13px; font-weight:600; background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  color:#fff; box-shadow:0 4px 10px -3px rgba(249,115,22,.45); transition:opacity 150ms;
}
.sh-btn-primary:hover:not(:disabled) { opacity:.88 }
.sh-btn-primary:disabled { opacity:.5; cursor:not-allowed }
.sh-btn-orange {
  display:inline-flex; align-items:center; padding:9px 20px; border-radius:9px; border:0; cursor:pointer;
  font-size:13px; font-weight:600; background:rgba(249,115,22,.15); color:#f97316; transition:background 150ms;
}
.sh-btn-orange:hover:not(:disabled) { background:rgba(249,115,22,.25) }
.sh-btn-orange:disabled { opacity:.5; cursor:not-allowed }
.sh-btn-success {
  display:inline-flex; align-items:center; padding:9px 20px; border-radius:9px; border:0; cursor:pointer;
  font-size:13px; font-weight:600; background:linear-gradient(180deg,#22c55e,#16a34a);
  color:#fff; box-shadow:0 4px 10px -3px rgba(22,163,74,.4); transition:opacity 150ms;
}
.sh-btn-success:hover:not(:disabled) { opacity:.88 }
.sh-btn-success:disabled { opacity:.5; cursor:not-allowed }
.sh-btn-danger {
  display:inline-flex; align-items:center; padding:9px 20px; border-radius:9px; border:0; cursor:pointer;
  font-size:13px; font-weight:600; background:rgba(239,68,68,.15); color:#f87171; transition:background 150ms;
}
.sh-btn-danger:hover:not(:disabled) { background:rgba(239,68,68,.25) }
.sh-btn-danger:disabled { opacity:.5; cursor:not-allowed }
.sh-btn-ghost {
  display:inline-flex; align-items:center; padding:9px 20px; border-radius:9px;
  border:1px solid var(--border-2); cursor:pointer; font-size:13px; font-weight:600;
  background:var(--surface); color:var(--fg-2); transition:background 150ms;
}
.sh-btn-ghost:hover { background:var(--hover) }

/* Reject panel */
.sh-reject-panel {
  background:var(--surface); border:1px solid rgba(239,68,68,.3); border-radius:14px;
  padding:20px; display:flex; flex-direction:column; gap:10px;
}
.sh-reject-ta {
  width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border-2);
  background:var(--surface-2); color:var(--fg); font-family:inherit; font-size:13px;
  resize:vertical; outline:none; transition:border-color 180ms; box-sizing:border-box;
}
.sh-reject-ta:focus { border-color:rgba(239,68,68,.6) }
.sh-reject-actions { display:flex; gap:10px; justify-content:flex-end }
.req { color:#f87171 }

/* Approval timeline */
.sh-timeline { display:flex; flex-direction:column; gap:0 }
.sh-timeline-item { display:flex; gap:12px; position:relative; padding-bottom:18px }
.sh-timeline-item:last-child { padding-bottom:0 }
.sh-timeline-item:not(:last-child)::before {
  content:''; position:absolute; left:10px; top:22px; bottom:0;
  width:1px; background:var(--border);
}
.sh-timeline-dot {
  width:22px; height:22px; border-radius:50%; flex-shrink:0; margin-top:2px;
  display:flex; align-items:center; justify-content:center; position:relative; z-index:1;
}
.dot-ok  { background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.3) }
.dot-err { background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.3) }
.sh-timeline-content { flex:1 }
.sh-timeline-header  { display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.sh-step-badge { display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700; background:rgba(100,116,139,.1); color:var(--fg-dim) }
.sh-act-badge  { display:inline-flex; padding:2px 8px; border-radius:5px; font-size:11px; font-weight:700 }
.act-ok  { background:rgba(16,185,129,.12); color:#34d399 }
.act-err { background:rgba(239,68,68,.12); color:#f87171 }
.sh-timeline-by { font-size:12.5px; color:var(--fg-2); margin-top:3px }
.sh-timeline-at { font-size:11px; color:var(--fg-dim) }
.sh-timeline-reason { font-size:12px; color:var(--fg-dim); font-style:italic; margin-top:4px; padding:6px 10px; background:var(--surface-2); border-radius:6px; border-left:2px solid rgba(239,68,68,.3) }

/* Photos */
.sh-photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); gap:8px }
.sh-photo-cell { position:relative; aspect-ratio:1; border-radius:8px; overflow:hidden; cursor:pointer; background:var(--surface-2); border:1px solid var(--border) }
.sh-photo-img  { width:100%; height:100%; object-fit:cover; transition:transform 200ms }
.sh-photo-cell:hover .sh-photo-img { transform:scale(1.05) }
.sh-photo-overlay { position:absolute; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 200ms; color:#fff }
.sh-photo-cell:hover .sh-photo-overlay { opacity:1 }
.sh-photo-meta { font-size:11px; color:var(--fg-dim) }
.sh-photo-del-btn {
  position:absolute; top:4px; right:4px; width:20px; height:20px;
  border-radius:50%; background:rgba(239,68,68,.85); color:#fff;
  border:0; cursor:pointer; font-size:15px; font-weight:700;
  display:flex; align-items:center; justify-content:center;
  opacity:0; transition:opacity 150ms; z-index:2; line-height:1;
  padding:0;
}
.sh-photo-cell:hover .sh-photo-del-btn { opacity:1 }

/* Handover card */
.sh-card-handover {
  border-color: rgba(34,197,94,.35);
  background: rgba(34,197,94,.03);
}
.sh-card-handover .sh-sec-lbl { color: #16a34a; }

/* Picking card */
.sh-picking-card {
  background:var(--surface); border:1px solid rgba(249,115,22,.3); border-radius:14px;
  padding:20px; display:flex; flex-direction:column; gap:14px;
}
.sh-pick-table-wrap { overflow-x:auto }
.sh-pick-table th, .sh-pick-table td { vertical-align:middle }
.sh-qty-input {
  width:80px; padding:6px 8px; border-radius:7px; border:1px solid var(--border-2);
  background:var(--surface-2); color:var(--fg); font-size:13px; text-align:center; outline:none;
}
.sh-qty-input:focus { border-color:rgba(249,115,22,.5) }
.sh-status-select-wrap { display:flex; flex-direction:column; gap:4px }
.sh-radio-lbl { display:flex; align-items:center; gap:6px; font-size:12px; cursor:pointer; padding:4px 8px; border-radius:6px; transition:background 150ms }
.sh-radio-lbl:hover, .sh-radio-lbl.active { background:rgba(16,185,129,.08) }
.sh-radio-lbl.sh-radio-danger:hover, .sh-radio-lbl.sh-radio-danger.active { background:rgba(239,68,68,.08) }
.sh-radio-lbl input { accent-color:#10b981 }
.sh-radio-lbl.sh-radio-danger input { accent-color:#ef4444 }
.sh-notes-ta { width:100%; padding:5px 8px; border-radius:7px; border:1px solid var(--border-2); background:var(--surface-2); color:var(--fg); font-size:12px; resize:none; outline:none; box-sizing:border-box; min-width:130px }
.sh-notes-ta:focus { border-color:rgba(249,115,22,.5) }
.sh-pick-photos     { display:flex; flex-direction:column; gap:8px }
.sh-pick-photos-lbl { font-size:12px; font-weight:600; color:var(--fg-dim) }
.sh-photo-preview-row { display:flex; flex-wrap:wrap; gap:8px }
.sh-photo-thumb-wrap  { position:relative }
.sh-photo-thumb { width:64px; height:64px; border-radius:8px; object-fit:cover; border:1px solid var(--border) }
.sh-photo-remove { position:absolute; top:-4px; right:-4px; width:16px; height:16px; border-radius:50%; background:#ef4444; color:#fff; border:0; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; line-height:1 }
.sh-upload-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:1px dashed var(--border-2); cursor:pointer; font-size:12px; font-weight:600; color:var(--fg-dim); background:var(--surface-2); transition:all 150ms; width:fit-content }
.sh-upload-btn:hover { border-color:rgba(249,115,22,.5); color:#f97316 }
.hidden-input { display:none }
.sh-pick-submit-row { display:flex; flex-direction:column; align-items:flex-end; gap:8px }
.sh-pick-submit-hint { font-size:12px; color:#f87171; background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:8px; padding:6px 12px; width:100%; text-align:center }

/* Barcode card */
.sh-barcode-card {
  background:var(--surface); border:1px solid rgba(16,185,129,.3); border-radius:14px;
  padding:24px; display:flex; flex-direction:column; gap:0;
}
.sh-barcode-top          { display:flex; align-items:flex-start; gap:24px; flex-wrap:wrap }
.sh-barcode-header-info  { display:flex; align-items:flex-start; gap:12px; flex:1; min-width:200px }
.sh-barcode-icon  { font-size:28px }
.sh-barcode-title { font-size:16px; font-weight:700; color:var(--fg) }
.sh-barcode-sub   { font-size:12px; color:var(--fg-dim); margin-top:3px }
.sh-qr-wrap   { display:flex; flex-direction:column; align-items:center; gap:8px }
.sh-qr-canvas { border:4px solid #fff; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.15) }
.sh-qr-label  { font-family:monospace; font-size:14px; font-weight:700; color:var(--fg); letter-spacing:.05em }
.sh-pickup-divider { height:1px; background:var(--border); margin:20px 0 }
.sh-pickup-title { font-size:14px; font-weight:700; color:var(--fg); margin-bottom:4px }
.sh-pickup-sub   { font-size:12px; color:var(--fg-dim); margin-bottom:12px }
.sh-pickup-row   { display:flex; gap:10px; flex-wrap:wrap }
.sh-pickup-input {
  flex:1; min-width:200px; padding:9px 14px; border-radius:9px;
  border:1px solid var(--border-2); background:var(--surface-2); color:var(--fg);
  font-size:14px; font-family:monospace; outline:none; transition:border-color 180ms;
}
.sh-pickup-input:focus { border-color:rgba(16,185,129,.5) }

/* Banners */
.sh-banner { display:flex; align-items:flex-start; gap:14px; padding:18px 20px; border-radius:14px; border:1px solid; flex-wrap:wrap }
.sh-banner-ok  { background:rgba(16,185,129,.08); border-color:rgba(16,185,129,.25) }
.sh-banner-err { background:rgba(239,68,68,.08);  border-color:rgba(239,68,68,.25) }
.sh-banner-icon  { font-size:22px; flex-shrink:0 }
.sh-banner-title { font-size:14px; font-weight:700; color:var(--fg); margin-bottom:3px }
.sh-banner-sub   { font-size:12px; color:var(--fg-dim) }

/* Lightbox */
.lb-overlay { position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px }
.lb-img   { max-width:100%; max-height:90vh; border-radius:12px; object-fit:contain }
.lb-close { position:absolute; top:16px; right:20px; font-size:28px; color:#fff; background:none; border:0; cursor:pointer; line-height:1; opacity:.8; transition:opacity 150ms }
.lb-close:hover { opacity:1 }

/* ── Progress stepper (GR-style) ─────────────────────────────────────── */
.sh-stepper {
  display:flex; align-items:flex-start;
  background:var(--surface); border:1px solid var(--border);
  border-radius:14px; padding:22px 24px; gap:0; overflow-x:auto;
}
.sh-connector {
  flex:1; min-width:20px; height:2px; margin-top:18px;
  background:var(--border-2); border-radius:2px; flex-shrink:1; transition:background 400ms ease;
}
.sh-connector.sh-con-done { background:var(--orange-500) }
.sh-connector.sh-con-err  { background:#ef4444 }
.sh-step {
  display:flex; flex-direction:column; align-items:center;
  gap:8px; min-width:80px; max-width:110px; text-align:center; flex-shrink:0;
}
.sh-step-dot {
  width:36px; height:36px; border-radius:50%;
  display:grid; place-items:center; flex-shrink:0;
  border:2px solid var(--border-2); background:var(--surface-2);
  position:relative; transition:background 300ms ease, border-color 300ms ease;
}
.sh-step-done .sh-step-dot     { background:var(--orange-500); border-color:var(--orange-500); color:#fff }
.sh-step-active .sh-step-dot   { border-color:var(--orange-500); background:rgba(249,115,22,.12) }
.sh-step-rejected .sh-step-dot { background:rgba(239,68,68,.12); border-color:#ef4444; color:#f87171 }
.sh-dot-num { font-size:13px; font-weight:700; color:var(--fg-dim) }
.sh-dot-pulse {
  width:10px; height:10px; border-radius:50%; background:var(--orange-500);
  animation:gi-pulse 1.4s ease-in-out infinite;
}
@keyframes gi-pulse {
  0%,100% { transform:scale(1);   opacity:1 }
  50%      { transform:scale(1.6); opacity:.55 }
}
.sh-step-body { display:flex; flex-direction:column; align-items:center; gap:2px }
.sh-step-label {
  font-size:12px; font-weight:700; color:var(--fg); white-space:nowrap;
  transition:color 300ms ease;
}
.sh-step-future .sh-step-label   { color:var(--fg-dim) }
.sh-step-active .sh-step-label   { color:var(--orange-500) }
.sh-step-rejected .sh-step-label { color:#f87171 }
.sh-step-sub {
  font-size:11px; color:var(--fg-dim);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100px;
}
.sh-step-done .sh-step-sub { color:var(--fg-2) }
.sh-step-time { font-size:10.5px; color:var(--fg-dim); margin-top:2px; white-space:nowrap }
.sh-step-in-progress {
  color:var(--orange-500); display:flex; align-items:center; gap:4px; justify-content:center;
}
.sh-step-rej-note { color:#f87171 }
.sh-prog-blink {
  width:6px; height:6px; border-radius:50%; background:var(--orange-500);
  animation:gi-pulse 1s ease-in-out infinite;
}

/* Location cells (items table) */
.sh-loc-cell { white-space:nowrap; vertical-align:top }
.sh-loc-row  { display:flex; align-items:center; gap:6px; margin-bottom:3px }
.sh-loc-chip { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:5px; font-size:11px; font-weight:600; background:rgba(16,185,129,.1); color:#34d399 }
.sh-loc-avail { font-size:10px; color:var(--fg-dim) }

/* Picking form location column */
.sh-pick-loc-cell { vertical-align:top; min-width:110px }
.sh-pick-loc-row  { display:flex; align-items:center; gap:5px; margin-bottom:3px; flex-wrap:wrap }
.sh-pick-nostock  { font-size:11px; color:#f87171; font-weight:600 }

/* QR download button */
.sh-qr-download {
  display:inline-flex; align-items:center; gap:5px; margin-top:4px;
  padding:5px 12px; border-radius:7px; border:1px solid var(--border-2);
  background:var(--surface-2); color:var(--fg-2); font-size:12px; font-weight:600;
  cursor:pointer; font-family:inherit; transition:all 150ms;
}
.sh-qr-download:hover { border-color:rgba(249,115,22,.5); color:#f97316; background:rgba(249,115,22,.06) }
</style>