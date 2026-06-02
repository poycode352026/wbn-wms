<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, useForm, usePage, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    items:      Object,
    categories: Array,
    warehouses: Array,
    filters:    Object,
    stats:      Object,
})

// ── Tab ──────────────────────────────────────────────────────────────────
const activeTab = ref(props.filters.tab === 'categories' ? 'categories' : 'items')

// ── Name helpers ──────────────────────────────────────────────────────────
function itemName(item) {
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en
}
function catName(cat) {
    if (locale.value === 'zh' && cat.name_zh) return cat.name_zh
    if (locale.value === 'id' && cat.name_id) return cat.name_id
    return cat.name_en
}

// ── Filters ───────────────────────────────────────────────────────────────
const search     = ref(props.filters.search ?? '')
const catFilter  = ref(props.filters.category_id ?? '')
const statusFilt = ref(props.filters.status ?? '')
const catSearch  = ref(props.filters.cat_search ?? '')

function applyFilter() {
    router.get(route('items.index'), {
        search:      search.value || undefined,
        category_id: catFilter.value || undefined,
        status:      statusFilt.value || undefined,
        cat_search:  catSearch.value || undefined,
        tab:         activeTab.value !== 'items' ? activeTab.value : undefined,
    }, { preserveState: true, replace: true })
}

let searchTimer
watch(search,    () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilter, 400) })
watch(catSearch, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilter, 400) })
watch([catFilter, statusFilt], applyFilter)

// ── Category options for filter bar (items tab) ───────────────────────────
const filteredCatOptions = computed(() =>
    props.categories.filter(c => c.is_active)
)

// ── Categories tab: passthrough (server-side filtered) ───────────────────
const displayedCats = computed(() => props.categories)

// ── Item form ─────────────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem   = ref(null)
const nameTab       = ref('en')
const partSuffix    = ref('')

const form = useForm({
    category_id:        '',
    part_number:        '',
    name_en:            '',
    name_id:            '',
    name_zh:            '',
    description:        '',
    base_uom:           '',
    alt_uom:            '',
    alt_uom_conversion: '',
    minimum_stock:      0,
    has_cooldown:       false,
    is_mandatory:       false,
    cooldown_days:      '',
    cooldown_track_by:  'employee_id',
    photo_required:     false,
    is_active:          true,
})

const formCatOptions = computed(() => props.categories.filter(c => c.is_active))

const selectedCatPrefix = computed(() => {
    const cat = props.categories.find(c => c.id == form.category_id)
    return cat?.prefix ?? null
})

watch([() => form.category_id, partSuffix], () => {
    if (!selectedCatPrefix.value) return
    const suffix = partSuffix.value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '')
    form.part_number = suffix ? `WBN-${selectedCatPrefix.value}-${suffix}` : ''
})

function openAdd() {
    editingItem.value      = null
    form.reset()
    form.is_active         = true
    form.minimum_stock     = 0
    form.has_cooldown      = false
    form.is_mandatory      = false
    form.photo_required    = false
    form.cooldown_track_by = 'employee_id'
    nameTab.value          = 'en'
    partSuffix.value       = ''
    showItemModal.value    = true
}



function openEdit(item) {
    editingItem.value       = item
    form.category_id        = item.category?.id ?? ''
    form.part_number        = item.part_number
    form.name_en            = item.name_en
    form.name_id            = item.name_id ?? ''
    form.name_zh            = item.name_zh ?? ''
    form.description        = item.description ?? ''
    form.base_uom           = item.base_uom
    form.alt_uom            = item.alt_uom ?? ''
    form.alt_uom_conversion = item.alt_uom_conversion != null ? parseFloat(item.alt_uom_conversion) : ''
    form.minimum_stock      = parseFloat(item.minimum_stock)
    form.has_cooldown       = item.has_cooldown
    form.is_mandatory       = item.is_mandatory ?? false
    form.cooldown_days      = item.cooldown_days ?? ''
    form.cooldown_track_by  = item.cooldown_track_by ?? 'employee_id'
    form.photo_required     = item.photo_required
    form.is_active          = item.is_active
    nameTab.value           = 'en'
    // Extract suffix from existing part_number (WBN-PREFIX-SUFFIX)
    const cat = props.categories.find(c => c.id == item.category?.id)
    const marker = cat ? `WBN-${cat.prefix}-` : null
    partSuffix.value = (marker && item.part_number.startsWith(marker))
        ? item.part_number.slice(marker.length)
        : item.part_number
    showItemModal.value = true
}

function submitItem() {
    const isNew = !editingItem.value
    const opts = {
        onSuccess: () => {
            showItemModal.value = false
            form.reset()
            partSuffix.value = ''
            if (isNew) {
                const newId = usePage().props.flash?.newItemId
                if (newId) {
                    const newItem = props.items.data.find(i => i.id === newId)
                    if (newItem) openVariants(newItem)
                }
            }
        }
    }
    if (editingItem.value) form.patch(route('items.update', editingItem.value.id), opts)
    else form.post(route('items.store'), opts)
}

function deleteItem(item) {
    if (!confirm(`Delete item "${item.part_number}"?`)) return
    router.delete(route('items.destroy', item.id))
}

// ── Category form ──────────────────────────────────────────────────────────
const showCatModal = ref(false)
const editingCat   = ref(null)

const catForm = useForm({
    code:      '',
    prefix:    '',
    name_id:   '',
    name_en:   '',
    name_zh:   '',
    is_active: true,
})

function openAddCat() {
    editingCat.value   = null
    catForm.reset()
    catForm.is_active  = true
    showCatModal.value = true
}

function openEditCat(cat) {
    editingCat.value   = cat
    catForm.code       = cat.code
    catForm.prefix     = cat.prefix
    catForm.name_id    = cat.name_id
    catForm.name_en    = cat.name_en
    catForm.name_zh    = cat.name_zh
    catForm.is_active  = cat.is_active
    showCatModal.value = true
}

