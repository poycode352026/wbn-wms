<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    warehouses:   Array,
    allVariants:  Array,
    userDeptId:   Number,
    userDeptName: String,
    stockMap:     Object,   // {warehouseId: {variantId: available_qty}}
    cooldownData: Array,    // [{variant_id, track_type, lv_id, employee_id, cooldown_until}]
    vehicles:        Array,    // [{id, lv_number, name}]
    employees:       Array,    // [{id, employee_id, name}]
    prefilledItems:  { type: Array, default: () => [] },
    sourceRequestIds:{ type: Array, default: () => [] },
})

// ── form ───────────────────────────────────────────────────────────────────
const form = useForm({
    warehouse_id:   '',
    project:        '',
    purpose:        '',
    usage_location: '',
    notes:          '',
    items:          [],
})

// ── photo upload ───────────────────────────────────────────────────────────
const photoFiles    = ref([])
const photoPreviews = ref([])

function onPhotoChange(e) {
    const newFiles = Array.from(e.target.files)
    const remaining = 10 - photoFiles.value.length
    const toAdd = newFiles.slice(0, remaining)
    toAdd.forEach(file => {
        photoFiles.value.push(file)
        const reader = new FileReader()
        reader.onload = (ev) => photoPreviews.value.push(ev.target.result)
        reader.readAsDataURL(file)
    })
    e.target.value = ''
}

function removePhoto(i) {
    photoFiles.value.splice(i, 1)
    photoPreviews.value.splice(i, 1)
}

// ── lightbox ───────────────────────────────────────────────────────────────
const lightboxSrc = ref(null)
function openLightbox(src) { lightboxSrc.value = src }
function closeLightbox()    { lightboxSrc.value = null }

// ── item rows ──────────────────────────────────────────────────────────────
const newRow = () => ({
    variantId:       null,
    query:           '',
    qty:             '',
    selectedUom:     'base',
    storeTo:         '',
    lvId:            null,
    employeeId:      null,
    itemReason:      '',
    notes:           '',
    itemWarehouseId: null,
})
const rows = ref([newRow()])

// Pre-fill rows from employee requests (when navigated from Employees page batch GI)
onMounted(() => {
    if (props.prefilledItems?.length) {
        const filled = props.prefilledItems.map(pi => {
            const v = props.allVariants.find(v => v.id === pi.variantId)
            if (!v) return null

            // Auto-fill storeTo based on tracking type
            let storeTo = ''
            if (v.cooldown_track_by === 'lv_number' && pi.lvId) {
                // Use driver (requester) name — vehicle is already shown in Tracking column
                if (pi.employeeId) {
                    const emp = props.employees?.find(e => e.id === pi.employeeId)
                    storeTo = emp ? emp.name : ''
                }
                if (!storeTo) {
                    const vehicle = props.vehicles?.find(lv => lv.id === pi.lvId)
                    storeTo = vehicle ? (vehicle.full_number ?? vehicle.lv_number ?? '') : ''
                }
            } else if (v.cooldown_track_by === 'employee_id' && pi.employeeId) {
                const emp = props.employees?.find(e => e.id === pi.employeeId)
                storeTo = emp ? emp.name : ''
            }

            const row = {
                ...newRow(),
                variantId:  pi.variantId,
                query:      itemName(v) || v.sku || '',
                qty:        String(pi.qty ?? 1),
                lvId:       pi.lvId       ?? null,
                employeeId: pi.employeeId ?? null,
                storeTo:    storeTo,
            }
            autoSelectWarehouse(row)
            return row
        }).filter(r => r && r.variantId)
        if (filled.length) rows.value = filled

        // Auto-fill usage_location with department name if currently empty
        if (!form.usage_location && props.userDeptName) {
            form.usage_location = props.userDeptName
        }
    }
})

// Teleport dropdown
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
}
function closeDropdown() {
    setTimeout(() => { activeDropIdx.value = -1 }, 160)
}

// Show variants that have SOH > 0 in ANY warehouse (per-item warehouse auto-selected)
const warehouseVariants = computed(() => {
    return props.allVariants.filter(v =>
        Object.values(props.stockMap ?? {}).some(wMap => parseFloat(wMap[v.id] ?? 0) > 0)
    )
})

function filteredVariants(row) {
    const base = warehouseVariants.value
    const q = (row.query || '').trim().toLowerCase()
    if (!q) return base.slice(0, 30)
    return base.filter(v =>
        (v.sku        && v.sku.toLowerCase().includes(q))  ||
        (v.name_en    && v.name_en.toLowerCase().includes(q))  ||
        (v.name_id    && v.name_id.toLowerCase().includes(q))  ||
        (v.name_zh    && v.name_zh.toLowerCase().includes(q))  ||
        (v.brand      && v.brand.toLowerCase().includes(q))    ||
        (v.model      && v.model.toLowerCase().includes(q))    ||
        (v.size       && v.size.toLowerCase().includes(q))     ||
        (v.color      && v.color.toLowerCase().includes(q))
    ).slice(0, 30)
}

