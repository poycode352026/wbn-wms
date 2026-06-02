<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'
import QRCode from 'qrcode'

const { t, locale } = useI18n()

const props = defineProps({
    gi:          Object,
    locationMap: Object,   // { item_variant_id: [{ location_code, location_name, qty_on_hand, available }] }
})

// ── QR code ───────────────────────────────────────────────────────────────
const qrDataUrl = ref('')
onMounted(async () => {
    if (props.gi?.gi_number) {
        try {
            qrDataUrl.value = await QRCode.toDataURL(props.gi.gi_number, {
                width: 200, margin: 1,
            })
        } catch (_) {}
    }
})

// ── Picking checklist (only in in_picking state) ──────────────────────────
const checkedItems = ref({})
const allChecked = computed(() =>
    (props.gi?.items ?? []).length > 0 &&
    (props.gi?.items ?? []).every(item => checkedItems.value[item.id])
)
function toggleCheck(itemId) {
    checkedItems.value[itemId] = !checkedItems.value[itemId]
}
const progress = computed(() => {
    const total = (props.gi?.items ?? []).length
    if (!total) return 0
    const done = Object.values(checkedItems.value).filter(Boolean).length
    return Math.round((done / total) * 100)
})

// ── Photo upload ──────────────────────────────────────────────────────────
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
const confirmForm = useForm({})

const canSubmit = computed(() =>
    !submitForm.processing && allChecked.value && photoFiles.value.length > 0
)

function doStartPicking() {
    startForm.post(route('operator.start-picking', props.gi.id))
}

function doSubmitPickup() {
    submitForm
        .transform(() => ({ photos: photoFiles.value }))
        .post(route('operator.submit-pickup', props.gi.id), {
            forceFormData: true,
            preserveScroll: true,
        })
}