function submitCat() {
    const opts = { onSuccess: () => { showCatModal.value = false; catForm.reset() } }
    if (editingCat.value) catForm.patch(route('item-categories.update', editingCat.value.id), opts)
    else catForm.post(route('item-categories.store'), opts)
}

function deleteCat(cat) {
    if (!confirm(`Delete category "${cat.code}"?`)) return
    router.delete(route('item-categories.destroy', cat.id))
}

// ── Variant modal ──────────────────────────────────────────────────────────
const showVarModal   = ref(false)
const variantItem    = ref(null)
const editingVariant = ref(null)

const varForm = useForm({
    brand:     '',
    model:     '',
    size:      '',
    color:     '',
    photo:     null,
    is_active: true,
})

watch(() => props.items?.data, (newData) => {
    if (variantItem.value && newData) {
        const updated = newData.find(i => i.id === variantItem.value.id)
        if (updated) variantItem.value = updated
    }
}, { deep: true })

function openVariants(item) {
    variantItem.value    = item
    editingVariant.value = null
    varForm.reset()
    varForm.is_active    = true
    photoPreview.value   = null
    showVarModal.value   = true
}

function editVariant(v) {
    editingVariant.value = v
    varForm.brand        = v.brand ?? ''
    varForm.model        = v.model ?? ''
    varForm.size         = v.size  ?? ''
    varForm.color        = v.color ?? ''
    varForm.photo        = null
    varForm.is_active    = v.is_active
    photoPreview.value   = null   // reset preview when switching to edit mode
}

function onVariantPhotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    varForm.photo = file
    const reader = new FileReader()
    reader.onload = (ev) => {
        photoPreview.value = ev.target.result  // data:image/...;base64,...
    }
    reader.readAsDataURL(file)
}

function cancelEditVar() {
    editingVariant.value = null
    varForm.reset()
    varForm.is_active  = true
    photoPreview.value = null
}

function submitVariant() {
    const opts = (cb) => ({ forceFormData: true, onSuccess: cb })
    if (editingVariant.value) {
        varForm.post(route('items.updateVariant', {
            item: variantItem.value.id,
            itemVariant: editingVariant.value.id,
        }), opts(() => { editingVariant.value = null; varForm.reset(); varForm.is_active = true }))
    } else {
        varForm.post(route('items.storeVariant', variantItem.value.id),
            opts(() => { varForm.reset(); varForm.is_active = true; photoPreview.value = null }))
    }
}

function deleteVariant(v) {
    if (!confirm('Delete this variant?')) return
    router.delete(route('items.destroyVariant', {
        item: variantItem.value.id,
        itemVariant: v.id,
    }), { preserveScroll: true })
}

// ── Action dropdown ───────────────────────────────────────────────────────
const openDropId = ref(null)
const dropStyle  = ref({})

function toggleDrop(id, ev) {
    ev.stopPropagation()
    if (openDropId.value === id) { openDropId.value = null; return }
    const r = ev.currentTarget.getBoundingClientRect()
    dropStyle.value = {
        position: 'absolute',
        top:      `${r.bottom + window.scrollY + 4}px`,
        left:     `${r.right + window.scrollX - 168}px`,
        minWidth: '160px',
    }
    openDropId.value = id
}
function closeDrop() { openDropId.value = null }

onMounted(() => document.addEventListener('click', closeDrop))
onUnmounted(() => document.removeEventListener('click', closeDrop))

// ── Photo preview & lightbox ──────────────────────────────────────────────
const photoPreview = ref(null)
const lightboxSrc  = ref(null)

// ── Import modal ──────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importFileRef   = ref(null)
const importForm = useForm({ file: null })

function submitImport() {
    importForm.post(route('items.import'), {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false
            importForm.reset()
            if (importFileRef.value) importFileRef.value.value = ''
        },
    })
}

// ── Badge colors ──────────────────────────────────────────────────────────
const BADGE_COLORS = [
    { bg: 'rgba(99,102,241,.15)',  color: '#818cf8' },
    { bg: 'rgba(245,158,11,.15)',  color: '#fbbf24' },
    { bg: 'rgba(16,185,129,.15)',  color: '#34d399' },
    { bg: 'rgba(239,68,68,.15)',   color: '#f87171' },
    { bg: 'rgba(59,130,246,.15)',  color: '#60a5fa' },
    { bg: 'rgba(236,72,153,.15)',  color: '#f472b6' },
]
function badgeColor(id) { return BADGE_COLORS[((id ?? 1) - 1) % BADGE_COLORS.length] }
</script>

