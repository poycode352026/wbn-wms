<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'
import QRCode from 'qrcode'

const { t, locale } = useI18n()

const props = defineProps({
    gi:          Object,
    locationMap: Object,  // { item_variant_id: [{ location_code, location_name, qty_on_hand, available }] }
})

// ── Picking checklist ─────────────────────────────────────────────────────
const checkedItems = ref({})      // { item.id: true/false }
const allChecked = computed(() =>
    (props.gi?.items ?? []).length > 0 &&
    (props.gi?.items ?? []).every(item => checkedItems.value[item.id])
)

function toggleCheck(itemId) {
    checkedItems.value[itemId] = !checkedItems.value[itemId]
}

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

// ── QR code ───────────────────────────────────────────────────────────────
const qrCanvas = ref(null)
onMounted(async () => {
    if (qrCanvas.value && props.gi?.gi_number) {
        try {
            await QRCode.toCanvas(qrCanvas.value, props.gi.gi_number, { width: 140 })
        } catch (e) { /* ignore */ }
    }
})

// ── Submit ────────────────────────────────────────────────────────────────
const form = useForm({})
const canSubmit = computed(() => !form.processing && photoFiles.value.length > 0)

function submit() {
    form
        .transform(() => ({ photos: photoFiles.value }))
        .post(route('operator.submit-pickup', props.gi.id), {
            forceFormData: true,
            preserveScroll: true,
        })
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
    return Number.isInteger(n) ? n : n
}

const progress = computed(() => {
    const total = (props.gi?.items ?? []).length
    if (!total) return 0
    const done = Object.values(checkedItems.value).filter(Boolean).length
    return Math.round((done / total) * 100)
})
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
      <canvas ref="qrCanvas" class="pk-qr-mini"></canvas>
    </div>

    <!-- ── Progress bar ──────────────────────────────────────────── -->
    <div class="pk-progress-wrap">
      <div class="pk-progress-bar">
        <div class="pk-progress-fill" :style="{ width: progress + '%' }"></div>
      </div>
      <span class="pk-progress-label">
        {{ Object.values(checkedItems).filter(Boolean).length }} / {{ gi?.items?.length ?? 0 }} diambil
      </span>
    </div>

    <!-- ── Picking list ───────────────────────────────────────────── -->
    <div class="pk-section-title">📋 Daftar Barang</div>

    <div class="pk-items">
      <div
        v-for="item in (gi?.items ?? [])"
        :key="item.id"
        class="pk-item"
        :class="{ 'pk-item-checked': checkedItems[item.id] }"
      >
        <!-- Checkbox tap area -->
        <button type="button" class="pk-check-btn" @click="toggleCheck(item.id)">
          <svg v-if="checkedItems[item.id]" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" width="22" height="22">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
          <span v-else class="pk-check-empty"></span>
        </button>

        <!-- Item info -->
        <div class="pk-item-body">
          <!-- Name + qty -->
          <div class="pk-item-header">
            <span class="pk-item-name" :class="{ 'pk-name-done': checkedItems[item.id] }">
              {{ itemName(item.variant) }}
            </span>
            <span class="pk-item-qty">
              {{ fmtQty(item.requested_qty) }} {{ item.requested_uom }}
            </span>
          </div>

          <!-- SKU + warehouse -->
          <div class="pk-item-meta">
            <span class="pk-sku">{{ item.variant?.sku }}</span>
            <span v-if="item.itemWarehouse" class="pk-wh-chip">
              🏭 {{ item.itemWarehouse.code }}
            </span>
          </div>

          <!-- ── LOCATION / RACK ── -->
          <div v-if="locationsFor(item).length" class="pk-locations">
            <div
              v-for="(loc, li) in locationsFor(item)"
              :key="li"
              class="pk-loc-card"
            >
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

          <!-- Store to / employee -->
          <div v-if="item.store_to" class="pk-store-to">
            → Diserahkan ke: <strong>{{ item.store_to }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Photo upload ───────────────────────────────────────────── -->
    <div class="pk-section-title">📷 Foto Bukti Pengambilan <span class="pk-req">*</span></div>

    <div class="pk-photo-card">
      <p class="pk-photo-hint">
        Foto barang saat diserahkan ke penerima. Minimal 1 foto wajib diupload.
      </p>

      <label class="pk-photo-label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
          stroke-linecap="round" stroke-linejoin="round" width="32" height="32">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
        <span>Ambil / Upload Foto</span>
        <input type="file" class="pk-hidden-input" multiple accept="image/*" capture="environment"
          @change="onPhotoChange" />
      </label>

      <div v-if="photoPreviews.length" class="pk-thumbs">
        <div v-for="(src, i) in photoPreviews" :key="i" class="pk-thumb">
          <img :src="src" @click="lightboxSrc = src" />
          <button type="button" class="pk-thumb-del" @click.stop="removePhoto(i)">×</button>
        </div>
      </div>
    </div>

    <!-- ── Submit button ─────────────────────────────────────────── -->
    <div class="pk-submit-area">
      <div v-if="!allChecked && (gi?.items?.length ?? 0) > 0" class="pk-warn-check">
        ⚠ Centang semua item sebelum submit
      </div>
      <div v-if="photoFiles.length === 0" class="pk-warn-photo">
        📷 Upload minimal 1 foto bukti
      </div>

      <button
        type="button"
        class="pk-submit-btn"
        :disabled="!canSubmit"
        @click="submit"
      >
        <svg v-if="form.processing" class="pk-spin" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
          stroke-linejoin="round" width="16" height="16">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
        {{ form.processing ? 'Mengirim…' : 'Serahkan & Selesai' }}
      </button>
    </div>

  </div>

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

.pk-topbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.pk-back-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--fg);
  flex-shrink: 0;
}

