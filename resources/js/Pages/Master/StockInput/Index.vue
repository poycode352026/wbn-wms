<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    variants:    Object,
    allVariants: Array,
    categories:  Array,
    warehouses:  Array,
    filters:     Object,
    stats:       Object,
})

// ── flash ──────────────────────────────────────────────────────────────────
const flash = computed(() => page.props.flash ?? {})

// ── import modal ────────────────────────────────────────────────────────────
const importOpen = ref(false)
const importForm = useForm({ file: null })
const importFileRef = ref(null)
const importFileName = ref('')

function onImportFile(e) {
    const f = e.target.files[0]
    if (!f) return
    importForm.file = f
    importFileName.value = f.name
}

function submitImport() {
    importForm.post(route('stock-input.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importOpen.value = false
            importForm.reset()
            importFileName.value = ''
            router.reload({ only: ['variants', 'stats'], preserveScroll: true })
        },
    })
}

// ── filters ────────────────────────────────────────────────────────────────
const search     = ref(props.filters.search      ?? '')
const categoryId = ref(props.filters.category_id ?? '')

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(), 400)
})
watch(categoryId, () => applyFilters())

function applyFilters() {
    router.get(route('stock-input.index'), {
        search:      search.value || undefined,
        category_id: categoryId.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

// ── i18n helpers ───────────────────────────────────────────────────────────
function catName(cat) {
    if (!cat) return '—'
    if (locale.value === 'zh' && cat.name_zh) return cat.name_zh
    if (locale.value === 'id' && cat.name_id) return cat.name_id
    return cat.name_en
}

function itemName(item) {
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en
}

function variantLabel(v) {
    const parts = [v.brand, v.model, v.size, v.color].filter(Boolean)
    return parts.length ? parts.join(' · ') : '—'
}

// ── pagination ─────────────────────────────────────────────────────────────
function goPage(url) {
    if (!url) return
    router.get(url, {}, { preserveState: true, preserveScroll: true })
}

// ── modal state ────────────────────────────────────────────────────────────
const modalOpen     = ref(false)
const activeVariant = ref(null)
const entries       = ref([])
const lightboxSrc   = ref(null)
const selectedUom   = ref('base')   // 'base' | 'alt'

function openModal(variant) {
    activeVariant.value = variant
    entries.value = [...(variant.stock_ledgers ?? [])]
    addForm.warehouse_id = ''
    addForm.location_id  = ''
    addForm.qty_on_hand  = ''
    addForm.clearErrors()
    selectedUom.value = 'base'
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    activeVariant.value = null
    lightboxSrc.value = null
    selectedUom.value = 'base'
}

// ── UOM helpers ─────────────────────────────────────────────────────────────
const activeItem = computed(() => activeVariant.value?.item ?? null)

const hasAltUom = computed(() =>
    !!(activeItem.value?.alt_uom && activeItem.value?.alt_uom_conversion)
)

const currentUomLabel = computed(() =>
    selectedUom.value === 'alt' && hasAltUom.value
        ? activeItem.value.alt_uom
        : activeItem.value?.base_uom ?? ''
)

// Qty in base UOM that will actually be saved
const qtyInBase = computed(() => {
    const raw = parseFloat(addForm.qty_on_hand)
    if (isNaN(raw) || raw < 0) return null
    if (selectedUom.value === 'alt' && hasAltUom.value) {
        return raw * activeItem.value.alt_uom_conversion
    }
    return raw
})

// Human-readable conversion hint below the qty input
const conversionHint = computed(() => {
    if (selectedUom.value !== 'alt' || !hasAltUom.value) return null
    const raw = parseFloat(addForm.qty_on_hand)
    if (isNaN(raw) || raw <= 0) return null
    const base = raw * activeItem.value.alt_uom_conversion
    return `= ${base.toLocaleString()} ${activeItem.value.base_uom}`
})

// ── add / update form ──────────────────────────────────────────────────────
const addForm = useForm({
    warehouse_id: '',
    location_id:  '',
    qty_on_hand:  '',
})

// ── warehouse → locations ───────────────────────────────────────────────────
const availableLocations = computed(() => {
    if (!addForm.warehouse_id) return []
    const wh = props.warehouses.find(w => w.id == addForm.warehouse_id)
    return wh ? wh.locations : []
})

watch(() => addForm.warehouse_id, () => {
    addForm.location_id = ''
})

function submitAdd() {
    if (!activeVariant.value) return
    // convert to base UOM before sending
    if (selectedUom.value === 'alt' && hasAltUom.value && qtyInBase.value !== null) {
        addForm.qty_on_hand = qtyInBase.value
    }
    addForm.post(route('stock-input.upsert', activeVariant.value.id), {
        preserveState:  true,
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['variants', 'stats'],
                preserveScroll: true,
                onSuccess: () => {
                    const fresh = props.variants.data.find(v => v.id === activeVariant.value.id)
                    if (fresh) {
                        entries.value = [...(fresh.stock_ledgers ?? [])]
                        activeVariant.value = fresh
                    }
                    addForm.reset()
                },
            })
        },
    })
}

// ── delete ─────────────────────────────────────────────────────────────────
const deletingId = ref(null)
const deleteForm = useForm({})

function confirmDelete(entryId) {
    if (!confirm(t('si.confirmDel'))) return
    deletingId.value = entryId
    deleteForm.delete(route('stock-input.destroy', entryId), {
        preserveState:  true,
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['variants', 'stats'],
                preserveScroll: true,
                onSuccess: () => {
                    const fresh = props.variants.data.find(v => v.id === activeVariant.value?.id)
                    if (fresh) {
                        entries.value = [...(fresh.stock_ledgers ?? [])]
                        activeVariant.value = fresh
                    }
                    deletingId.value = null
                },
            })
        },
        onError: () => { deletingId.value = null },
    })
}

// ── BULK STOCK MODAL ───────────────────────────────────────────────────────
const bulkOpen        = ref(false)
const bulkWh          = ref('')
const bulkLocId       = ref('')
const bulkProcessing  = ref(false)