// Auto-select nearest warehouse with sufficient stock (sorted by sort_order from backend)
// If no warehouse covers the full qty, pick the one with most stock
function autoSelectWarehouse(row) {
    if (!row.variantId) { row.itemWarehouseId = null; return }
    const needed = parseFloat(row.qty) || 0
    let bestWh = null, bestAvail = 0
    for (const wh of (props.warehouses ?? [])) {
        const avail = parseFloat(props.stockMap?.[wh.id]?.[row.variantId] ?? 0)
        if (avail <= 0) continue
        // If this warehouse has enough — pick it immediately (warehouses sorted nearest-first)
        if (needed === 0 || avail >= needed) {
            row.itemWarehouseId = wh.id
            return
        }
        // Track best partial-stock warehouse as fallback
        if (avail > bestAvail) { bestAvail = avail; bestWh = wh.id }
    }
    // No warehouse has enough; use the one with most stock (or null if no stock anywhere)
    row.itemWarehouseId = bestWh
}

function selectVariant(row, v) {
    row.variantId       = v.id
    row.query           = itemName(v) || v.sku
    row.qty             = ''
    row.selectedUom     = 'base'
    row.lvId            = null
    row.employeeId      = null
    activeDropIdx.value = -1
    autoSelectWarehouse(row)
}

function variantOf(row) {
    if (!row.variantId) return null
    return props.allVariants.find(a => a.id === row.variantId) ?? null
}

function rowHasAlt(row) {
    const v = variantOf(row)
    return !!(v?.alt_uom && v?.alt_uom_conversion)
}

function currentUom(row) {
    const v = variantOf(row)
    if (!v) return '—'
    return row.selectedUom === 'alt' ? v.alt_uom : v.base_uom
}

function convHint(row) {
    if (row.selectedUom !== 'alt' || !rowHasAlt(row)) return null
    const raw = parseFloat(row.qty)
    if (isNaN(raw) || raw <= 0) return null
    const v = variantOf(row)
    return `= ${(raw * v.alt_uom_conversion).toLocaleString()} ${v.base_uom}`
}

function baseQty(row) {
    const v   = variantOf(row)
    const qty = parseFloat(row.qty)
    if (!v || isNaN(qty) || qty <= 0) return 0
    return row.selectedUom === 'alt' && v.alt_uom_conversion
        ? qty * v.alt_uom_conversion
        : qty
}

function itemName(v) {
    if (!v) return ''
    if (locale.value === 'zh' && v.name_zh) return v.name_zh
    if (locale.value === 'id' && v.name_id) return v.name_id
    return v.name_en || v.name_id || ''
}

function addRow()       { rows.value.push(newRow()) }
function removeRow(idx) { if (rows.value.length > 1) rows.value.splice(idx, 1) }

// ── SOH ────────────────────────────────────────────────────────────────────
function rowSoh(row) {
    if (!row.variantId) return null
    const whId = row.itemWarehouseId ?? form.warehouse_id
    if (!whId) return null
    const wMap = props.stockMap?.[whId]
    if (!wMap) return null
    return parseFloat(wMap[row.variantId] ?? 0)
}

function rowSohInsufficient(row) {
    const soh = rowSoh(row)
    const qty = baseQty(row)
    if (soh === null || qty <= 0) return false
    return soh < qty
}

// ── Cooldown ────────────────────────────────────────────────────────────────
function rowCooldownBlocked(row) {
    if (!row.variantId) return false
    const v = variantOf(row)
    if (!v?.has_cooldown || !v?.cooldown_track_by) return false
    return (props.cooldownData ?? []).some(cd => {
        if (cd.variant_id !== row.variantId) return false
        if (cd.track_type === 'lv_number'   && cd.lv_id      === row.lvId)       return true
        if (cd.track_type === 'employee_id' && cd.employee_id === row.employeeId) return true
        return false
    })
}

function rowCooldownUntil(row) {
    if (!row.variantId) return null
    const v = variantOf(row)
    if (!v?.has_cooldown || !v?.cooldown_track_by) return null
    const cd = (props.cooldownData ?? []).find(cd => {
        if (cd.variant_id !== row.variantId) return false
        if (cd.track_type === 'lv_number'   && cd.lv_id      === row.lvId)       return true
        if (cd.track_type === 'employee_id' && cd.employee_id === row.employeeId) return true
        return false
    })
    return cd?.cooldown_until ?? null
}

function fmtCooldownDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(locale.value === 'id' ? 'id-ID' : 'en-US', {
        day: 'numeric', month: 'long', year: 'numeric',
    })
}

const hasCooldownItem = computed(() =>
    rows.value.some(r => variantOf(r)?.has_cooldown)
)

const hasBlockedItem = computed(() =>
    rows.value.some(r => rowCooldownBlocked(r))
)

// ── submit ─────────────────────────────────────────────────────────────────
const validRows = computed(() =>
    rows.value.filter(r => r.variantId && r.qty !== '' && !isNaN(parseFloat(r.qty)) && parseFloat(r.qty) > 0)
)

// Draft can be saved with just warehouse + 1 item (purpose/location can be filled later)
const canSubmit = computed(() => {
    if (form.processing) return false
    if (!form.warehouse_id) return false
    if (validRows.value.length === 0) return false
    if (hasBlockedItem.value) return false
    return true
})

function submit() {
    const items = validRows.value.map(r => ({
        variant_id:         r.variantId,
        requested_qty:      parseFloat(r.qty),
        uom:                currentUom(r),
        base_qty:           baseQty(r),
        lv_id:              r.lvId || null,
        employee_id:        r.employeeId || null,
        store_to:           r.storeTo || null,
        item_reason:        r.itemReason || null,
        notes:              r.notes || null,
        item_warehouse_id:  r.itemWarehouseId || null,
    }))

    // Use transform so photos (File objects) are included as FormData
    form
        .transform(data => ({ ...data, items, photos: photoFiles.value }))
        .post(route('gi.store'), { forceFormData: true, preserveScroll: true })
}
</script>