function doConfirmPickup() {
    confirmForm.post(route('operator.confirm-pickup', props.gi.id))
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
function fmtQty(val) {
    const n = parseFloat(val)
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
          <div class="pk-gi-dept">{{ gi?.department?.name }}</div>
        </div>
      </div>
      <img v-if="qrDataUrl" :src="qrDataUrl" class="pk-qr-mini" alt="QR" />
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 1: ASSIGNED — belum mulai picking                     -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-if="gi?.status === 'assigned'" class="pk-state-card pk-state-assigned">
      <div class="pk-state-icon">📋</div>
      <div class="pk-state-title">Siap Dikerjakan</div>
      <div class="pk-state-desc">
        GI ini di-assign ke Anda. Tekan tombol di bawah untuk mulai menyiapkan barang.
      </div>

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
        <span v-else>⚡</span>
        {{ startForm.processing ? 'Memulai…' : 'Mulai Picking' }}
      </button>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 2: IN_PICKING — checklist + foto + submit             -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <template v-else-if="gi?.status === 'in_picking'">

      <!-- Progress bar -->
      <div class="pk-progress-wrap">
        <div class="pk-progress-bar">
          <div class="pk-progress-fill" :style="{ width: progress + '%' }"></div>
        </div>
        <span class="pk-progress-label">
          {{ Object.values(checkedItems).filter(Boolean).length }} / {{ gi?.items?.length ?? 0 }} diambil
        </span>
      </div>

      <!-- Item checklist -->
      <div class="pk-section-title">📋 Daftar Barang</div>

      <div class="pk-items">
        <div
          v-for="item in (gi?.items ?? [])"
          :key="item.id"
          class="pk-item"
          :class="{ 'pk-item-checked': checkedItems[item.id] }"
        >
          <button type="button" class="pk-check-btn" @click="toggleCheck(item.id)">
            <svg v-if="checkedItems[item.id]" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
              stroke-linejoin="round" width="20" height="20">
              <path d="M20 6 9 17l-5-5"/>
            </svg>
            <span v-else class="pk-check-empty"></span>
          </button>

          <div class="pk-item-body">
            <div class="pk-item-header">
              <span class="pk-item-name" :class="{ 'pk-name-done': checkedItems[item.id] }">
                {{ itemName(item.variant) }}
              </span>
              <span class="pk-item-qty">
                {{ fmtQty(item.requested_qty) }} {{ item.requested_uom }}
              </span>
            </div>

            <div class="pk-item-meta">
              <span class="pk-sku">{{ item.variant?.sku }}</span>
              <span v-if="item.itemWarehouse" class="pk-wh-chip">
                🏭 {{ item.itemWarehouse.code }}
              </span>
            </div>

            <!-- Location cards -->
            <div v-if="locationsFor(item).length" class="pk-locations">
              <div v-for="(loc, li) in locationsFor(item)" :key="li" class="pk-loc-card">
                <div class="pk-loc-rack">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                  </svg>
                  {{ loc.location_code }}
                </div>
                <div class="pk-loc-name">{{ loc.location_name }}</div>
                <div class="pk-loc-avail">Tersedia: {{ fmtQty(loc.available) }}</div>
              </div>
            </div>
            <div v-else class="pk-no-loc">
              ⚠ Lokasi tidak ditemukan — cek ke staf gudang
            </div>

            <div v-if="item.store_to" class="pk-store-to">
              → Diserahkan ke: <strong>{{ item.store_to }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Photo upload -->
      <div class="pk-section-title">
        📷 Foto Bukti Picking <span class="pk-req">*</span>
      </div>

      <div class="pk-photo-card">
        <p class="pk-photo-hint">
          Foto barang yang sudah disiapkan. Minimal 1 foto wajib diupload.
        </p>

        <!-- Two buttons: camera + gallery -->
        <div class="pk-photo-btns">
          <label class="pk-photo-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
            Kamera
            <input type="file" class="pk-hidden-input" accept="image/*" capture="environment"
              @change="onPhotoChange" />
          </label>
          <label class="pk-photo-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
            Galeri
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
      </div>

      <!-- Submit area -->
      <div class="pk-submit-area">
        <div v-if="!allChecked && (gi?.items?.length ?? 0) > 0" class="pk-warn">
          ⚠ Centang semua item terlebih dahulu
        </div>
        <div v-if="photoFiles.length === 0" class="pk-warn">
          📷 Upload minimal 1 foto bukti
        </div>

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
          {{ submitForm.processing ? 'Mengirim…' : 'Selesai Picking' }}
        </button>
      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 3: READY_TO_PICKUP — konfirmasi penyerahan           -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-else-if="gi?.status === 'ready_to_pickup'" class="pk-state-card pk-state-ready">
      <div class="pk-state-icon">✅</div>
      <div class="pk-state-title">Barang Siap di Staging Area</div>
      <div class="pk-state-desc">
        Minta requester tunjukkan barcode GI mereka. Scan barcode atau tekan
        <strong>Konfirmasi Serahkan</strong> saat barang diserahkan.
      </div>

      <!-- QR Code besar untuk dilihat operator -->
      <div class="pk-qr-big-wrap">
        <img v-if="qrDataUrl" :src="qrDataUrl" class="pk-qr-big" alt="QR Code" />
        <div class="pk-qr-label">{{ gi?.gi_number }}</div>
      </div>

      <button
        type="button"
        class="pk-action-btn pk-btn-confirm"
        :disabled="confirmForm.processing"
        @click="doConfirmPickup"
      >
        <svg v-if="confirmForm.processing" class="pk-spin" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
          stroke-linejoin="round" width="18" height="18">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        <span v-else>🤝</span>
        {{ confirmForm.processing ? 'Memproses…' : 'Konfirmasi Serahkan' }}
      </button>

      <p class="pk-confirm-hint">
        Stok otomatis dikurangi setelah konfirmasi.
      </p>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- STATE 4: COMPLETED / other                                  -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div v-else class="pk-state-card pk-state-done">
      <div class="pk-state-icon">🎉</div>
      <div class="pk-state-title">GI Selesai</div>
      <div class="pk-state-desc">Barang telah diserahkan ke requester.</div>
      <Link :href="route('operator.scan-list')" class="pk-action-btn pk-btn-back-list">
        ← Kembali ke Daftar
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
  padding: 14px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 10;
}
.pk-topbar-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pk-back-btn {
  width: 36px; height: 36px;
  border-radius: 8px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--fg);
  flex-shrink: 0;
}
.pk-back-btn:hover { background: var(--hover); }
.pk-gi-number { font-size: 15px; font-weight: 800; color: var(--fg); font-family: 'JetBrains Mono', monospace; }
.pk-gi-dept   { font-size: 11px; color: var(--fg-2); margin-top: 1px; }
.pk-qr-mini {
  width: 52px !important; height: 52px !important;
  flex-shrink: 0;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: #fff;
}

/* ── STATE CARDS ─────────────────────────────────────────────────────────── */
.pk-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 32px 20px;
  margin: 16px 12px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--surface);
  text-align: center;
}