// Each row: { variantId, query, qty, selectedUom }
const newBulkRow = () => ({
    variantId:   null,
    query:       '',
    qty:         '',
    selectedUom: 'base',   // 'base' | 'alt'
})
const bulkRows = ref([newBulkRow()])

// Teleported dropdown state
const activeDropIdx  = ref(-1)
const dropFixedStyle = ref({})

const bulkLocations = computed(() => {
    if (!bulkWh.value) return []
    const wh = props.warehouses.find(w => w.id == bulkWh.value)
    return wh ? wh.locations : []
})

watch(() => bulkWh.value, () => { bulkLocId.value = '' })

function openBulk() {
    bulkWh.value        = ''
    bulkLocId.value     = ''
    bulkRows.value      = [newBulkRow()]
    activeDropIdx.value = -1
    bulkOpen.value      = true
}
function closeBulk() {
    activeDropIdx.value = -1
    bulkOpen.value      = false
}

function addBulkRow() { bulkRows.value.push(newBulkRow()) }
function removeBulkRow(idx) {
    if (bulkRows.value.length > 1) bulkRows.value.splice(idx, 1)
}

// Filter allVariants by row's query, cap at 10 results
function filteredVariants(row) {
    const q = (row.query || '').trim().toLowerCase()
    if (!q) return props.allVariants.slice(0, 8)
    return props.allVariants.filter(v =>
        v.sku.toLowerCase().includes(q) ||
        (v.name_en && v.name_en.toLowerCase().includes(q)) ||
        (v.name_id && v.name_id.toLowerCase().includes(q)) ||
        (v.label   && v.label.toLowerCase().includes(q))
    ).slice(0, 8)
}

function selectBulkVariant(row, v) {
    row.variantId       = v.id
    row.query           = v.label || v.sku
    row.qty             = ''
    row.selectedUom     = 'base'
    activeDropIdx.value = -1
}

// Get the full allVariants record for a row
function variantOf(row) {
    if (!row.variantId) return null
    return props.allVariants.find(a => a.id === row.variantId) ?? null
}

// Does this row's variant have an alt UOM?
function rowHasAlt(row) {
    const v = variantOf(row)
    return !!(v?.alt_uom && v?.alt_uom_conversion)
}

// Conversion hint: "= X base_uom" when alt selected
function rowConvHint(row) {
    if (row.selectedUom !== 'alt' || !rowHasAlt(row)) return null
    const raw = parseFloat(row.qty)
    if (isNaN(raw) || raw <= 0) return null
    const v   = variantOf(row)
    return `= ${(raw * v.alt_uom_conversion).toLocaleString()} ${v.base_uom}`
}

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

function submitBulk() {
    if (bulkProcessing.value) return

    const entries = bulkRows.value
        .filter(r => r.variantId && r.qty !== '' && !isNaN(parseFloat(r.qty)))
        .map(r => {
            const v   = variantOf(r)
            let qty   = parseFloat(r.qty)
            // convert alt UOM → base UOM before sending
            if (r.selectedUom === 'alt' && v?.alt_uom_conversion) {
                qty = qty * v.alt_uom_conversion
            }
            return { variant_id: r.variantId, qty_on_hand: qty }
        })

    if (!entries.length || !bulkLocId.value) return

    bulkProcessing.value = true
    router.post(route('stock-input.bulk'), {
        location_id: bulkLocId.value,
        entries,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkOpen.value       = false
            bulkProcessing.value = false
            router.reload({ only: ['variants', 'stats', 'allVariants'], preserveScroll: true })
        },
        onError: () => { bulkProcessing.value = false },
    })
}

const bulkValidRows = computed(() =>
    bulkRows.value.filter(r => r.variantId && r.qty !== '' && !isNaN(parseFloat(r.qty))).length
)

// ── stat card config ───────────────────────────────────────────────────────
const STAT_COLORS = [
    { bg: 'rgba(59,130,246,.12)',  icon: '#60a5fa' },
    { bg: 'rgba(16,185,129,.12)',  icon: '#34d399' },
    { bg: 'rgba(239,68,68,.12)',   icon: '#f87171' },
    { bg: 'rgba(249,115,22,.12)',  icon: '#fb923c' },
]
</script>