<template>
<AppLayout>
  <template #title>{{ $t('gi.createTitle') }}</template>
  <template #breadcrumb>
    <Link :href="route('gi.index')" class="bc-link">{{ $t('gi.title') }}</Link>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
      stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
      <path d="m9 18 6-6-6-6"/>
    </svg>
    {{ $t('gi.createTitle') }}
  </template>

  <div class="gi-wrap">

    <!-- ── Page header ──────────────────────────────────────── -->
    <div class="gi-hdr">
      <div>
        <h2 class="gi-title">{{ $t('gi.createTitle') }}</h2>
        <p class="gi-sub">{{ $t('gi.createSub') }}</p>
      </div>
      <Link :href="route('gi.index')" class="gi-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
          <path d="m15 18-6-6 6-6"/>
        </svg>
        {{ $t('btn.back') }}
      </Link>
    </div>

    <form @submit.prevent="submit" enctype="multipart/form-data">
    <div class="gi-body">

      <!-- ── LEFT: Request info ──────────────────────────────── -->
      <div class="gi-card gi-card-left">
        <div class="gi-sec-lbl">{{ $t('gi.sectionInfo') }}</div>

        <!-- Department (read-only) -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.department') }}</label>
          <div class="gi-readonly-val">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            {{ userDeptName || '—' }}
          </div>
        </div>

        <!-- Warehouse -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.warehouse') }} <span class="gi-req">*</span></label>
          <select class="gi-input" v-model="form.warehouse_id" required>
            <option value="">{{ $t('gi.selectWh') }}</option>
            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
              {{ wh.code }} · {{ wh.name }}
            </option>
          </select>
          <span class="gi-err" v-if="form.errors.warehouse_id">{{ form.errors.warehouse_id }}</span>
        </div>

        <!-- Project -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.project') }}</label>
          <input class="gi-input" type="text" v-model="form.project"
            :placeholder="$t('gi.projectPh')" maxlength="255" />
        </div>

        <!-- Purpose -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.purpose') }} <span class="gi-req">*</span></label>
          <textarea class="gi-input gi-ta" v-model="form.purpose"
            :placeholder="$t('gi.purposePh')" rows="3" required />
          <span class="gi-err" v-if="form.errors.purpose">{{ form.errors.purpose }}</span>
        </div>

        <!-- Usage Location -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.usageLocation') }} <span class="gi-req">*</span></label>
          <input class="gi-input" type="text" v-model="form.usage_location"
            :placeholder="$t('gi.usageLocationPh')" maxlength="255" required />
          <span class="gi-err" v-if="form.errors.usage_location">{{ form.errors.usage_location }}</span>
        </div>

        <!-- Notes -->
        <div class="gi-fg">
          <label class="gi-lbl">{{ $t('gi.notes') }}</label>
          <textarea class="gi-input gi-ta" v-model="form.notes"
            :placeholder="$t('gi.notesPh')" rows="2" />
        </div>
      </div>

      <!-- ── RIGHT: Items ──────────────────────────────────────── -->
      <div class="gi-card gi-card-right">

        <!-- Pre-fill banner (from employee requests) -->
        <div v-if="sourceRequestIds?.length" class="gi-prefill-banner">
          📋 {{ $t('gi.prefilledBanner').replace('{n}', sourceRequestIds.length) }}
        </div>

        <div class="gi-sec-lbl">
          {{ $t('gi.sectionItems') }}
          <button type="button" class="gi-add-row" @click="addRow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round" stroke-linejoin="round" width="11" height="11">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            {{ $t('gi.addItem') }}
          </button>
        </div>

        <!-- Rows -->
        <div class="gi-rows">
          <div class="gi-row" v-for="(row, idx) in rows" :key="idx"
            :class="{ 'row-blocked': rowCooldownBlocked(row) }">

            <div class="gi-row-top">
              <span class="gi-no">{{ idx + 1 }}</span>

              <!-- SKU search -->
              <div class="gi-combo-wrap">
                <input
                  class="gi-input gi-combo"
                  :class="{ 'has-val': row.variantId }"
                  type="text"
                  v-model="row.query"
                  :placeholder="$t('gi.searchVariant')"
                  autocomplete="off"
                  @focus="openDropdown(idx, $event)"
                  @blur="closeDropdown()"
                  @input="row.variantId = null; row.lvId = null; row.employeeId = null"
                />
                <div v-if="row.variantId" class="gi-item-detail">
                  <span class="gi-detail-sku">{{ variantOf(row)?.sku }}</span>
                  <span v-if="variantOf(row)?.brand" class="gi-detail-tag gi-detail-brand">{{ variantOf(row).brand }}</span>
                  <span v-if="variantOf(row)?.model" class="gi-detail-tag gi-detail-model">{{ variantOf(row).model }}</span>
                  <span v-if="variantOf(row)?.size"  class="gi-detail-tag">{{ variantOf(row).size }}</span>
                  <span v-if="variantOf(row)?.color" class="gi-detail-tag">{{ variantOf(row).color }}</span>
                </div>
              </div>

              <!-- Qty -->
              <input class="gi-input gi-qty" type="number" v-model="row.qty"
                step="any" min="0.01"
                :disabled="!row.variantId"
                :placeholder="row.variantId ? '0' : '—'"
              />

              <!-- UOM -->
              <div class="gi-uom-cell">
                <template v-if="row.variantId && rowHasAlt(row)">
                  <div class="gi-uom-toggle">
                    <button type="button" class="gi-uom-btn"
                      :class="{ active: row.selectedUom === 'base' }"
                      @click="row.selectedUom = 'base'; row.qty = ''">
                      {{ variantOf(row).base_uom }}
                    </button>
                    <button type="button" class="gi-uom-btn"
                      :class="{ active: row.selectedUom === 'alt' }"
                      @click="row.selectedUom = 'alt'; row.qty = ''">
                      {{ variantOf(row).alt_uom }}
                    </button>
                  </div>
                  <span v-if="convHint(row)" class="gi-conv-hint">{{ convHint(row) }}</span>
                </template>
                <span v-else class="gi-uom-static">{{ currentUom(row) }}</span>
              </div>

              <!-- Delete -->
              <button type="button" class="gi-del" @click="removeRow(idx)"
                :disabled="rows.length === 1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                  <path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
              </button>
            </div>

            <!-- ── Row detail (shown when variant selected) ── -->
            <div v-if="row.variantId" class="gi-row-detail">

              <!-- Warehouse chip (auto-selected, overridable) -->
              <div class="gi-detail-field gi-detail-wh">
                <label class="gi-det-lbl">Gudang Item</label>
                <select class="gi-input gi-input-sm gi-wh-select" v-model="row.itemWarehouseId">
                  <option :value="null">— (Ikuti header)</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.code }} · {{ wh.name }}
                  </option>
                </select>
              </div>

              <!-- Insufficient SOH warning for selected warehouse -->
              <div v-if="rowSohInsufficient(row)" class="gi-soh-chip soh-low">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span>Stok di gudang ini tidak mencukupi</span>
              </div>

              <!-- Store To -->
              <div class="gi-detail-field">
                <label class="gi-det-lbl">{{ $t('gi.storeTo') }}</label>
                <input class="gi-input gi-input-sm" type="text" v-model="row.storeTo"
                  :placeholder="$t('gi.storeToPh')" maxlength="255" />
              </div>

              <!-- LV Number (if cooldown_track_by = lv_number) -->
              <div v-if="variantOf(row)?.cooldown_track_by === 'lv_number'" class="gi-detail-field">
                <label class="gi-det-lbl gi-det-req">{{ $t('gi.selectLv') }} <span class="gi-req">*</span></label>
                <select class="gi-input gi-input-sm" v-model="row.lvId">
                  <option :value="null">{{ $t('gi.selectLv') }}…</option>
                  <option v-for="lv in vehicles" :key="lv.id" :value="lv.id">
                    {{ lv.full_number ?? lv.lv_number }}
                  </option>
                </select>
              </div>

              <!-- Employee ID (if cooldown_track_by = employee_id) -->
              <div v-if="variantOf(row)?.cooldown_track_by === 'employee_id'" class="gi-detail-field">
                <label class="gi-det-lbl gi-det-req">{{ $t('gi.selectEmployee') }} <span class="gi-req">*</span></label>
                <select class="gi-input gi-input-sm" v-model="row.employeeId">
                  <option :value="null">{{ $t('gi.selectEmployee') }}…</option>
                  <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                    {{ emp.employee_id }} · {{ emp.name }}
                  </option>
                </select>
              </div>

              <!-- Cooldown blocked alert -->
              <div v-if="rowCooldownBlocked(row)" class="gi-cooldown-alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                  <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                ⛔ {{ $t('gi.cooldownBlocked', { date: fmtCooldownDate(rowCooldownUntil(row)) }) }}
              </div>

              <!-- Item Reason (if has_cooldown) -->
              <div v-if="variantOf(row)?.has_cooldown" class="gi-detail-field">
                <label class="gi-det-lbl gi-det-req">{{ $t('gi.itemReason') }} <span class="gi-req">*</span></label>
                <textarea class="gi-input gi-ta gi-input-sm" v-model="row.itemReason"
                  :placeholder="$t('gi.itemReasonPh')" rows="2" />
              </div>

            </div><!-- /.gi-row-detail -->

          </div>
        </div>

        <span class="gi-err" v-if="form.errors.items">{{ form.errors.items }}</span>

        <!-- Footer -->
        <div class="gi-footer">
          <div class="gi-valid-hint">
            <template v-if="validRows.length">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                <path d="M20 6 9 17l-5-5"/>
              </svg>
              {{ $t('gi.validItems', { n: validRows.length }) }}
            </template>
          </div>
          <div class="gi-footer-btns">
            <Link :href="route('gi.index')" class="gi-cancel">{{ $t('btn.cancel') }}</Link>
            <button type="submit" class="gi-save" :disabled="!canSubmit">
              <svg v-if="form.processing" class="gi-spin" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                stroke-linejoin="round" width="14" height="14">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
              </svg>
              {{ form.processing ? $t('gi.saving') : $t('gi.saveDraft') }}
            </button>
          </div>
        </div>
      </div>

    </div><!-- /.gi-body -->

    <!-- ── Photo Upload Card ─────────────────────────────────── -->
    <div class="gi-card gi-photo-card">
      <div class="gi-sec-lbl">
        {{ $t('gi.photosRequest') }}
        <span v-if="hasCooldownItem" class="gi-photo-req">
          ⚠ {{ $t('gi.photoRequired') }}
        </span>
      </div>

      <div class="gi-photo-upload-area">
        <label class="gi-photo-label" :class="{ 'photo-warn': hasCooldownItem && photoFiles.length === 0 }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
            stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
          </svg>
          <span>{{ $t('gi.photosUploadPh') }}</span>
          <input type="file" class="gi-photo-input" multiple accept="image/*"
            @change="onPhotoChange" />
        </label>
        <span class="gi-err" v-if="form.errors.photos">{{ form.errors.photos }}</span>
      </div>

      <!-- Preview thumbnails -->
      <div v-if="photoPreviews.length" class="gi-photo-previews">
        <div v-for="(src, i) in photoPreviews" :key="i"
          class="gi-photo-thumb" @click="openLightbox(src)">
          <img :src="src" />
          <span class="gi-photo-num">{{ i+1 }}</span>
          <button type="button" class="gi-photo-del" @click.stop="removePhoto(i)">×</button>
        </div>
      </div>
    </div>

    </form>

  </div>

  <!-- Lightbox -->
  <Teleport to="body">
    <div v-if="lightboxSrc" class="lb-overlay" @click.self="closeLightbox">
      <img :src="lightboxSrc" class="lb-img" />
      <button class="lb-close" @click="closeLightbox">×</button>
    </div>
  </Teleport>

  <!-- Teleport dropdown -->
  <Teleport to="body">
    <div v-if="activeDropIdx >= 0" class="gi-drop-fixed" :style="dropFixedStyle">
      <template v-if="filteredVariants(rows[activeDropIdx]).length">
        <div
          v-for="v in filteredVariants(rows[activeDropIdx])" :key="v.id"
          class="gi-opt"
          @mousedown.prevent="selectVariant(rows[activeDropIdx], v)"
        >
          <div class="gi-opt-main">
            <span class="gi-opt-name">{{ itemName(v) }}</span>
            <span class="gi-opt-sku">{{ v.sku }}</span>
          </div>
          <div class="gi-opt-tags">
            <span v-if="v.brand"  class="gi-tag gi-tag-brand">{{ v.brand }}</span>
            <span v-if="v.model"  class="gi-tag gi-tag-model">{{ v.model }}</span>
            <span v-if="v.size"   class="gi-tag gi-tag-spec">{{ v.size }}</span>
            <span v-if="v.color"  class="gi-tag gi-tag-spec">{{ v.color }}</span>
            <span v-if="v.has_cooldown" class="gi-tag gi-tag-cooldown">⏱ cooldown</span>
          </div>
        </div>
      </template>
      <div v-else class="gi-opt gi-opt-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        {{ $t('gi.noVariantFound') }}
      </div>
    </div>
  </Teleport>

