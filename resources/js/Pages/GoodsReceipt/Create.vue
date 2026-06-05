<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    allVariants:      Array,
    preselectedItems: { type: Array, default: () => [] },
})

// ── form ───────────────────────────────────────────────────────────────────
const form = useForm({
    gr_type:   props.preselectedItems.length ? 'external' : 'procurement',
    pr_number: '',
    po_number: '',
    notes:     '',
    items:     [],
})

// ── item rows ──────────────────────────────────────────────────────────────
const newRow = () => ({ variantId: null, query: '', qty: '', selectedUom: 'base' })

// Pre-fill from reorder if present, otherwise start with one empty row
const rows = ref(
    props.preselectedItems.length
        ? props.preselectedItems.map(p => ({
            variantId:   p.variant_id,
            query:       p.name_en || p.sku,
            qty:         String(p.suggested_qty),
            selectedUom: 'base',
          }))
        : [newRow()]
)

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

function filteredVariants(row) {
    const q = (row.query || '').trim().toLowerCase()
    if (!q) return props.allVariants.slice(0, 20)
    return props.allVariants.filter(v =>
        v.sku.toLowerCase().includes(q) ||
        (v.name_en  && v.name_en.toLowerCase().includes(q))  ||
        (v.name_id  && v.name_id.toLowerCase().includes(q))  ||
        (v.name_zh  && v.name_zh.toLowerCase().includes(q))  ||
        (v.brand    && v.brand.toLowerCase().includes(q))    ||
        (v.model    && v.model.toLowerCase().includes(q))    ||
        (v.size     && v.size.toLowerCase().includes(q))     ||
        (v.color    && v.color.toLowerCase().includes(q))
    ).slice(0, 20)
}

