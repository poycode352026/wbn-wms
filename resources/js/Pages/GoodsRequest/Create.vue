<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    departments: Array,
    allVariants: Array,
    // stockMap: { [variantId]: [{ warehouse_id, warehouse_code, location_id, location_code, qty }] }
    stockMap:    Object,
})

// ── form ───────────────────────────────────────────────────────────────────
const form = useForm({
    requester_name:   '',
    requester_emp_id: '',
    department_id:    '',
    remark:           '',
    items:            [],
})

// ── item rows ──────────────────────────────────────────────────────────────
const newRow = () => ({
    variantId:   null,
    query:       '',
    qty:         '',
    selectedUom: 'base',
    warehouseId: null,
    locationId:  null,
})
const rows = ref([newRow()])

// ── dropdown ───────────────────────────────────────────────────────────────
const activeDropIdx  = ref(-1)
const dropFixedStyle = ref({})

function openDropdown(idx, event) {
    const rect = event.currentTarget.getBoundingClientRect()
    dropFixedStyle.value = {
        position: 'fixed',
        top:      (rect.bottom + 4) + 'px',
        left:     rect.left + 'px',
        width:    rect.width + 'px',
        zIndex:   9999,
    }
    activeDropIdx.value = idx
    // Clear query so user gets fresh search results (not filtered by previous selection name)
    const row = rows.value[idx]
    if (row.variantId) row.query = ''
}
function closeDropdown() {
    const idx = activeDropIdx.value
    setTimeout(() => {
        // Restore display name if user closed dropdown without picking a new variant
        if (idx >= 0) {
            const row = rows.value[idx]
            if (row.variantId && !row.query.trim()) {
                const v = variantOf(row)
                row.query = v ? (itemName(v) || v.sku) : ''
            }
        }
        activeDropIdx.value = -1
    }, 160)
}

// ── variant helpers ────────────────────────────────────────────────────────
function itemName(v) {
    if (!v) return ''
    if (locale.value === 'zh' && v.name_zh) return v.name_zh
    if (locale.value === 'id' && v.name_id) return v.name_id
    return v.name_en || v.name_id || ''
}

function filteredVariants(row) {
    const all = props.allVariants ?? []
    const q = (row.query || '').trim().toLowerCase()
    if (!q) return all.slice(0, 30)
    return all.filter(v =>
        (v.sku     && v.sku.toLowerCase().includes(q))     ||
        (v.name_en && v.name_en.toLowerCase().includes(q)) ||
        (v.name_id && v.name_id.toLowerCase().includes(q)) ||
        (v.name_zh && v.name_zh.toLowerCase().includes(q)) ||
        (v.brand   && v.brand.toLowerCase().includes(q))   ||
        (v.model   && v.model.toLowerCase().includes(q))
    ).slice(0, 30)
}

function selectVariant(row, v) {
    row.variantId   = v.id
    row.query       = itemName(v) || v.sku
    row.qty         = ''
    row.warehouseId = null
    row.locationId  = null
    activeDropIdx.value = -1

    // Auto-select if only one location available
    const locs = locationsForRow(row)
    if (locs.length === 1) {
        row.warehouseId = locs[0].warehouse_id
        row.locationId  = locs[0].location_id
    }
}

function variantOf(row) {
    if (!row.variantId) return null
    return (props.allVariants ?? []).find(a => a.id === row.variantId) ?? null
}

// ── location options per row ───────────────────────────────────────────────
function locationsForRow(row) {
    if (!row.variantId) return []
    return (props.stockMap?.[row.variantId] ?? []).filter(e => e.qty > 0)
}

function selectLocation(row, loc) {
    if (row.warehouseId === loc.warehouse_id && row.locationId === loc.location_id) {
        // Toggle off
        row.warehouseId = null
        row.locationId  = null
    } else {
        row.warehouseId = loc.warehouse_id
        row.locationId  = loc.location_id
    }
}

function isLocSelected(row, loc) {
    return row.warehouseId === loc.warehouse_id && row.locationId === loc.location_id
}

function sohForRow(row) {
    if (!row.variantId || !row.warehouseId) return null
    const locs = locationsForRow(row)
    return locs.find(l => l.warehouse_id === row.warehouseId && l.location_id === row.locationId)?.qty ?? null
}