</AppLayout>
</template>

<style scoped>
.bc-link { color:var(--orange-500); text-decoration:none; font-weight:600 }
.bc-link:hover { text-decoration:underline }

.gi-wrap  { display:flex; flex-direction:column; gap:18px; width:100% }
.gi-hdr   { display:flex; align-items:flex-start; justify-content:space-between; gap:12px }
.gi-title { font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.02em }
.gi-sub   { font-size:13px; color:var(--fg-2); margin-top:3px }
.gi-back  {
  display:inline-flex; align-items:center; gap:5px; padding:8px 14px; border-radius:8px;
  font-size:13px; font-weight:600; background:var(--surface); border:1px solid var(--border-2);
  color:var(--fg-2); text-decoration:none; transition:background 150ms; white-space:nowrap;
}
.gi-back:hover { background:var(--hover); color:var(--fg) }

.gi-body {
  display:grid; grid-template-columns:300px 1fr; gap:16px; align-items:start;
}
@media (max-width:760px) { .gi-body { grid-template-columns:1fr } }

.gi-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:14px; padding:20px; box-shadow:var(--shadow-sm);
  display:flex; flex-direction:column; gap:14px;
}
.gi-photo-card { flex-direction:column; gap:12px }

.gi-sec-lbl {
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;
  font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em;
  color:var(--fg-dim); padding-bottom:10px; border-bottom:1px solid var(--border);
}
.gi-photo-req { font-size:11px; color:#f87171; text-transform:none; letter-spacing:0; font-weight:600 }

.gi-fg  { display:flex; flex-direction:column; gap:5px }
.gi-lbl { font-size:12px; font-weight:600; color:var(--fg-2) }
.gi-req { color:#f87171 }

.gi-readonly-val {
  display:flex; align-items:center; gap:7px;
  padding:8px 11px; font-size:13px; font-weight:600;
  background:rgba(249,115,22,.06); border:1px solid rgba(249,115,22,.2);
  border-radius:8px; color:var(--orange-500);
}

.gi-input {
  padding:8px 11px; font-size:13px; background:var(--surface-2);
  border:1px solid var(--border-2); border-radius:8px; color:var(--fg);
  outline:none; font-family:inherit; transition:border-color 180ms;
  width:100%; box-sizing:border-box;
}
.gi-input:focus   { border-color:var(--orange-500) }
.gi-input:disabled { opacity:.45; cursor:default }
.gi-ta  { resize:vertical; min-height:74px; line-height:1.5 }
.gi-input-sm { font-size:12px; padding:6px 10px }
.gi-err { font-size:11.5px; color:#f87171 }

.gi-add-row {
  display:inline-flex; align-items:center; gap:5px; padding:4px 10px;
  border-radius:6px; font-size:12px; font-weight:600;
  background:rgba(249,115,22,.1); color:var(--orange-500);
  border:1px solid rgba(249,115,22,.25); cursor:pointer; font-family:inherit; white-space:nowrap;
}
.gi-add-row:hover { background:rgba(249,115,22,.18) }

.gi-rows { display:flex; flex-direction:column; gap:8px }
.gi-row {
  background:var(--surface-2); border:1px solid var(--border);
  border-radius:10px; overflow:hidden;
}
.gi-row.row-blocked { border-color:rgba(239,68,68,.4); background:rgba(239,68,68,.04) }

.gi-row-top {
  display:grid; grid-template-columns:28px 1fr 90px 110px 32px;
  gap:8px; align-items:start; padding:8px;
}
.gi-no { font-size:11px; font-weight:700; color:var(--fg-dim); text-align:center; padding-top:9px }

.gi-combo-wrap { position:relative; display:flex; flex-direction:column; gap:3px }
.gi-combo { width:100%; box-sizing:border-box }
.gi-combo.has-val { border-color:rgba(249,115,22,.4); background:rgba(249,115,22,.06) }
.gi-item-detail { display:flex; flex-wrap:wrap; align-items:center; gap:4px; padding:2px 2px 0 }
.gi-detail-sku   { font-family:monospace; font-size:10.5px; font-weight:700; color:var(--orange-500) }
.gi-detail-tag   { font-size:10.5px; padding:1px 6px; border-radius:4px; background:var(--surface-3); color:var(--fg-dim); border:1px solid var(--border) }
.gi-detail-brand { color:var(--fg-2); font-weight:600; background:rgba(249,115,22,.08); border-color:rgba(249,115,22,.2) }
.gi-detail-model { color:var(--fg-2) }

.gi-qty { text-align:right }

.gi-uom-cell  { display:flex; flex-direction:column; gap:3px; align-items:flex-start }
.gi-uom-toggle { display:inline-flex; border:1px solid var(--border-2); border-radius:7px; overflow:hidden; width:100% }
.gi-uom-btn {
  flex:1; padding:5px 4px; font-size:11px; font-weight:700;
  cursor:pointer; background:transparent; border:none;
  color:var(--fg-2); font-family:inherit; transition:background 150ms, color 150ms;
  white-space:nowrap; text-align:center;
}
.gi-uom-btn.active { background:var(--orange-500); color:#fff }
.gi-uom-btn:not(.active):hover { background:rgba(249,115,22,.1); color:var(--orange-500) }
.gi-uom-static { font-size:12px; font-weight:700; color:var(--fg-dim); padding-top:7px }
.gi-conv-hint  { font-size:10.5px; font-weight:600; color:var(--orange-500) }

.gi-del {
  appearance:none; border:1px solid var(--border-2); background:transparent;
  width:32px; height:32px; border-radius:6px; cursor:pointer; color:var(--fg-dim);
  display:grid; place-items:center; transition:background 150ms, color 150ms;
}
.gi-del:hover:not(:disabled) { background:rgba(239,68,68,.12); color:#f87171; border-color:rgba(239,68,68,.25) }
.gi-del:disabled { opacity:.3; cursor:default }

/* row detail area */
.gi-row-detail {
  padding:0 10px 10px 10px;
  border-top:1px solid var(--border);
  display:flex; flex-direction:column; gap:8px;
  background:var(--surface-2);
}

.gi-det-lbl { font-size:11px; font-weight:600; color:var(--fg-dim); display:block; margin-bottom:3px }
.gi-det-req { color:var(--fg-2) }
.gi-detail-field { display:flex; flex-direction:column }

.gi-soh-chip {
  display:inline-flex; align-items:center; gap:5px; margin-top:8px;
  padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:600;
}
.soh-ok  { background:rgba(16,185,129,.1); color:#34d399 }
.soh-low { background:rgba(239,68,68,.1);  color:#f87171 }
.soh-warn { font-weight:400; opacity:.85 }

.gi-cooldown-alert {
  display:flex; align-items:center; gap:6px;
  padding:8px 12px; border-radius:8px;
  background:rgba(239,68,68,.12); color:#f87171;
  border:1px solid rgba(239,68,68,.25);
  font-size:12px; font-weight:600;
}

/* footer */
.gi-footer {
  display:flex; align-items:center; justify-content:space-between;
  padding-top:14px; border-top:1px solid var(--border); gap:12px;
}
.gi-valid-hint  { display:flex; align-items:center; gap:5px; font-size:12px; color:#34d399; font-weight:600 }
.gi-footer-btns { display:flex; gap:10px; flex-shrink:0 }
.gi-cancel {
  display:inline-flex; align-items:center; padding:8px 16px; border-radius:8px;
  font-size:13px; font-weight:600; background:transparent; border:1px solid var(--border-2);
  color:var(--fg-2); text-decoration:none; transition:background 180ms;
}
.gi-cancel:hover { background:var(--hover); color:var(--fg) }
.gi-save {
  padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500)); color:#fff; border:0;
  box-shadow:0 4px 10px -3px rgba(249,115,22,.45); transition:opacity 180ms;
  display:inline-flex; align-items:center; gap:7px;
}
.gi-save:disabled { opacity:.55; cursor:default }

/* photo upload */
.gi-photo-upload-area { display:flex; flex-direction:column; gap:6px }
.gi-photo-label {
  display:flex; flex-direction:column; align-items:center; gap:8px;
  padding:24px 16px; border:2px dashed var(--border-2); border-radius:10px;
  cursor:pointer; color:var(--fg-dim); font-size:12.5px; text-align:center;
  transition:border-color 180ms, background 180ms;
}
.gi-photo-label:hover { border-color:var(--orange-500); background:rgba(249,115,22,.04) }
.gi-photo-label.photo-warn { border-color:#f87171; background:rgba(239,68,68,.04) }
.gi-photo-input { display:none }

.gi-photo-previews {
  display:flex; flex-wrap:wrap; gap:8px;
}
.gi-photo-thumb {
  position:relative; width:72px; height:72px; border-radius:8px; overflow:hidden;
  border:1px solid var(--border-2); cursor:pointer;
}
.gi-photo-thumb img { width:100%; height:100%; object-fit:cover; transition:transform 200ms }
.gi-photo-thumb:hover img { transform:scale(1.06) }
.gi-photo-num {
  position:absolute; top:2px; left:4px; font-size:9px; font-weight:800;
  color:#fff; text-shadow:0 1px 2px rgba(0,0,0,.8);
}
.gi-photo-del {
  position:absolute; top:2px; right:2px; width:18px; height:18px;
  border-radius:50%; background:rgba(239,68,68,.9); color:#fff;
  border:0; cursor:pointer; font-size:14px; font-weight:700;
  display:flex; align-items:center; justify-content:center; line-height:1;
  opacity:0; transition:opacity 150ms; padding:0;
}
.gi-photo-thumb:hover .gi-photo-del { opacity:1 }

/* lightbox */
.lb-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999;
  display:flex; align-items:center; justify-content:center; padding:20px;
}
.lb-img    { max-width:100%; max-height:90vh; border-radius:12px; object-fit:contain }
.lb-close  {
  position:absolute; top:16px; right:20px; font-size:32px; color:#fff;
  background:none; border:0; cursor:pointer; line-height:1; opacity:.8; transition:opacity 150ms;
}
.lb-close:hover { opacity:1 }

/* dropdown */
.gi-drop-fixed {
  background:var(--surface); border:1px solid var(--border-2);
  border-radius:9px; box-shadow:0 10px 32px rgba(0,0,0,.5);
  overflow:hidden; max-height:220px; overflow-y:auto;
}
.gi-opt { display:flex; flex-direction:column; gap:5px; padding:10px 14px; cursor:pointer; transition:background 120ms }
.gi-opt:hover  { background:rgba(249,115,22,.08) }
.gi-opt-main   { display:flex; align-items:center; justify-content:space-between; gap:8px }
.gi-opt-name   { font-size:13px; font-weight:700; color:var(--fg); overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
.gi-opt-sku    { font-family:monospace; font-size:11px; font-weight:700; color:var(--orange-500); flex-shrink:0; white-space:nowrap; background:rgba(249,115,22,.1); padding:1px 6px; border-radius:4px }
.gi-opt-tags   { display:flex; flex-wrap:wrap; gap:4px }
.gi-tag        { font-size:10.5px; padding:1px 6px; border-radius:4px; background:var(--surface-3); color:var(--fg-dim); border:1px solid var(--border); white-space:nowrap }
.gi-tag-brand  { color:var(--fg-2); font-weight:600; background:rgba(249,115,22,.08); border-color:rgba(249,115,22,.2) }
.gi-tag-model  { color:var(--fg-2) }
.gi-tag-spec   { color:var(--fg-dim) }
.gi-tag-cooldown { color:#fbbf24; background:rgba(251,191,36,.1); border-color:rgba(251,191,36,.3) }
.gi-opt-empty  { color:var(--fg-dim); font-size:12px; cursor:default; display:flex; align-items:center; gap:7px }

@keyframes gi-spin { to { transform:rotate(360deg) } }
.gi-spin { animation:gi-spin .8s linear infinite; flex-shrink:0 }

/* Per-row warehouse select */
.gi-detail-wh { flex-direction:row; align-items:center; gap:10px; flex-wrap:wrap }
.gi-wh-select { width:auto; min-width:160px; flex:1 }

/* Pre-fill banner */
.gi-prefill-banner {
  display:flex; align-items:center; gap:8px;
  padding:10px 14px; border-radius:9px;
  background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.25);
  color:#60a5fa; font-size:12.5px; font-weight:600;
  margin-bottom:4px;
}
</style>