function selectVariant(row, v) {
    row.variantId       = v.id
    row.query           = itemName(v) || v.sku
    row.qty             = ''
    row.selectedUom     = 'base'
    activeDropIdx.value = -1
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

function itemName(v) {
    if (!v) return ''
    if (locale.value === 'zh' && v.name_zh) return v.name_zh
    if (locale.value === 'id' && v.name_id) return v.name_id
    return v.name_en || v.name_id || ''
}

function addRow()        { rows.value.push(newRow()) }
function removeRow(idx)  { if (rows.value.length > 1) rows.value.splice(idx, 1) }

// ── submit ─────────────────────────────────────────────────────────────────
const validRows = computed(() =>
    rows.value.filter(r => r.variantId && r.qty !== '' && !isNaN(parseFloat(r.qty)) && parseFloat(r.qty) > 0)
)

function submit() {
    const items = validRows.value.map(r => {
        const v   = variantOf(r)
        let qty   = parseFloat(r.qty)
        const uom = v?.base_uom ?? ''
        if (r.selectedUom === 'alt' && v?.alt_uom_conversion) qty = qty * v.alt_uom_conversion
        return { variant_id: r.variantId, expected_qty: qty, uom }
    })
    form.items = items
    form.post(route('gr.store'), { preserveScroll: true, onError: () => {} })
}
</script>

<template>
<AppLayout>
  <template #title>{{ $t('gr.createTitle') }}</template>
  <template #breadcrumb>
    <Link :href="route('gr.index')" class="bc-link">{{ $t('gr.title') }}</Link>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="m9 18 6-6-6-6"/></svg>
    {{ $t('gr.createTitle') }}
  </template>

  <div class="cr-wrap">

    <!-- Reorder banner -->
    <div v-if="props.preselectedItems.length" class="cr-reorder-banner">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M3 3v18h18"/><path d="m7 16 4-4 4 4 4-6"/></svg>
      <span>{{ $t('gr.reorderBanner', { n: props.preselectedItems.length }) }}</span>
    </div>

    <!-- Header -->
    <div class="cr-header">
      <div>
        <h2 class="cr-title">{{ $t('gr.createTitle') }}</h2>
        <p class="cr-sub">{{ $t('gr.createSub') }}</p>
      </div>
      <Link :href="route('gr.index')" class="cr-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="m15 18-6-6 6-6"/></svg>
        {{ $t('btn.back') }}
      </Link>
    </div>

    <form @submit.prevent="submit">
    <div class="cr-body">

      <!-- ── Left column: Info GR ────────────────────────────── -->
      <div class="cr-card cr-card-info">
        <div class="cr-section-label">{{ $t('gr.sectionInfo') }}</div>

        <!-- Type -->
        <div class="cr-fg">
          <label class="cr-label">{{ $t('gr.grType') }}</label>
          <div class="cr-type-toggle">
            <button type="button" class="cr-type-btn"
              :class="{ active: form.gr_type === 'procurement' }"
              @click="form.gr_type = 'procurement'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
                <path d="m9 12 2 2 4-4"/>
              </svg>
              {{ $t('gr.typeProcurement') }}
            </button>
            <button type="button" class="cr-type-btn"
              :class="{ active: form.gr_type === 'external' }"
              @click="form.gr_type = 'external'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                <rect x="1" y="3" width="15" height="13" rx="1"/>
                <path d="M16 8h4l3 3v5h-7V8Z"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
              </svg>
              {{ $t('gr.typeExternal') }}
            </button>
          </div>
          <p class="cr-type-desc">
            {{ form.gr_type === 'procurement' ? $t('gr.typeProcurementDesc') : $t('gr.typeExternalDesc') }}
          </p>
        </div>

        <!-- PR Number -->
        <div class="cr-fg">
          <label class="cr-label">{{ $t('gr.prNumber') }}</label>
          <input class="cr-input" type="text" v-model="form.pr_number"
            :placeholder="$t('gr.prNumberPh')" maxlength="100" />
          <span class="cr-err" v-if="form.errors.pr_number">{{ form.errors.pr_number }}</span>
        </div>

        <!-- PO Number -->
        <div class="cr-fg">
          <label class="cr-label">{{ $t('gr.poNumber') }}</label>
          <input class="cr-input" type="text" v-model="form.po_number"
            :placeholder="$t('gr.poNumberPh')" maxlength="100" />
          <span class="cr-err" v-if="form.errors.po_number">{{ form.errors.po_number }}</span>
        </div>

        <div class="cr-fg">
          <label class="cr-label">{{ $t('gr.notes') }}</label>
          <textarea class="cr-input cr-textarea" v-model="form.notes"
            :placeholder="$t('gr.notesPh')" rows="4" />
        </div>
      </div>

      <!-- ── Right column: Items ─────────────────────────────── -->
      <div class="cr-card cr-card-items">
        <div class="cr-section-label">
          {{ $t('gr.sectionItems') }}
          <button type="button" class="cr-add-row" @click="addRow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round" stroke-linejoin="round" width="11" height="11">
              <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            {{ $t('gr.addItem') }}
          </button>
        </div>

        <!-- Column headers -->
        <div class="cr-col-header">
          <span class="crh-no">#</span>
          <span class="crh-item">{{ $t('gr.colVariant') }}</span>
          <span class="crh-qty">{{ $t('gr.colExpQty') }}</span>
          <span class="crh-uom">{{ $t('gr.colUom') }}</span>
          <span class="crh-del"></span>
        </div>

        <!-- Item rows -->
        <div class="cr-rows">
          <div class="cr-row" v-for="(row, idx) in rows" :key="idx">

            <!-- # -->
            <span class="cr-no">{{ idx + 1 }}</span>

            <!-- SKU search -->
            <div class="cr-combo-wrap">
              <input
                class="cr-input cr-combo"
                :class="{ 'has-val': row.variantId }"
                type="text"
                v-model="row.query"
                :placeholder="$t('gr.searchVariant')"
                autocomplete="off"
                @focus="openDropdown(idx, $event)"
                @blur="closeDropdown()"
                @input="row.variantId = null"
              />
              <!-- variant detail below input -->
              <div v-if="row.variantId" class="cr-item-detail">
                <span class="cr-detail-sku">{{ variantOf(row)?.sku }}</span>
                <span v-if="variantOf(row)?.brand"  class="cr-detail-tag cr-detail-brand">{{ variantOf(row).brand }}</span>
                <span v-if="variantOf(row)?.model"  class="cr-detail-tag cr-detail-model">{{ variantOf(row).model }}</span>
                <span v-if="variantOf(row)?.size"   class="cr-detail-tag">{{ variantOf(row).size }}</span>
                <span v-if="variantOf(row)?.color"  class="cr-detail-tag">{{ variantOf(row).color }}</span>
              </div>
            </div>

            <!-- Qty -->
            <input
              class="cr-input cr-qty"
              type="number"
              v-model="row.qty"
              step="any" min="0.01"
              :disabled="!row.variantId"
              :placeholder="row.variantId ? '0' : '—'"
            />

            <!-- UOM -->
            <div class="cr-uom-cell">
              <template v-if="row.variantId && rowHasAlt(row)">
                <div class="cr-uom-toggle">
                  <button type="button" class="cr-uom-btn"
                    :class="{ active: row.selectedUom === 'base' }"
                    @click="row.selectedUom = 'base'; row.qty = ''">
                    {{ variantOf(row).base_uom }}
                  </button>
                  <button type="button" class="cr-uom-btn"
                    :class="{ active: row.selectedUom === 'alt' }"
                    @click="row.selectedUom = 'alt'; row.qty = ''">
                    {{ variantOf(row).alt_uom }}
                  </button>
                </div>
                <span v-if="convHint(row)" class="cr-conv-hint">{{ convHint(row) }}</span>
              </template>
              <span v-else class="cr-uom-static">{{ currentUom(row) }}</span>
            </div>

            <!-- Delete -->
            <button type="button" class="cr-del" @click="removeRow(idx)" :disabled="rows.length === 1">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                <path d="M3 6h18"/><path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
              </svg>
            </button>
          </div>
        </div>

        <span class="cr-err" v-if="form.errors.items">{{ form.errors.items }}</span>

        <!-- Footer -->
        <div class="cr-footer">
          <div class="cr-valid-hint">
            <template v-if="validRows.length">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
                <path d="M20 6 9 17l-5-5"/>
              </svg>
              {{ $t('gr.validItems', { n: validRows.length }) }}
            </template>
          </div>
          <div class="cr-footer-btns">
            <Link :href="route('gr.index')" class="cr-cancel">{{ $t('btn.cancel') }}</Link>
            <button type="submit" class="cr-save"
              :disabled="form.processing || validRows.length === 0">
              <svg v-if="form.processing" class="cr-spin" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                stroke-linejoin="round" width="14" height="14">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
              </svg>
              {{ form.processing ? $t('gr.saving') : $t('gr.saveDraft') }}
            </button>
          </div>
        </div>
      </div>

    </div><!-- /.cr-body -->
    </form>

  </div>

  <!-- Teleport dropdown -->
  <Teleport to="body">
    <div v-if="activeDropIdx >= 0" class="cr-drop-fixed" :style="dropFixedStyle">
      <template v-if="filteredVariants(rows[activeDropIdx]).length">
        <div
          v-for="v in filteredVariants(rows[activeDropIdx])" :key="v.id"
          class="cr-opt"
          @mousedown.prevent="selectVariant(rows[activeDropIdx], v)"
        >
          <div class="cr-opt-main">
            <span class="cr-opt-name">{{ itemName(v) }}</span>
            <span class="cr-opt-sku">{{ v.sku }}</span>
          </div>
          <div class="cr-opt-tags">
            <span v-if="v.brand"  class="cr-tag cr-tag-brand">{{ v.brand }}</span>
            <span v-if="v.model"  class="cr-tag cr-tag-model">{{ v.model }}</span>
            <span v-if="v.size"   class="cr-tag cr-tag-spec">{{ v.size }}</span>
            <span v-if="v.color"  class="cr-tag cr-tag-spec">{{ v.color }}</span>
          </div>
        </div>
      </template>
      <div v-else class="cr-opt cr-opt-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        {{ $t('gr.noVariantFound') }}
      </div>
      <!-- Register new item link -->
      <a :href="route('items.index')" target="_blank" class="cr-opt-register"
        @mousedown.prevent>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
          stroke-linecap="round" stroke-linejoin="round" width="12" height="12">
          <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        {{ $t('gr.registerNewItem') }}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" width="11" height="11" style="margin-left:auto">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
          <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
      </a>
    </div>
  </Teleport>

</AppLayout>
</template>

<style scoped>
/* ── breadcrumb ─────────────────────────────────────────────────────── */
.bc-link { color:var(--orange-500); text-decoration:none; font-weight:600 }
.bc-link:hover { text-decoration:underline }

/* ── page wrap ──────────────────────────────────────────────────────── */
.cr-wrap { display:flex; flex-direction:column; gap:18px; width:100% }
.cr-reorder-banner {
  display:flex; align-items:center; gap:10px;
  padding:11px 16px; border-radius:10px;
  background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.25);
  color:#92400e; font-size:13px; font-weight:600;
}
:root[data-theme="light"] .cr-reorder-banner { color:#92400e }
:root:not([data-theme="light"]) .cr-reorder-banner { color:#fbbf24 }

/* ── header row ─────────────────────────────────────────────────────── */
.cr-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px }
.cr-title  { font-size:20px; font-weight:800; color:var(--fg); letter-spacing:-.02em }
.cr-sub    { font-size:13px; color:var(--fg-2); margin-top:3px }
.cr-back   {
  display:inline-flex; align-items:center; gap:5px; padding:8px 14px; border-radius:8px;
  font-size:13px; font-weight:600; background:var(--surface); border:1px solid var(--border-2);
  color:var(--fg-2); text-decoration:none; transition:background 150ms; white-space:nowrap;
}
.cr-back:hover { background:var(--hover); color:var(--fg) }

/* ── two-column body ────────────────────────────────────────────────── */
.cr-body {
  display:grid;
  grid-template-columns:300px 1fr;
  gap:16px;
  align-items:start;
  width:100%;
}
@media (max-width:900px) { .cr-body { grid-template-columns:1fr } }

/* ── cards ──────────────────────────────────────────────────────────── */
.cr-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:14px; padding:20px; box-shadow:var(--shadow-sm);
  display:flex; flex-direction:column; gap:14px;
}