.pk-state-icon  { font-size: 48px; line-height: 1; }
.pk-state-title { font-size: 20px; font-weight: 800; color: var(--fg); }
.pk-state-desc  { font-size: 14px; color: var(--fg-2); line-height: 1.6; max-width: 340px; }

/* assigned: orange border */
.pk-state-assigned { border-color: rgba(249,115,22,0.4); background: rgba(249,115,22,0.03); }
/* ready: green border */
.pk-state-ready    { border-color: rgba(34,197,94,0.4);  background: rgba(34,197,94,0.03); }
/* done: gray */
.pk-state-done     { border-color: var(--border); }

/* item preview in assigned state */
.pk-preview-items {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--surface-2);
  border-radius: 10px;
  padding: 10px 12px;
  max-height: 200px;
  overflow-y: auto;
}
.pk-preview-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
}
.pk-preview-name { color: var(--fg); font-weight: 500; text-align: left; }
.pk-preview-qty  { color: var(--orange-500); font-weight: 700; white-space: nowrap; }

/* big QR for ready state */
.pk-qr-big-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.pk-qr-big {
  width: 160px !important; height: 160px !important;
  border-radius: 10px;
  border: 2px solid rgba(34,197,94,0.4);
  background: #fff;
  padding: 4px;
}
.pk-qr-label { font-size: 12px; font-weight: 700; color: var(--fg-2); font-family: 'JetBrains Mono', monospace; }

/* ── ACTION BUTTONS ──────────────────────────────────────────────────────── */
.pk-action-btn {
  width: 100%;
  max-width: 320px;
  appearance: none;
  border: none;
  font-size: 15px;
  font-weight: 700;
  padding: 14px 16px;
  border-radius: 12px;
  cursor: pointer;
  font-family: inherit;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity 150ms;
  text-decoration: none;
}
.pk-action-btn:hover:not(:disabled) { opacity: 0.9; }
.pk-action-btn:disabled { opacity: 0.45; cursor: default; }

