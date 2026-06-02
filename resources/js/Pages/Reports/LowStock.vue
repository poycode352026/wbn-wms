<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { locale } = useI18n()
const page = usePage()

const props = defineProps({
    lowStockItems: { type: Array,  default: () => [] },
    totalCount:    { type: Number, default: 0 },
    filters:       { type: Object, default: () => ({}) },
    categories:    { type: Array,  default: () => [] },
})

const role       = computed(() => page.props.auth?.user?.role ?? '')
const canReorder = computed(() => ['wh_admin', 'super_admin'].includes(role.value))

// ── Filters ────────────────────────────────────────────────────────────────
const search   = ref(props.filters?.search   ?? '')
const category = ref(props.filters?.category ?? '')

let filterTimer = null
function applyFilter() {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        router.get(route('low-stock.index'), {
            search:   search.value || undefined,
            category: category.value || undefined,
        }, {
            preserveScroll: true,
            preserveState:  true,
            only:           ['lowStockItems', 'totalCount', 'filters', 'categories'],
            replace:        true,
        })
    }, 350)
}
watch([search, category], applyFilter)

// ── Select / Reorder ───────────────────────────────────────────────────────
const selectedReorder = ref([])

const allSelected = computed(() =>
    props.lowStockItems.length > 0 &&
    props.lowStockItems.filter(i => i.variant_id).every(i => isSelected(i))
)
const someSelected = computed(() =>
    selectedReorder.value.length > 0 && !allSelected.value
)

function toggleAll() {
    if (allSelected.value) {
        selectedReorder.value = []
    } else {
        selectedReorder.value = props.lowStockItems
            .filter(i => i.variant_id)
            .map(i => ({ variant_id: i.variant_id, suggested_qty: i.suggested_qty }))
    }
}

function toggleReorder(item) {
    const idx = selectedReorder.value.findIndex(s => s.variant_id === item.variant_id)
    if (idx >= 0) selectedReorder.value.splice(idx, 1)
    else selectedReorder.value.push({ variant_id: item.variant_id, suggested_qty: item.suggested_qty })
}

function isSelected(item) {
    return selectedReorder.value.some(s => s.variant_id === item.variant_id)
}

function doReorder() {
    if (!selectedReorder.value.length) return
    const reorderParam = selectedReorder.value
        .map(s => `${s.variant_id}:${s.suggested_qty}`)
        .join(',')
    router.get(route('gr.create'), { reorder: reorderParam })
}

// ── Helpers ────────────────────────────────────────────────────────────────
function itemName(item) {
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}

function clearSearch() { search.value = ''; category.value = '' }
</script>