// ── UOM ────────────────────────────────────────────────────────────────────
function rowHasAlt(row) {
    const v = variantOf(row)
    return !!(v?.alt_uom && v?.alt_uom_conversion)
}

function currentUom(row) {
    const v = variantOf(row)
    if (!v) return '—'
    return row.selectedUom === 'alt' ? v.alt_uom : v.base_uom
}

function baseQty(row) {
    const v   = variantOf(row)
    const qty = parseFloat(row.qty)
    if (!v || isNaN(qty) || qty <= 0) return 0
    return row.selectedUom === 'alt' && v.alt_uom_conversion
        ? qty * v.alt_uom_conversion
        : qty
}

function convHint(row) {
    if (row.selectedUom !== 'alt' || !rowHasAlt(row)) return null
    const raw = parseFloat(row.qty)
    if (isNaN(raw) || raw <= 0) return null
    const v = variantOf(row)
    return `= ${(raw * v.alt_uom_conversion).toLocaleString()} ${v.base_uom}`
}

function rowSohInsufficient(row) {
    const soh = sohForRow(row)
    const qty = baseQty(row)
    if (soh === null || qty <= 0) return false
    return soh < qty
}

function addRow()       { rows.value.push(newRow()) }
function removeRow(idx) {
    if (rows.value.length > 1) rows.value.splice(idx, 1)
    else rows.value[0] = newRow()
}

// ── submit ─────────────────────────────────────────────────────────────────
const submitError = ref('')

const validRows = computed(() =>
    rows.value.filter(r =>
        r.variantId && r.warehouseId &&
        r.qty !== '' && !isNaN(parseFloat(r.qty)) && parseFloat(r.qty) > 0
    )
)

const hasSohError = computed(() => validRows.value.some(r => rowSohInsufficient(r)))

const canSubmit = computed(() =>
    !form.processing
    && validRows.value.length > 0
    && form.requester_name.trim()
    && !hasSohError.value
)

function submit() {
    submitError.value = ''

    if (!form.requester_name.trim()) {
        submitError.value = 'Nama pemohon wajib diisi.'; return
    }
    if (validRows.value.length === 0) {
        submitError.value = 'Tambahkan minimal 1 item dengan lokasi dan qty.'; return
    }
    if (hasSohError.value) {
        submitError.value = 'Qty melebihi stok tersedia. Kurangi qty yang diminta.'; return
    }

    form.items = validRows.value.map(r => ({
        variant_id:    r.variantId,
        warehouse_id:  r.warehouseId,
        location_id:   r.locationId ?? null,
        requested_qty: parseFloat(r.qty),
        uom:           currentUom(r),
        base_qty:      baseQty(r),
    }))

    form.post(route('grq.store'), {
        onError: (errors) => {
            submitError.value = Object.values(errors)[0] ?? 'Terjadi kesalahan.'
        },
    })
}
</script>