<template>
<AppLayout>
  <template #title>{{ $t('si.title') }}</template>
  <template #breadcrumb>{{ $t('si.title') }}</template>

  <!-- flash -->
  <div v-if="flash.success" class="si-flash si-flash-ok">{{ flash.success }}</div>
  <div v-if="flash.error"   class="si-flash si-flash-err">{{ flash.error }}</div>

  <!-- ── STAT CARDS ──────────────────────────────────────────────────────── -->
  <div class="si-stat-row">
    <div class="si-stat" v-for="(s, i) in [
      { key: 'totalVariants', icon: 'box',   lbl: $t('si.totalVariants') },
      { key: 'withStock',     icon: 'check', lbl: $t('si.withStock')     },
      { key: 'noStock',       icon: 'alert', lbl: $t('si.noStock')       },
      { key: 'totalQty',      icon: 'chart', lbl: $t('si.totalQty')      },
    ]" :key="i">
      <div class="si-stat-ico" :style="{ background: STAT_COLORS[i].bg }">
        <svg v-if="s.icon==='box'" :style="{ color: STAT_COLORS[i].icon }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8L12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        <svg v-else-if="s.icon==='check'" :style="{ color: STAT_COLORS[i].icon }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        <svg v-else-if="s.icon==='alert'" :style="{ color: STAT_COLORS[i].icon }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <svg v-else :style="{ color: STAT_COLORS[i].icon }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 16 4-4 4 4 4-6"/></svg>
      </div>
      <div class="si-stat-body">
        <div class="si-stat-val">{{ (stats[s.key] ?? 0).toLocaleString() }}</div>
        <div class="si-stat-lbl">{{ s.lbl }}</div>
      </div>
    </div>
  </div>

  <!-- ── TOOLBAR ─────────────────────────────────────────────────────────── -->
  <div class="si-toolbar">
    <div class="si-search-wrap">
      <svg class="si-search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="si-search" type="text" v-model="search" :placeholder="$t('si.search')" />
    </div>
    <select class="si-select" v-model="categoryId">
      <option value="">{{ $t('si.filterCat') }}</option>
      <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.code }} · {{ catName(c) }}</option>
    </select>
    <div class="si-tb-actions">
      <a class="si-btn-export" :href="route('stock-input.export')" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        {{ $t('si.exportBtn') }}
      </a>
      <button class="si-btn-bulk" type="button" @click="openBulk">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
        {{ $t('si.bulkBtn') }}
      </button>
      <button class="si-btn-import" type="button" @click="importOpen = true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/><polyline points="17 8 12 3 7 8"/></svg>
        {{ $t('si.importBtn') }}
      </button>
    </div>
  </div>

  <!-- ── TABLE ───────────────────────────────────────────────────────────── -->
  <div class="si-table-wrap">
    <table class="si-table">
      <thead>
        <tr>
          <th style="width:52px"></th>
          <th>{{ $t('si.colSku') }}</th>
          <th>{{ $t('si.colItem') }}</th>
          <th>{{ $t('si.colCategory') }}</th>
          <th>{{ $t('si.colUom') }}</th>
          <th>{{ $t('si.colStockAt') }}</th>
          <th class="tr">{{ $t('si.colTotalQty') }}</th>
          <th class="tc">{{ $t('si.colActions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!variants.data.length">
          <td colspan="7" class="si-empty">{{ $t('si.noResults') }}</td>
        </tr>
        <tr v-for="v in variants.data" :key="v.id">
          <td class="si-thumb-cell">
            <img v-if="v.photo_path" :src="v.photo_path" :alt="v.sku" class="si-thumb" />
            <div v-else class="si-thumb-placeholder">WBN</div>
          </td>
          <td><span class="si-sku">{{ v.sku }}</span></td>
          <td>
            <div class="si-item-name">{{ itemName(v.item) }}</div>
            <div class="si-item-sub">{{ variantLabel(v) }}</div>
            <div class="si-item-part" v-if="v.item">{{ v.item.part_number }}</div>
          </td>
          <td>
            <span class="si-cat" v-if="v.item && v.item.category">{{ v.item.category.code }}</span>
            <span v-else class="si-dim">—</span>
          </td>
          <td><span class="si-uom">{{ v.item ? v.item.base_uom : '—' }}</span></td>
          <td>
            <div class="si-pills" v-if="v.stock_ledgers.length">
              <span class="si-pill" v-for="sl in v.stock_ledgers" :key="sl.id">
                {{ sl.location && sl.location.warehouse ? sl.location.warehouse.code : '?' }}
                /
                {{ sl.location ? sl.location.code : '?' }}
                <b class="si-pill-qty">{{ sl.qty_on_hand }}</b>
              </span>
            </div>
            <span class="si-dim" v-else>{{ $t('si.noStock_') }}</span>
          </td>
          <td class="tr">
            <span class="si-total" v-if="v.stock_ledgers.length">
              {{ v.stock_ledgers.reduce((s, sl) => s + sl.qty_on_hand, 0).toLocaleString() }}
            </span>
            <span class="si-dim" v-else>0</span>
          </td>
          <td class="tc">
            <button class="si-btn-set" type="button" @click="openModal(v)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
              {{ $t('si.setStock') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- pagination -->
    <div class="si-pg" v-if="variants.last_page > 1">
      <span class="si-pg-info">{{ $t('si.showing', { from: variants.from, to: variants.to, total: variants.total }) }}</span>
      <div class="si-pg-btns">
        <button class="si-pg-btn" :disabled="!variants.prev_page_url" @click="goPage(variants.prev_page_url)">‹</button>
        <template v-for="link in variants.links.slice(1, -1)" :key="link.label">
          <button class="si-pg-btn" :class="{ active: link.active }" :disabled="!link.url" @click="goPage(link.url)" v-html="link.label" />
        </template>
        <button class="si-pg-btn" :disabled="!variants.next_page_url" @click="goPage(variants.next_page_url)">›</button>
      </div>
    </div>
  </div>

  <!-- ── MODAL ───────────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <!-- lightbox -->
    <div v-if="lightboxSrc" class="si-lightbox" @click="lightboxSrc = null">
      <button class="si-lb-close" @click="lightboxSrc = null" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <img :src="lightboxSrc" class="si-lb-img" @click.stop />
    </div>

    <!-- ── BULK STOCK MODAL ──────────────────────────────────────────────────── -->
    <div v-if="bulkOpen" class="si-backdrop" @click.self="closeBulk">
      <div class="si-modal si-modal-bulk">
        <div class="si-modal-head">
          <div>
            <div class="si-modal-title">{{ $t('si.bulkTitle') }}</div>
            <div class="si-modal-sub">{{ $t('si.bulkSub') }}</div>
          </div>
          <button class="si-modal-close" @click="closeBulk" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="si-modal-body">
          <!-- Warehouse + Rack -->
          <div class="bulk-loc-row">
            <div class="si-fg">
              <label class="si-label">{{ $t('si.warehouse') }}</label>
              <select class="si-input" v-model="bulkWh" required>
                <option value="">{{ $t('si.selectWh') }}</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} · {{ wh.name }}</option>
              </select>
            </div>
            <div class="si-fg">
              <label class="si-label">{{ $t('si.rack') }}</label>
              <select class="si-input" v-model="bulkLocId" :disabled="!bulkLocations.length" required>
                <option value="">{{ $t('si.selectRack') }}</option>
                <option v-for="l in bulkLocations" :key="l.id" :value="l.id">{{ l.code }} · {{ l.name }}</option>
              </select>
            </div>
          </div>

          <!-- Entries header -->
          <div class="bulk-entries-head">
            <div class="si-sec-label" style="margin:0">{{ $t('si.bulkEntries') }}</div>
            <button type="button" class="bulk-add-row" @click="addBulkRow">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
              {{ $t('si.addRow') }}
            </button>
          </div>

          <!-- Rows — single-line layout -->
          <div class="bulk-rows">
            <div class="bulk-row" v-for="(row, idx) in bulkRows" :key="idx">

              <span class="bulk-row-num">{{ idx + 1 }}</span>

              <!-- SKU combobox (dropdown via Teleport, no clipping) -->
              <div class="bulk-combo-wrap">
                <input
                  class="si-input bulk-combo-input"
                  :class="{ 'has-val': row.variantId }"
                  type="text"
                  v-model="row.query"
                  :placeholder="$t('si.searchSku')"
                  autocomplete="off"
                  @focus="openDropdown(idx, $event)"
                  @blur="closeDropdown()"
                  @input="row.variantId = null"
                />
              </div>

              <!-- Qty -->
              <input
                class="si-input bulk-qty-input"
                type="number"
                v-model="row.qty"
                step="any" min="0"
                :disabled="!row.variantId"
                :placeholder="row.variantId
                  ? ('0 ' + (row.selectedUom === 'alt' && rowHasAlt(row) ? variantOf(row).alt_uom : (variantOf(row)?.base_uom ?? '')))
                  : '—'"
              />

              <!-- UOM: toggle (if alt exists) or static label — only when variant selected -->
              <template v-if="row.variantId">
                <div v-if="rowHasAlt(row)" class="bulk-uom-toggle">
                  <button type="button" class="bulk-uom-btn" :class="{ active: row.selectedUom === 'base' }"
                    @click="row.selectedUom = 'base'; row.qty = ''">{{ variantOf(row).base_uom }}</button>
                  <button type="button" class="bulk-uom-btn" :class="{ active: row.selectedUom === 'alt' }"
                    @click="row.selectedUom = 'alt'; row.qty = ''">{{ variantOf(row).alt_uom }}</button>
                </div>
                <span v-else class="bulk-uom-static">{{ variantOf(row)?.base_uom ?? '' }}</span>

                <!-- Conversion hint (inline, appears only when alt UOM qty entered) -->
                <span v-if="rowConvHint(row)" class="bulk-conv-hint">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                  {{ rowConvHint(row) }}
                </span>
              </template>

              <!-- Delete -->
              <button type="button" class="bulk-del" @click="removeBulkRow(idx)" :disabled="bulkRows.length === 1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>

            </div>
          </div>

          <!-- Footer -->
          <div class="si-modal-foot">
            <button type="button" class="si-cancel" @click="closeBulk">{{ $t('btn.cancel') }}</button>
            <button
              type="button"
              class="si-save"
              :disabled="bulkProcessing || !bulkLocId || bulkValidRows === 0"
              @click="submitBulk"
            >
              <svg v-if="bulkProcessing" class="si-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              {{ bulkProcessing ? $t('si.saving') : $t('si.bulkSave', { n: bulkValidRows }) }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk SKU dropdown — fixed position, avoids overflow clipping -->
    <div
      v-if="bulkOpen && activeDropIdx >= 0"
      class="bulk-dropdown-fixed"
      :style="dropFixedStyle"
    >
      <template v-if="filteredVariants(bulkRows[activeDropIdx]).length">
        <div
          v-for="v in filteredVariants(bulkRows[activeDropIdx])"
          :key="v.id"
          class="bulk-option"
          @mousedown.prevent="selectBulkVariant(bulkRows[activeDropIdx], v)"
        >
          <span class="bulk-opt-sku">{{ v.sku }}</span>
          <span class="bulk-opt-name">{{ v.name_en || v.name_id || '' }}</span>
        </div>
      </template>
      <div v-else class="bulk-option bulk-opt-empty">No results</div>
    </div>

    <!-- import modal -->
    <div v-if="importOpen" class="si-backdrop" @click.self="importOpen = false">
      <div class="si-modal" style="max-width:460px">
        <div class="si-modal-head">
          <div>
            <div class="si-modal-title">{{ $t('si.importTitle') }}</div>
            <div class="si-modal-sub">{{ $t('si.importSub') }}</div>
          </div>
          <button class="si-modal-close" @click="importOpen = false" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="si-modal-body">
          <!-- info box -->
          <div class="si-import-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            <div>{{ $t('si.importInfo') }}</div>
          </div>
          <!-- download template hint -->
          <div class="si-import-hint">
            {{ $t('si.importTplHint') }}
            <a :href="route('stock-input.export')" target="_blank" class="si-import-dl">{{ $t('si.importTplDl') }}</a>
          </div>
          <!-- file upload -->
          <form @submit.prevent="submitImport">
            <div
              class="si-drop-zone"
              :class="{ 'has-file': importFileName }"
              @click="importFileRef.click()"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span v-if="importFileName" class="si-drop-name">{{ importFileName }}</span>
              <span v-else>{{ $t('si.importDropzone') }}</span>
              <input
                ref="importFileRef"
                type="file"
                accept=".xlsx,.xls,.csv"
                style="display:none"
                @change="onImportFile"
              />
            </div>
            <div v-if="importForm.errors.file" class="si-err" style="margin-top:6px">{{ importForm.errors.file }}</div>
            <div class="si-modal-foot">
              <button type="button" class="si-cancel" @click="importOpen = false">{{ $t('btn.cancel') }}</button>
              <button type="submit" class="si-save" :disabled="!importFileName || importForm.processing">
                {{ importForm.processing ? $t('si.importing') : $t('si.importNow') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div v-if="modalOpen" class="si-backdrop" @click.self="closeModal">
      <div class="si-modal">
        <div class="si-modal-head">
          <div>
            <div class="si-modal-title">{{ $t('si.modalTitle', { sku: activeVariant ? activeVariant.sku : '' }) }}</div>
            <div class="si-modal-sub">{{ $t('si.modalSub') }}</div>
          </div>
          <button class="si-modal-close" @click="closeModal" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="si-modal-body">
          <div class="si-variant-strip" v-if="activeVariant">
            <img
              v-if="activeVariant.photo_path"
              :src="activeVariant.photo_path"
              :alt="activeVariant.sku"
              class="si-vs-img"
              title="Klik untuk preview"
              @click="lightboxSrc = activeVariant.photo_path"
            />
            <div v-else class="si-vs-img-placeholder">WBN</div>
            <div class="si-vs-info">
              <div class="si-vs-top">
                <span class="si-vs-sku">{{ activeVariant.sku }}</span>
                <span class="si-vs-name">{{ itemName(activeVariant.item) }}</span>
                <template v-if="variantLabel(activeVariant) !== '—'">
                  <span class="si-vs-sep">·</span>
                  <span class="si-vs-detail">{{ variantLabel(activeVariant) }}</span>
                </template>
              </div>
              <div class="si-vs-bottom" v-if="activeVariant.item">
                <span class="si-vs-uom">{{ activeVariant.item.base_uom }}</span>
                <span v-if="activeVariant.photo_path" class="si-vs-zoom-hint" @click="lightboxSrc = activeVariant.photo_path">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6M8 11h6"/></svg>
                  Lihat foto
                </span>
              </div>
            </div>
          </div>

          <div class="si-sec-label">{{ $t('si.currentEntries') }}</div>
          <div v-if="entries.length" class="si-entries">
            <div class="si-entry" v-for="sl in entries" :key="sl.id">
              <div class="si-entry-loc">
                <span class="si-entry-wh">{{ sl.location && sl.location.warehouse ? sl.location.warehouse.name : '—' }}</span>
                <svg class="si-entry-arr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                <span class="si-entry-rack">{{ sl.location ? (sl.location.code + ' · ' + sl.location.name) : '—' }}</span>
              </div>
              <div class="si-entry-qty">
                <b>{{ sl.qty_on_hand }}</b>
                <span>{{ activeVariant && activeVariant.item ? activeVariant.item.base_uom : '' }}</span>
              </div>
              <button class="si-del-btn" type="button" :disabled="deletingId === sl.id" @click="confirmDelete(sl.id)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>
          <div v-else class="si-no-entries">{{ $t('si.noEntries') }}</div>

          <div class="si-sec-label" style="margin-top:18px">{{ $t('si.addEntry') }}</div>
          <form @submit.prevent="submitAdd" class="si-add-form">
            <div class="si-form-row">
              <div class="si-fg">
                <label class="si-label">{{ $t('si.warehouse') }}</label>
                <select class="si-input" v-model="addForm.warehouse_id" required>
                  <option value="">{{ $t('si.selectWh') }}</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} · {{ wh.name }}</option>
                </select>
                <span class="si-err" v-if="addForm.errors.warehouse_id">{{ addForm.errors.warehouse_id }}</span>
              </div>
              <div class="si-fg">
                <label class="si-label">{{ $t('si.rack') }}</label>
                <select class="si-input" v-model="addForm.location_id" required :disabled="!availableLocations.length">
                  <option value="">{{ $t('si.selectRack') }}</option>
                  <option v-for="l in availableLocations" :key="l.id" :value="l.id">{{ l.code }} · {{ l.name }}</option>
                </select>
                <span class="si-err" v-if="addForm.errors.location_id">{{ addForm.errors.location_id }}</span>
              </div>
              <div class="si-fg si-fg-sm">
                <label class="si-label">
                  {{ $t('si.qty') }}
                  <!-- UOM toggle — only when alt UOM exists -->
                  <span v-if="hasAltUom" class="si-uom-toggle">
                    <button
                      type="button"
                      class="si-uom-btn"
                      :class="{ active: selectedUom === 'base' }"
                      @click="selectedUom = 'base'; addForm.qty_on_hand = ''"
                    >{{ activeItem.base_uom }}</button>
                    <button
                      type="button"
                      class="si-uom-btn"
                      :class="{ active: selectedUom === 'alt' }"
                      @click="selectedUom = 'alt'; addForm.qty_on_hand = ''"
                    >{{ activeItem.alt_uom }}</button>
                  </span>
                  <span v-else-if="activeItem" class="si-uom-static">{{ activeItem.base_uom }}</span>
                </label>
                <input
                  class="si-input"
                  type="number"
                  v-model="addForm.qty_on_hand"
                  step="any" min="0"
                  :placeholder="currentUomLabel ? '0 ' + currentUomLabel : '0'"
                  required
                />
                <!-- conversion hint -->
                <span v-if="conversionHint" class="si-conv-hint">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                  {{ conversionHint }}
                </span>
                <span class="si-err" v-if="addForm.errors.qty_on_hand">{{ addForm.errors.qty_on_hand }}</span>
              </div>
            </div>
            <div class="si-modal-foot">
              <button type="button" class="si-cancel" @click="closeModal">{{ $t('btn.cancel') }}</button>
              <button type="submit" class="si-save" :disabled="addForm.processing">
                {{ addForm.processing ? $t('si.saving') : $t('btn.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>

</AppLayout>
</template>

<style scoped>
.si-stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px }
@media(max-width:900px){ .si-stat-row{ grid-template-columns:repeat(2,1fr) } }
.si-stat { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; display:flex; align-items:center; gap:16px; box-shadow:var(--shadow-sm) }
.si-stat-ico { width:44px; height:44px; border-radius:10px; display:grid; place-items:center; flex-shrink:0 }
.si-stat-ico svg { width:20px; height:20px }
.si-stat-val { font-size:24px; font-weight:700; letter-spacing:-.02em; line-height:1 }
.si-stat-lbl { font-size:12px; color:var(--fg-2); margin-top:4px; font-weight:500 }
.si-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap }
.si-search-wrap { position:relative; flex:1; min-width:220px }
.si-search-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--fg-dim) }
.si-search { width:100%; padding:9px 12px 9px 32px; font-size:13.5px; background:var(--surface); border:1px solid var(--border-2); border-radius:8px; color:var(--fg); outline:none; transition:border-color 180ms }
.si-search:focus { border-color:var(--orange-500) }
.si-select { padding:9px 12px; font-size:13.5px; background:var(--surface); border:1px solid var(--border-2); border-radius:8px; color:var(--fg); outline:none; cursor:pointer; min-width:160px }
.si-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:var(--shadow-sm) }
.si-table { width:100%; border-collapse:collapse }
.si-table th { padding:11px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--fg-2); background:var(--surface-2); border-bottom:1px solid var(--border); white-space:nowrap }
.si-table td { padding:13px 16px; font-size:13.5px; border-bottom:1px solid var(--border-soft); vertical-align:middle }
.si-table tbody tr:last-child td { border-bottom:none }
.si-table tbody tr:hover td { background:var(--hover) }
.tr { text-align:right }
.tc { text-align:center }
.si-empty { text-align:center; color:var(--fg-dim); padding:40px 16px }
.si-dim { color:var(--fg-dim); font-size:12px }
.si-sku { display:inline-block; padding:3px 8px; border-radius:5px; background:var(--surface-3); border:1px solid var(--border-2); font-size:12px; font-weight:700; font-family:monospace; color:var(--orange-500) }
.si-item-name { font-weight:600 }
.si-item-sub { font-size:12px; color:var(--fg-2); margin-top:2px }
.si-item-part { font-size:11px; color:var(--fg-dim); font-family:monospace }
.si-cat { display:inline-block; padding:3px 8px; background:rgba(59,130,246,.12); color:#60a5fa; border-radius:5px; font-size:11.5px; font-weight:700 }
.si-uom { display:inline-block; padding:3px 8px; background:var(--surface-3); border:1px solid var(--border-2); border-radius:5px; font-size:11.5px; font-weight:600; color:var(--fg-2) }
.si-pills { display:flex; flex-wrap:wrap; gap:4px }
.si-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:5px; background:rgba(16,185,129,.1); color:#34d399; font-size:11.5px; font-weight:600 }
.si-pill-qty { background:rgba(16,185,129,.2); padding:1px 5px; border-radius:4px; font-size:10.5px; font-weight:700 }
.si-total { font-weight:700; font-size:15px }
.si-btn-set { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:7px; cursor:pointer; font-size:12.5px; font-weight:600; background:rgba(249,115,22,.12); color:var(--orange-500); border:1px solid rgba(249,115,22,.25); transition:background 180ms,border-color 180ms }
.si-btn-set:hover { background:rgba(249,115,22,.2); border-color:rgba(249,115,22,.4) }
.si-btn-set svg { width:13px; height:13px }
.si-pg { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-top:1px solid var(--border); gap:12px }
.si-pg-info { font-size:12.5px; color:var(--fg-2) }
.si-pg-btns { display:flex; gap:4px }
.si-pg-btn { min-width:32px; height:32px; padding:0 8px; border-radius:7px; border:1px solid var(--border-2); background:var(--surface); font-size:13px; font-weight:600; color:var(--fg-2); cursor:pointer; transition:background 180ms,color 180ms }
.si-pg-btn:hover:not(:disabled) { background:var(--hover); color:var(--fg) }
.si-pg-btn.active { background:var(--orange-500); color:#fff; border-color:var(--orange-500) }
.si-pg-btn:disabled { opacity:.4; cursor:default }
.si-flash { padding:12px 16px; border-radius:8px; font-size:13.5px; font-weight:600 }
.si-flash-ok { background:rgba(16,185,129,.12); color:#34d399; border:1px solid rgba(16,185,129,.25) }
.si-flash-err { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.25) }
.si-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.6); display:flex; align-items:center; justify-content:center; z-index:100; padding:16px }
.si-modal { background:var(--surface); border:1px solid var(--border-2); border-radius:16px; box-shadow:var(--shadow-lg); width:100%; max-width:680px; max-height:90vh; display:flex; flex-direction:column; color:var(--fg) }
.si-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:20px 24px 16px; border-bottom:1px solid var(--border); flex-shrink:0 }
.si-modal-title { font-size:16px; font-weight:700; color:var(--fg) }
.si-modal-sub { font-size:12.5px; color:var(--fg-2); margin-top:3px }
.si-modal-close { appearance:none; border:1px solid var(--border); background:transparent; width:32px; height:32px; border-radius:7px; cursor:pointer; color:var(--fg-2); display:grid; place-items:center; flex-shrink:0; transition:background 180ms,color 180ms }
.si-modal-close:hover { background:var(--hover); color:var(--fg) }
.si-modal-close svg { width:15px; height:15px }
.si-modal-body { padding:20px 24px; overflow-y:auto; flex:1 }
.si-variant-strip { background:var(--surface-2); border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.si-vs-sku { font-family:monospace; font-weight:700; color:var(--orange-500); font-size:13px }
.si-vs-name { font-size:13px; font-weight:600; color:var(--fg) }
.si-vs-sep { color:var(--fg-dim) }
.si-vs-detail { font-size:13px; color:var(--fg-2) }
.si-vs-uom { font-size:13px; font-weight:700; color:var(--fg) }
.si-sec-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--fg-dim); margin-bottom:10px }
.si-entries { display:flex; flex-direction:column; gap:6px; margin-bottom:4px }
.si-entry { display:flex; align-items:center; gap:10px; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:10px 14px }
.si-entry-loc { flex:1; display:flex; align-items:center; gap:6px; flex-wrap:wrap; min-width:0 }
.si-entry-wh { font-size:13px; font-weight:600; color:var(--fg) }
.si-entry-arr { width:12px; height:12px; color:var(--fg-dim); flex-shrink:0 }
.si-entry-rack { font-size:12.5px; color:var(--fg-2) }
.si-entry-qty { display:flex; align-items:baseline; gap:4px; flex-shrink:0 }
.si-entry-qty b { font-size:16px; font-weight:700 }
.si-entry-qty span { font-size:11px; color:var(--fg-2) }
.si-del-btn { appearance:none; border:1px solid var(--border-2); background:transparent; width:30px; height:30px; border-radius:6px; cursor:pointer; color:var(--fg-dim); display:grid; place-items:center; flex-shrink:0; transition:background 180ms,color 180ms,border-color 180ms }
.si-del-btn:hover:not(:disabled) { background:rgba(239,68,68,.12); color:#f87171; border-color:rgba(239,68,68,.25) }
.si-del-btn:disabled { opacity:.4; cursor:default }
.si-del-btn svg { width:14px; height:14px }
.si-no-entries { font-size:13px; color:var(--fg-dim); text-align:center; padding:16px; background:var(--surface-2); border:1px dashed var(--border-2); border-radius:8px }
.si-add-form { display:flex; flex-direction:column; gap:12px }
.si-form-row { display:grid; grid-template-columns:1fr 1fr 110px; gap:12px; align-items:end }
@media(max-width:600px){ .si-form-row{ grid-template-columns:1fr } }
.si-fg { display:flex; flex-direction:column; gap:5px; min-width:0 }
.si-fg-sm { min-width:100px }
select.si-input { width:100% }
.si-label { font-size:12px; font-weight:600; color:var(--fg-2) }
.si-input { padding:9px 12px; font-size:13.5px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:8px; color:var(--fg); outline:none; font-family:inherit; transition:border-color 180ms }
.si-input:focus { border-color:var(--orange-500) }
.si-input:disabled { opacity:.5; cursor:default }
.si-err { font-size:11.5px; color:#f87171 }
.si-modal-foot { display:flex; gap:10px; justify-content:flex-end; padding-top:16px; margin-top:8px; border-top:1px solid var(--border) }
.si-cancel { padding:9px 18px; border-radius:8px; cursor:pointer; font-size:13.5px; font-weight:600; background:transparent; border:1px solid var(--border-2); color:var(--fg-2); transition:background 180ms }
.si-cancel:hover { background:var(--hover); color:var(--fg) }
.si-save { padding:9px 20px; border-radius:8px; cursor:pointer; font-size:13.5px; font-weight:600; background:linear-gradient(180deg,var(--orange-400),var(--orange-500)); color:#fff; border:0; box-shadow:0 4px 10px -3px rgba(249,115,22,.5); transition:opacity 180ms }
.si-save:disabled { opacity:.6; cursor:default }

/* ── toolbar actions ─────────────────────────────────────────────────────── */
.si-tb-actions { display:flex; gap:8px; margin-left:auto }
.si-btn-export, .si-btn-import, .si-btn-bulk {
  display:inline-flex; align-items:center; gap:6px;
  padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600;
  cursor:pointer; transition:background 150ms, border-color 150ms; white-space:nowrap;
  text-decoration:none; font-family:inherit; border:1px solid transparent;
}
.si-btn-export {
  background:rgba(16,185,129,.12); color:#34d399;
  border-color:rgba(16,185,129,.25);
}
.si-btn-export:hover { background:rgba(16,185,129,.2); border-color:rgba(16,185,129,.4) }
.si-btn-export svg { width:14px; height:14px }
.si-btn-bulk {
  background:rgba(139,92,246,.12); color:#a78bfa;
  border-color:rgba(139,92,246,.25);
}
.si-btn-bulk:hover { background:rgba(139,92,246,.2); border-color:rgba(139,92,246,.4) }
.si-btn-bulk svg { width:14px; height:14px }
.si-btn-import {
  background:rgba(59,130,246,.12); color:#60a5fa;
  border-color:rgba(59,130,246,.25);
}
.si-btn-import:hover { background:rgba(59,130,246,.2); border-color:rgba(59,130,246,.4) }
.si-btn-import svg { width:14px; height:14px; transform:rotate(180deg) }

/* ── import modal ────────────────────────────────────────────────────────── */
.si-import-info {
  display:flex; align-items:flex-start; gap:8px;
  background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2);
  border-radius:8px; padding:12px 14px; font-size:12.5px;
  color:var(--fg-2); line-height:1.5; margin-bottom:12px;
}
.si-import-info svg { color:#60a5fa; flex-shrink:0; margin-top:1px }
.si-import-info code { background:var(--surface-3); padding:1px 5px; border-radius:4px; font-size:11.5px; color:var(--fg) }
.si-import-hint { font-size:12.5px; color:var(--fg-2); margin-bottom:14px }
.si-import-dl { color:var(--orange-500); font-weight:600; text-decoration:none }
.si-import-dl:hover { text-decoration:underline }
.si-drop-zone {
  border:2px dashed var(--border-2); border-radius:10px;
  padding:28px 20px; text-align:center; cursor:pointer;
  font-size:13px; color:var(--fg-2);
  transition:border-color 150ms, background 150ms;
  display:flex; flex-direction:column; align-items:center; gap:8px;
}
.si-drop-zone svg { width:28px; height:28px; color:var(--fg-dim) }
.si-drop-zone:hover, .si-drop-zone.has-file {
  border-color:var(--orange-500); background:rgba(249,115,22,.04);
}
.si-drop-zone.has-file { color:var(--fg) }
.si-drop-name { font-weight:600; color:var(--orange-500) }

/* ── UOM toggle ─────────────────────────────────────────────────────────── */
.si-label { display:flex; align-items:center; gap:6px; flex-wrap:wrap }
.si-uom-toggle { display:inline-flex; border:1px solid var(--border-2); border-radius:6px; overflow:hidden; margin-left:auto }
.si-uom-btn {
  padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer;
  background:transparent; border:none; color:var(--fg-2);
  transition:background 150ms, color 150ms;
}
.si-uom-btn.active { background:#f97316; color:#fff }
.si-uom-static { font-size:11px; font-weight:700; color:var(--fg-dim); margin-left:auto }
.si-conv-hint {
  display:inline-flex; align-items:center; gap:4px;
  font-size:11.5px; font-weight:600; color:#f97316;
  margin-top:-2px;
}

/* ── thumbnail (table) ──────────────────────────────────────────────────── */
.si-thumb-cell { width:68px; padding:8px 8px 8px 16px }
.si-thumb {
  width:52px; height:52px; object-fit:cover; border-radius:10px;
  border:1px solid var(--border); display:block; flex-shrink:0;
}
.si-thumb-placeholder {
  width:52px; height:52px; border-radius:10px; flex-shrink:0;
  background:linear-gradient(135deg,#f97316,#ea580c);
  display:grid; place-items:center;
  font-size:11px; font-weight:800; color:#fff; letter-spacing:.05em;
  border:1px solid rgba(249,115,22,.3);
}
/* modal variant strip */
.si-vs-img {
  width:56px; height:56px; object-fit:cover; border-radius:10px;
  border:1px solid var(--border); flex-shrink:0;
  cursor:zoom-in; transition:opacity 150ms;
}
.si-vs-img:hover { opacity:.85 }
.si-vs-img-placeholder {
  width:56px; height:56px; border-radius:10px; flex-shrink:0;
  background:linear-gradient(135deg,#f97316,#ea580c);
  display:grid; place-items:center;
  font-size:12px; font-weight:800; color:#fff; letter-spacing:.05em;
  border:1px solid rgba(249,115,22,.3);
}
.si-vs-info { display:flex; flex-direction:column; gap:4px; min-width:0 }
.si-vs-top { display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.si-vs-bottom { display:flex; align-items:center; gap:10px }
.si-vs-zoom-hint {
  display:inline-flex; align-items:center; gap:4px;
  font-size:11px; color:var(--orange-500); cursor:pointer; font-weight:600;
  opacity:.8; transition:opacity 150ms;
}
.si-vs-zoom-hint:hover { opacity:1 }
/* lightbox */
.si-lightbox {
  position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.88); display:flex;
  align-items:center; justify-content:center; padding:24px;
  cursor:zoom-out;
}
.si-lb-img {
  max-width:100%; max-height:90vh; object-fit:contain;
  border-radius:12px; box-shadow:0 24px 64px rgba(0,0,0,.6);
  cursor:default;
}
.si-lb-close {
  position:absolute; top:16px; right:16px;
  width:36px; height:36px; border-radius:8px;
  border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.1);
  color:#fff; cursor:pointer; display:grid; place-items:center;
  transition:background 150ms;
}
.si-lb-close:hover { background:rgba(255,255,255,.2) }
.si-lb-close svg { width:16px; height:16px }

/* ── Bulk Stock Modal ────────────────────────────────────────────────────── */
.si-modal-bulk { max-width:min(960px, calc(100vw - 32px)) }
.bulk-loc-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px }
@media(max-width:540px){ .bulk-loc-row{ grid-template-columns:1fr } }

.bulk-entries-head {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:10px;
}
.bulk-add-row {
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 12px; border-radius:7px; font-size:12.5px; font-weight:600;
  background:rgba(139,92,246,.12); color:#a78bfa;
  border:1px solid rgba(139,92,246,.25); cursor:pointer; font-family:inherit;
  transition:background 150ms;
}
.bulk-add-row:hover { background:rgba(139,92,246,.22) }

.bulk-rows { display:flex; flex-direction:column; gap:6px; margin-bottom:4px; max-height:380px; overflow-y:auto }

/* single-line row */
.bulk-row {
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
  background:var(--surface-2); border:1px solid var(--border);
  border-radius:10px; padding:8px 10px;
}

.bulk-row-num {
  font-size:11px; font-weight:700; color:var(--fg-dim);
  width:18px; text-align:center; flex-shrink:0;
}

/* combobox */
.bulk-combo-wrap { position:relative; flex:1; min-width:180px; max-width:100% }
.bulk-combo-input { width:100%; box-sizing:border-box }
.bulk-combo-input.has-val {
  border-color:rgba(139,92,246,.45);
  background:rgba(139,92,246,.06);
}

/* dropdown rendered at body level (fixed position via Teleport) */
.bulk-dropdown-fixed {
  background:var(--surface); border:1px solid var(--border-2);
  border-radius:9px; box-shadow:0 10px 32px rgba(0,0,0,.5);
  overflow:hidden; max-height:220px; overflow-y:auto;
}
.bulk-option {
  display:flex; align-items:baseline; gap:10px;
  padding:9px 14px; cursor:pointer; transition:background 120ms;
}
.bulk-option:hover { background:rgba(139,92,246,.1) }
.bulk-opt-sku {
  font-family:monospace; font-size:12.5px; font-weight:700;
  color:#a78bfa; flex-shrink:0;
}
.bulk-opt-name {
  font-size:12px; color:var(--fg-2);
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.bulk-opt-empty { color:var(--fg-dim); font-size:12px; cursor:default }

.bulk-qty-input { width:90px; flex-shrink:0 }

/* UOM toggle */
.bulk-uom-toggle {
  display:inline-flex; border:1px solid var(--border-2);
  border-radius:7px; overflow:hidden; flex-shrink:0;
}
.bulk-uom-btn {
  padding:6px 12px; font-size:12px; font-weight:700; cursor:pointer;
  background:transparent; border:none; color:var(--fg-2);
  font-family:inherit; transition:background 150ms, color 150ms;
}
.bulk-uom-btn.active { background:#a78bfa; color:#fff }
.bulk-uom-btn:not(.active):hover { background:rgba(139,92,246,.1); color:#a78bfa }
.bulk-uom-static { font-size:12px; font-weight:700; color:var(--fg-dim) }

/* conversion hint */
.bulk-conv-hint {
  display:inline-flex; align-items:center; gap:4px;
  font-size:11.5px; font-weight:600; color:#a78bfa;
}

/* delete button */
.bulk-del {
  appearance:none; border:1px solid var(--border-2); background:transparent;
  width:30px; height:30px; border-radius:6px; cursor:pointer;
  color:var(--fg-dim); display:grid; place-items:center; flex-shrink:0;
  transition:background 150ms,color 150ms,border-color 150ms;
}
.bulk-del:hover:not(:disabled) { background:rgba(239,68,68,.12); color:#f87171; border-color:rgba(239,68,68,.25) }
.bulk-del:disabled { opacity:.3; cursor:default }
.bulk-del svg { width:13px; height:13px }

@keyframes si-spin { to { transform:rotate(360deg) } }
.si-spin { animation:si-spin .8s linear infinite; flex-shrink:0 }
</style>