/* ── section label ──────────────────────────────────────────────────── */
.cr-section-label {
  display:flex; align-items:center; justify-content:space-between;
  font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em;
  color:var(--fg-dim); padding-bottom:10px; border-bottom:1px solid var(--border);
}

/* ── form group ─────────────────────────────────────────────────────── */
.cr-fg    { display:flex; flex-direction:column; gap:5px }
.cr-label { font-size:12px; font-weight:600; color:var(--fg-2) }

/* ── inputs ─────────────────────────────────────────────────────────── */
.cr-input {
  padding:8px 11px; font-size:13px; background:var(--surface-2);
  border:1px solid var(--border-2); border-radius:8px; color:var(--fg);
  outline:none; font-family:inherit; transition:border-color 180ms;
  width:100%; box-sizing:border-box;
}
.cr-input:focus   { border-color:var(--orange-500) }
.cr-input:disabled { opacity:.45; cursor:default }
.cr-textarea { resize:vertical; min-height:90px; line-height:1.5 }
.cr-err { font-size:11.5px; color:#f87171}

/* ── add row button ─────────────────────────────────────────────────── */
.cr-add-row {
  display:inline-flex; align-items:center; gap:5px; padding:4px 10px;
  border-radius:6px; font-size:12px; font-weight:600;
  background:rgba(139,92,246,.12); color:#a78bfa;
  border:1px solid rgba(139,92,246,.22); cursor:pointer; font-family:inherit;
  white-space:nowrap;
}
.cr-add-row:hover { background:rgba(139,92,246,.22) }

/* ── column header ──────────────────────────────────────────────────── */
.cr-col-header {
  display:grid;
  grid-template-columns:28px 1fr 90px 110px 32px;
  gap:8px;
  padding:0 4px 6px;
  font-size:10.5px; font-weight:700; text-transform:uppercase;
  letter-spacing:.06em; color:var(--fg-dim);
}
.crh-no  { text-align:center }
.crh-qty { text-align:right }

/* ── rows ───────────────────────────────────────────────────────────── */
.cr-rows { display:flex; flex-direction:column; gap:5px }

.cr-row {
  display:grid;
  grid-template-columns:28px 1fr 90px 110px 32px;
  gap:8px;
  align-items:start;
  background:var(--surface-2); border:1px solid var(--border);
  border-radius:10px; padding:8px;
}

/* ── row cells ──────────────────────────────────────────────────────── */
.cr-no { font-size:11px; font-weight:700; color:var(--fg-dim); text-align:center; padding-top:9px }

.cr-combo-wrap { position:relative; display:flex; flex-direction:column; gap:3px }
.cr-combo { width:100%; box-sizing:border-box }
.cr-combo.has-val { border-color:rgba(139,92,246,.45); background:rgba(139,92,246,.06) }
.cr-item-detail {
  display:flex; flex-wrap:wrap; align-items:center; gap:4px; padding:2px 2px 0;
}
.cr-detail-sku {
  font-family:monospace; font-size:10.5px; font-weight:700; color:#a78bfa;
}
.cr-detail-tag {
  font-size:10.5px; padding:1px 6px; border-radius:4px;
  background:var(--surface-3); color:var(--fg-dim); border:1px solid var(--border);
}
.cr-detail-brand { color:var(--fg-2); font-weight:600; background:rgba(139,92,246,.08); border-color:rgba(139,92,246,.2) }
.cr-detail-model { color:var(--fg-2) }

.cr-qty { text-align:right }

/* ── UOM cell ───────────────────────────────────────────────────────── */
.cr-uom-cell { display:flex; flex-direction:column; gap:3px; align-items:flex-start }
.cr-uom-toggle {
  display:inline-flex; border:1px solid var(--border-2);
  border-radius:7px; overflow:hidden; width:100%;
}
.cr-uom-btn {
  flex:1; padding:5px 4px; font-size:11px; font-weight:700;
  cursor:pointer; background:transparent; border:none;
  color:var(--fg-2); font-family:inherit;
  transition:background 150ms, color 150ms;
  white-space:nowrap; text-align:center;
}
.cr-uom-btn.active { background:#a78bfa; color:#fff }
.cr-uom-btn:not(.active):hover { background:rgba(139,92,246,.1); color:#a78bfa }
.cr-uom-static { font-size:12px; font-weight:700; color:var(--fg-dim); padding-top:7px }
.cr-conv-hint  { font-size:10.5px; font-weight:600; color:#a78bfa }

/* ── delete ─────────────────────────────────────────────────────────── */
.cr-del {
  appearance:none; border:1px solid var(--border-2); background:transparent;
  width:32px; height:32px; border-radius:6px; cursor:pointer; color:var(--fg-dim);
  display:grid; place-items:center; transition:background 150ms, color 150ms;
}
.cr-del:hover:not(:disabled) { background:rgba(239,68,68,.12); color:#f87171; border-color:rgba(239,68,68,.25) }
.cr-del:disabled { opacity:.3; cursor:default }

/* ── footer ─────────────────────────────────────────────────────────── */
.cr-footer {
  display:flex; align-items:center; justify-content:space-between;
  padding-top:14px; border-top:1px solid var(--border); gap:12px;
}
.cr-valid-hint  { display:flex; align-items:center; gap:5px; font-size:12px; color:#34d399; font-weight:600 }
.cr-footer-btns { display:flex; gap:10px; flex-shrink:0 }
.cr-cancel {
  display:inline-flex; align-items:center; padding:8px 16px; border-radius:8px;
  font-size:13px; font-weight:600; background:transparent; border:1px solid var(--border-2);
  color:var(--fg-2); text-decoration:none; transition:background 180ms;
}
.cr-cancel:hover { background:var(--hover); color:var(--fg) }
.cr-save {
  padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600;
  background:linear-gradient(180deg,var(--orange-400),var(--orange-500)); color:#fff; border:0;
  box-shadow:0 4px 10px -3px rgba(249,115,22,.45); transition:opacity 180ms;
  display:inline-flex; align-items:center; gap:7px;
}
.cr-save:disabled { opacity:.55; cursor:default }

/* ── type toggle ────────────────────────────────────────────────────── */
.cr-type-toggle {
  display:grid; grid-template-columns:1fr 1fr; gap:6px;
}
.cr-type-btn {
  display:flex; align-items:center; justify-content:center; gap:6px;
  padding:8px 10px; border-radius:8px; cursor:pointer; font-family:inherit;
  font-size:12px; font-weight:600; border:1.5px solid var(--border-2);
  background:var(--surface-2); color:var(--fg-2); transition:all 150ms;
}
.cr-type-btn:hover { border-color:rgba(249,115,22,.4); color:var(--fg) }
.cr-type-btn.active {
  border-color:var(--orange-500); background:rgba(249,115,22,.1);
  color:var(--orange-500);
}
.cr-type-desc { font-size:11px; color:var(--fg-dim); line-height:1.4; margin-top:2px }

/* ── dropdown ───────────────────────────────────────────────────────── */
.cr-drop-fixed {
  background:var(--surface); border:1px solid var(--border-2);
  border-radius:9px; box-shadow:0 10px 32px rgba(0,0,0,.5);
  overflow:hidden; max-height:400px; overflow-y:auto;
}
.cr-opt { display:flex; flex-direction:column; gap:5px; padding:10px 14px; cursor:pointer; transition:background 120ms }
.cr-opt:hover { background:rgba(139,92,246,.1) }
.cr-opt-main  { display:flex; align-items:center; justify-content:space-between; gap:8px }
.cr-opt-name  { font-size:13px; font-weight:700; color:var(--fg); overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
.cr-opt-sku   { font-family:monospace; font-size:11px; font-weight:700; color:#a78bfa; flex-shrink:0; white-space:nowrap; background:rgba(139,92,246,.1); padding:1px 6px; border-radius:4px }
.cr-opt-tags  { display:flex; flex-wrap:wrap; gap:4px }
.cr-tag       { font-size:10.5px; padding:1px 6px; border-radius:4px; background:var(--surface-3); color:var(--fg-dim); border:1px solid var(--border); white-space:nowrap }
.cr-tag-brand { color:var(--fg-2); font-weight:600; background:rgba(59,130,246,.08); border-color:rgba(59,130,246,.2) }
.cr-tag-model { color:var(--fg-2) }
.cr-tag-spec  { color:var(--fg-dim) }
.cr-opt-empty { color:var(--fg-dim); font-size:12px; cursor:default; display:flex; align-items:center; gap:7px }
.cr-opt-register {
  display:flex; align-items:center; gap:7px;
  padding:9px 14px; font-size:12px; font-weight:600;
  color:#a78bfa; border-top:1px solid var(--border);
  text-decoration:none; transition:background 120ms; cursor:pointer;
}
.cr-opt-register:hover { background:rgba(139,92,246,.1) }

@keyframes cr-spin { to { transform:rotate(360deg) } }
.cr-spin { animation:cr-spin .8s linear infinite; flex-shrink:0 }
</style>