<template>
<AppLayout>
  <template #title>Low Stock Alert</template>
  <template #breadcrumb>
    <Link :href="route('dashboard')" class="bc-link">Dashboard</Link>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="m9 18 6-6-6-6"/></svg>
    Low Stock Alert
  </template>

  <!-- Page Header -->
  <div class="ls-page-head">
    <div class="ls-head-left">
      <div class="ls-head-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <h1 class="ls-title">Low Stock Alert</h1>
        <p class="ls-sub">Items / variants below minimum stock level</p>
      </div>
    </div>
    <div class="ls-head-right">
      <div class="ls-stat-badge" :class="props.totalCount > 0 ? 'danger' : 'ok'">
        <span class="ls-stat-num">{{ props.totalCount }}</span>
        <span class="ls-stat-lbl">{{ props.totalCount === 1 ? 'variant' : 'variants' }} low</span>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="ls-filters">
    <div class="ls-search-wrap">
      <svg class="ls-search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input
        class="ls-search"
        type="text"
        v-model="search"
        placeholder="Search SKU, item name…"
      />
      <button v-if="search" class="ls-clear-btn" type="button" @click="search = ''">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <select class="ls-select" v-model="category">
      <option value="">All Categories</option>
      <option v-for="cat in props.categories" :key="cat.id" :value="cat.id">
        {{ locale === 'id' ? (cat.name_id || cat.name_en) : cat.name_en }}
      </option>
    </select>

    <button v-if="search || category" class="ls-reset-btn" type="button" @click="clearSearch">
      Reset
    </button>
  </div>

  <!-- Table -->
  <div class="ls-panel">
    <table class="ls-tbl" v-if="props.lowStockItems?.length">
      <thead>
        <tr>
          <th v-if="canReorder" style="width:38px;text-align:center">
            <input type="checkbox"
              class="ls-check"
              :checked="allSelected"
              :indeterminate="someSelected"
              @change="toggleAll"
              title="Select all" />
          </th>
          <th>SKU</th>
          <th>Item</th>
          <th>Details</th>
          <th>Category</th>
          <th class="right">SOH</th>
          <th class="right">Min</th>
          <th class="right">Reorder Qty</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in props.lowStockItems" :key="item.variant_id"
            :class="{ 'ls-row-selected': isSelected(item) }">
          <td v-if="canReorder" style="text-align:center">
            <input type="checkbox" class="ls-check"
              :checked="isSelected(item)"
              :disabled="!item.variant_id"
              @change="toggleReorder(item)" />
          </td>
          <td class="mono">{{ item.sku }}</td>
          <td>
            <div class="ls-item-name">{{ itemName(item) }}</div>
          </td>
          <td>
            <div v-if="item.details" class="ls-detail-tag">{{ item.details }}</div>
            <span v-else class="dim">—</span>
          </td>
          <td>
            <span v-if="item.category_en" class="ls-cat-pill">{{ item.category_en }}</span>
            <span v-else class="dim">—</span>
          </td>
          <td class="right">
            <span class="ls-soh" :class="item.soh === 0 ? 'zero' : 'low'">
              {{ item.soh.toLocaleString() }}
            </span>
          </td>
          <td class="right dim num">{{ item.min.toLocaleString() }}</td>
          <td class="right">
            <span class="ls-reorder-qty">+{{ item.suggested_qty.toLocaleString() }}</span>
            <span class="ls-uom">{{ item.uom }}</span>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Empty state -->
    <div v-else class="ls-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" style="color:var(--fg-2)"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z"/></svg>
      <div v-if="search || category">
        <p style="font-weight:600;margin:12px 0 4px">No results found</p>
        <p style="font-size:13px">Try adjusting your search or filter.</p>
        <button class="ls-reset-btn" style="margin-top:12px" @click="clearSearch">Clear filters</button>
      </div>
      <div v-else>
        <p style="font-weight:600;margin:12px 0 4px">All stock levels are above minimum ✓</p>
        <p style="font-size:13px;color:var(--fg-2)">No items need restocking right now.</p>
      </div>
    </div>
  </div>

  <!-- Sticky Reorder Bar -->
  <Teleport to="body">
    <Transition name="slide-up">
      <div v-if="canReorder && selectedReorder.length" class="ls-reorder-bar">
        <div class="ls-reorder-bar-inner">
          <div class="ls-reorder-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="1"/><path d="m12 15 3 3 5-5"/></svg>
            <strong>{{ selectedReorder.length }}</strong> variant{{ selectedReorder.length > 1 ? 's' : '' }} selected for reorder
          </div>
          <div class="ls-reorder-actions">
            <button class="ls-deselect-btn" type="button" @click="selectedReorder = []">Deselect All</button>
            <button class="ls-go-btn" type="button" @click="doReorder">
              Create Reorder GR
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

</AppLayout>
</template>