<template>
<AppLayout>
  <template #title>{{ $t('grq.createTitle') }}</template>
  <template #breadcrumb>
    <Link :href="route('grq.index')" class="bc-link">{{ $t('grq.title') }}</Link>
    <span class="bc-sep">›</span>
    {{ $t('grq.createTitle') }}
  </template>

  <div class="grq-create-page">

    <div class="grq-ph">
      <h1 class="grq-pt">{{ $t('grq.createTitle') }}</h1>
      <p class="grq-ps">{{ $t('grq.createSub') }}</p>
    </div>

    <div class="grq-body">

      <!-- ── Requester Info ──────────────────────────────────────────── -->
      <div class="grq-card">
        <div class="grq-section-title">Request Information</div>
        <div class="grq-grid">
          <div class="grq-fg">
            <label class="grq-lbl">{{ $t('grq.requesterName') }} <span class="req">*</span></label>
            <input class="grq-input" type="text" v-model="form.requester_name" :placeholder="$t('grq.requesterNamePh')" />
          </div>
          <div class="grq-fg">
            <label class="grq-lbl">{{ $t('grq.requesterEmpId') }}</label>
            <input class="grq-input" type="text" v-model="form.requester_emp_id" :placeholder="$t('grq.requesterEmpIdPh')" />
          </div>
          <div class="grq-fg">
            <label class="grq-lbl">{{ $t('grq.department') }}</label>
            <select class="grq-input" v-model="form.department_id">
              <option value="">{{ $t('grq.selectDept') }}</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="grq-fg">
            <label class="grq-lbl">{{ $t('grq.remark') }}</label>
            <input class="grq-input" type="text" v-model="form.remark" :placeholder="$t('grq.remarkPh')" />
          </div>
        </div>
      </div>

      <!-- ── Items ────────────────────────────────────────────────────── -->
      <div class="grq-card">
        <div class="grq-section-title">Item List</div>

        <div v-for="(row, idx) in rows" :key="idx" class="grq-item-block">

          <!-- Line 1: number + search + delete -->
          <div class="grq-line1">
            <span class="row-num">{{ idx + 1 }}</span>

            <div class="grq-variant-col">
              <input
                class="grq-input"
                type="text"
                v-model="row.query"
                :placeholder="$t('grq.searchVariant')"
                @focus="openDropdown(idx, $event)"
                @blur="closeDropdown"
                autocomplete="off"
              />
              <div v-if="row.variantId" class="variant-badge-row">
                <span class="v-sku">{{ variantOf(row)?.sku }}</span>
                <span class="v-name">{{ itemName(variantOf(row)) }}</span>
                <span v-if="variantOf(row)?.brand || variantOf(row)?.model || variantOf(row)?.size || variantOf(row)?.color"
                      class="v-meta">
                  {{ [variantOf(row)?.brand, variantOf(row)?.model, variantOf(row)?.size, variantOf(row)?.color].filter(Boolean).join(' · ') }}
                </span>
              </div>
            </div>

            <button type="button" class="del-btn" @click="removeRow(idx)" title="Remove">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>

          <!-- Dropdown portal -->
          <Teleport to="body">
            <div v-if="activeDropIdx === idx" class="grq-drop" :style="dropFixedStyle">
              <div
                v-for="v in filteredVariants(row)" :key="v.id"
                class="grq-drop-item"
                @mousedown.prevent="selectVariant(row, v)"
              >
                <div class="gdi-top">
                  <span class="gdi-sku">{{ v.sku }}</span>
                  <span class="gdi-name">{{ itemName(v) }}</span>
                </div>
                <div v-if="v.brand || v.model || v.size || v.color" class="gdi-meta">
                  {{ [v.brand, v.model, v.size, v.color].filter(Boolean).join(' · ') }}
                </div>
              </div>
              <div v-if="filteredVariants(row).length === 0" class="gdi-empty">
                {{ $t('grq.noVariantFound') }}
              </div>
            </div>
          </Teleport>

          <!-- Line 2: location chips + qty + uom (only if variant selected) -->
          <div v-if="row.variantId" class="grq-line2">

            <!-- Location chips -->
            <div class="loc-chips-wrap">
              <div v-if="locationsForRow(row).length === 0" class="no-stock-tag">
                Tidak ada stok
              </div>
              <button
                v-for="loc in locationsForRow(row)"
                :key="`${loc.warehouse_id}-${loc.location_id}`"
                type="button"
                :class="['loc-chip', { 'chip-on': isLocSelected(row, loc) }]"
                @click="selectLocation(row, loc)"
              >
                <span class="chip-wh">{{ loc.warehouse_code }}</span>
                <template v-if="loc.location_code">
                  <span class="chip-dot">·</span>
                  <span class="chip-loc">{{ loc.location_code }}</span>
                </template>
                <span class="chip-dot">·</span>
                <span class="chip-qty" :class="{ 'qty-low': loc.qty < 5 }">{{ loc.qty % 1 === 0 ? loc.qty : loc.qty.toFixed(2) }}</span>
              </button>
            </div>

            <!-- Qty + UOM (only after location selected) -->
            <template v-if="row.warehouseId">
              <div class="qty-wrap">
                <input
                  class="grq-input qty-input"
                  :class="{ 'err': rowSohInsufficient(row) }"
                  type="number" v-model="row.qty" min="0.01" step="any"
                  placeholder="0"
                />
                <div v-if="convHint(row)" class="conv-hint">{{ convHint(row) }}</div>
                <div v-if="rowSohInsufficient(row)" class="soh-warn">
                  ⚠ Max {{ sohForRow(row)?.toLocaleString() }}
                </div>
              </div>

              <div class="uom-wrap">
                <div v-if="rowHasAlt(row)" class="uom-toggle">
                  <button type="button" :class="['uom-btn', { on: row.selectedUom === 'base' }]" @click="row.selectedUom = 'base'">
                    {{ variantOf(row)?.base_uom }}
                  </button>
                  <button type="button" :class="['uom-btn', { on: row.selectedUom === 'alt' }]" @click="row.selectedUom = 'alt'">
                    {{ variantOf(row)?.alt_uom }}
                  </button>
                </div>
                <span v-else class="uom-txt">{{ variantOf(row)?.base_uom ?? '—' }}</span>
              </div>
            </template>
          </div>

        </div><!-- /grq-item-block -->

        <button type="button" class="add-row-btn" @click="addRow">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          {{ $t('grq.addItem') }}
        </button>
      </div>

      <!-- Error -->
      <div v-if="submitError" class="grq-error">{{ submitError }}</div>
      <div v-if="Object.keys(form.errors).length" class="grq-error">
        <span v-for="(e, k) in form.errors" :key="k">{{ e }} </span>
      </div>

      <!-- Actions -->
      <div class="grq-actions">
        <Link :href="route('grq.index')" class="grq-cancel-btn">{{ $t('btn.cancel') }}</Link>
        <button type="button" class="grq-submit-btn" :disabled="!canSubmit" @click="submit">
          <span v-if="form.processing">{{ $t('grq.saving') }}</span>
          <span v-else>{{ $t('grq.createBtn') }}</span>
        </button>
      </div>

    </div>
  </div>