<template>
<AppLayout>
  <template #title>{{ $t('menu.itemMaster') }}</template>
  <template #breadcrumb>{{ $t('menu.itemMaster') }}</template>

  <div class="page-body">

    <!-- ── Stats ──────────────────────────────────────────────────────── -->
    <div class="stats-row">
      <div class="sc sc-b">
        <div class="sc-ico ic-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8 12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        </div>
        <div><div class="sc-n">{{ stats.totalItems }}</div><div class="sc-l">{{ $t('im.totalItems') }}</div></div>
      </div>
      <div class="sc sc-g">
        <div class="sc-ico ic-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div><div class="sc-n">{{ stats.activeItems }}</div><div class="sc-l">{{ $t('im.activeItems') }}</div></div>
      </div>
      <div class="sc sc-o">
        <div class="sc-ico ic-orange">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        </div>
        <div><div class="sc-n">{{ stats.totalCategories }}</div><div class="sc-l">{{ $t('im.totalCats') }}</div></div>
      </div>
      <div class="sc sc-p">
        <div class="sc-ico ic-purple">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
        </div>
        <div><div class="sc-n">{{ stats.totalVariants }}</div><div class="sc-l">{{ $t('im.totalVariants') }}</div></div>
      </div>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────── -->
    <div style="display:flex;gap:4px;margin-bottom:16px">
      <button class="tab-btn" :class="{ active: activeTab === 'items' }"
        @click="activeTab = 'items'" type="button">
        {{ $t('im.tabItems') }}
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'categories' }"
        @click="activeTab = 'categories'" type="button">
        {{ $t('im.tabCategories') }}
      </button>
    </div>

    <!-- ════════════ ITEMS TAB ════════════════════════════════════════════ -->
    <template v-if="activeTab === 'items'">
      <div class="filter-bar">
        <input class="form-input" style="flex:1;min-width:220px"
          v-model="search" :placeholder="$t('im.search')" />
        <select class="form-select" style="width:180px" v-model="catFilter">
          <option value="">{{ $t('im.filterCat') }}</option>
          <option v-for="c in filteredCatOptions" :key="c.id" :value="c.id">{{ c.code }} · {{ catName(c) }}</option>
        </select>
        <select class="form-select" style="width:130px" v-model="statusFilt">
          <option value="">{{ $t('im.filterStatus') }}</option>
          <option value="active">{{ $t('status.active') }}</option>
          <option value="inactive">{{ $t('status.inactive') }}</option>
        </select>
        <button class="btn-primary" @click="openAdd" type="button">+ {{ $t('im.addItem') }}</button>
        <a :href="route('items.export')" class="btn-export">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          {{ $t('im.exportBtn') }}
        </a>
        <button class="btn-import" @click="showImportModal = true" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          {{ $t('im.importBtn') }}
        </button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>{{ $t('im.colPart') }}</th>
              <th>{{ $t('im.colName') }}</th>
              <th>{{ $t('im.colCategory') }}</th>
              <th>{{ $t('im.colUom') }}</th>
              <th style="text-align:right">{{ $t('im.colMinStock') }}</th>
              <th>{{ $t('im.colCooldown') }}</th>
              <th>{{ $t('im.colStatus') }}</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.data.length">
              <td colspan="9" style="text-align:center;padding:32px;opacity:.5">{{ $t('im.noResults') }}</td>
            </tr>
            <tr v-for="(item, idx) in items.data" :key="item.id">
              <td style="opacity:.4;font-size:12px">{{ (items.from ?? 1) + idx }}</td>
              <td>
                <span style="font-family:monospace;background:rgba(251,146,60,.15);color:#fb923c;padding:2px 7px;border-radius:4px;font-size:12px;font-weight:600;letter-spacing:.02em">
                  {{ item.part_number }}
                </span>
              </td>
              <td style="max-width:220px">
                <div style="font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ itemName(item) }}</div>
              </td>
              <td>
                <span v-if="item.category"
                  :style="{ background: badgeColor(item.category.id).bg, color: badgeColor(item.category.id).color, padding:'2px 8px', borderRadius:'10px', fontSize:'11px', fontWeight:600 }">
                  {{ item.category.code }}
                </span>
                <span v-else style="opacity:.3">—</span>
              </td>
              <td>
                <span style="font-size:12px;font-weight:500;font-family:monospace">{{ item.base_uom }}</span>
                <span v-if="item.alt_uom" style="font-size:11px;opacity:.45"> / {{ item.alt_uom }}</span>
              </td>
              <td style="text-align:right;font-weight:500;font-size:13px">{{ item.minimum_stock }}</td>
              <td>
                <span v-if="item.has_cooldown"
                  style="background:rgba(251,146,60,.12);color:#fb923c;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600">
                  {{ $t('im.cooldownYes', { n: item.cooldown_days }) }}
                </span>
                <span v-else style="opacity:.3;font-size:12px">{{ $t('im.cooldownNo') }}</span>
                <span v-if="item.is_mandatory"
                  style="margin-left:4px;background:rgba(239,68,68,.12);color:#ef4444;padding:2px 6px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px">
                  {{ $t('im.mandatory') }}
                </span>
              </td>
              <td>
                <span :style="{
                  background: item.is_active ? 'rgba(16,185,129,.12)' : 'rgba(100,116,139,.1)',
                  color:      item.is_active ? '#34d399' : '#64748b',
                  padding:'2px 8px', borderRadius:'10px', fontSize:'11px', fontWeight:600
                }">
                  {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
                </span>
              </td>
              <td>
                <button class="icon-btn" @click.stop="toggleDrop(`item-${item.id}`, $event)" type="button">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="items.last_page > 1" class="pager-row">
        <span>{{ $t('im.showing', { from: items.from ?? 0, to: items.to ?? 0, total: items.total }) }}</span>
        <div style="display:flex;gap:4px">
          <Link v-for="link in items.links" :key="link.label"
            :href="link.url ?? '#'" v-html="link.label"
            preserve-scroll
            :class="['page-btn', { active: link.active, disabled: !link.url }]" />
        </div>
      </div>
    </template>

    <!-- ════════════ CATEGORIES TAB ══════════════════════════════════════ -->
    <template v-else>
      <div class="filter-bar">
        <input class="form-input" style="flex:1;min-width:200px"
          v-model="catSearch" :placeholder="$t('im.catSearch')" />
        <button class="btn-primary" @click="openAddCat" type="button">+ {{ $t('im.addCategory') }}</button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>{{ $t('lm.colCode') }}</th>
              <th>Prefix</th>
              <th>{{ $t('im.colName') }}</th>
              <th style="text-align:right">{{ $t('im.catItems') }}</th>
              <th>{{ $t('lm.colStatus') }}</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!displayedCats.length">
              <td colspan="7" style="text-align:center;padding:32px;opacity:.5">{{ $t('im.noResults') }}</td>
            </tr>
            <tr v-for="(cat, idx) in displayedCats" :key="cat.id">
              <td style="opacity:.4;font-size:12px">{{ idx + 1 }}</td>
              <td>
                <span :style="{ background: badgeColor(cat.id).bg, color: badgeColor(cat.id).color, padding:'2px 8px', borderRadius:'4px', fontSize:'12px', fontWeight:600, fontFamily:'monospace' }">
                  {{ cat.code }}
                </span>
              </td>
              <td>
                <span style="font-family:monospace;font-size:11px;opacity:.6;background:rgba(99,102,241,.1);color:#818cf8;padding:2px 7px;border-radius:4px;font-weight:700">
                  WBN-{{ cat.prefix }}-…
                </span>
              </td>
              <td style="max-width:220px">
                <div style="font-weight:500">{{ catName(cat) }}</div>
              </td>
              <td style="text-align:right;font-weight:500">{{ cat.items_count }}</td>
              <td>
                <span :style="{
                  background: cat.is_active ? 'rgba(16,185,129,.12)' : 'rgba(100,116,139,.1)',
                  color:      cat.is_active ? '#34d399' : '#64748b',
                  padding:'2px 8px', borderRadius:'10px', fontSize:'11px', fontWeight:600
                }">
                  {{ cat.is_active ? $t('status.active') : $t('status.inactive') }}
                </span>
              </td>
              <td>
                <button class="icon-btn" @click.stop="toggleDrop(`cat-${cat.id}`, $event)" type="button">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

  </div>

  <!-- ── Action Dropdown ───────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="openDropId" class="action-drop" :style="dropStyle" @click.stop>
      <template v-if="openDropId.startsWith('item-')">
        <button @click="openEdit(items.data.find(i => `item-${i.id}` === openDropId)); closeDrop()" type="button">
          {{ $t('btn.edit') }}
        </button>
        <button @click="openVariants(items.data.find(i => `item-${i.id}` === openDropId)); closeDrop()" type="button">
          {{ $t('im.variants') }}
        </button>
        <button class="danger" @click="deleteItem(items.data.find(i => `item-${i.id}` === openDropId)); closeDrop()" type="button">
          {{ $t('btn.delete') }}
        </button>
      </template>
      <template v-if="openDropId.startsWith('cat-')">
        <button @click="openEditCat(displayedCats.find(c => `cat-${c.id}` === openDropId)); closeDrop()" type="button">
          {{ $t('btn.edit') }}
        </button>
        <button class="danger" @click="deleteCat(displayedCats.find(c => `cat-${c.id}` === openDropId)); closeDrop()" type="button">
          {{ $t('btn.delete') }}
        </button>
      </template>
    </div>
  </Teleport>

  <!-- ── Item Modal ────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showItemModal" class="modal-backdrop" @click.self="showItemModal = false">
      <div class="modal-box" style="max-width:560px">
        <div class="modal-header">
          <h2>{{ editingItem ? $t('im.editItem') : $t('im.addItem') }}</h2>
          <button class="icon-btn" @click="showItemModal = false" type="button">✕</button>
        </div>
        <form @submit.prevent="submitItem" class="modal-body">

          <div class="form-group">
            <label class="form-label">{{ $t('im.category') }} <span class="req">*</span></label>
            <select class="form-select" v-model="form.category_id" required>
              <option value="">— Select —</option>
              <option v-for="c in formCatOptions" :key="c.id" :value="c.id">{{ c.code }} · {{ catName(c) }}</option>
            </select>
            <div v-if="form.errors.category_id" class="form-err">{{ form.errors.category_id }}</div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ $t('im.partNumber') }} <span class="req">*</span></label>
            <div class="pn-compose-row">
              <span class="pn-prefix-badge">WBN-{{ selectedCatPrefix ?? '???' }}-</span>
              <input class="form-input pn-suffix-input"
                v-model="partSuffix"
                placeholder="e.g. STLI"
                style="font-family:monospace"
                @input="partSuffix = partSuffix.toUpperCase().replace(/[^A-Z0-9]/g, '')" />
            </div>
            <div v-if="form.part_number" class="pn-preview">
              <span style="opacity:.45">Part No:</span>
              <strong style="font-family:monospace;letter-spacing:.03em">{{ form.part_number }}</strong>
            </div>
            <div v-if="!form.category_id" style="font-size:11px;color:#f97316;margin-top:3px">
              {{ $t('im.selectCatFirst') }}
            </div>
            <div v-if="form.errors.part_number" class="form-err">{{ form.errors.part_number }}</div>
          </div>

          <div class="form-group">
            <div class="name-tab-row">
              <label class="form-label" style="margin:0">{{ $t('im.colName') }} <span class="req">*</span></label>
              <div class="name-tab-group">
                <button v-for="l in ['en','id','zh']" :key="l" type="button"
                  class="name-tab" :class="{ active: nameTab === l }"
                  @click="nameTab = l">
                  {{ l === 'zh' ? '中' : l.toUpperCase() }}
                  <span v-if="l==='en' && form.name_en" class="name-dot"></span>
                  <span v-if="l==='id' && form.name_id" class="name-dot"></span>
                  <span v-if="l==='zh' && form.name_zh" class="name-dot"></span>
                </button>
              </div>
            </div>
            <input v-if="nameTab === 'en'" class="form-input" v-model="form.name_en"
              :placeholder="$t('im.nameEn')" required style="margin-top:5px" />
            <input v-if="nameTab === 'id'" class="form-input" v-model="form.name_id"
              :placeholder="$t('im.nameId')" style="margin-top:5px" />
            <input v-if="nameTab === 'zh'" class="form-input" v-model="form.name_zh"
              :placeholder="$t('im.nameZh')" style="margin-top:5px" />
            <div v-if="form.errors.name_en" class="form-err">{{ form.errors.name_en }}</div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ $t('im.description') }}</label>
            <textarea class="form-input" v-model="form.description"
              :placeholder="$t('im.descPh')" rows="2" style="resize:vertical" />
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="form-group">
              <label class="form-label">{{ $t('im.baseUom') }} <span class="req">*</span></label>
              <input class="form-input" style="font-family:monospace;text-transform:uppercase"
                v-model="form.base_uom" :placeholder="$t('im.baseUomPh')" required />
              <div v-if="form.errors.base_uom" class="form-err">{{ form.errors.base_uom }}</div>
            </div>
            <div class="form-group">
              <label class="form-label">{{ $t('im.altUom') }}</label>
              <input class="form-input" style="font-family:monospace;text-transform:uppercase"
                v-model="form.alt_uom" :placeholder="$t('im.altUomPh')" />
            </div>
          </div>

          <div v-if="form.alt_uom" class="form-group">
            <label class="form-label">{{ $t('im.conversion') }}</label>
            <input class="form-input" v-model="form.alt_uom_conversion" type="number" step="any" min="0"
              :placeholder="`1 ${form.alt_uom} = ? ${form.base_uom}`" />
            <div style="font-size:11px;opacity:.45;margin-top:3px">
              {{ $t('im.conversionHint', { alt: form.alt_uom || '?', n: form.alt_uom_conversion || '?', base: form.base_uom || '?' }) }}
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="form-group">
              <label class="form-label">{{ $t('im.minStock') }}</label>
              <input class="form-input" v-model="form.minimum_stock" type="number" step="0.01" min="0" />
              <div v-if="form.errors.minimum_stock" class="form-err">{{ form.errors.minimum_stock }}</div>
            </div>
            <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end">
              <label class="toggle-row">
                <input type="checkbox" v-model="form.is_active" style="display:none" />
                <div class="toggle-track green" :class="{ on: form.is_active }"><div class="toggle-thumb"></div></div>
                <span class="form-label" style="margin:0">{{ $t('im.isActive') }}</span>
              </label>
            </div>
          </div>

          <!-- Photo Required + Cooldown toggles -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
            <label class="toggle-row">
              <input type="checkbox" v-model="form.photo_required" style="display:none" />
              <div class="toggle-track" :class="{ on: form.photo_required }"><div class="toggle-thumb"></div></div>
              <span class="form-label" style="margin:0">Photo Required</span>
            </label>
            <label class="toggle-row">
              <input type="checkbox" v-model="form.has_cooldown" style="display:none" />
              <div class="toggle-track" :class="{ on: form.has_cooldown }"><div class="toggle-thumb"></div></div>
              <span class="form-label" style="margin:0">{{ $t('im.hasCooldown') }}</span>
            </label>
          </div>

          <!-- Cooldown detail -->
          <div v-if="form.has_cooldown" class="form-group">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="form-group" style="margin:0">
                <label class="form-label">{{ $t('im.cooldownDays') }}</label>
                <input class="form-input" v-model="form.cooldown_days" type="number" min="1" />
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">{{ $t('im.trackBy') }}</label>
                <select class="form-select" v-model="form.cooldown_track_by">
                  <option value="lv_number">{{ $t('im.lvNumber') }}</option>
                  <option value="employee_id">{{ $t('im.employeeId') }}</option>
                </select>
              </div>
            </div>
            <!-- Mandatory Distribution toggle -->
            <div style="margin-top:10px">
              <label class="toggle-row">
                <input type="checkbox" v-model="form.is_mandatory" style="display:none" />
                <div class="toggle-track" :class="{ on: form.is_mandatory }"><div class="toggle-thumb"></div></div>
                <span class="form-label" style="margin:0">{{ $t('im.isMandatory') }}</span>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-ghost" @click="showItemModal = false">{{ $t('btn.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">
              {{ form.processing ? $t('im.saving') : $t('btn.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Category Modal ────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showCatModal" class="modal-backdrop" @click.self="showCatModal = false">
      <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
          <h2>{{ editingCat ? $t('im.editCategory') : $t('im.addCategory') }}</h2>
          <button class="icon-btn" @click="showCatModal = false" type="button">✕</button>
        </div>
        <form @submit.prevent="submitCat" class="modal-body">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="form-group">
              <label class="form-label">{{ $t('im.catCode') }} <span class="req">*</span></label>
              <input class="form-input" style="font-family:monospace;text-transform:uppercase"
                v-model="catForm.code" :placeholder="$t('im.catCodePh')" required />
              <div v-if="catForm.errors.code" class="form-err">{{ catForm.errors.code }}</div>
            </div>
            <div class="form-group">
              <label class="form-label">Prefix Part No <span class="req">*</span></label>
              <input class="form-input" style="font-family:monospace;text-transform:uppercase"
                v-model="catForm.prefix" placeholder="e.g. MCNC" maxlength="10" required
                @input="catForm.prefix = catForm.prefix.toUpperCase().replace(/[^A-Z0-9]/g, '')" />
              <div style="font-size:11px;opacity:.4;margin-top:2px">→ WBN-{{ catForm.prefix || '???' }}-XXXXX</div>
              <div v-if="catForm.errors.prefix" class="form-err">{{ catForm.errors.prefix }}</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">{{ $t('im.catNameEn') }} <span class="req">*</span></label>
            <input class="form-input" v-model="catForm.name_en" required />
            <div v-if="catForm.errors.name_en" class="form-err">{{ catForm.errors.name_en }}</div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="form-group">
              <label class="form-label">{{ $t('im.catNameId') }}</label>
              <input class="form-input" v-model="catForm.name_id" />
            </div>
            <div class="form-group">
              <label class="form-label">{{ $t('im.catNameZh') }}</label>
              <input class="form-input" v-model="catForm.name_zh" />
            </div>
          </div>

          <label class="toggle-row" style="margin-top:4px">
            <input type="checkbox" v-model="catForm.is_active" style="display:none" />
            <div class="toggle-track green" :class="{ on: catForm.is_active }"><div class="toggle-thumb"></div></div>
            <span class="form-label" style="margin:0">{{ $t('im.isActive') }}</span>
          </label>

          <div class="modal-footer">
            <button type="button" class="btn-ghost" @click="showCatModal = false">{{ $t('btn.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="catForm.processing">
              {{ catForm.processing ? $t('im.saving') : $t('btn.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Variants Modal ────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showVarModal" class="modal-backdrop" @click.self="showVarModal = false">
      <div class="modal-box" style="max-width:560px">
        <div class="modal-header">
          <h2>{{ $t('im.variants') }}
            <span style="font-family:monospace;font-size:13px;opacity:.5;margin-left:8px">{{ variantItem?.part_number }}</span>
          </h2>
          <button class="icon-btn" @click="showVarModal = false" type="button">✕</button>
        </div>
        <div class="modal-body">

          <!-- Existing variants list -->
          <div style="margin-bottom:16px">
            <div v-if="!variantItem?.variants?.length"
              style="text-align:center;padding:20px;opacity:.4;font-size:13px">
              {{ $t('im.noVariants') }}
            </div>
            <div v-for="v in variantItem?.variants" :key="v.id"
              style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:6px;margin-bottom:4px;background:var(--surface-2)">
              <img v-if="v.photo_path" :src="v.photo_path" class="var-thumb" alt="photo"
                style="cursor:zoom-in" @click="lightboxSrc = v.photo_path" />
              <div v-else class="var-thumb-ph">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-family:monospace;font-size:11px;font-weight:700;opacity:.65;margin-bottom:3px">{{ v.sku }}</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:12px">
                  <span v-if="v.brand"><span style="opacity:.45">{{ $t('im.brand') }}:</span> {{ v.brand }}</span>
                  <span v-if="v.model"><span style="opacity:.45">{{ $t('im.model') }}:</span> {{ v.model }}</span>
                  <span v-if="v.size"><span style="opacity:.45">{{ $t('im.size') }}:</span> {{ v.size }}</span>
                  <span v-if="v.color"><span style="opacity:.45">{{ $t('im.color') }}:</span> {{ v.color }}</span>
                  <span v-if="!v.brand && !v.model && !v.size && !v.color" style="opacity:.35">—</span>
                </div>
              </div>
              <span :style="{
                background: v.is_active ? 'rgba(16,185,129,.12)' : 'rgba(100,116,139,.1)',
                color: v.is_active ? '#34d399' : '#64748b',
                padding:'1px 6px', borderRadius:'8px', fontSize:'11px', flexShrink:0
              }">{{ v.is_active ? $t('status.active') : $t('status.inactive') }}</span>
              <button class="icon-btn" style="width:26px;height:26px" @click="editVariant(v)" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
              </button>
              <button class="icon-btn" style="width:26px;height:26px;color:#f87171" @click="deleteVariant(v)" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>

          <!-- Add / Edit variant form -->
          <div style="border-top:1px solid var(--border);padding-top:14px">
            <div style="font-size:11px;font-weight:600;opacity:.45;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
              {{ editingVariant ? $t('im.editVariant') : $t('im.addVariant') }}
            </div>
            <form @submit.prevent="submitVariant">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                <div class="form-group" style="margin:0">
                  <label class="form-label">{{ $t('im.brand') }}</label>
                  <input class="form-input" v-model="varForm.brand" />
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label">{{ $t('im.model') }}</label>
                  <input class="form-input" v-model="varForm.model" />
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label">{{ $t('im.size') }}</label>
                  <input class="form-input" v-model="varForm.size" />
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label">{{ $t('im.color') }}</label>
                  <input class="form-input" v-model="varForm.color" />
                </div>
              </div>

              <!-- Photo upload -->
              <div class="form-group">
                <label class="form-label">
                  Photo
                  <span v-if="variantItem?.photo_required" class="req"> * (required)</span>
                  <span v-else style="opacity:.4;font-weight:400"> (optional)</span>
                </label>
                <div class="photo-upload-row">
                  <!-- new preview (after file selected) -->
                  <img v-if="photoPreview"
                    :src="photoPreview" class="photo-thumb" alt="preview"
                    style="cursor:zoom-in" @click="lightboxSrc = photoPreview" />
                  <!-- existing saved photo (edit mode, no new file chosen yet) -->
                  <img v-else-if="editingVariant?.photo_path"
                    :src="editingVariant.photo_path" class="photo-thumb" alt="current photo"
                    style="cursor:zoom-in" @click="lightboxSrc = editingVariant.photo_path" />
                  <!-- placeholder (new variant, nothing selected) -->
                  <div v-else class="photo-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  </div>
                  <label class="btn-upload">
                    <input type="file" accept="image/*" style="display:none"
                      @change="onVariantPhotoChange" />
                    {{ photoPreview ? 'Change' : (editingVariant?.photo_path ? 'Replace' : 'Upload') }}
                  </label>
                </div>
                <div v-if="varForm.errors.photo" class="form-err">{{ varForm.errors.photo }}</div>
              </div>

              <label class="toggle-row" style="margin-bottom:12px">
                <input type="checkbox" v-model="varForm.is_active" style="display:none" />
                <div class="toggle-track green" :class="{ on: varForm.is_active }"><div class="toggle-thumb"></div></div>
                <span class="form-label" style="margin:0">{{ $t('im.isActive') }}</span>
              </label>
              <div style="display:flex;gap:8px;justify-content:flex-end">
                <button v-if="editingVariant" type="button" class="btn-ghost" @click="cancelEditVar">{{ $t('btn.cancel') }}</button>
                <button type="submit" class="btn-primary" :disabled="varForm.processing">
                  {{ varForm.processing ? $t('im.saving') : (editingVariant ? $t('btn.save') : $t('im.addVariant')) }}
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Lightbox ─────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="lightboxSrc" class="lightbox-bd" @click="lightboxSrc = null">
      <button class="lightbox-close" @click.stop="lightboxSrc = null">✕</button>
      <img :src="lightboxSrc" class="lightbox-img" @click.stop />
    </div>
  </Teleport>

  <!-- ── Import Modal ──────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showImportModal" class="modal-backdrop" @click.self="showImportModal = false">
      <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
          <h2>{{ $t('im.importTitle') }}</h2>
          <button class="icon-btn" @click="showImportModal = false" type="button">✕</button>
        </div>
        <div class="modal-body">

          <!-- Steps -->
          <div class="import-steps">
            <div class="import-step">
              <span class="step-num">1</span>
              <div>
                <div style="font-weight:600;font-size:13px">{{ $t('im.importStep1') }}</div>
                <div style="font-size:12px;opacity:.55">{{ $t('im.importStep1Sub') }}</div>
              </div>
            </div>
            <div class="import-step">
              <span class="step-num">2</span>
              <div style="font-size:13px;font-weight:600">{{ $t('im.importStep2') }}</div>
            </div>
            <div class="import-step">
              <span class="step-num">3</span>
              <div style="font-size:13px;font-weight:600">{{ $t('im.importStep3') }}</div>
            </div>
          </div>

          <a :href="route('items.importTemplate')"
            class="btn-ghost" style="display:inline-flex;align-items:center;gap:7px;margin-bottom:18px;width:100%;justify-content:center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {{ $t('im.importDownloadTpl') }}
          </a>

          <!-- Format guide -->
          <div class="import-format">
            <div style="font-size:11px;font-weight:700;opacity:.5;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
              {{ $t('im.importFormatTitle') }}
            </div>
            <ul style="font-size:12px;opacity:.7;margin:0;padding-left:16px;line-height:1.8">
              <li>{{ $t('im.importFmt1') }}</li>
              <li>{{ $t('im.importFmt2') }}</li>
              <li>{{ $t('im.importFmt3') }}</li>
              <li>{{ $t('im.importFmt4') }}</li>
            </ul>
          </div>

          <form @submit.prevent="submitImport" style="margin-top:16px">
            <div class="form-group">
              <label class="form-label">{{ $t('im.importFile') }} <span class="req">*</span></label>
              <input ref="importFileRef" type="file" accept=".csv,.xlsx,.xls"
                class="form-input"
                @change="e => { importForm.file = e.target.files[0] }" />
              <div style="font-size:11px;opacity:.4;margin-top:3px">{{ $t('im.importFileHint') }}</div>
              <div v-if="importForm.errors.file" class="form-err">{{ importForm.errors.file }}</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-ghost" @click="showImportModal = false">{{ $t('btn.cancel') }}</button>
              <button type="submit" class="btn-primary"
                :disabled="importForm.processing || !importForm.file">
                {{ importForm.processing ? $t('im.importing') : $t('im.importBtn') }}
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
/* ── page layout ─────────────────────────────────────────────────────── */
.page-body { display: flex; flex-direction: column; gap: 0; }
.filter-bar { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }

/* ── stat cards ──────────────────────────────────────────────────────── */
.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 20px; }
.sc { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm); border-left: 3px solid transparent; }
.sc-b { border-left-color: #3b82f6; }
.sc-g { border-left-color: #10b981; }
.sc-o { border-left-color: #f97316; }
.sc-p { border-left-color: #8b5cf6; }
.sc-ico { width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; }
.sc-ico svg { width: 22px; height: 22px; }
.ic-blue   { background: rgba(59,130,246,.15); color: #60a5fa; }
.ic-green  { background: rgba(16,185,129,.15); color: #10b981; }
.ic-orange { background: rgba(249,115,22,.15); color: #fb923c; }
.ic-purple { background: rgba(139,92,246,.15); color: #a78bfa; }
.sc-n { font-size: 26px; font-weight: 800; letter-spacing: -.03em; color: var(--fg); line-height: 1; }
.sc-l { font-size: 12px; color: var(--fg-2); font-weight: 500; margin-top: 4px; }

/* ── form controls ───────────────────────────────────────────────────── */
.form-input, .form-select {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 7px; padding: 7px 10px; font-size: 13px;
  color: var(--fg); font-family: inherit; outline: none; width: 100%;
  transition: border-color .15s, box-shadow .15s;
}
.form-input:focus, .form-select:focus {
  border-color: var(--orange-400); box-shadow: 0 0 0 2px rgba(251,146,60,.12);
}
.form-select { cursor: pointer; }
textarea.form-input { resize: vertical; }
.form-group { margin-bottom: 12px; }
.form-label { display: block; font-size: 12px; font-weight: 600; color: var(--fg-2); margin-bottom: 4px; }
.form-err { font-size: 11.5px; color: #f87171; margin-top: 3px; }

/* ── buttons ─────────────────────────────────────────────────────────── */
.btn-primary {
  background: linear-gradient(180deg, var(--orange-400), var(--orange-500));
  color: #fff; border: none; border-radius: 7px; padding: 7px 14px;
  font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
  white-space: nowrap; flex-shrink: 0; transition: opacity .15s;
}
.btn-primary:hover { opacity: .88; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.btn-ghost {
  background: transparent; color: var(--fg-2); border: 1px solid var(--border);
  border-radius: 7px; padding: 7px 14px; font-size: 13px; font-weight: 500;
  cursor: pointer; font-family: inherit; transition: background .15s, color .15s;
}
.btn-ghost:hover { background: var(--hover); color: var(--fg); }
.btn-export, .btn-import {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: inherit; white-space: nowrap; text-decoration: none;
  transition: background .15s, border-color .15s;
}
.btn-export {
  background: rgba(16,185,129,.12); color: #34d399;
  border: 1px solid rgba(16,185,129,.25);
}
.btn-export:hover { background: rgba(16,185,129,.2); border-color: rgba(16,185,129,.4); }
.btn-import {
  background: rgba(59,130,246,.12); color: #60a5fa;
  border: 1px solid rgba(59,130,246,.25);
}
.btn-import:hover { background: rgba(59,130,246,.2); border-color: rgba(59,130,246,.4); }

/* ── table ───────────────────────────────────────────────────────────── */
.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th {
  background: var(--surface-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em; color: var(--fg-2);
  padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border);
}
.data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--hover); }

/* ── pagination ──────────────────────────────────────────────────────── */
.page-btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 4px 9px; border-radius: 5px; font-size: 12px; text-decoration: none;
  border: 1px solid var(--border); color: var(--fg-2);
  transition: background .12s, color .12s;
}
.page-btn:hover { background: var(--hover); color: var(--fg); }
.page-btn.active { background: var(--orange-500); color: #fff; border-color: var(--orange-500); }
.page-btn.disabled { opacity: .4; pointer-events: none; }

/* ── modal ───────────────────────────────────────────────────────────── */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,.55);
  display: flex; align-items: center; justify-content: center;
  z-index: 200; padding: 16px;
}
.modal-box {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 14px; box-shadow: var(--shadow-lg); width: 100%;
  max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
  color: var(--fg);
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.modal-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--fg); }
.modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 8px;
  padding-top: 16px; margin-top: 8px; border-top: 1px solid var(--border);
}

/* ── name language tabs ──────────────────────────────────────────────── */
.name-tab-row {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;
}
.name-tab-group { display: flex; gap: 3px; }
.name-tab {
  position: relative; padding: 2px 9px; font-size: 11px; font-weight: 700;
  border-radius: 5px; border: 1px solid var(--border-2); cursor: pointer;
  background: var(--surface-2); color: var(--fg-2);
  transition: background .15s, color .15s, border-color .15s;
  letter-spacing: .04em;
}
.name-tab:hover:not(.active) { background: var(--surface-3); color: var(--fg); }
.name-tab.active { background: var(--orange-500); color: #fff; border-color: var(--orange-500); }
.name-dot {
  position: absolute; top: 2px; right: 2px; width: 4px; height: 4px;
  border-radius: 50%; background: #fff; opacity: .7;
}
.name-tab:not(.active) .name-dot { background: var(--emerald); opacity: 1; }

/* ── main tabs ───────────────────────────────────────────────────────── */
.tab-btn {
  padding: 6px 18px; border-radius: 6px; font-size: 13px; font-weight: 500;
  border: 1px solid transparent; cursor: pointer; background: transparent;
  color: var(--fg-2); transition: all .15s;
}
.tab-btn.active { background: var(--orange-500); color: #fff; }
.tab-btn:not(.active):hover { background: var(--surface-2); color: var(--fg); }

.toggle-track {
  width: 34px; height: 18px; border-radius: 9px; background: var(--border-2);
  position: relative; flex-shrink: 0; transition: background .2s; cursor: pointer;
}
.toggle-track.on { background: var(--orange-500); }
.toggle-track.on.green { background: var(--emerald); }
.toggle-thumb {
  position: absolute; top: 2px; left: 2px; width: 14px; height: 14px;
  border-radius: 50%; background: #fff; transition: left .2s;
}
.toggle-track.on .toggle-thumb { left: 18px; }
.toggle-row { display: flex; align-items: center; gap: 8px; cursor: pointer; }

.req { color: #f87171; }
.pager-row { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 13px; opacity: .7; }

/* ── lightbox ────────────────────────────────────────────────────────── */
.lightbox-bd {
  position: fixed; inset: 0; background: rgba(0,0,0,.88);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; cursor: zoom-out;
}
.lightbox-img {
  max-width: 90vw; max-height: 90vh; object-fit: contain;
  border-radius: 8px; box-shadow: 0 8px 40px rgba(0,0,0,.6); cursor: default;
}
.lightbox-close {
  position: absolute; top: 18px; right: 22px;
  background: rgba(255,255,255,.12); color: #fff;
  border: none; width: 38px; height: 38px; border-radius: 50%;
  font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .15s;
}
.lightbox-close:hover { background: rgba(255,255,255,.25); }

/* ── import modal ────────────────────────────────────────────────────── */
.import-steps { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.import-step { display: flex; align-items: flex-start; gap: 10px; }
.step-num {
  width: 22px; height: 22px; border-radius: 50%; background: var(--orange-500);
  color: #fff; font-size: 11px; font-weight: 800; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
.import-format {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 8px; padding: 10px 12px;
}
.import-format code { font-family: monospace; font-size: 11px; }

/* ── part number composer ────────────────────────────────────────────── */
.pn-compose-row { display: flex; align-items: center; gap: 0; }
.pn-prefix-badge {
  flex-shrink: 0; padding: 7px 10px; background: var(--surface-2);
  border: 1px solid var(--border); border-right: none;
  border-radius: 7px 0 0 7px; font-family: monospace; font-size: 13px;
  font-weight: 700; color: var(--orange-400); white-space: nowrap;
}
.pn-suffix-input { border-radius: 0 7px 7px 0 !important; }
.pn-preview {
  margin-top: 5px; padding: 5px 10px; border-radius: 6px;
  background: rgba(251,146,60,.08); border: 1px dashed rgba(251,146,60,.3);
  font-size: 12px; color: var(--fg); display: flex; gap: 6px; align-items: center;
}

/* ── photo upload ────────────────────────────────────────────────────── */
.photo-upload-row { display: flex; align-items: center; gap: 10px; }
.photo-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); flex-shrink: 0; }
.photo-placeholder {
  width: 60px; height: 60px; border-radius: 8px; border: 1px dashed var(--border-2);
  display: grid; place-items: center; color: var(--fg-2); opacity: .5; flex-shrink: 0;
}
.btn-upload {
  padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 600;
  border: 1px solid var(--border); background: var(--surface-2); color: var(--fg-2);
  cursor: pointer; transition: background .15s, color .15s;
}
.btn-upload:hover { background: var(--surface-3); color: var(--fg); }

/* ── variant list thumbnail ──────────────────────────────────────────── */
.var-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); flex-shrink: 0; }
.var-thumb-ph {
  width: 40px; height: 40px; border-radius: 6px; border: 1px dashed var(--border);
  display: grid; place-items: center; color: var(--fg-2); opacity: .35; flex-shrink: 0;
}

.action-drop {
  z-index: 9999; background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,.25); overflow: hidden;
}
.action-drop button {
  display: block; width: 100%; text-align: left; padding: 8px 14px;
  font-size: 13px; background: transparent; border: none; cursor: pointer;
  color: var(--fg); transition: background .12s;
}
.action-drop button:hover { background: var(--surface-2); }
.action-drop button.danger { color: #f87171; }
.action-drop button.danger:hover { background: rgba(239,68,68,.08); }
</style>
