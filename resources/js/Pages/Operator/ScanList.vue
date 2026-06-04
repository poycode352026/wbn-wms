<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    gis: Array,
})

const searchQuery = ref('')
const scanInput   = ref('')

// ── Filter GI by search ────────────────────────────────────────────────────
const filteredGis = computed(() => {
    if (!searchQuery.value.trim()) return props.gis ?? []
    const q = searchQuery.value.toLowerCase()
    return (props.gis ?? []).filter(gi =>
        gi.gi_number.toLowerCase().includes(q) ||
        gi.purpose?.toLowerCase().includes(q) ||
        gi.department?.name?.toLowerCase().includes(q)
    )
})

// ── Text barcode scan (hardware scanner → Enter) ───────────────────────────
function handleScan() {
    const barcode = scanInput.value.trim()
    if (!barcode) return
    navigateToGI(barcode)
    scanInput.value = ''
}

function navigateToGI(barcode) {
    const gi = (props.gis ?? []).find(g => g.gi_number === barcode)
    if (gi) {
        router.visit(route('operator.scan-detail', gi.id))
    } else {
        alert(`GI "${barcode}" tidak ditemukan atau tidak di-assign ke Anda`)
    }
}

// ── Camera QR Scan (BarcodeDetector API) ──────────────────────────────────
const cameraOpen   = ref(false)
const cameraError  = ref('')
const cameraVideo  = ref(null)
let   scanTimer    = null
let   mediaStream  = null

const hasBarcodeDetector = typeof window !== 'undefined' && 'BarcodeDetector' in window

async function openCamera() {
    cameraError.value = ''
    cameraOpen.value  = true

    // Cek apakah browser support getUserMedia
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraError.value = 'Kamera tidak tersedia. Pastikan akses via HTTPS dan gunakan Chrome terbaru. Atau gunakan input manual di bawah.'
        return
    }

    if (!hasBarcodeDetector) {
        cameraError.value = 'Browser ini tidak mendukung scan QR otomatis. Gunakan Chrome versi terbaru, atau input manual di bawah.'
        return
    }

    await new Promise(r => setTimeout(r, 100)) // let DOM render

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        })
        cameraVideo.value.srcObject = mediaStream
        await cameraVideo.value.play()

        const detector = new BarcodeDetector({ formats: ['qr_code'] })

        scanTimer = setInterval(async () => {
            if (!cameraVideo.value || cameraVideo.value.readyState < 2) return
            try {
                const barcodes = await detector.detect(cameraVideo.value)
                if (barcodes.length > 0) {
                    const rawValue = barcodes[0].rawValue
                    closeCamera()
                    navigateToGI(rawValue)
                }
            } catch (_) { /* ignore decode errors */ }
        }, 400)
    } catch (err) {
        cameraError.value = err.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Tap Allow/Izinkan saat browser meminta akses kamera.'
            : err.name === 'NotFoundError'
            ? 'Kamera tidak ditemukan di perangkat ini.'
            : 'Tidak bisa membuka kamera: ' + err.message
    }
}

function closeCamera() {
    clearInterval(scanTimer)
    scanTimer = null
    if (mediaStream) {
        mediaStream.getTracks().forEach(t => t.stop())
        mediaStream = null
    }
    cameraOpen.value = false
}

onMounted(() => {
    // Open camera if triggered by center nav button (via sessionStorage flag)
    // sessionStorage is cleared immediately so refresh won't re-trigger it
    if (sessionStorage.getItem('open_camera') === '1') {
        sessionStorage.removeItem('open_camera')
        openCamera()
    }
})

onUnmounted(closeCamera)

// ── Helpers ────────────────────────────────────────────────────────────────
function itemName(variant) {
    const item = variant?.item ?? variant
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}
</script>