.pk-back-btn:hover { background: var(--hover); }

.pk-gi-number {
  font-size: 15px;
  font-weight: 800;
  color: var(--fg);
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: -0.01em;
}

.pk-gi-dept {
  font-size: 11px;
  color: var(--fg-2);
  margin-top: 1px;
}

.pk-qr-mini {
  width: 52px !important;
  height: 52px !important;
  flex-shrink: 0;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: #fff;
}

/* ── progress ────────────────────────────────────────────────────────────── */
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

.pk-progress-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--fg-2);
  white-space: nowrap;
}

/* ── section title ───────────────────────────────────────────────────────── */
.pk-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--fg-dim);
  padding: 14px 16px 6px;
}

.pk-req { color: #f87171; }

/* ── items ───────────────────────────────────────────────────────────────── */
.pk-items {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0 12px;
  margin-bottom: 8px;
}

.pk-item {
  display: flex;
  gap: 12px;
  padding: 14px 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 10px;
  transition: border-color 150ms, background 150ms;
}

.pk-item-checked {
  border-color: rgba(34, 197, 94, 0.5);
  background: rgba(34, 197, 94, 0.04);
}

/* check button */
.pk-check-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 2px solid var(--orange-500);
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  margin-top: 2px;
  transition: background 150ms;
  color: var(--orange-500);
}

.pk-item-checked .pk-check-btn {
  background: #22c55e;
  border-color: #22c55e;
  color: #fff;
}

.pk-check-empty {
  width: 10px;
  height: 10px;
  border-radius: 2px;
}

/* item body */
.pk-item-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pk-item-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.pk-item-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--fg);
  line-height: 1.3;
  flex: 1;
  transition: color 150ms;
}

.pk-name-done {
  color: var(--fg-2);
  text-decoration: line-through;
  opacity: 0.7;
}

.pk-item-qty {
  font-size: 13px;
  font-weight: 700;
  background: rgba(249, 115, 22, 0.12);
  color: var(--orange-500);
  padding: 2px 9px;
  border-radius: 6px;
  white-space: nowrap;
  flex-shrink: 0;
}