</AppLayout>
</template>

<style scoped>
.grq-create-page { display:flex; flex-direction:column; gap:18px }
.grq-ph { display:flex; flex-direction:column; gap:3px }
.grq-pt { font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.02em }
.grq-ps { font-size:13px; color:var(--fg-2) }
.bc-link { color:var(--fg-2); text-decoration:none }
.bc-link:hover { color:var(--fg) }
.bc-sep  { margin:0 6px; color:var(--fg-dim) }

.grq-body { display:flex; flex-direction:column; gap:16px }

/* ── card ─────────────────────────────────────────────────────────── */
.grq-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px }
.grq-section-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--fg-dim); margin-bottom:14px }

/* ── form grid ─────────────────────────────────────────────────────── */
.grq-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px }
.grq-fg { display:flex; flex-direction:column; gap:4px }
.grq-lbl { font-size:12px; font-weight:600; color:var(--fg-2) }
.req { color:var(--orange-500) }
.grq-input {
  padding:7px 10px; font-size:13px; background:var(--surface-2);
  border:1px solid var(--border-2); border-radius:7px; color:var(--fg);
  outline:none; font-family:inherit; transition:border-color 180ms; width:100%; box-sizing:border-box;
}
.grq-input:focus { border-color:var(--orange-500) }

/* ── item block ───────────────────────────────────────────────────── */
.grq-item-block {
  padding:10px 0; border-bottom:1px solid var(--border);
}
.grq-item-block:last-of-type { border-bottom:0 }

/* ── line 1 ───────────────────────────────────────────────────────── */
.grq-line1 { display:flex; align-items:flex-start; gap:8px }
.row-num { font-size:11.5px; color:var(--fg-dim); font-weight:700; width:18px; flex-shrink:0; padding-top:8px; text-align:right }
.grq-variant-col { flex:1; display:flex; flex-direction:column; gap:3px }
.variant-badge-row { display:flex; align-items:center; gap:6px; padding:0 2px }
.v-sku  { font-family:monospace; font-size:10.5px; font-weight:700; color:var(--orange-500); background:rgba(249,115,22,.1); padding:1px 5px; border-radius:3px }
.v-name { font-size:11.5px; color:var(--fg-dim) }
.v-meta { font-size:10.5px; color:var(--fg-dim); opacity:.75 }

