<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    gi:          Object,
    locationMap: Object,   // { item_variant_id: [{ location_code, location_name, qty_on_hand, available }] }
})

// ── Picking state per item (in_picking) ───────────────────────────────────
const actualQtys   = ref({})   // { item.id: Number }
const itemStatuses = ref({})   // { item.id: 'ready' | 'rejected' }
const itemNotes    = ref({})   // { item.id: '' }

onMounted(() => {
    ;(props.gi?.items ?? []).forEach(item => {
        actualQtys.value[item.id]   = Number(item.qty_in_base_uom ?? item.requested_qty ?? 0)
        itemStatuses.value[item.id] = item.status === 'rejected' ? 'rejected' : 'ready'
        itemNotes.value[item.id]    = item.notes ?? ''
    })
})

function setStatus(itemId, status) {
    itemStatuses.value[itemId] = status
    if (status === 'rejected') {
        actualQtys.value[itemId] = 0
    } else {
        // restore to requested qty if currently 0
        if (parseFloat(actualQtys.value[itemId] ?? 0) === 0) {
            const item = (props.gi?.items ?? []).find(i => i.id === itemId)
            actualQtys.value[itemId] = Number(item?.qty_in_base_uom ?? item?.requested_qty ?? 0)
        }
    }
}

const rejectedCount = computed(() =>
    (props.gi?.items ?? []).filter(i => itemStatuses.value[i.id] === 'rejected').length
)

// ── Photo upload (ready_to_pickup state) ─────────────────────────────────
const photoFiles    = ref([])
const photoPreviews = ref([])
const lightboxSrc   = ref(null)

function onPhotoChange(e) {
    const toAdd = Array.from(e.target.files).slice(0, 5 - photoFiles.value.length)
    toAdd.forEach(file => {
        photoFiles.value.push(file)
        const reader = new FileReader()
        reader.onload = ev => photoPreviews.value.push(ev.target.result)
        reader.readAsDataURL(file)
    })
    e.target.value = ''
}
function removePhoto(i) {
    photoFiles.value.splice(i, 1)
    photoPreviews.value.splice(i, 1)
}

// ── Forms ─────────────────────────────────────────────────────────────────
const startForm   = useForm({})
const submitForm  = useForm({})
const confirmForm = useForm({ photos: [] })
const confirmProcessing = ref(false)

const canSubmit = computed(() =>
    !submitForm.processing &&
    (props.gi?.items ?? []).length > 0 &&
    (props.gi?.items ?? []).every(item => {
        if (itemStatuses.value[item.id] === 'rejected') {
            // Rejected → notes wajib diisi
            return (itemNotes.value[item.id] ?? '').trim().length > 0
        }
        return parseFloat(actualQtys.value[item.id] ?? 0) > 0
    })
)

function doStartPicking() {
    startForm.post(route('operator.start-picking', props.gi.id))
}

function doSubmitPickup() {
    const items = (props.gi?.items ?? []).map(item => ({
        id:         item.id,
        actual_qty: itemStatuses.value[item.id] === 'rejected' ? 0 : parseFloat(actualQtys.value[item.id] ?? 0),
        status:     itemStatuses.value[item.id] ?? 'ready',
        notes:      itemNotes.value[item.id] ?? '',
    }))
    submitForm
        .transform(() => ({ items }))
        .post(route('operator.submit-pickup', props.gi.id), { preserveScroll: true })
}

// Compress image to JPEG max 1280px, quality 0.82 — prevents upload hang on large phone photos
function compressPhoto(file) {
    return new Promise(resolve => {
        const img = new Image()
        const url = URL.createObjectURL(file)
        img.onload = () => {
            URL.revokeObjectURL(url)
            const MAX = 1280
            const scale = Math.min(1, MAX / Math.max(img.width, img.height))
            const canvas = document.createElement('canvas')
            canvas.width  = Math.round(img.width  * scale)
            canvas.height = Math.round(img.height * scale)
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height)
            canvas.toBlob(
                blob => resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' })),
                'image/jpeg', 0.82
            )
        }
        img.onerror = () => { URL.revokeObjectURL(url); resolve(file) } // fallback: use original
        img.src = url
    })
}