.pk-item-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.pk-sku {
  font-size: 11px;
  color: var(--fg-2);
  font-family: 'JetBrains Mono', monospace;
}

.pk-wh-chip {
  font-size: 10.5px;
  font-weight: 600;
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
  padding: 1px 7px;
  border-radius: 5px;
}

/* ── LOCATION CARDS ─────────────────────────────────────────────────────── */
.pk-locations {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.pk-loc-card {
  background: rgba(249, 115, 22, 0.08);
  border: 1px solid rgba(249, 115, 22, 0.3);
  border-radius: 8px;
  padding: 6px 10px;
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 100px;
}

.pk-loc-rack {
  font-size: 13px;
  font-weight: 800;
  color: var(--orange-500);
  display: flex;
  align-items: center;
  gap: 4px;
  letter-spacing: 0.02em;
}

.pk-loc-name {
  font-size: 11px;
  color: var(--fg-2);
  font-weight: 500;
}

.pk-loc-avail {
  font-size: 10.5px;
  color: var(--fg-dim);
  font-weight: 600;
}

.pk-no-loc {
  font-size: 12px;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 6px;
  padding: 6px 10px;
}

.pk-store-to {
  font-size: 11.5px;
  color: var(--fg-2);
}

/* ── photos ──────────────────────────────────────────────────────────────── */
.pk-photo-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0 12px 4px;
}

.pk-photo-hint {
  font-size: 12px;
  color: var(--fg-2);
  margin: 0;
  padding: 0 2px;
}

.pk-photo-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 22px 16px;
  border: 2px dashed rgba(249, 115, 22, 0.4);
  border-radius: 12px;
  background: rgba(249, 115, 22, 0.03);
  cursor: pointer;
  transition: border-color 150ms, background 150ms;
  color: var(--orange-500);
  font-size: 13px;
  font-weight: 600;
  text-align: center;
}

.pk-photo-label:hover {
  border-color: var(--orange-500);
  background: rgba(249, 115, 22, 0.07);
}

.pk-hidden-input { display: none; }

.pk-thumbs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
}

.pk-thumb {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  background: var(--surface-2);
}

.pk-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  cursor: pointer;
}

.pk-thumb-del {
  position: absolute;
  top: 3px;
  right: 3px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.9);
  color: #fff;
  border: none;
  cursor: pointer;
  font-size: 16px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

/* ── submit area ─────────────────────────────────────────────────────────── */
.pk-submit-area {
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pk-warn-check,
.pk-warn-photo {
  font-size: 12px;
  color: #f59e0b;
  font-weight: 600;
  padding: 6px 10px;
  background: rgba(245, 158, 11, 0.1);
  border-radius: 6px;
}

.pk-submit-btn {
  width: 100%;
  appearance: none;
  border: none;
  background: var(--orange-500);
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  padding: 14px 16px;
  border-radius: 12px;
  cursor: pointer;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity 150ms;
}

.pk-submit-btn:hover:not(:disabled) { opacity: 0.9; }
.pk-submit-btn:disabled { opacity: 0.45; cursor: default; }

.pk-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── lightbox ────────────────────────────────────────────────────────────── */
.lb-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.85);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; cursor: pointer;
}
.lb-img {
  max-width: 90vw; max-height: 90vh;
  border-radius: 8px; object-fit: contain; cursor: default;
}
.lb-close {
  position: fixed; top: 16px; right: 16px;
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,.15); color: #fff;
  border: none; cursor: pointer; font-size: 22px;
  display: flex; align-items: center; justify-content: center;
}

/* ── mobile fine-tuning ──────────────────────────────────────────────────── */
@media (max-width: 480px) {
  .pk-item-name { font-size: 13px; }
  .pk-loc-rack  { font-size: 12px; }
  .pk-submit-btn { font-size: 14px; padding: 13px 14px; }
}
</style>