.del-btn {
  flex-shrink:0; width:28px; height:28px; margin-top:2px;
  display:flex; align-items:center; justify-content:center;
  border:1px solid var(--border-2); border-radius:6px; background:transparent;
  color:var(--fg-dim); cursor:pointer;
}
.del-btn:hover { background:rgba(239,68,68,.1); color:#f87171; border-color:#f87171 }

/* ── line 2 ───────────────────────────────────────────────────────── */
.grq-line2 {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  margin-top:7px; margin-left:26px;
}

/* ── location chips ───────────────────────────────────────────────── */
.loc-chips-wrap { display:flex; flex-wrap:wrap; gap:5px; flex:1 }
.no-stock-tag { font-size:11px; color:#f87171; padding:3px 0 }

.loc-chip {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 8px; border-radius:5px; border:1px solid var(--border-2);
  background:var(--surface-2); cursor:pointer; font-family:inherit;
  font-size:11.5px; color:var(--fg-2); transition:border-color 120ms, background 120ms;
  white-space:nowrap;
}
.loc-chip:hover { border-color:var(--orange-400); color:var(--fg) }
.chip-on {
  border-color:var(--orange-500) !important;
  background:rgba(249,115,22,.12) !important;
  color:var(--fg) !important;
}
.chip-wh  { font-weight:700; color:var(--fg) }
.chip-dot { color:var(--fg-dim); font-size:10px }
.chip-loc { color:var(--fg-dim) }
.chip-qty { font-weight:700; color:#34d399 }
.qty-low  { color:#f87171 }

/* ── qty ──────────────────────────────────────────────────────────── */
.qty-wrap { display:flex; flex-direction:column; gap:2px }
.qty-input { width:88px; text-align:right; padding:6px 8px }
.err { border-color:#f87171 !important }
.conv-hint { font-size:10px; color:var(--fg-dim) }
.soh-warn  { font-size:10.5px; color:#f87171; font-weight:600 }

/* ── uom ──────────────────────────────────────────────────────────── */
.uom-wrap { display:flex; align-items:center }
.uom-toggle { display:flex; border:1px solid var(--border-2); border-radius:6px; overflow:hidden }
.uom-btn { padding:5px 9px; font-size:11px; font-weight:600; border:0; background:transparent; color:var(--fg-dim); cursor:pointer; font-family:inherit }
.uom-btn.on { background:var(--orange-500); color:#fff }
.uom-txt { font-size:12px; color:var(--fg-dim); padding:0 4px }

/* ── add row ──────────────────────────────────────────────────────── */
.add-row-btn {
  display:inline-flex; align-items:center; gap:5px; margin-top:12px; padding:6px 12px;
  border:1px dashed var(--border-2); border-radius:6px; font-size:12px; font-weight:600;
  color:var(--fg-dim); background:transparent; cursor:pointer; font-family:inherit;
  transition:border-color 150ms, color 150ms;
}
.add-row-btn:hover { border-color:var(--orange-500); color:var(--orange-500) }

/* ── dropdown ─────────────────────────────────────────────────────── */
.grq-drop {
  background:var(--surface); border:1px solid var(--border); border-radius:8px;
  max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.2);
}
.grq-drop-item { display:flex; flex-direction:column; gap:2px; padding:7px 12px; cursor:pointer; transition:background 120ms }
.grq-drop-item:hover { background:var(--hover) }
.gdi-top  { display:flex; align-items:baseline; gap:7px }
.gdi-sku  { font-size:10.5px; font-weight:700; color:var(--orange-500); font-family:monospace; flex-shrink:0 }
.gdi-name { font-size:12px; color:var(--fg-2) }
.gdi-meta { font-size:10.5px; color:var(--fg-dim); padding-left:2px }
.gdi-empty { padding:12px; font-size:12px; color:var(--fg-dim); text-align:center }

/* ── actions ──────────────────────────────────────────────────────── */
.grq-error {
  padding:9px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);
  border-radius:8px; font-size:12px; color:#f87171;
}
.grq-actions { display:flex; gap:10px; justify-content:flex-end }
.grq-cancel-btn {
  padding:8px 20px; border-radius:8px; border:1px solid var(--border-2);
  font-size:13px; font-weight:600; color:var(--fg-2); background:var(--surface);
  text-decoration:none; display:inline-flex; align-items:center;
}
.grq-cancel-btn:hover { background:var(--hover) }
.grq-submit-btn {
  padding:8px 22px; border-radius:8px; border:0; font-size:13px; font-weight:600; color:#fff;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500));
  box-shadow:0 4px 10px -3px rgba(249,115,22,.45);
  cursor:pointer; transition:opacity 150ms; font-family:inherit;
}
.grq-submit-btn:hover:not(:disabled) { opacity:.88 }
.grq-submit-btn:disabled { opacity:.45; cursor:not-allowed }

@media (max-width:640px) {
  .grq-grid { grid-template-columns:1fr }
}
</style>