async function doConfirmPickup() {
    if (confirmProcessing.value || photoFiles.value.length === 0) return
    confirmProcessing.value = true
    try {
        const fd = new FormData()
        // Compress each photo before upload
        for (const f of photoFiles.value) {
            const compressed = await compressPhoto(f)
            fd.append('photos[]', compressed)
        }
        router.post(route('operator.confirm-pickup', props.gi.id), fd, {
            forceFormData: true,
            onFinish: () => { confirmProcessing.value = false },
            onError:  () => { confirmProcessing.value = false },
        })
    } catch (_) {
        confirmProcessing.value = false
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────
function itemName(variant) {
    const item = variant?.item ?? variant
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? item.name_id ?? '—'
}
function locationsFor(item) {
    return (props.locationMap?.[item.item_variant_id] ?? [])
}
function warehouseName(item) {
    return item.itemWarehouse?.name ?? props.gi?.warehouse?.name ?? '—'
}
function fmtQty(val) {
    const n = parseFloat(val)
    if (isNaN(n)) return '0'
    return Number.isInteger(n) ? n : n.toFixed(2)
}
</script>

<template>
<OperatorLayout>
  <template #title>{{ gi?.gi_number }} · {{ gi?.department?.name }}</template>

  <div class="pk-wrap">

    <!-- ── Top bar ───────────────────────────────────────────────── -->
    <div class="pk-topbar">
      <div class="pk-topbar-left">
        <Link :href="route('operator.scan-list')" class="pk-back-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
            <path d="m15 18-6-6 6-6"/>
          </svg>
        </Link>
        <div>
          <div class="pk-gi-number">{{ gi?.gi_number }}</div>
          <div class="pk-gi-dept">{{ gi?.department?.name }} · {{ gi?.warehouse?.code }}</div>
        </div>
      </div>
      <!-- Status badge -->
      <span class="pk-status-badge" :class="`pk-status-${gi?.status}`">
        {{ { assigned: t('operator.statusAssigned'), in_picking: t('operator.statusPicking'), ready_to_pickup: t('operator.statusReady'), completed: t('operator.statusDone'), rejected: t('operator.statusRejected') }[gi?.status] ?? gi?.status }}
      </span>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 1: ASSIGNED — belum mulai picking                     -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-if="gi?.status === 'assigned'" class="pk-state-card pk-state-assigned">
      <div class="pk-state-title">{{ $t('operator.assignedTitle') }}</div>
      <div class="pk-state-desc">{{ $t('operator.assignedDesc') }}</div>

      <!-- Item preview -->
      <div class="pk-preview-items">
        <div v-for="item in (gi?.items ?? [])" :key="item.id" class="pk-preview-row">
          <span class="pk-preview-name">{{ itemName(item.variant) }}</span>
          <span class="pk-preview-qty">{{ fmtQty(item.requested_qty) }} {{ item.requested_uom }}</span>
        </div>
      </div>

      <button
        type="button"
        class="pk-action-btn pk-btn-start"
        :disabled="startForm.processing"
        @click="doStartPicking"
      >
        <svg v-if="startForm.processing" class="pk-spin" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
          stroke-linejoin="round" width="18" height="18">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        {{ startForm.processing ? $t('operator.startingBtn') : $t('operator.startBtn') }}
      </button>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 2: IN_PICKING — Issue Qty + Status + Notes           -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <template v-else-if="gi?.status === 'in_picking'">

      <!-- Summary bar -->
      <div class="pk-pick-summary">
        <span class="pk-pick-total">{{ gi?.items?.length ?? 0 }} item</span>
        <span v-if="rejectedCount > 0" class="pk-pick-rejected-count">
          {{ rejectedCount }} rejected
        </span>
      </div>

      <div class="pk-section-title">{{ $t('operator.prepareTitle') }}</div>

      <div class="pk-items">
        <div
          v-for="item in (gi?.items ?? [])"
          :key="item.id"
          class="pk-item"
          :class="{
            'pk-item-ready-state':    itemStatuses[item.id] !== 'rejected',
            'pk-item-rejected-state': itemStatuses[item.id] === 'rejected',
          }"
        >
          <div class="pk-item-body">

            <!-- Row 1: Item name + Req badge -->
            <div class="pk-item-header">
              <span class="pk-item-name">{{ itemName(item.variant) }}</span>
              <span class="pk-item-req-qty">
                Req {{ fmtQty(item.requested_qty) }} {{ item.requested_uom }}
              </span>
            </div>

            <!-- Row 2: SKU -->
            <div class="pk-item-meta">
              <span class="pk-sku">{{ item.variant?.sku }}</span>
            </div>

            <!-- Row 3: Location (text only, no emoji icons) -->
            <div v-if="locationsFor(item).length" class="pk-loc-list">
              <div v-for="(loc, li) in locationsFor(item)" :key="li" class="pk-loc-row-text">
                <span class="pk-loc-wh-label">{{ loc.warehouse_code ?? '—' }}</span>
                <span class="pk-loc-sep">›</span>
                <span class="pk-loc-code-label">{{ loc.location_code ?? '—' }}</span>
                <span class="pk-loc-avail-label">Avail: {{ fmtQty(loc.available) }}</span>
              </div>
            </div>
            <div v-else class="pk-no-loc-text">{{ $t('operator.noLocation') }}</div>

            <!-- Row 4: Issue Qty + Status toggle -->
            <div class="pk-issue-row">

              <!-- Issue Qty input -->
              <div class="pk-issue-qty-wrap">
                <span class="pk-issue-lbl">Issue Qty</span>
                <input
                  type="number"
                  class="pk-qty-input"
                  v-model="actualQtys[item.id]"
                  :disabled="itemStatuses[item.id] === 'rejected'"
                  min="0"
                  step="any"
                  inputmode="numeric"
                />
                <span class="pk-qty-uom">{{ item.variant?.item?.base_uom ?? item.requested_uom }}</span>
              </div>

              <!-- Status toggle: Ready / Rejected -->
              <div class="pk-status-toggle">
                <button
                  type="button"
                  class="pk-st-btn pk-st-ready"
                  :class="{ 'pk-st-active-ready': itemStatuses[item.id] === 'ready' }"
                  @click="setStatus(item.id, 'ready')"
                >Ready</button>
                <button
                  type="button"
                  class="pk-st-btn pk-st-reject"
                  :class="{ 'pk-st-active-reject': itemStatuses[item.id] === 'rejected' }"
                  @click="setStatus(item.id, 'rejected')"
                >Reject</button>
              </div>
            </div>

            <!-- Qty warning -->
            <div v-if="itemStatuses[item.id] === 'ready' && parseFloat(actualQtys[item.id] ?? 0) < parseFloat(item.qty_in_base_uom ?? 0)"
                 class="pk-qty-warn">
              {{ $t('operator.belowRequested', { n: fmtQty(item.qty_in_base_uom) }) }}
            </div>

            <!-- Row 5: Notes -->
            <div class="pk-notes-label-row">
              <span class="pk-notes-lbl">Notes</span>
              <span v-if="itemStatuses[item.id] === 'rejected'" class="pk-notes-required-badge">
                * Wajib jika ditolak
              </span>
            </div>
            <textarea
              class="pk-notes-ta"
              :class="{ 'pk-notes-err': itemStatuses[item.id] === 'rejected' && !(itemNotes[item.id] ?? '').trim() }"
              v-model="itemNotes[item.id]"
              rows="1"
              :placeholder="itemStatuses[item.id] === 'rejected' ? 'Alasan penolakan (wajib)…' : 'Notes (opsional)…'"
            />

          </div>
        </div>
      </div>

      <!-- Submit area -->
      <div class="pk-submit-area">
        <button
          type="button"
          class="pk-action-btn pk-btn-submit"
          :disabled="!canSubmit"
          @click="doSubmitPickup"
        >
          <svg v-if="submitForm.processing" class="pk-spin" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" width="16" height="16">
            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
          {{ submitForm.processing ? $t('operator.submittingBtn') : $t('operator.donePickingBtn') }}
        </button>
      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 3: READY_TO_PICKUP — item summary + foto + konfirmasi -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-else-if="gi?.status === 'ready_to_pickup'" class="pk-state-card pk-state-ready">
      <div class="pk-state-title">{{ $t('operator.readyTitle') }}</div>
      <div class="pk-state-desc">{{ $t('operator.readyDesc') }}</div>

      <!-- Item summary read-only -->
      <div class="pk-ready-items">
        <div v-for="item in (gi?.items ?? [])" :key="item.id" class="pk-ready-row">
          <span class="pk-ready-name">{{ itemName(item.variant) }}</span>
          <span class="pk-ready-qty">
            {{ fmtQty(item.actual_qty ?? item.requested_qty) }} {{ item.requested_uom }}
          </span>
        </div>
      </div>

      <!-- Photo upload — wajib -->
      <div class="pk-photo-section">
        <div class="pk-photo-title">
          {{ $t('operator.photoTitle') }} <span class="pk-req">*</span>
        </div>

        <div class="pk-photo-btns">
          <label class="pk-photo-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
            {{ $t('operator.cameraBtn') }}
            <input type="file" class="pk-hidden-input" accept="image/*" capture="environment"
              @change="onPhotoChange" />
          </label>
          <label class="pk-photo-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
            {{ $t('operator.galleryBtn') }}
            <input type="file" class="pk-hidden-input" accept="image/*" multiple
              @change="onPhotoChange" />
          </label>
        </div>

        <div v-if="photoPreviews.length" class="pk-thumbs">
          <div v-for="(src, i) in photoPreviews" :key="i" class="pk-thumb">
            <img :src="src" @click="lightboxSrc = src" />
            <button type="button" class="pk-thumb-del" @click.stop="removePhoto(i)">×</button>
          </div>
        </div>

        <div v-if="photoFiles.length === 0" class="pk-warn">
          {{ $t('operator.photoRequiredWarn') }}
        </div>
      </div>

      <button
        type="button"
        class="pk-action-btn pk-btn-confirm"
        :disabled="confirmProcessing || photoFiles.length === 0"
        @click="doConfirmPickup"
      >
        <svg v-if="confirmProcessing" class="pk-spin" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
          stroke-linejoin="round" width="18" height="18">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        {{ confirmProcessing ? $t('operator.processingBtn') : $t('operator.confirmBtn') }}
      </button>

      <p class="pk-confirm-hint">{{ $t('operator.confirmHint') }}</p>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 4: COMPLETED / other                                  -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-else class="pk-state-card pk-state-done">
      <div class="pk-state-title">{{ $t('operator.completedTitle') }}</div>
      <div class="pk-state-desc">{{ $t('operator.completedDesc') }}</div>
      <Link :href="route('operator.scan-list')" class="pk-action-btn pk-btn-back-list">
        {{ $t('operator.backToList') }}
      </Link>
    </div>

  </div><!-- /pk-wrap -->

  <!-- Lightbox -->
  <Teleport to="body">
    <div v-if="lightboxSrc" class="lb-overlay" @click.self="lightboxSrc = null">
      <img :src="lightboxSrc" class="lb-img" />
      <button class="lb-close" @click="lightboxSrc = null">×</button>
    </div>
  </Teleport>

</OperatorLayout>
</template>

<style scoped>
/* ── wrapper ─────────────────────────────────────────────────────────────── */
.pk-wrap {
  display: flex;
  flex-direction: column;
  gap: 0;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  padding: 0 0 40px;
}

/* ── top bar ─────────────────────────────────────────────────────────────── */
.pk-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 10;
}
.pk-topbar-left { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
.pk-back-btn {
  width: 34px; height: 34px;
  border-radius: 8px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--fg);
  flex-shrink: 0;
  text-decoration: none;
}
.pk-back-btn:hover { background: var(--hover); }
.pk-gi-number { font-size: 14px; font-weight: 800; color: var(--fg); font-family: 'JetBrains Mono', monospace; }
.pk-gi-dept   { font-size: 11px; color: var(--fg-2); margin-top: 1px; }
.pk-status-badge {
  font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 999px;
  text-transform: uppercase; letter-spacing: .04em; flex-shrink: 0;
}
.pk-status-assigned      { background:rgba(249,115,22,.14); color:var(--orange-500); }
.pk-status-in_picking    { background:rgba(234,179,8,.14);  color:#a16207; }
.pk-status-ready_to_pickup{ background:rgba(34,197,94,.14); color:#16a34a; }
.pk-status-completed     { background:rgba(16,185,129,.14); color:#10b981; }

/* ── STATE CARDS ─────────────────────────────────────────────────────────── */
.pk-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 28px 18px;
  margin: 14px 12px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--surface);
  text-align: center;
}
.pk-state-icon  { font-size: 44px; line-height: 1; }
.pk-state-title { font-size: 19px; font-weight: 800; color: var(--fg); }
.pk-state-desc  { font-size: 13.5px; color: var(--fg-2); line-height: 1.6; max-width: 340px; }

.pk-state-assigned { border-color: rgba(249,115,22,.4); background: rgba(249,115,22,.03); }
.pk-state-ready    { border-color: rgba(34,197,94,.4);  background: rgba(34,197,94,.03); }
.pk-state-done     { border-color: var(--border); }

/* item preview in assigned state */
.pk-preview-items {
  width: 100%;
  display: flex; flex-direction: column; gap: 6px;
  background: var(--surface-2); border-radius: 10px;
  padding: 10px 12px;
  max-height: 200px; overflow-y: auto;
}
.pk-preview-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; }
.pk-preview-name { color: var(--fg); font-weight: 500; text-align: left; }
.pk-preview-qty  { color: var(--orange-500); font-weight: 700; white-space: nowrap; }