<template>
<OperatorLayout>
  <template #title>{{ $t('operator.scanTitle') }}</template>

  <div class="op-wrap">

    <!-- Header -->
    <div class="op-hdr">
      <div>
        <h2 class="op-title">📦 {{ $t('operator.scanTitle') }}</h2>
        <p class="op-sub">{{ $t('operator.scanSub') }}</p>
      </div>
    </div>

    <!-- ── SCAN CARD ────────────────────────────────────────────────────────── -->
    <div class="op-card op-scan-card">
      <!-- Text input for hardware scanner / manual entry -->
      <div class="op-scan-row">
        <input
          v-model="scanInput"
          @keydown.enter="handleScan"
          type="text"
          class="op-scan-input"
          placeholder="GI-2025-001..."
          autocomplete="off"
        />
        <button type="button" class="op-scan-go" @click="handleScan">→</button>
      </div>
      <span class="op-scan-hint">{{ $t('operator.scanHint') }}</span>
    </div>

    <!-- Search filter -->
    <div class="op-card op-filter-card">
      <input
        v-model="searchQuery"
        type="text"
        class="op-search-input"
        :placeholder="$t('operator.searchPlaceholder')"
      />
    </div>

    <!-- GI List -->
    <div v-if="filteredGis.length" class="op-list">
      <div v-for="gi in filteredGis" :key="gi.id" class="op-list-item">
        <Link :href="route('operator.scan-detail', gi.id)" class="op-item-link">
          <div class="op-item-header">
            <span class="op-gi-number">{{ gi.gi_number }}</span>
            <span class="op-item-count">{{ gi.items_count }} {{ $t('operator.items') }}</span>
          </div>
          <div class="op-item-details">
            <span class="op-dept">{{ gi.department?.name }}</span>
            <span class="op-purpose">{{ gi.purpose }}</span>
          </div>
          <div class="op-item-footer">
            <span class="op-status-badge" :class="`op-status-${gi.status}`">
              {{ $t(`gi.status.${gi.status}`) }}
            </span>
          </div>
        </Link>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="op-empty">
      <div class="op-empty-icon">📭</div>
      <div class="op-empty-text">{{ $t('operator.noGi') }}</div>
    </div>

  </div><!-- /op-wrap -->

  <!-- ── Camera Overlay ──────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="cameraOpen" class="cam-overlay">
      <div class="cam-modal">
        <div class="cam-header">
          <span class="cam-title">📷 Scan QR Code GI</span>
          <button type="button" class="cam-close" @click="closeCamera">✕</button>
        </div>

        <div class="cam-viewfinder">
          <video ref="cameraVideo" class="cam-video" autoplay playsinline muted></video>
          <div class="cam-reticle"></div>
          <div class="cam-scan-line"></div>
        </div>

        <p v-if="cameraError" class="cam-error">{{ cameraError }}</p>
        <p v-else class="cam-hint">Arahkan kamera ke QR Code pada barcode GI</p>
      </div>
    </div>
  </Teleport>

</OperatorLayout>
</template>

<style scoped>
.op-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
}

.op-hdr {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.op-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--fg);
  letter-spacing: -0.02em;
}

.op-sub {
  font-size: 13px;
  color: var(--fg-2);
  margin-top: 3px;
}

.op-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  box-shadow: var(--shadow-sm);
}

.op-scan-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border: 2px solid var(--orange-500);
  background: rgba(249, 115, 22, 0.03);
}

.op-scan-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--orange-500);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

/* Camera button */
.op-cam-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 16px;
  background: var(--orange-500);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 150ms;
}
.op-cam-btn:hover { opacity: 0.9; }

/* Divider between camera and manual input */
.op-scan-divider {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 600;
  color: var(--fg-dim);
  text-transform: uppercase;
  letter-spacing: .06em;
}
.op-scan-divider::before,
.op-scan-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* Row: text input + go button */
.op-scan-row {
  display: flex;
  gap: 8px;
}

.op-scan-input {
  flex: 1;
  padding: 12px 14px;
  font-size: 16px;
  font-weight: 600;
  background: var(--surface-2);
  border: 1px solid var(--orange-400);
  border-radius: 10px;
  color: var(--fg);
  outline: none;
  font-family: 'JetBrains Mono', monospace;
  transition: border-color 180ms;
}
.op-scan-input:focus { border-color: var(--orange-500); }