<style scoped>
/* ── Page Header ─────────────────────────────────────────────────────────── */
.ls-page-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap}
.ls-head-left{display:flex;align-items:center;gap:14px}
.ls-head-icon{width:48px;height:48px;border-radius:12px;background:rgba(239,68,68,.12);color:#ef4444;display:grid;place-items:center;flex-shrink:0}
.ls-head-icon svg{width:22px;height:22px}
.ls-title{margin:0;font-size:24px;font-weight:700;letter-spacing:-.025em;color:var(--fg)}
.ls-sub{margin:4px 0 0;font-size:13px;color:var(--fg-2)}
.ls-head-right{flex-shrink:0}
.ls-stat-badge{display:flex;align-items:baseline;gap:6px;padding:10px 16px;border-radius:12px;border:1px solid}
.ls-stat-badge.danger{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25)}
.ls-stat-badge.ok{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.25)}
.ls-stat-num{font-size:28px;font-weight:700;color:#ef4444;line-height:1}
.ls-stat-badge.ok .ls-stat-num{color:#10b981}
.ls-stat-lbl{font-size:12px;font-weight:600;color:var(--fg-2)}

/* ── Filter Bar ──────────────────────────────────────────────────────────── */
.ls-filters{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.ls-search-wrap{position:relative;flex:1;min-width:220px;max-width:400px}
.ls-search-ico{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--fg-2);pointer-events:none}
.ls-search{width:100%;padding:8px 32px 8px 34px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--fg);font-size:13px;font-family:inherit;outline:none;box-sizing:border-box}
.ls-search:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.ls-clear-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);appearance:none;border:0;background:transparent;color:var(--fg-2);cursor:pointer;padding:2px;display:flex;align-items:center}
.ls-select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--fg);font-size:13px;font-family:inherit;outline:none;cursor:pointer}
.ls-select:focus{border-color:#3b82f6}
.ls-reset-btn{appearance:none;border:1px solid var(--border);background:var(--surface-2);color:var(--fg-2);padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit}
.ls-reset-btn:hover{background:var(--surface);color:var(--fg)}

/* ── Table ───────────────────────────────────────────────────────────────── */
.ls-panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow-sm)}
.ls-tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.ls-tbl th{text-align:left;font-size:10.5px;font-weight:700;color:var(--fg-2);letter-spacing:.1em;text-transform:uppercase;padding:11px 14px;border-bottom:1px solid var(--border);background:var(--surface-2);white-space:nowrap}
.ls-tbl td{padding:12px 14px;border-bottom:1px solid var(--border-soft);color:var(--fg);vertical-align:middle}
.ls-tbl tr:last-child td{border-bottom:0}
.ls-tbl tr:hover td{background:var(--hover)}
.ls-row-selected td{background:rgba(249,115,22,.12)!important;border-bottom-color:rgba(249,115,22,.2)!important}
.mono{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--fg-2)}
.right{text-align:right}
.num{font-variant-numeric:tabular-nums}
.dim{color:var(--fg-2)}

.ls-check{width:15px;height:15px;cursor:pointer;accent-color:#f97316}
.ls-item-name{font-weight:600;color:var(--fg);font-size:13px}
.ls-detail-tag{font-size:11.5px;color:var(--fg-2);margin-top:2px}
.ls-cat-pill{display:inline-flex;align-items:center;font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:999px;background:var(--surface-2);border:1px solid var(--border-soft);color:var(--fg-2)}
.ls-soh{font-weight:700;font-variant-numeric:tabular-nums}
.ls-soh.zero{color:#ef4444}
.ls-soh.low{color:#f59e0b}
.ls-reorder-qty{font-weight:700;color:#f97316;font-variant-numeric:tabular-nums}
.ls-uom{font-size:10.5px;color:var(--fg-2);margin-left:3px;font-weight:500}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.ls-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;color:var(--fg-2);text-align:center;gap:0}

/* ── Sticky Reorder Bar ──────────────────────────────────────────────────── */
.ls-reorder-bar{position:fixed;bottom:0;left:0;right:0;z-index:200;padding:0 24px 24px;pointer-events:none}
.ls-reorder-bar-inner{max-width:1200px;margin:0 auto;background:#0f172a;color:#f1f5f9;border-radius:14px;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 8px 40px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.08);pointer-events:all;flex-wrap:wrap}
.ls-reorder-info{display:flex;align-items:center;gap:10px;font-size:13.5px;color:#f1f5f9}
.ls-reorder-info strong{color:#fb923c;font-size:16px}
.ls-reorder-actions{display:flex;align-items:center;gap:10px}
.ls-deselect-btn{appearance:none;border:1px solid rgba(241,245,249,.2);background:transparent;color:rgba(241,245,249,.65);padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all 180ms}
.ls-deselect-btn:hover{border-color:rgba(241,245,249,.5);color:#f1f5f9}
.ls-go-btn{appearance:none;border:0;background:#f97316;color:#fff;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;transition:background 180ms}
.ls-go-btn:hover{background:#ea6c0a}

/* ── Transition ──────────────────────────────────────────────────────────── */
.slide-up-enter-active,.slide-up-leave-active{transition:transform .22s ease,opacity .22s ease}
.slide-up-enter-from,.slide-up-leave-to{transform:translateY(100%);opacity:0}

@media(max-width:760px){
  .ls-page-head{gap:12px}
  .ls-filters{gap:8px}
  .ls-search-wrap{max-width:100%;width:100%}
  .ls-reorder-bar-inner{flex-direction:column;gap:12px;align-items:flex-start}
}
</style>