.pk-btn-start   { background: var(--orange-500); color: #fff; }
.pk-btn-submit  { background: var(--orange-500); color: #fff; width: 100%; max-width: 100%; }
.pk-btn-confirm { background: #22c55e; color: #fff; }
.pk-btn-back-list { background: var(--surface-2); border: 1px solid var(--border); color: var(--fg); }

.pk-confirm-hint { font-size: 11px; color: var(--fg-dim); margin: -4px 0 0; }

/* ── PROGRESS ────────────────────────────────────────────────────────────── */
.pk-progress-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.pk-progress-bar {
  flex: 1;
  height: 6px;
  background: var(--surface-2);
  border-radius: 99px;
  overflow: hidden;
}
.pk-progress-fill {
  height: 100%;
  background: #22c55e;
  border-radius: 99px;
  transition: width 300ms ease;
}
.pk-progress-label { font-size: 11px; font-weight: 700; color: var(--fg-2); white-space: nowrap; }

/* ── SECTION TITLE ───────────────────────────────────────────────────────── */
.pk-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--fg-dim);
  padding: 14px 16px 6px;
}
.pk-req { color: #f87171; }

/* ── ITEMS ───────────────────────────────────────────────────────────────── */
.pk-items { display: flex; flex-direction: column; gap: 0; padding: 0 12px; margin-bottom: 8px; }
.pk-item {
  display: flex;
  gap: 12px;
  padding: 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 10px;
  transition: border-color 150ms, background 150ms;
}
.pk-item-checked { border-color: rgba(34,197,94,.5); background: rgba(34,197,94,.04); }

.pk-check-btn {
  width: 28px; height: 28px;
  border-radius: 8px;
  border: 2px solid var(--orange-500);
  background: transparent;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  margin-top: 2px;
  transition: background 150ms;
  color: var(--orange-500);
}
.pk-item-checked .pk-check-btn { background: #22c55e; border-color: #22c55e; color: #fff; }
.pk-check-empty { width: 10px; height: 10px; border-radius: 2px; }

.pk-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.pk-item-header {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
}
.pk-item-name {
  font-size: 14px; font-weight: 700; color: var(--fg); line-height: 1.3; flex: 1;
  transition: color 150ms;
}
.pk-name-done { color: var(--fg-2); text-decoration: line-through; opacity: 0.7; }
.pk-item-qty {
  font-size: 13px; font-weight: 700;
  background: rgba(249,115,22,.12); color: var(--orange-500);
  padding: 2px 9px; border-radius: 6px; white-space: nowrap; flex-shrink: 0;
}
.pk-item-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pk-sku { font-size: 11px; color: var(--fg-2); font-family: 'JetBrains Mono', monospace; }
.pk-wh-chip {
  font-size: 10.5px; font-weight: 600;
  background: rgba(59,130,246,.1); color: #3b82f6;
  padding: 1px 7px; border-radius: 5px;
}

/* ── LOCATION CARDS ─────────────────────────────────────────────────────── */
.pk-locations { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.pk-loc-card {
  background: rgba(249,115,22,.08);
  border: 1px solid rgba(249,115,22,.3);
  border-radius: 8px;
  padding: 6px 10px;
  display: flex; flex-direction: column; gap: 1px;
  min-width: 100px;
}
.pk-loc-rack {
  font-size: 13px; font-weight: 800; color: var(--orange-500);
  display: flex; align-items: center; gap: 4px;
}
.pk-loc-name  { font-size: 11px; color: var(--fg-2); font-weight: 500; }
.pk-loc-avail { font-size: 10.5px; color: var(--fg-dim); font-weight: 600; }
.pk-no-loc {
  font-size: 12px; color: #f59e0b;
  background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3);
  border-radius: 6px; padding: 6px 10px;
}
.pk-store-to { font-size: 11.5px; color: var(--fg-2); }

/* ── PHOTOS ──────────────────────────────────────────────────────────────── */
.pk-photo-card { display: flex; flex-direction: column; gap: 10px; padding: 0 12px 4px; }
.pk-photo-hint { font-size: 12px; color: var(--fg-2); margin: 0; padding: 0 2px; }

.pk-photo-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.pk-photo-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 16px 12px;
  border: 2px dashed rgba(249,115,22,.4);
  border-radius: 12px;
  background: rgba(249,115,22,.03);
  cursor: pointer;
  transition: border-color 150ms, background 150ms;
  color: var(--orange-500);
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}
.pk-photo-btn:hover { border-color: var(--orange-500); background: rgba(249,115,22,.07); }

.pk-hidden-input { display: none; }

.pk-thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; }
.pk-thumb { position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: var(--surface-2); }
.pk-thumb img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
.pk-thumb-del {
  position: absolute; top: 3px; right: 3px;
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(239,68,68,.9); color: #fff; border: none;
  cursor: pointer; font-size: 16px; font-weight: bold;
  display: flex; align-items: center; justify-content: center; line-height: 1;
}

/* ── SUBMIT AREA ─────────────────────────────────────────────────────────── */
.pk-submit-area { padding: 16px 12px; display: flex; flex-direction: column; gap: 8px; }
.pk-warn {
  font-size: 12px; color: #f59e0b; font-weight: 600;
  padding: 6px 10px; background: rgba(245,158,11,.1); border-radius: 6px;
}

/* ── SPINNING ────────────────────────────────────────────────────────────── */
.pk-spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── LIGHTBOX ────────────────────────────────────────────────────────────── */
.lb-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.85);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; cursor: pointer;
}
.lb-img { max-width: 90vw; max-height: 90vh; border-radius: 8px; object-fit: contain; cursor: default; }
.lb-close {
  position: fixed; top: 16px; right: 16px;
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,.15); color: #fff;
  border: none; cursor: pointer; font-size: 22px;
  display: flex; align-items: center; justify-content: center;
}

/* ── MOBILE ──────────────────────────────────────────────────────────────── */
@media (max-width: 480px) {
  .pk-item-name  { font-size: 13px; }
  .pk-loc-rack   { font-size: 12px; }
  .pk-action-btn { font-size: 14px; padding: 13px 14px; }
}
</style>