.op-scan-go {
  padding: 12px 18px;
  background: var(--surface-2);
  border: 1px solid var(--orange-400);
  border-radius: 10px;
  color: var(--orange-500);
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms;
}
.op-scan-go:hover { background: rgba(249,115,22,0.1); }

.op-scan-hint {
  font-size: 11px;
  color: var(--fg-2);
}

.op-filter-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.op-search-input {
  padding: 10px 12px;
  font-size: 14px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--fg);
  outline: none;
  font-family: inherit;
  transition: border-color 180ms;
}
.op-search-input:focus { border-color: var(--orange-500); }

.op-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.op-list-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow 150ms, border-color 150ms;
}
.op-list-item:hover {
  border-color: var(--orange-500);
  box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
}

.op-item-link {
  display: block;
  padding: 14px 16px;
  text-decoration: none;
  color: inherit;
}

.op-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.op-gi-number {
  font-size: 14px;
  font-weight: 700;
  color: var(--fg);
  font-family: 'JetBrains Mono', monospace;
}

.op-item-count {
  font-size: 12px;
  font-weight: 600;
  background: rgba(249, 115, 22, 0.1);
  color: var(--orange-500);
  padding: 2px 8px;
  border-radius: 6px;
}

.op-item-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-bottom: 8px;
}

.op-dept    { font-size: 12px; font-weight: 600; color: var(--fg-2); }
.op-purpose { font-size: 13px; color: var(--fg); line-height: 1.4; word-break: break-word; }

.op-item-footer { display: flex; align-items: center; gap: 6px; }

.op-status-badge {
  display: inline-flex;
  align-items: center;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.op-status-assigned       { background: rgba(59,130,246,.15); color:#3b82f6; }
.op-status-in_picking     { background: rgba(249,115,22,.15);  color:var(--orange-500); }
.op-status-ready_to_pickup{ background: rgba(34,197,94,.15);   color:#22c55e; }
.op-status-completed      { background: rgba(107,114,128,.1);  color:#6b7280; }

.op-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
}
.op-empty-icon { font-size: 40px; margin-bottom: 12px; }
.op-empty-text { font-size: 14px; color: var(--fg-2); font-weight: 500; }

/* ── Camera Overlay ──────────────────────────────────────────────────────── */
.cam-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.85);
  z-index: 9000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.cam-modal {
  width: 100%;
  max-width: 420px;
  background: var(--surface);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}

.cam-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
}
.cam-title { font-size: 15px; font-weight: 700; color: var(--fg); }
.cam-close {
  width: 32px; height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--fg);
  cursor: pointer;
  font-size: 16px;
  display: flex; align-items: center; justify-content: center;
}

.cam-viewfinder {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  background: #000;
  overflow: hidden;
}

.cam-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cam-reticle {
  position: absolute;
  inset: 20%;
  border: 3px solid var(--orange-500);
  border-radius: 12px;
  pointer-events: none;
}

.cam-scan-line {
  position: absolute;
  left: 20%;
  right: 20%;
  top: 50%;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--orange-500), transparent);
  animation: scan-sweep 2s ease-in-out infinite;
}
@keyframes scan-sweep {
  0%   { top: 22%; }
  50%  { top: 78%; }
  100% { top: 22%; }
}

.cam-error {
  padding: 12px 16px;
  font-size: 13px;
  color: #ef4444;
  font-weight: 600;
  text-align: center;
}
.cam-hint {
  padding: 12px 16px;
  font-size: 12px;
  color: var(--fg-2);
  text-align: center;
  margin: 0;
}

@media (max-width: 640px) {
  .op-wrap { max-width: 100%; padding: 12px; }
  .op-scan-input { font-size: 18px; }
  .op-gi-number  { font-size: 13px; }
  .op-purpose    { font-size: 12px; }
}
</style>