/* ── ACTION BUTTONS ──────────────────────────────────────────────────────── */
.pk-action-btn {
  width: 100%; max-width: 320px;
  appearance: none; border: none;
  font-size: 15px; font-weight: 700;
  padding: 14px 16px; border-radius: 12px;
  cursor: pointer; font-family: inherit;
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  transition: opacity 150ms; text-decoration: none;
}
.pk-action-btn:hover:not(:disabled) { opacity: 0.9; }
.pk-action-btn:disabled { opacity: 0.45; cursor: default; }
.pk-btn-start    { background: var(--orange-500); color: #fff; }
.pk-btn-submit   { background: var(--orange-500); color: #fff; width: 100%; max-width: 100%; }
.pk-btn-confirm  { background: #22c55e; color: #fff; }
.pk-btn-back-list{ background: var(--surface-2); border: 1px solid var(--border); color: var(--fg); }
.pk-confirm-hint { font-size: 11px; color: var(--fg-dim); margin: -4px 0 0; }

/* ── PICK SUMMARY BAR ────────────────────────────────────────────────────── */
.pk-pick-summary {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 16px;
  background: var(--surface); border-bottom: 1px solid var(--border);
  font-size: 12px; font-weight: 600; color: var(--fg-2);
}
.pk-pick-rejected-count {
  background: rgba(239,68,68,.12); color: #ef4444;
  padding: 1px 8px; border-radius: 20px; font-size: 11px; font-weight: 700;
}

/* ── SECTION TITLE ───────────────────────────────────────────────────────── */
.pk-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .1em; color: var(--fg-dim);
  padding: 14px 16px 6px;
}
.pk-req { color: #f87171; }

/* ── ITEMS ───────────────────────────────────────────────────────────────── */
.pk-items { display: flex; flex-direction: column; gap: 0; padding: 0 12px; margin-bottom: 8px; }
.pk-item {
  padding: 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 10px;
  transition: border-color 150ms, background 150ms;
}
.pk-item-ready-state    { border-color: var(--border); }
.pk-item-rejected-state { border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.02); opacity: 0.85; }
.pk-item-body   { display: flex; flex-direction: column; gap: 8px; }
.pk-item-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.pk-item-name   { font-size: 14px; font-weight: 700; color: var(--fg); line-height: 1.3; flex: 1; }
.pk-item-req-qty{
  font-size: 11.5px; font-weight: 700;
  background: rgba(249,115,22,.1); color: var(--orange-500);
  padding: 2px 8px; border-radius: 6px; white-space: nowrap; flex-shrink: 0;
}
.pk-item-meta  { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pk-sku        { font-size: 11px; color: var(--fg-2); font-family: 'JetBrains Mono', monospace; }

/* ── LOCATION (text-only, no icons) ─────────────────────────────────────── */
.pk-loc-list { display: flex; flex-direction: column; gap: 3px; }
.pk-loc-row-text {
  display: flex; align-items: center; gap: 5px;
  font-size: 11.5px; color: var(--fg-2);
  background: var(--surface-2); border-radius: 6px;
  padding: 4px 8px;
}
.pk-loc-wh-label   { font-weight: 700; color: #3b82f6; }
.pk-loc-sep        { color: var(--fg-dim); }
.pk-loc-code-label { font-weight: 700; color: var(--fg); font-family: 'JetBrains Mono', monospace; }
.pk-loc-avail-label{ margin-left: auto; color: #22c55e; font-weight: 600; }
.pk-no-loc-text { font-size: 11.5px; color: var(--fg-2); }

/* ── ISSUE ROW (qty + status toggle) ────────────────────────────────────── */
.pk-issue-row {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.pk-issue-qty-wrap {
  display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0;
}
.pk-issue-lbl  { font-size: 12px; font-weight: 600; color: var(--fg-2); white-space: nowrap; }
.pk-qty-input {
  width: 72px;
  padding: 7px 8px;
  border: 1.5px solid var(--border-2);
  border-radius: 8px;
  background: var(--surface-2);
  color: var(--fg); font-size: 15px; font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  outline: none; text-align: center;
  transition: border-color 150ms;
}
.pk-qty-input:focus { border-color: var(--orange-500); }
.pk-qty-input:disabled { opacity: 0.4; cursor: not-allowed; }
.pk-qty-uom { font-size: 12px; color: var(--fg-2); font-weight: 600; white-space: nowrap; }
.pk-qty-warn { font-size: 11px; color: #f59e0b; font-weight: 600; }

/* ── STATUS TOGGLE ───────────────────────────────────────────────────────── */
.pk-status-toggle {
  display: flex; border-radius: 8px; overflow: hidden;
  border: 1px solid var(--border); flex-shrink: 0;
}
.pk-st-btn {
  appearance: none; border: 0; background: var(--surface-2);
  color: var(--fg-2); font-size: 12px; font-weight: 700;
  padding: 6px 13px; cursor: pointer; font-family: inherit;
  transition: background 150ms, color 150ms;
}
.pk-st-btn + .pk-st-btn { border-left: 1px solid var(--border); }
.pk-st-active-ready  { background: rgba(34,197,94,.15) !important; color: #22c55e !important; }
.pk-st-active-reject { background: rgba(239,68,68,.12) !important; color: #ef4444 !important; }

/* ── NOTES ───────────────────────────────────────────────────────────────── */
.pk-notes-label-row {
  display: flex; align-items: center; gap: 6px;
}
.pk-notes-lbl { font-size: 11.5px; font-weight: 600; color: var(--fg-2); }
.pk-notes-required-badge {
  font-size: 10.5px; font-weight: 700;
  color: #ef4444;
  background: rgba(239,68,68,.1);
  padding: 1px 7px; border-radius: 20px;
}
.pk-notes-ta {
  width: 100%; resize: none; overflow: hidden;
  border: 1.5px solid var(--border);
  border-radius: 8px; background: var(--surface-2);
  color: var(--fg); font-size: 12px; font-family: inherit;
  padding: 6px 10px; outline: none;
  transition: border-color 150ms;
  field-sizing: content;
  min-height: 34px;
}
.pk-notes-ta:focus    { border-color: var(--orange-400); }
.pk-notes-ta.pk-notes-err { border-color: #ef4444; background: rgba(239,68,68,.04); }

/* ── SUBMIT AREA ─────────────────────────────────────────────────────────── */
.pk-submit-area {
  padding: 12px 12px 0;
  display: flex; flex-direction: column; gap: 8px;
}

/* ── READY STATE ─────────────────────────────────────────────────────────── */
.pk-ready-items {
  width: 100%;
  display: flex; flex-direction: column; gap: 8px;
  background: var(--surface-2);
  border-radius: 10px; padding: 12px 14px;
  text-align: left;
}
.pk-ready-row {
  display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px;
}
.pk-ready-name { color: var(--fg); font-weight: 500; flex: 1; }
.pk-ready-qty  { color: #22c55e; font-weight: 700; white-space: nowrap; }

/* Photo upload in ready state */
.pk-photo-section { width: 100%; display: flex; flex-direction: column; gap: 10px; text-align: left; }
.pk-photo-title   { font-size: 12px; font-weight: 700; color: var(--fg-2); }
.pk-photo-btns    { display: flex; gap: 8px; flex-wrap: wrap; }
.pk-photo-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 10px; padding: 9px 16px;
  font-size: 13px; font-weight: 600; color: var(--fg);
  cursor: pointer;
}
.pk-hidden-input { display: none; }
.pk-thumbs { display: flex; flex-wrap: wrap; gap: 8px; }
.pk-thumb { position: relative; width: 70px; height: 70px; }
.pk-thumb img {
  width: 70px; height: 70px;
  object-fit: cover; border-radius: 8px;
  border: 1px solid var(--border); cursor: pointer;
}
.pk-thumb-del {
  position: absolute; top: -5px; right: -5px;
  width: 20px; height: 20px; border-radius: 50%;
  background: #ef4444; color: #fff; border: 0;
  font-size: 14px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}
.pk-warn { font-size: 12px; color: #f59e0b; font-weight: 600; padding: 4px 0; }

/* ── LIGHTBOX ────────────────────────────────────────────────────────────── */
.lb-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,.85);
  display: flex; align-items: center; justify-content: center;
}
.lb-img { max-width: 92vw; max-height: 86vh; border-radius: 12px; object-fit: contain; }
.lb-close {
  position: fixed; top: 18px; right: 18px;
  width: 38px; height: 38px; border-radius: 50%;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
  color: #fff; font-size: 22px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}

/* ── SPIN ────────────────────────────────────────────────────────────────── */
.pk-spin {
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg) } }
</style>
