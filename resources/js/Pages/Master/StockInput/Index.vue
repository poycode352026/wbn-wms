<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
    variants:   Object,
    categories: Array,
    warehouses: Array,
    filters:    Object,
    stats:      Object,
})

// ── flash ──────────────────────────────────────────────────────────────────
const flash = computed(() => page.props.flash ?? {})

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
.si-modal { background:var(--surface); border:1px solid var(--border-2); border-radius:16px; box-shadow:var(--shadow-lg); width:100%; max-width:680px; max-height:90vh; overflow-y:auto }
.si-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:20px 24px 16px; border-bottom:1px solid var(--border) }
.si-modal-title { font-size:16px; font-weight:700 }
.si-modal-sub { font-size:12.5px; color:var(--fg-2); margin-top:3px }
.si-modal-close { appearance:none; border:1px solid var(--border); background:transparent; width:32px; height:32px; border-radius:7px; cursor:pointer; color:var(--fg-2); display:grid; place-items:center; flex-shrink:0; transition:background 180ms,color 180ms }
.si-modal-close:hover { background:var(--hover); color:var(--fg) }
.si-modal-close svg { width:15px; height:15px }
.si-modal-body { padding:20px 24px }
.si-variant-strip { background:var(--surface-2); border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.si-vs-sku { font-family:monospace; font-weight:700; color:var(--orange-500); font-size:13px }
.si-vs-name { font-size:13px; font-weight:600 }
.si-vs-sep { color:var(--fg-dim) }
.si-vs-detail { font-size:13px; color:var(--fg-2) }
.si-vs-uom { font-size:13px; font-weight:700 }
.si-sec-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--fg-dim); margin-bottom:10px }
.si-entries { display:flex; flex-direction:column; gap:6px; margin-bottom:4px }
.si-entry { display:flex; align-items:center; gap:10px; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:10px 14px }
.si-entry-loc { flex:1; display:flex; align-items:center; gap:6px; flex-wrap:wrap; min-width:0 }
.si-entry-wh { font-size:13px; font-weight:600 }
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
.si-fg { display:flex; flex-direction:column; gap:5px }
.si-fg-sm { min-width:100px }
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
</style>
