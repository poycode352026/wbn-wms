<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, useForm, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    gr:                     Object,
    warehouses:             Array,
    operators:              Array,
    userRole:               String,
    userId:                 Number,
    hoursUntilAutoApprove:  Number,
})

const flash = computed(() => page.props.flash ?? {})

// ── i18n helpers ───────────────────────────────────────────────────────────
function itemName(item) {
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function variantLabel(v) {
    if (!v) return ''
    return [v.brand, v.model, v.size, v.color].filter(Boolean).join(' · ')
}

// ── status styling ─────────────────────────────────────────────────────────
const STATUS_MAP = {
    draft:              { label: t('gr.status_draft'),              bg: 'rgba(100,116,139,.15)', color: '#94a3b8' },
    arrived:            { label: t('gr.status_arrived'),            bg: 'rgba(59,130,246,.15)',  color: '#60a5fa' },
    assigned:           { label: t('gr.status_assigned'),           bg: 'rgba(249,115,22,.15)',  color: '#fb923c' },
    pending_supervisor: { label: t('gr.status_pending_supervisor'), bg: 'rgba(234,179,8,.15)',   color: '#fbbf24' },
    completed:          { label: t('gr.status_completed'),          bg: 'rgba(16,185,129,.15)',  color: '#34d399' },
}
const statusStyle = (s) => {
    const m = STATUS_MAP[s] ?? {}
    return { background: m.bg, color: m.color }
}

// ── role-based UI flags ────────────────────────────────────────────────────
const canSubmit = computed(() =>
    props.gr.status === 'draft' &&
    (['procurement_admin', 'wh_admin'].includes(props.userRole) && props.gr.created_by?.id === props.userId ||
     props.userRole === 'super_admin')
)
// WH Admin places items in racks + assigns operator
const canAssign = computed(() =>
    props.gr.status === 'arrived' && ['wh_admin', 'super_admin'].includes(props.userRole)
)
// Operator (or wh_admin/super_admin) verifies actual qty + condition
const canInspect = computed(() =>
    props.gr.status === 'assigned' && (
        ['wh_admin', 'super_admin'].includes(props.userRole) ||
        (props.userRole === 'operator' && props.gr.assigned_to?.id === props.userId)
    )
)
const canApprove = computed(() =>
    props.gr.status === 'pending_supervisor' && ['wh_supervisor', 'super_admin'].includes(props.userRole)
)
const canEdit = computed(() => canSubmit.value)

// ── edit draft details ─────────────────────────────────────────────────────
const editMode = ref(false)
const editForm = useForm({
    pr_number: props.gr.pr_number ?? '',
    po_number: props.gr.po_number ?? '',
    notes:     props.gr.notes     ?? '',
})

function saveEdit() {
    editForm.patch(route('gr.update', props.gr.id), {
        preserveScroll: true,
        onSuccess: () => { editMode.value = false },
    })
}

// ── delete draft ───────────────────────────────────────────────────────────
const deleteProcessing = ref(false)
function deleteGr() {
    if (!confirm(t('gr.confirmDelete'))) return
    deleteProcessing.value = true
    router.delete(route('gr.destroy', props.gr.id))
}

// ── photo upload: arrived stage ────────────────────────────────────────────
const arrivalPhotoInput    = ref(null)
const arrivalPhotoPreviews = ref([])

function onArrivalPhotos(e) {
    const files = Array.from(e.target.files || [])
    arrivalPhotoPreviews.value = files.map(f => ({ url: URL.createObjectURL(f), name: f.name, file: f }))
}
function removeArrivalPhoto(idx) {
    arrivalPhotoPreviews.value.splice(idx, 1)
    if (!arrivalPhotoPreviews.value.length && arrivalPhotoInput.value) arrivalPhotoInput.value.value = ''
}

// ── photo upload: assign/placement stage ──────────────────────────────────
const assignPhotoInput    = ref(null)
const assignPhotoPreviews = ref([])

function onAssignPhotos(e) {
    const files = Array.from(e.target.files || [])
    assignPhotoPreviews.value = files.map(f => ({ url: URL.createObjectURL(f), name: f.name, file: f }))
}
function removeAssignPhoto(idx) {
    assignPhotoPreviews.value.splice(idx, 1)
    if (!assignPhotoPreviews.value.length && assignPhotoInput.value) assignPhotoInput.value.value = ''
}

// ── photo upload: inspection stage ─────────────────────────────────────────
const inspectPhotoInput    = ref(null)
const inspectPhotoPreviews = ref([])

function onInspectPhotos(e) {
    const files = Array.from(e.target.files || [])
    inspectPhotoPreviews.value = files.map(f => ({ url: URL.createObjectURL(f), name: f.name, file: f }))
}
function removeInspectPhoto(idx) {
    inspectPhotoPreviews.value.splice(idx, 1)
    if (!inspectPhotoPreviews.value.length && inspectPhotoInput.value) inspectPhotoInput.value.value = ''
}

// ── submit (mark arrived) ──────────────────────────────────────────────────
const submitProcessing = ref(false)
function submitArrived() {
    if (!confirm(t('gr.confirmArrived'))) return
    submitProcessing.value = true
    const fd = new FormData()
    arrivalPhotoPreviews.value.forEach(p => fd.append('photos[]', p.file))
    router.post(route('gr.submit', props.gr.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { submitProcessing.value = false },
    })
}

// ── assign form (WH Admin: rack placement + operator assignment) ───────────
const assignItems = ref(
    props.gr.items.map(item => ({
        id:           item.id,
        warehouse_id: item.location?.warehouse_id ?? '',
        location_id:  item.location_id ?? '',
    }))
)
const selectedOperator = ref(null)
const assignProcessing = ref(false)

function locationsFor(assignItem) {
    if (!assignItem.warehouse_id || !props.warehouses?.length) return []
    const wh = props.warehouses.find(w => w.id == assignItem.warehouse_id)
    return wh ? wh.locations : []
}
function onWhChange(assignItem) {
    assignItem.location_id = ''
}

function selfAssignOp() {
    selectedOperator.value = props.userId
}

const assignAllValid = computed(() =>
    assignItems.value.every(i => i.location_id) && selectedOperator.value
)

function submitAssign() {
    if (!assignAllValid.value) return
    assignProcessing.value = true
    const fd = new FormData()
    fd.append('operator_id', selectedOperator.value)
    assignItems.value.forEach((item, idx) => {
        fd.append(`items[${idx}][id]`,          item.id)
        fd.append(`items[${idx}][location_id]`, item.location_id)
    })
    assignPhotoPreviews.value.forEach(p => fd.append('photos[]', p.file))
    router.post(route('gr.assign', props.gr.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { assignProcessing.value = false },
    })
}

// ── inspect form (Operator: actual qty + condition) ────────────────────────
const inspectItems = ref(
    props.gr.items.map(item => ({
        id:              item.id,
        actual_qty:      item.actual_qty ?? '',
        condition:       item.condition ?? 'good',
        condition_notes: item.condition_notes ?? '',
    }))
)

const CONDITIONS = ['good', 'damaged', 'broken', 'other']
const COND_STYLES = {
    good:    { bg: 'rgba(16,185,129,.12)', color: '#34d399' },
    damaged: { bg: 'rgba(234,179,8,.12)',  color: '#fbbf24' },
    broken:  { bg: 'rgba(239,68,68,.12)',  color: '#f87171' },
    other:   { bg: 'rgba(100,116,139,.12)', color: '#94a3b8' },
}
function condStyle(c) { return COND_STYLES[c] ?? COND_STYLES.other }

const inspectProcessing = ref(false)

function submitInspect() {
    const validItems = inspectItems.value.filter(i =>
        i.actual_qty !== '' && !isNaN(parseFloat(i.actual_qty))
    )
    if (!validItems.length) return

    inspectProcessing.value = true
    const fd = new FormData()
    inspectItems.value.forEach((item, idx) => {
        fd.append(`items[${idx}][id]`,              item.id)
        fd.append(`items[${idx}][actual_qty]`,      parseFloat(item.actual_qty) || 0)
        fd.append(`items[${idx}][condition]`,       item.condition)
        fd.append(`items[${idx}][condition_notes]`, item.condition_notes || '')
    })
    inspectPhotoPreviews.value.forEach(p => fd.append('photos[]', p.file))

    router.post(route('gr.inspect', props.gr.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { inspectProcessing.value = false },
    })
}

// ── approve ────────────────────────────────────────────────────────────────
const approveProcessing = ref(false)
function submitApprove() {
    if (!confirm(t('gr.confirmApprove'))) return
    approveProcessing.value = true
    router.post(route('gr.approve', props.gr.id), {}, {
        onFinish: () => { approveProcessing.value = false },
    })
}

// ── progress stepper ──────────────────────────────────────────────────────
const steps = computed(() => {
    const s = props.gr.status
    return [
        {
            key:    'created',
            label:  t('gr.stepCreated'),
            sub:    props.gr.created_by?.name ?? '—',
            time:   props.gr.created_at,
            done:   true,
            active: false,
        },
        {
            key:    'arrived',
            label:  t('gr.stepArrived'),
            sub:    props.gr.created_by?.name ?? '—',
            time:   props.gr.submitted_at,
            done:   ['arrived','assigned','pending_supervisor','completed'].includes(s),
            active: s === 'draft',
        },
        {
            key:    'assigned',
            label:  t('gr.stepAssigned'),
            sub:    props.gr.assigned_to?.name ?? t('gr.stepWHAdmin'),
            time:   props.gr.assigned_at,
            done:   ['assigned','pending_supervisor','completed'].includes(s),
            active: s === 'arrived',
        },
        {
            key:    'inspected',
            label:  t('gr.stepInspected'),
            sub:    props.gr.inspected_by?.name ?? t('gr.stepOperatorRole'),
            time:   props.gr.inspected_at,
            done:   ['pending_supervisor','completed'].includes(s),
            active: s === 'assigned',
        },
        {
            key:    'approval',
            label:  t('gr.stepApproval'),
            sub:    props.gr.auto_approved ? t('gr.systemAuto') : (props.gr.approved_by?.name ?? t('gr.stepSupervisor')),
            time:   props.gr.completed_at,
            done:   s === 'completed',
            active: s === 'pending_supervisor',
        },
        {
            key:    'selesai',
            label:  t('gr.stepDone'),
            sub:    s === 'completed' ? t('gr.stepStockUpdated') : t('gr.stepWaiting'),
            time:   props.gr.completed_at,
            done:   s === 'completed',
            active: false,
        },
    ]
})

// ── countdown display ──────────────────────────────────────────────────────
const countdownLabel = computed(() => {
    const h = props.hoursUntilAutoApprove
    if (h === null || h === undefined) return null
    if (h <= 0) return t('gr.autoApproveNow')
    const hrs = Math.floor(h)
    const mins = Math.round((h - hrs) * 60)
    return t('gr.autoApproveIn', { h: hrs, m: mins })
})

// ── lightbox ───────────────────────────────────────────────────────────────
const lightbox = ref(null)

function openLightbox(photos, idx) { lightbox.value = { list: photos, idx } }
function closeLightbox() { lightbox.value = null }
function lightboxPrev() {
    if (!lightbox.value) return
    lightbox.value.idx = (lightbox.value.idx - 1 + lightbox.value.list.length) % lightbox.value.list.length
}
function lightboxNext() {
    if (!lightbox.value) return
    lightbox.value.idx = (lightbox.value.idx + 1) % lightbox.value.list.length
}
const lightboxCurrent = computed(() =>
    lightbox.value ? lightbox.value.list[lightbox.value.idx] : null
)

function onKey(e) {
    if (!lightbox.value) return
    if (e.key === 'Escape')     closeLightbox()
    if (e.key === 'ArrowLeft')  lightboxPrev()
    if (e.key === 'ArrowRight') lightboxNext()
}
onMounted(()   => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

function previewLightbox(previews, idx) { lightbox.value = { list: previews, idx } }
</script>

<template>
<AppLayout>
  <template #title>{{ gr.gr_number }}</template>
  <template #breadcrumb>
    <Link :href="route('gr.index')" class="bc-link">{{ $t('gr.title') }}</Link>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="m9 18 6-6-6-6"/></svg>
    {{ gr.gr_number }}
  </template>

  <!-- flash -->
  <div v-if="flash.success" class="sh-flash sh-flash-ok">{{ flash.success }}</div>
  <div v-if="flash.error"   class="sh-flash sh-flash-err">{{ flash.error }}</div>

  <!-- ── HEADER CARD ─────────────────────────────────────────────────────── -->
  <div class="sh-header-card">
    <div class="sh-header-main">
      <div class="sh-gr-num">{{ gr.gr_number }}</div>
      <span class="sh-status-badge" :style="statusStyle(gr.status)">{{ t('gr.status_' + gr.status) }}</span>
      <span v-if="gr.auto_approved" class="sh-auto-badge">{{ $t('gr.autoApproved') }}</span>
      <div class="sh-header-actions" v-if="canEdit && !editMode">
        <button type="button" class="sh-edit-toggle-btn" @click="editMode = true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
          {{ $t('gr.editDetails') }}
        </button>
        <button type="button" class="sh-edit-toggle-btn sh-delete-btn" :disabled="deleteProcessing" @click="deleteGr" :title="$t('gr.deleteGr')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          {{ $t('gr.deleteGr') }}
        </button>
      </div>
    </div>

    <!-- VIEW mode meta grid -->
    <div v-if="!editMode" class="sh-meta-grid">
      <div class="sh-meta-item" v-if="gr.pr_number">
        <span class="sh-meta-lbl">PR #</span>
        <span class="sh-meta-val mono">{{ gr.pr_number }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.po_number">
        <span class="sh-meta-lbl">PO #</span>
        <span class="sh-meta-val mono">{{ gr.po_number }}</span>
      </div>
      <div class="sh-meta-item">
        <span class="sh-meta-lbl">{{ $t('gr.warehouse') }}</span>
        <span class="sh-meta-val">{{ gr.warehouse?.code }} · {{ gr.warehouse?.name }}</span>
      </div>
      <div class="sh-meta-item">
        <span class="sh-meta-lbl">{{ $t('gr.createdBy') }}</span>
        <span class="sh-meta-val">{{ gr.created_by?.name ?? '—' }}</span>
      </div>
      <div class="sh-meta-item">
        <span class="sh-meta-lbl">{{ $t('gr.createdAt') }}</span>
        <span class="sh-meta-val">{{ fmtDate(gr.created_at) }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.submitted_at">
        <span class="sh-meta-lbl">{{ $t('gr.arrivedAt') }}</span>
        <span class="sh-meta-val">{{ fmtDate(gr.submitted_at) }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.assigned_to">
        <span class="sh-meta-lbl">{{ $t('gr.assignedTo') }}</span>
        <span class="sh-meta-val">{{ gr.assigned_to?.name ?? '—' }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.assigned_at">
        <span class="sh-meta-lbl">{{ $t('gr.assignedAt') }}</span>
        <span class="sh-meta-val">{{ fmtDate(gr.assigned_at) }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.inspected_at">
        <span class="sh-meta-lbl">{{ $t('gr.inspectedBy') }}</span>
        <span class="sh-meta-val">{{ gr.inspected_by?.name ?? '—' }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.inspected_at">
        <span class="sh-meta-lbl">{{ $t('gr.inspectedAt') }}</span>
        <span class="sh-meta-val">{{ fmtDate(gr.inspected_at) }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.completed_at">
        <span class="sh-meta-lbl">{{ $t('gr.approvedBy') }}</span>
        <span class="sh-meta-val">{{ gr.approved_by?.name ?? $t('gr.systemAuto') }}</span>
      </div>
      <div class="sh-meta-item" v-if="gr.completed_at">
        <span class="sh-meta-lbl">{{ $t('gr.completedAt') }}</span>
        <span class="sh-meta-val">{{ fmtDate(gr.completed_at) }}</span>
      </div>
    </div>

    <!-- EDIT mode form -->
    <div v-else class="sh-edit-form">
      <p class="sh-edit-form-sub">{{ $t('gr.editPanelSub') }}</p>
      <div class="sh-edit-row">
        <label class="sh-edit-lbl">PR #</label>
        <input v-model="editForm.pr_number" class="sh-edit-input" :placeholder="$t('gr.prNumberPh')" />
      </div>
      <div class="sh-edit-row">
        <label class="sh-edit-lbl">PO #</label>
        <input v-model="editForm.po_number" class="sh-edit-input" :placeholder="$t('gr.poNumberPh')" />
      </div>
      <div class="sh-edit-row">
        <label class="sh-edit-lbl">{{ $t('gr.notes') }}</label>
        <textarea v-model="editForm.notes" class="sh-edit-input sh-edit-textarea" :placeholder="$t('gr.notesPh')" rows="3"></textarea>
      </div>
      <div class="sh-edit-btns">
        <button type="button" class="sh-edit-save" :disabled="editForm.processing" @click="saveEdit">
          {{ editForm.processing ? '…' : $t('gr.saveDetails') }}
        </button>
        <button type="button" class="sh-edit-cancel" @click="editMode = false">
          {{ $t('gr.cancelEdit') }}
        </button>
      </div>
    </div>

    <div class="sh-notes" v-if="gr.notes && !editMode">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      {{ gr.notes }}
    </div>
  </div>

  <!-- ── PROGRESS STEPPER ─────────────────────────────────────────────────── -->
  <div class="sh-stepper">
    <template v-for="(step, i) in steps" :key="step.key">
      <div v-if="i > 0" class="sh-connector" :class="{ 'sh-con-done': steps[i].done || steps[i].active }"></div>
      <div class="sh-step" :class="{
        'sh-step-done':   step.done,
        'sh-step-active': step.active,
        'sh-step-future': !step.done && !step.active,
      }">
        <div class="sh-step-dot">
          <svg v-if="step.done" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
          <div v-else-if="step.active" class="sh-dot-pulse"></div>
          <span v-else class="sh-dot-num">{{ i + 1 }}</span>
        </div>
        <div class="sh-step-body">
          <div class="sh-step-label">{{ step.label }}</div>
          <div class="sh-step-sub">{{ step.sub }}</div>
          <div v-if="step.time && step.done" class="sh-step-time">{{ fmtDate(step.time) }}</div>
          <div v-else-if="step.active" class="sh-step-time sh-step-in-progress">
            <span class="sh-prog-dot"></span> {{ $t('gr.stepInProgress') }}
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- ── ACTION PANELS ─────────────────────────────────────────────────────── -->

  <!-- 1. Mark as arrived (draft, creator/super_admin) -->
  <div v-if="canSubmit" class="sh-action-panel sh-action-blue sh-action-col">
    <div class="sh-action-row">
      <div class="sh-action-info">
        <div class="sh-action-title">{{ $t('gr.actionArrivedTitle') }}</div>
        <div class="sh-action-sub">{{ $t('gr.actionArrivedSub') }}</div>
      </div>
      <button type="button" class="sh-action-btn sh-btn-blue" :disabled="submitProcessing" @click="submitArrived">
        <svg v-if="submitProcessing" class="sh-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M20 6 9 17l-5-5"/></svg>
        {{ $t('gr.markArrived') }}
      </button>
    </div>
    <div class="sh-photo-upload">
      <label class="sh-photo-lbl">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        {{ $t('gr.photoUpload') }}
        <input ref="arrivalPhotoInput" type="file" multiple accept="image/*" class="sh-photo-hidden" @change="onArrivalPhotos" />
      </label>
      <div v-if="arrivalPhotoPreviews.length" class="sh-photo-previews">
        <div v-for="(p, i) in arrivalPhotoPreviews" :key="i" class="sh-photo-thumb">
          <img :src="p.url" :alt="p.name" @click.stop="previewLightbox(arrivalPhotoPreviews, i)" />
          <button type="button" class="sh-photo-remove" @click.stop="removeArrivalPhoto(i)">×</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Rack placement + assign operator (arrived, wh_admin/super_admin) -->
  <div v-if="canAssign" class="sh-assign-wrap">
    <div class="sh-assign-header">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <div>
        <div class="sh-assign-title">{{ $t('gr.actionAssignTitle') }}</div>
        <div class="sh-assign-sub">{{ $t('gr.actionAssignSub') }}</div>
      </div>
    </div>

    <!-- Per-item rack assignment -->
    <div class="sh-assign-items">
      <div class="sh-assign-item" v-for="(aItem, idx) in assignItems" :key="aItem.id">
        <div class="sh-ai-info">
          <div class="sh-ai-top">
            <span class="sh-cat" v-if="gr.items[idx]?.variant?.item?.category">{{ gr.items[idx].variant.item.category.code }}</span>
            <span class="sh-sku">{{ gr.items[idx]?.variant?.sku ?? '—' }}</span>
          </div>
          <div class="sh-item-name">{{ itemName(gr.items[idx]?.variant?.item) }}</div>
          <div class="sh-item-variant" v-if="gr.items[idx]?.variant && variantLabel(gr.items[idx].variant)">
            {{ variantLabel(gr.items[idx].variant) }}
          </div>
          <div class="sh-ai-expected">
            {{ $t('gr.expected') }}: <b>{{ gr.items[idx]?.expected_qty?.toLocaleString() }}</b> {{ gr.items[idx]?.uom }}
          </div>
        </div>
        <div class="sh-ai-inputs">
          <div class="sh-iifg">
            <label class="sh-iilbl">{{ $t('gr.warehouse') }} <span class="cr-req">*</span></label>
            <select class="sh-iinput" v-model="aItem.warehouse_id" @change="onWhChange(aItem)" required>
              <option value="">{{ $t('gr.selectWh') }}</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} · {{ wh.name }}</option>
            </select>
          </div>
          <div class="sh-iifg">
            <label class="sh-iilbl">{{ $t('gr.rack') }} <span class="cr-req">*</span></label>
            <select class="sh-iinput" v-model="aItem.location_id" :disabled="!aItem.warehouse_id" required>
              <option value="">{{ $t('gr.selectRack') }}</option>
              <option v-for="loc in locationsFor(aItem)" :key="loc.id" :value="loc.id">{{ loc.code }} · {{ loc.name }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Operator selector -->
    <div class="sh-assign-op-row">
      <div class="sh-assign-op-left">
        <label class="sh-iilbl">{{ $t('gr.assignedTo') }} <span class="cr-req">*</span></label>
        <div class="sh-op-controls">
          <select class="sh-iinput sh-op-select" v-model="selectedOperator">
            <option :value="null">{{ $t('gr.selectOperator') }}</option>
            <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
          </select>
          <button type="button" class="sh-self-assign-btn" @click="selfAssignOp">
            {{ $t('gr.selfAssignBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Optional placement photos -->
    <div class="sh-photo-upload sh-photo-upload-inspect">
      <label class="sh-photo-lbl">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        {{ $t('gr.photoUpload') }}
        <input ref="assignPhotoInput" type="file" multiple accept="image/*" class="sh-photo-hidden" @change="onAssignPhotos" />
      </label>
      <div v-if="assignPhotoPreviews.length" class="sh-photo-previews">
        <div v-for="(p, i) in assignPhotoPreviews" :key="i" class="sh-photo-thumb">
          <img :src="p.url" :alt="p.name" @click.stop="previewLightbox(assignPhotoPreviews, i)" />
          <button type="button" class="sh-photo-remove" @click.stop="removeAssignPhoto(i)">×</button>
        </div>
      </div>
    </div>

    <div class="sh-inspect-footer">
      <button type="button" class="sh-btn-inspect" :disabled="assignProcessing || !assignAllValid" @click="submitAssign">
        <svg v-if="assignProcessing" class="sh-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        {{ assignProcessing ? $t('gr.submitting') : $t('gr.assignBtn') }}
      </button>
    </div>
  </div>

  <!-- 3. Approve (pending_supervisor, supervisor/super_admin) -->
  <div v-if="canApprove" class="sh-action-panel sh-action-amber">
    <div class="sh-action-info">
      <div class="sh-action-title">{{ $t('gr.actionApproveTitle') }}</div>
      <div class="sh-action-sub" v-if="countdownLabel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        {{ countdownLabel }}
      </div>
    </div>
    <button type="button" class="sh-action-btn sh-btn-green" :disabled="approveProcessing" @click="submitApprove">
      <svg v-if="approveProcessing" class="sh-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M20 6 9 17l-5-5"/></svg>
      {{ $t('gr.approve') }}
    </button>
  </div>

  <!-- completed banner -->
  <div v-if="gr.status === 'completed'" class="sh-completed-banner">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M20 6 9 17l-5-5"/></svg>
    <div>
      <div class="sh-completed-title">{{ $t('gr.completedTitle') }}</div>
      <div class="sh-completed-sub">
        {{ gr.auto_approved ? $t('gr.completedAutoSub') : $t('gr.completedSub', { name: gr.approved_by?.name ?? '—' }) }}
        · {{ fmtDate(gr.completed_at) }}
      </div>
    </div>
  </div>

  <!-- ── ITEMS TABLE ─────────────────────────────────────────────────────── -->
  <div class="sh-section-label">{{ $t('gr.sectionItems') }} ({{ gr.items.length }})</div>

  <!-- READ-ONLY table: shown when not in assign or inspect form mode -->
  <div v-if="!canAssign && !canInspect" class="sh-table-wrap">
    <table class="sh-table">
      <thead>
        <tr>
          <th style="width:52px"></th>
          <th>{{ $t('gr.colVariant') }}</th>
          <th class="tr">{{ $t('gr.colExpQty') }}</th>
          <th v-if="['pending_supervisor','completed'].includes(gr.status)" class="tr">{{ $t('gr.colActQty') }}</th>
          <th v-if="['pending_supervisor','completed'].includes(gr.status)">{{ $t('gr.colCondition') }}</th>
          <th v-if="['assigned','pending_supervisor','completed'].includes(gr.status)">{{ $t('gr.colLocation') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in gr.items" :key="item.id">
          <td class="sh-thumb-cell">
            <img v-if="item.variant?.photo_path" :src="item.variant.photo_path" :alt="item.variant?.sku" class="sh-thumb" />
            <div v-else class="sh-thumb-ph">WBN</div>
          </td>
          <td>
            <div class="sh-item-top">
              <span class="sh-cat" v-if="item.variant?.item?.category">{{ item.variant.item.category.code }}</span>
              <span class="sh-sku">{{ item.variant?.sku ?? '—' }}</span>
            </div>
            <div class="sh-item-name">{{ itemName(item.variant?.item) }}</div>
            <div class="sh-item-variant" v-if="item.variant && variantLabel(item.variant)">{{ variantLabel(item.variant) }}</div>
          </td>
          <td class="tr">
            <span class="sh-qty">{{ item.expected_qty.toLocaleString() }}</span>
            <span class="sh-uom">{{ item.uom }}</span>
          </td>
          <td v-if="['pending_supervisor','completed'].includes(gr.status)" class="tr">
            <span class="sh-qty" :class="{ 'sh-qty-diff': item.actual_qty && item.actual_qty !== item.expected_qty }">
              {{ item.actual_qty?.toLocaleString() ?? '—' }}
            </span>
            <span class="sh-uom" v-if="item.actual_qty">{{ item.uom }}</span>
          </td>
          <td v-if="['pending_supervisor','completed'].includes(gr.status)">
            <span v-if="item.condition" class="sh-cond-badge" :style="condStyle(item.condition)">
              {{ $t('gr.cond_' + item.condition) }}
            </span>
            <span v-else class="sh-dim">—</span>
            <div class="sh-cond-notes" v-if="item.condition_notes">{{ item.condition_notes }}</div>
          </td>
          <td v-if="['assigned','pending_supervisor','completed'].includes(gr.status)">
            <div v-if="item.location" class="sh-loc">
              <span class="sh-loc-wh">{{ item.location.warehouse?.code ?? '—' }}</span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="10" height="10"><path d="m9 18 6-6-6-6"/></svg>
              <span class="sh-loc-rack">{{ item.location.code }} · {{ item.location.name }}</span>
            </div>
            <span v-else class="sh-dim">—</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- INSPECT FORM: operator/wh_admin + assigned status -->
  <div v-if="canInspect" class="sh-inspect-wrap">
    <div class="sh-inspect-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      {{ $t('gr.actionInspectSub') }}
    </div>

    <div class="sh-inspect-items">
      <div class="sh-inspect-item" v-for="(inspItem, idx) in inspectItems" :key="inspItem.id">
        <!-- Item info -->
        <div class="sh-ii-info">
          <div class="sh-ii-top">
            <span class="sh-cat" v-if="gr.items[idx]?.variant?.item?.category">{{ gr.items[idx].variant.item.category.code }}</span>
            <span class="sh-sku">{{ gr.items[idx]?.variant?.sku ?? '—' }}</span>
          </div>
          <div class="sh-item-name">{{ itemName(gr.items[idx]?.variant?.item) }}</div>
          <div class="sh-item-variant" v-if="gr.items[idx]?.variant && variantLabel(gr.items[idx].variant)">
            {{ variantLabel(gr.items[idx].variant) }}
          </div>
          <div class="sh-ii-expected">
            {{ $t('gr.expected') }}: <b>{{ gr.items[idx]?.expected_qty?.toLocaleString() }}</b> {{ gr.items[idx]?.uom }}
          </div>
          <!-- Read-only rack location (set by WH Admin) -->
          <div v-if="gr.items[idx]?.location" class="sh-ii-rack">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            {{ gr.items[idx].location.warehouse?.code ?? '—' }} →
            {{ gr.items[idx].location.code }} · {{ gr.items[idx].location.name }}
          </div>
        </div>

        <!-- Inputs -->
        <div class="sh-ii-inputs">
          <!-- Actual Qty -->
          <div class="sh-iifg">
            <label class="sh-iilbl">{{ $t('gr.colActQty') }} ({{ gr.items[idx]?.uom }}) <span class="cr-req">*</span></label>
            <input class="sh-iinput" type="number" v-model="inspItem.actual_qty" step="any" min="0" required />
          </div>

          <!-- Condition -->
          <div class="sh-iifg">
            <label class="sh-iilbl">{{ $t('gr.colCondition') }} <span class="cr-req">*</span></label>
            <div class="sh-cond-btns">
              <button
                v-for="c in CONDITIONS" :key="c"
                type="button"
                class="sh-cond-btn"
                :class="{ active: inspItem.condition === c }"
                :style="inspItem.condition === c ? condStyle(c) : {}"
                @click="inspItem.condition = c"
              >{{ $t('gr.cond_' + c) }}</button>
            </div>
          </div>

          <!-- Condition notes (if not good) -->
          <div class="sh-iifg sh-iifg-full" v-if="inspItem.condition && inspItem.condition !== 'good'">
            <label class="sh-iilbl">{{ $t('gr.condNotes') }}</label>
            <input class="sh-iinput" type="text" v-model="inspItem.condition_notes" :placeholder="$t('gr.condNotesPh')" maxlength="500" />
          </div>
        </div>
      </div>
    </div>

    <!-- Photo upload for inspection -->
    <div class="sh-photo-upload sh-photo-upload-inspect">
      <label class="sh-photo-lbl">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        {{ $t('gr.photoUpload') }}
        <input ref="inspectPhotoInput" type="file" multiple accept="image/*" class="sh-photo-hidden" @change="onInspectPhotos" />
      </label>
      <div v-if="inspectPhotoPreviews.length" class="sh-photo-previews">
        <div v-for="(p, i) in inspectPhotoPreviews" :key="i" class="sh-photo-thumb">
          <img :src="p.url" :alt="p.name" @click.stop="previewLightbox(inspectPhotoPreviews, i)" />
          <button type="button" class="sh-photo-remove" @click.stop="removeInspectPhoto(i)">×</button>
        </div>
      </div>
    </div>

    <!-- Submit inspection button -->
    <div class="sh-inspect-footer">
      <button type="button" class="sh-btn-inspect" :disabled="inspectProcessing" @click="submitInspect">
        <svg v-if="inspectProcessing" class="sh-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        {{ inspectProcessing ? $t('gr.submitting') : $t('gr.submitInspect') }}
      </button>
    </div>
  </div>

  <!-- ── PHOTO GALLERY ─────────────────────────────────────────────────────── -->
  <div v-if="gr.photos && gr.photos.length" class="sh-gallery-wrap">
    <div class="sh-section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      {{ $t('gr.photoGallery') }} ({{ gr.photos.length }})
    </div>

    <template v-if="gr.photos.filter(p => p.stage === 'arrived').length">
      <div class="sh-gallery-group-lbl">
        <span class="sh-stage-badge sh-stage-arrived">{{ $t('gr.stepArrived') }}</span>
      </div>
      <div class="sh-gallery-grid">
        <div v-for="(p, i) in gr.photos.filter(p => p.stage === 'arrived')" :key="p.id"
          class="sh-gallery-item" @click="openLightbox(gr.photos.filter(px => px.stage === 'arrived'), i)">
          <img :src="p.url" :alt="p.original_name" loading="lazy" />
          <div class="sh-gallery-meta">{{ p.uploaded_by }}</div>
        </div>
      </div>
    </template>

    <template v-if="gr.photos.filter(p => p.stage === 'inspection').length">
      <div class="sh-gallery-group-lbl" style="margin-top:12px">
        <span class="sh-stage-badge sh-stage-inspect">{{ $t('gr.stepInspected') }}</span>
      </div>
      <div class="sh-gallery-grid">
        <div v-for="(p, i) in gr.photos.filter(p => p.stage === 'inspection')" :key="p.id"
          class="sh-gallery-item" @click="openLightbox(gr.photos.filter(px => px.stage === 'inspection'), i)">
          <img :src="p.url" :alt="p.original_name" loading="lazy" />
          <div class="sh-gallery-meta">{{ p.uploaded_by }}</div>
        </div>
      </div>
    </template>
  </div>

  <!-- ── LIGHTBOX ─────────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="lightbox" class="sh-lb-overlay" @click.self="closeLightbox">
      <button class="sh-lb-close" @click="closeLightbox">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <button v-if="lightbox.list.length > 1" class="sh-lb-nav sh-lb-prev" @click.stop="lightboxPrev">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <div class="sh-lb-img-wrap" @click.stop>
        <img v-if="lightboxCurrent" :src="lightboxCurrent.url" :alt="lightboxCurrent.original_name" class="sh-lb-img" />
        <div class="sh-lb-caption" v-if="lightboxCurrent">
          <span>{{ lightboxCurrent.original_name }}</span>
          <span class="sh-lb-sep">·</span>
          <span>{{ lightboxCurrent.uploaded_by }}</span>
          <span class="sh-lb-sep">·</span>
          <span>{{ fmtDate(lightboxCurrent.created_at) }}</span>
          <span class="sh-lb-counter">{{ lightbox.idx + 1 }} / {{ lightbox.list.length }}</span>
        </div>
      </div>
      <button v-if="lightbox.list.length > 1" class="sh-lb-nav sh-lb-next" @click.stop="lightboxNext">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
  </Teleport>

</AppLayout>
</template>

<style scoped>
.bc-link { color:var(--orange-500); text-decoration:none; font-weight:600 }
.bc-link:hover { text-decoration:underline }

/* flash */
.sh-flash { padding:12px 16px; border-radius:8px; font-size:13.5px; font-weight:600 }
.sh-flash-ok  { background:rgba(16,185,129,.12); color:#34d399; border:1px solid rgba(16,185,129,.25) }
.sh-flash-err { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.25) }

/* header card */
.sh-header-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px 24px; box-shadow:var(--shadow-sm) }
.sh-header-main { display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap }
.sh-gr-num { font-family:monospace; font-size:22px; font-weight:800; color:var(--fg); letter-spacing:-.01em }
.sh-status-badge { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; letter-spacing:.04em }
.sh-auto-badge { padding:3px 9px; border-radius:999px; font-size:10.5px; font-weight:700; background:rgba(100,116,139,.1); color:var(--fg-dim) }

.sh-meta-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:10px 20px; margin-bottom:12px }
.sh-meta-item { display:flex; flex-direction:column; gap:2px }
.sh-meta-lbl { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-dim) }
.sh-meta-val { font-size:13.5px; color:var(--fg); font-weight:500 }
.sh-meta-val.mono { font-family:monospace; font-weight:700; color:var(--orange-500) }

.sh-notes { display:flex; align-items:flex-start; gap:8px; padding:10px 14px; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; font-size:13px; color:var(--fg-2); margin-top:8px }

/* ── progress stepper ─────────────────────────────────────────────── */
.sh-stepper {
  display:flex; align-items:flex-start;
  background:var(--surface); border:1px solid var(--border);
  border-radius:14px; padding:22px 24px; gap:0;
  overflow-x:auto;
}
.sh-connector {
  flex:1; min-width:20px; height:2px; margin-top:18px;
  background:var(--border-2); border-radius:2px; flex-shrink:1;
}
.sh-connector.sh-con-done { background:var(--orange-500) }
.sh-step {
  display:flex; flex-direction:column; align-items:center;
  gap:8px; min-width:80px; max-width:110px; text-align:center; flex-shrink:0;
}
.sh-step-dot {
  width:36px; height:36px; border-radius:50%;
  display:grid; place-items:center; flex-shrink:0;
  border:2px solid var(--border-2);
  background:var(--surface-2); position:relative;
}
.sh-step-done .sh-step-dot { background:var(--orange-500); border-color:var(--orange-500); color:#fff }
.sh-step-active .sh-step-dot { border-color:var(--orange-500); background:rgba(249,115,22,.12) }
.sh-step-future .sh-step-dot { background:var(--surface-2); border-color:var(--border-2) }
.sh-dot-num { font-size:13px; font-weight:700; color:var(--fg-dim) }
.sh-dot-pulse { width:10px; height:10px; border-radius:50%; background:var(--orange-500); animation:sh-pulse 1.4s ease-in-out infinite }
@keyframes sh-pulse { 0%,100% { transform:scale(1); opacity:1 } 50% { transform:scale(1.5); opacity:.6 } }
.sh-step-body { display:flex; flex-direction:column; align-items:center; gap:2px }
.sh-step-label { font-size:11.5px; font-weight:700; color:var(--fg); white-space:nowrap }
.sh-step-future .sh-step-label { color:var(--fg-dim) }
.sh-step-active .sh-step-label { color:var(--orange-500) }
.sh-step-sub { font-size:10.5px; color:var(--fg-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100px }
.sh-step-done .sh-step-sub { color:var(--fg-2) }
.sh-step-time { font-size:10px; color:var(--fg-dim); margin-top:2px; white-space:nowrap }
.sh-step-in-progress { color:var(--orange-500); display:flex; align-items:center; gap:4px; justify-content:center }
.sh-prog-dot { width:6px; height:6px; border-radius:50%; background:var(--orange-500); animation:sh-pulse 1s ease-in-out infinite }

/* action panels */
.sh-action-panel { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:16px 20px; border-radius:12px; border:1px solid transparent; flex-wrap:wrap }
.sh-action-blue { background:rgba(59,130,246,.08); border-color:rgba(59,130,246,.2) }
.sh-action-amber { background:rgba(234,179,8,.08); border-color:rgba(234,179,8,.2) }
.sh-action-info { display:flex; flex-direction:column; gap:3px }
.sh-action-title { font-size:14px; font-weight:700; color:var(--fg) }
.sh-action-sub { display:flex; align-items:center; gap:5px; font-size:12.5px; color:var(--fg-2) }
.sh-action-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border-radius:9px; font-size:13.5px; font-weight:700; cursor:pointer; border:none; transition:opacity 180ms }
.sh-action-btn:disabled { opacity:.6; cursor:default }
.sh-btn-blue { background:linear-gradient(180deg,#3b82f6,#2563eb); color:#fff; box-shadow:0 4px 12px -3px rgba(59,130,246,.5) }
.sh-btn-green { background:linear-gradient(180deg,#10b981,#059669); color:#fff; box-shadow:0 4px 12px -3px rgba(16,185,129,.5) }
.sh-action-col { flex-direction:column; align-items:stretch; gap:12px }
.sh-action-row { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap }

.sh-completed-banner { display:flex; align-items:center; gap:14px; padding:16px 20px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.2); border-radius:12px }
.sh-completed-banner svg { color:#34d399; flex-shrink:0 }
.sh-completed-title { font-size:14px; font-weight:700; color:#34d399 }
.sh-completed-sub { font-size:12.5px; color:var(--fg-2); margin-top:2px }

/* section label */
.sh-section-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--fg-dim); padding-bottom:6px; border-bottom:1px solid var(--border) }

/* read-only table */
.sh-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:var(--shadow-sm) }
.sh-table { width:100%; border-collapse:collapse }
.sh-table th { padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-2); background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap }
.sh-table td { padding:12px 14px; font-size:13.5px; border-bottom:1px solid var(--border-soft); vertical-align:middle }
.sh-table tbody tr:last-child td { border-bottom:none }
.tr { text-align:right }

.sh-thumb-cell { width:64px; padding:8px 8px 8px 14px }
.sh-thumb { width:48px; height:48px; object-fit:cover; border-radius:9px; border:1px solid var(--border) }
.sh-thumb-ph { width:48px; height:48px; border-radius:9px; background:linear-gradient(135deg,#f97316,#ea580c); display:grid; place-items:center; font-size:10px; font-weight:800; color:#fff; letter-spacing:.05em }
.sh-item-top { display:flex; align-items:center; gap:6px; margin-bottom:3px }
.sh-cat { padding:2px 6px; border-radius:4px; font-size:10px; font-weight:700; background:rgba(59,130,246,.12); color:#60a5fa }
.sh-sku { font-family:monospace; font-size:12px; font-weight:700; color:var(--orange-500); background:rgba(249,115,22,.08); padding:2px 6px; border-radius:4px; border:1px solid rgba(249,115,22,.18) }
.sh-item-name { font-size:13.5px; font-weight:600; color:var(--fg) }
.sh-item-variant { font-size:12px; color:var(--fg-2); margin-top:2px }
.sh-qty { font-size:15px; font-weight:700; color:var(--fg) }
.sh-qty-diff { color:#f87171 }
.sh-uom { font-size:11px; color:var(--fg-dim); margin-left:3px }
.sh-cond-badge { display:inline-block; padding:3px 8px; border-radius:5px; font-size:11.5px; font-weight:700 }
.sh-cond-notes { font-size:11px; color:var(--fg-dim); margin-top:2px }
.sh-loc { display:flex; align-items:center; gap:5px }
.sh-loc-wh { font-family:monospace; font-weight:700; font-size:12px; color:var(--fg); background:var(--surface-3); padding:2px 6px; border-radius:4px }
.sh-loc-rack { font-size:12.5px; color:var(--fg-2) }
.sh-dim { color:var(--fg-dim); font-size:12px }

/* ── assign form (rack placement) ────────────────────────────────────────── */
.sh-assign-wrap { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; display:flex; flex-direction:column; gap:16px; box-shadow:var(--shadow-sm) }
.sh-assign-header { display:flex; align-items:flex-start; gap:12px; padding-bottom:12px; border-bottom:1px solid var(--border) }
.sh-assign-header svg { color:var(--orange-500); flex-shrink:0; margin-top:2px }
.sh-assign-title { font-size:14px; font-weight:700; color:var(--fg) }
.sh-assign-sub { font-size:12.5px; color:var(--fg-2); margin-top:2px }

.sh-assign-items { display:flex; flex-direction:column; gap:10px }
.sh-assign-item { background:var(--surface-2); border:1px solid var(--border); border-radius:10px; padding:14px 16px; display:grid; grid-template-columns:1fr 1.6fr; gap:16px }
@media(max-width:640px){ .sh-assign-item{ grid-template-columns:1fr } }

.sh-ai-info { display:flex; flex-direction:column; gap:4px }
.sh-ai-top { display:flex; align-items:center; gap:6px; margin-bottom:2px }
.sh-ai-expected { font-size:12px; color:var(--fg-2); margin-top:4px }
.sh-ai-expected b { color:var(--fg); font-weight:700 }

.sh-ai-inputs { display:grid; grid-template-columns:1fr 1fr; gap:10px }
@media(max-width:420px){ .sh-ai-inputs{ grid-template-columns:1fr } }

/* operator select row */
.sh-assign-op-row { background:var(--surface-2); border:1px solid var(--border); border-radius:10px; padding:14px 16px }
.sh-assign-op-left { display:flex; flex-direction:column; gap:8px }
.sh-op-controls { display:flex; gap:8px; flex-wrap:wrap }
.sh-op-select { flex:1; min-width:200px }
.sh-self-assign-btn {
  padding:8px 14px; border-radius:8px; font-size:13px; font-weight:700;
  background:rgba(249,115,22,.12); color:var(--orange-500);
  border:1px solid rgba(249,115,22,.3); cursor:pointer; font-family:inherit;
  transition:background 160ms; white-space:nowrap;
}
.sh-self-assign-btn:hover { background:rgba(249,115,22,.2) }

/* inspect form */
.sh-inspect-wrap { display:flex; flex-direction:column; gap:12px }
.sh-inspect-info { display:flex; align-items:center; gap:8px; padding:12px 16px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); border-radius:9px; font-size:13px; color:var(--fg-2) }
.sh-inspect-info svg { color:#60a5fa; flex-shrink:0 }

.sh-inspect-items { display:flex; flex-direction:column; gap:12px }
.sh-inspect-item { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px 20px; display:grid; grid-template-columns:1fr 1.8fr; gap:20px }
@media(max-width:700px){ .sh-inspect-item{ grid-template-columns:1fr } }

.sh-ii-info { display:flex; flex-direction:column; gap:4px }
.sh-ii-top { display:flex; align-items:center; gap:6px; margin-bottom:4px }
.sh-ii-expected { font-size:12.5px; color:var(--fg-2); margin-top:6px }
.sh-ii-expected b { color:var(--fg); font-weight:700 }
.sh-ii-rack { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--fg-2); margin-top:4px; padding:4px 8px; background:rgba(249,115,22,.08); border-radius:5px; border:1px solid rgba(249,115,22,.15) }
.sh-ii-rack svg { color:var(--orange-500); flex-shrink:0 }

.sh-ii-inputs { display:grid; grid-template-columns:1fr 1fr; gap:10px }
.sh-iifg { display:flex; flex-direction:column; gap:4px }
.sh-iifg-full { grid-column:1/-1 }
.sh-iilbl { font-size:11.5px; font-weight:600; color:var(--fg-2) }
.sh-iinput { padding:8px 11px; font-size:13px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:7px; color:var(--fg); outline:none; font-family:inherit; transition:border-color 180ms; width:100%; box-sizing:border-box }
.sh-iinput:focus { border-color:var(--orange-500) }
.sh-iinput:disabled { opacity:.5 }
.cr-req { color:#f87171 }

.sh-cond-btns { display:flex; gap:5px; flex-wrap:wrap }
.sh-cond-btn { padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; background:var(--surface-3); border:1px solid var(--border-2); color:var(--fg-2); font-family:inherit; transition:all 120ms }
.sh-cond-btn:hover { border-color:var(--border) }
.sh-cond-btn.active { border-color:transparent }

.sh-inspect-footer { display:flex; justify-content:flex-end; padding-top:8px }
.sh-btn-inspect { display:inline-flex; align-items:center; gap:7px; padding:11px 24px; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; background:linear-gradient(180deg,var(--orange-400),var(--orange-500)); color:#fff; border:none; box-shadow:0 4px 12px -3px rgba(249,115,22,.5); transition:opacity 180ms }
.sh-btn-inspect:disabled { opacity:.6; cursor:default }

@keyframes sh-spin { to { transform:rotate(360deg) } }
.sh-spin { animation:sh-spin .8s linear infinite; flex-shrink:0 }

/* header edit button */
.sh-header-actions { margin-left:auto; display:flex; align-items:center; gap:8px }
.sh-edit-toggle-btn {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 12px; border-radius:7px; border:1px solid var(--border-2);
  background:var(--surface-2); color:var(--fg-2); font-size:12px; font-weight:600;
  cursor:pointer; font-family:inherit; transition:background 180ms, color 180ms;
}
.sh-edit-toggle-btn:hover { background:var(--hover); color:var(--fg) }
.sh-delete-btn { border-color:rgba(239,68,68,.3); color:var(--rose) }
.sh-delete-btn:hover { background:rgba(239,68,68,.08); color:var(--rose) }
.sh-delete-btn:disabled { opacity:.6; cursor:default }

/* edit form */
.sh-edit-form { display:flex; flex-direction:column; gap:10px; padding-top:4px }
.sh-edit-form-sub { font-size:12px; color:var(--fg-2); margin:0 0 4px }
.sh-edit-row { display:flex; flex-direction:column; gap:4px }
.sh-edit-lbl { font-size:11.5px; font-weight:600; color:var(--fg-2); letter-spacing:.02em }
.sh-edit-input { width:100%; padding:8px 11px; border-radius:8px; border:1px solid var(--border-2); background:var(--surface-2); color:var(--fg); font-size:13px; font-family:inherit; transition:border-color 180ms; box-sizing:border-box }
.sh-edit-input:focus { outline:none; border-color:var(--blue-500) }
.sh-edit-textarea { resize:vertical; min-height:70px }
.sh-edit-btns { display:flex; gap:8px; padding-top:4px }
.sh-edit-save { padding:8px 18px; border-radius:8px; border:none; background:linear-gradient(180deg,var(--orange-400),var(--orange-500)); color:#fff; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity 180ms }
.sh-edit-save:disabled { opacity:.6; cursor:default }
.sh-edit-cancel { padding:8px 16px; border-radius:8px; border:1px solid var(--border-2); background:transparent; color:var(--fg-2); font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:background 180ms }
.sh-edit-cancel:hover { background:var(--hover) }

/* photo upload */
.sh-photo-upload { display:flex; flex-direction:column; gap:8px; padding-top:4px }
.sh-photo-upload-inspect { padding:14px 16px; background:var(--surface-2); border:1px dashed var(--border-2); border-radius:9px }
.sh-photo-lbl { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:8px; cursor:pointer; font-size:12.5px; font-weight:600; color:var(--fg-2); background:var(--surface-3); border:1px solid var(--border-2); transition:border-color 160ms, color 160ms; width:fit-content }
.sh-photo-lbl:hover { border-color:var(--orange-400); color:var(--orange-500) }
.sh-photo-hidden { display:none }
.sh-photo-previews { display:flex; flex-wrap:wrap; gap:8px }
.sh-photo-thumb { position:relative; width:72px; height:72px; border-radius:9px; overflow:hidden; border:2px solid var(--border-2) }
.sh-photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; cursor:zoom-in }
.sh-photo-remove { position:absolute; top:2px; right:2px; width:18px; height:18px; border-radius:50%; background:rgba(0,0,0,.65); color:#fff; border:none; cursor:pointer; font-size:13px; line-height:1; display:grid; place-items:center; padding:0 }
.sh-photo-remove:hover { background:rgba(239,68,68,.85) }

/* photo gallery */
.sh-gallery-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; display:flex; flex-direction:column; gap:14px }
.sh-gallery-group-lbl { display:flex; align-items:center; gap:8px }
.sh-stage-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.04em }
.sh-stage-arrived  { background:rgba(59,130,246,.12); color:#60a5fa }
.sh-stage-inspect  { background:rgba(234,179,8,.12); color:#fbbf24 }
.sh-gallery-grid { display:flex; flex-wrap:wrap; gap:10px }
.sh-gallery-item { display:flex; flex-direction:column; gap:5px; width:120px; cursor:pointer }
.sh-gallery-item img { width:120px; height:90px; object-fit:cover; border-radius:9px; border:2px solid var(--border); transition:transform 160ms, border-color 160ms; display:block }
.sh-gallery-item:hover img { transform:scale(1.03); border-color:var(--orange-400) }
.sh-gallery-meta { font-size:10.5px; color:var(--fg-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:center }

/* lightbox */
.sh-lb-overlay { position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.88); display:flex; align-items:center; justify-content:center; padding:20px }
.sh-lb-img-wrap { display:flex; flex-direction:column; align-items:center; gap:12px; max-width:90vw; max-height:90vh }
.sh-lb-img { max-width:100%; max-height:80vh; object-fit:contain; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,.6); display:block }
.sh-lb-caption { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:center; font-size:12px; color:rgba(255,255,255,.7) }
.sh-lb-sep { opacity:.4 }
.sh-lb-counter { margin-left:8px; opacity:.5; font-size:11px }
.sh-lb-close { position:fixed; top:18px; right:20px; width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.12); border:none; color:#fff; cursor:pointer; display:grid; place-items:center; transition:background 160ms }
.sh-lb-close:hover { background:rgba(239,68,68,.7) }
.sh-lb-nav { position:fixed; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; background:rgba(255,255,255,.12); border:none; color:#fff; cursor:pointer; display:grid; place-items:center; transition:background 160ms }
.sh-lb-nav:hover { background:rgba(255,255,255,.25) }
.sh-lb-prev { left:16px }
.sh-lb-next { right:16px }
</style>
