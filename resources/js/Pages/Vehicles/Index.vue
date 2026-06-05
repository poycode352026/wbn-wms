<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
    vehicles: Object,
    filters:  Object,
})

// ── Filters ──────────────────────────────────────────────────────────────
const search       = ref(props.filters?.search ?? '')
const filterStatus = ref(props.filters?.status ?? '')

function params() {
    return {
        search: search.value       || undefined,
        status: filterStatus.value || undefined,
    }
}
const go = (extra = {}) =>
    router.get(route('vehicles.index'), { ...params(), ...extra },
        { preserveState: true, preserveScroll: true, replace: true })

let st = null
watch(search, () => { clearTimeout(st); st = setTimeout(() => go(), 350) })
watch([filterStatus], () => go())

function clearFilters() {
    search.value = ''; filterStatus.value = ''
    router.get(route('vehicles.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}

function goPage(url) {
    if (url) router.get(url, params(), { preserveState: true, preserveScroll: true })
}

// ── Action dropdown ───────────────────────────────────────────────────────
const openDropId  = ref(null)
const dropStyle   = ref({})
const dropVehicle = ref(null)

function toggleDrop(v, ev) {
    ev.stopPropagation()
    if (openDropId.value === v.id) { openDropId.value = null; return }
    const r = ev.currentTarget.getBoundingClientRect()
    dropStyle.value = {
        position: 'fixed',
        top:  `${r.bottom + 4}px`,
        right:`${window.innerWidth - r.right}px`,
        minWidth: '160px',
    }
    openDropId.value = v.id
    dropVehicle.value = v
}
function closeDrop() { openDropId.value = null; dropVehicle.value = null }

function onEsc(e) {
    if (e.key === 'Escape') { closeDrop(); showModal.value = false; showImportModal.value = false }
}
onMounted(() => {
    document.addEventListener('click', closeDrop)
    document.addEventListener('keydown', onEsc)
    document.addEventListener('scroll', closeDrop, true)
})
onUnmounted(() => {
    document.removeEventListener('click', closeDrop)
    document.removeEventListener('keydown', onEsc)
    document.removeEventListener('scroll', closeDrop, true)
})

// ── Add / Edit modal ──────────────────────────────────────────────────────
const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
    lv_code:   'WBN-LV',
    lv_number: '',
    type:      '',
    is_active: true,
})

function openAdd() {
    editingId.value = null
    form.reset()
    form.lv_code   = 'WBN-LV'
    form.is_active = true
    showModal.value = true; closeDrop()
}
function openEdit(v) {
    editingId.value = v.id
    Object.assign(form, {
        lv_code:   v.lv_code   ?? 'WBN-LV',
        lv_number: v.lv_number ?? '',
        type:      v.type      ?? '',
        is_active: v.is_active,
    })
    form.clearErrors(); showModal.value = true; closeDrop()
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors() }
function submitForm() {
    const opts = { onSuccess: closeModal }
    editingId.value
        ? form.patch(route('vehicles.update', editingId.value), opts)
        : form.post(route('vehicles.store'), opts)
}
function doDisable(v) {
    closeDrop()
    if (!confirm(`${t('lv.confirmDisable')} "${v.lv_code}-${v.lv_number}"?`)) return
    router.delete(route('vehicles.destroy', v.id), { preserveScroll: true })
}

// ── Import modal ──────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importForm      = useForm({ file: null })
const importFileRef   = ref(null)
const importErrors    = ref([])

function openImportModal() { showImportModal.value = true; importErrors.value = [] }
function closeImportModal() {
    showImportModal.value = false
    importForm.reset(); importErrors.value = []
    if (importFileRef.value) importFileRef.value.value = ''
}
function submitImport() {
    importErrors.value = []
    importForm.post(route('vehicles.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeImportModal,
        onError: errs => { importErrors.value = Object.values(errs) },
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────
function lastUsed(v) {
    const cd = v.cooldowns?.[0]
    if (!cd) return null
    const itemName = cd.variant?.item?.name_en ?? cd.variant?.item?.name_id ?? '—'
    const d = cd.cooldown_until
        ? new Date(cd.cooldown_until).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
        : '—'
    return `${itemName} — ${d}`
}

const VEHICLE_TYPES = [
    'LV (Light Vehicle)', 'Pickup', 'Dump Truck', 'Water Truck',
    'Fuel Truck', 'Bus / Minibus', 'Forklift', 'Excavator',
    'Bulldozer', 'Grader', 'Other',
]

// Auto-compute lv_code prefix from type
const TYPE_CODE_MAP = {
    'LV (Light Vehicle)': 'WBN-LV',
    'Pickup':             'WBN-PU',
    'Dump Truck':         'WBN-DT',
    'Water Truck':        'WBN-WT',
    'Fuel Truck':         'WBN-FT',
    'Bus / Minibus':      'WBN-BS',
    'Forklift':           'WBN-FK',
    'Excavator':          'WBN-EX',
    'Bulldozer':          'WBN-BD',
    'Grader':             'WBN-GD',
    'Other':              'WBN-OT',
}

// When type changes and we're adding a new vehicle, auto-fill lv_code
watch(() => form.type, (newType) => {
    if (!editingId.value && newType && TYPE_CODE_MAP[newType]) {
        form.lv_code = TYPE_CODE_MAP[newType]
    }
})
</script>

<template>
  <AppLayout>
    <template #title>{{ $t('lv.title') }}</template>
    <template #breadcrumb>{{ $t('lv.title') }}</template>

    <!-- ── Page header ─────────────────────────────────────────────── -->
    <div class="page-head">
      <div>
        <h1 class="page-title">{{ $t('lv.title') }}</h1>
        <p class="page-sub">{{ $t('lv.sub') }}</p>
      </div>
      <div class="head-actions">
        <button class="btn-import" @click="openImportModal" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          {{ $t('lv.importBtn') }}
        </button>
        <button class="btn-primary" @click="openAdd" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          {{ $t('lv.addBtn') }}
        </button>
      </div>
    </div>

    <!-- ── Info banner ─────────────────────────────────────────────── -->
    <div class="info-banner">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      {{ $t('lv.driverAssignNote') }}
    </div>

    <!-- ── Toolbar ──────────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="search-wrap">
        <svg class="search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="search" class="search-input" type="text" :placeholder="$t('lv.searchPh')" />
        <button v-if="search" class="search-clr" @click="search = ''" type="button">✕</button>
      </div>
      <select v-model="filterStatus" class="filter-sel">
        <option value="">{{ $t('lv.filterStatus') }}</option>
        <option value="active">{{ $t('lv.activeStatus') }}</option>
        <option value="inactive">{{ $t('lv.inactiveStatus') }}</option>
      </select>
      <button v-if="search || filterStatus" class="btn-clear" @click="clearFilters" type="button">
        {{ $t('common.clearFilter') }}
      </button>
    </div>

    <!-- ── Table ────────────────────────────────────────────────────── -->
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('lv.colIdentifier') }}</th>
            <th>{{ $t('lv.colType') }}</th>
            <th>{{ $t('lv.colDriver') }}</th>
            <th>{{ $t('lv.colLastUsed') }}</th>
            <th>{{ $t('lv.colStatus') }}</th>
            <th style="width:48px;"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!vehicles.data.length">
            <td colspan="6" class="empty-row">{{ $t('lv.noResults') }}</td>
          </tr>
          <tr v-for="v in vehicles.data" :key="v.id">
            <td>
              <div class="lv-id-cell">
                <span class="lv-code-pill">{{ v.lv_code }}</span>
                <span class="lv-separator">—</span>
                <span class="mono-tag">{{ v.lv_number }}</span>
              </div>
            </td>
            <td>
              <div v-if="v.type" class="cell-sub">{{ v.type }}</div>
              <span v-else class="cell-sub">—</span>
            </td>
            <td>
              <div v-if="v.driver" class="cell-sub">
                <div style="font-weight:600">{{ v.driver.name }}</div>
                <div style="font-size:11px;color:var(--fg-dim)">{{ v.driver.department?.name ?? '—' }}</div>
              </div>
              <span v-else class="cell-sub">—</span>
            </td>
            <td>
              <div v-if="lastUsed(v)" class="cell-sub" style="font-size:11.5px;line-height:1.4">{{ lastUsed(v) }}</div>
              <span v-else class="cell-sub">{{ $t('lv.neverUsed') }}</span>
            </td>
            <td>
              <span :class="v.is_active ? 'chip-on' : 'chip-off'">
                {{ v.is_active ? $t('lv.activeStatus') : $t('lv.inactiveStatus') }}
              </span>
            </td>
            <td>
              <button class="icon-btn" @click.stop="toggleDrop(v, $event)" type="button">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination ────────────────────────────────────────────────── -->
    <div v-if="vehicles.last_page > 1" class="pag-row">
      <button class="pag-btn" :disabled="!vehicles.prev_page_url" @click="goPage(vehicles.prev_page_url)">‹</button>
      <span class="pag-info">{{ vehicles.current_page }} / {{ vehicles.last_page }}</span>
      <button class="pag-btn" :disabled="!vehicles.next_page_url" @click="goPage(vehicles.next_page_url)">›</button>
    </div>

    <!-- ── Action Dropdown ───────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="openDropId" class="action-drop" :style="dropStyle" @click.stop>
        <button @click="openEdit(dropVehicle)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
          {{ $t('common.edit') }}
        </button>
        <button v-if="dropVehicle?.is_active" class="danger" @click="doDisable(dropVehicle)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          {{ $t('common.disable') }}
        </button>
        <button v-else @click="openEdit(dropVehicle)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          {{ $t('common.reactivate') }}
        </button>
      </div>
    </Teleport>

    <!-- ── Add / Edit Modal ──────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-box" style="max-width:440px">
          <div class="modal-header">
            <h2>{{ editingId ? $t('lv.editTitle') : $t('lv.addTitle') }}</h2>
            <button class="icon-btn" @click="closeModal" type="button">✕</button>
          </div>
          <form @submit.prevent="submitForm" class="modal-body">

            <!-- LV Code (prefix) -->
            <div class="form-group">
              <label class="form-label">{{ $t('lv.formCode') }} <span class="req">*</span></label>
              <input v-model="form.lv_code" class="form-input" type="text"
                placeholder="WBN-LV"
                :class="{ 'inp-err': form.errors.lv_code }" />
              <div class="form-hint">{{ $t('lv.formCodeHint') }}</div>
              <div v-if="form.errors.lv_code" class="form-err">{{ form.errors.lv_code }}</div>
            </div>

            <!-- LV Number (suffix) -->
            <div class="form-group">
              <label class="form-label">{{ $t('lv.formLvNumber') }} <span class="req">*</span></label>
              <input v-model="form.lv_number" class="form-input" type="text"
                :placeholder="$t('lv.formLvNumberPh')"
                :class="{ 'inp-err': form.errors.lv_number }" />
              <!-- Preview of full identifier -->
              <div v-if="form.lv_code && form.lv_number" class="id-preview">
                {{ form.lv_code }}-{{ form.lv_number }}
              </div>
              <div v-if="form.errors.lv_number" class="form-err">{{ form.errors.lv_number }}</div>
            </div>

            <!-- Type -->
            <div class="form-group">
              <label class="form-label">{{ $t('lv.formType') }}</label>
              <select v-model="form.type" class="form-input form-select">
                <option value="">— {{ $t('lv.formTypePh') }} —</option>
                <option v-for="tp in VEHICLE_TYPES" :key="tp" :value="tp">{{ tp }}</option>
              </select>
              <div v-if="form.errors.type" class="form-err">{{ form.errors.type }}</div>
            </div>

            <!-- Active toggle (edit only) -->
            <div v-if="editingId" class="form-group">
              <label class="toggle-row">
                <input type="checkbox" v-model="form.is_active" class="sr-only" />
                <span class="toggle-track" :class="{ on: form.is_active }">
                  <span class="toggle-thumb"></span>
                </span>
                <span class="form-label" style="margin:0">{{ $t('lv.formActive') }}</span>
              </label>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
              <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? $t('common.saving') : $t('common.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Import Modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showImportModal" class="modal-backdrop" @click.self="closeImportModal">
        <div class="modal-box" style="max-width:480px">
          <div class="modal-header">
            <h2>{{ $t('lv.importTitle') }}</h2>
            <button class="icon-btn" @click="closeImportModal" type="button">✕</button>
          </div>
          <div class="modal-body">
            <div class="import-steps">
              <div class="import-step">
                <span class="step-num">1</span>
                <div>
                  <div style="font-weight:600;font-size:13px">{{ $t('lv.importStep1') }}</div>
                  <div style="font-size:12px;opacity:.55">{{ $t('lv.importStep1Sub') }}</div>
                </div>
              </div>
              <div class="import-step">
                <span class="step-num">2</span>
                <div style="font-size:13px;font-weight:600">{{ $t('lv.importStep2') }}</div>
              </div>
              <div class="import-step">
                <span class="step-num">3</span>
                <div style="font-size:13px;font-weight:600">{{ $t('lv.importStep3') }}</div>
              </div>
            </div>

            <a :href="route('vehicles.importTemplate')"
              class="btn-ghost" style="display:inline-flex;align-items:center;gap:7px;margin-bottom:18px;width:100%;justify-content:center">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              {{ $t('lv.importTemplateBtn') }}
            </a>

            <div class="import-format">
              <div class="fmt-title">Format</div>
              <ul class="fmt-list">
                <li>{{ $t('lv.importFmt1') }}</li>
                <li>{{ $t('lv.importFmt2') }}</li>
                <li>{{ $t('lv.importFmt3') }}</li>
              </ul>
            </div>

            <div v-if="importErrors.length" class="import-errors">
              <div v-for="(e, i) in importErrors" :key="i" class="import-err-row">⚠ {{ e }}</div>
            </div>

            <form @submit.prevent="submitImport" style="margin-top:16px">
              <div class="form-group">
                <label class="form-label">{{ $t('lv.importFile') }} <span class="req">*</span></label>
                <input ref="importFileRef" type="file" accept=".csv,.xlsx,.xls,.txt"
                  class="form-input"
                  @change="e => importForm.file = e.target.files[0]" />
                <div style="font-size:11px;opacity:.45;margin-top:3px">{{ $t('lv.importFileHint') }}</div>
                <div v-if="importForm.errors.file" class="form-err">{{ importForm.errors.file }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn-ghost" @click="closeImportModal">{{ $t('common.cancel') }}</button>
                <button type="submit" class="btn-primary"
                  :disabled="importForm.processing || !importForm.file">
                  {{ importForm.processing ? $t('lv.importing') : $t('lv.importBtn') }}
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
/* ── Layout ─────────────────────────────────────────────────────────── */
.page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; gap:12px; }
.page-title { font-size:21px; font-weight:700; color:var(--fg); margin:0 0 4px; }
.page-sub { font-size:13px; color:var(--fg-2); margin:0; }
.head-actions { display:flex; gap:10px; align-items:center; flex-shrink:0; }

/* ── Info banner ─────────────────────────────────────────────────────── */
.info-banner {
  display:flex; align-items:center; gap:8px;
  background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.2);
  color:#60a5fa; border-radius:8px; padding:9px 14px; font-size:12.5px;
  margin-bottom:16px;
}

/* ── Buttons ─────────────────────────────────────────────────────────── */
.btn-primary {
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(180deg, var(--orange-400), var(--orange-500));
  color:#fff; border:none; border-radius:7px; padding:7px 14px;
  font-size:13px; font-weight:600; cursor:pointer; font-family:inherit;
  white-space:nowrap; transition:opacity .15s;
}
.btn-primary:hover { opacity:.88; }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-ghost {
  display:inline-flex; align-items:center; gap:6px;
  background:transparent; color:var(--fg-2); border:1px solid var(--border);
  border-radius:7px; padding:7px 14px; font-size:13px; font-weight:500;
  cursor:pointer; font-family:inherit; text-decoration:none;
  transition:background .15s, color .15s;
}
.btn-ghost:hover { background:var(--hover); color:var(--fg); }
.btn-import {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600;
  cursor:pointer; font-family:inherit; white-space:nowrap; text-decoration:none;
  transition:background .15s, border-color .15s;
  background:rgba(59,130,246,.12); color:#60a5fa;
  border:1px solid rgba(59,130,246,.25);
}
.btn-import:hover { background:rgba(59,130,246,.2); border-color:rgba(59,130,246,.4); }
.btn-clear { font-size:12px; color:var(--fg-dim); background:transparent; border:none; cursor:pointer; text-decoration:underline; }

/* ── Toolbar ─────────────────────────────────────────────────────────── */
.toolbar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.search-wrap { position:relative; flex:1; min-width:180px; max-width:300px; }
.search-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--fg-dim); pointer-events:none; }
.search-input { width:100%; padding:7px 30px 7px 34px; background:var(--surface-2); border:1px solid var(--border); border-radius:7px; color:var(--fg); font-size:13px; outline:none; box-sizing:border-box; font-family:inherit; }
.search-input:focus { border-color:var(--orange-400); }
.search-clr { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:11px; color:var(--fg-dim); }
.filter-sel { padding:7px 10px; background:var(--surface-2); border:1px solid var(--border); border-radius:7px; color:var(--fg); font-size:13px; cursor:pointer; outline:none; font-family:inherit; }
.filter-sel:focus { border-color:var(--orange-400); }

/* ── Table ───────────────────────────────────────────────────────────── */
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden; box-shadow:var(--shadow-sm); }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table th { background:var(--surface-2); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--fg-2); padding:10px 14px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
.data-table td { padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover { background:var(--hover); }
.empty-row { text-align:center; color:var(--fg-dim); padding:52px !important; }

/* ── LV identifier cell ──────────────────────────────────────────────── */
.lv-id-cell { display:flex; align-items:center; gap:6px; }
.lv-code-pill {
  font-size:10.5px; font-weight:700; letter-spacing:.04em;
  background:rgba(249,115,22,.15); color:#fb923c;
  padding:2px 8px; border-radius:5px;
}
.lv-separator { color:var(--fg-dim); font-weight:300; }
.mono-tag { font-family:monospace; font-size:12px; background:var(--surface-2); border:1px solid var(--border); padding:2px 8px; border-radius:5px; font-weight:600; color:var(--fg); }

/* ── Cell helpers ────────────────────────────────────────────────────── */
.cell-sub { font-size:12px; color:var(--fg-2); }
.chip-on { font-size:11px; font-weight:600; background:rgba(16,185,129,.15); color:#34d399; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.chip-off { font-size:11px; font-weight:600; background:rgba(100,116,139,.15); color:var(--fg-dim); padding:3px 10px; border-radius:20px; white-space:nowrap; }

/* ── Pagination ──────────────────────────────────────────────────────── */
.pag-row { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:18px; }
.pag-btn { background:var(--surface); border:1px solid var(--border); border-radius:7px; padding:5px 14px; cursor:pointer; font-size:17px; color:var(--fg-2); transition:background .12s; }
.pag-btn:hover:not(:disabled) { background:var(--hover); color:var(--fg); }
.pag-btn:disabled { opacity:.4; cursor:default; }
.pag-info { font-size:13px; color:var(--fg-2); }

/* ── Action dropdown ─────────────────────────────────────────────────── */
.action-drop { z-index:9999; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.3); overflow:hidden; }
.action-drop button { display:flex; align-items:center; gap:8px; width:100%; text-align:left; padding:9px 14px; font-size:13px; background:transparent; border:none; cursor:pointer; color:var(--fg); transition:background .12s; font-family:inherit; }
.action-drop button:hover { background:var(--surface-2); }
.action-drop button.danger { color:#f87171; }
.action-drop button.danger:hover { background:rgba(239,68,68,.08); }

/* ── Modal ───────────────────────────────────────────────────────────── */
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:200; padding:16px; }
.modal-box { background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-lg); width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; color:var(--fg); }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); flex-shrink:0; }
.modal-header h2 { font-size:15px; font-weight:700; margin:0; color:var(--fg); }
.modal-body { padding:20px; overflow-y:auto; flex:1; }
.modal-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; margin-top:8px; border-top:1px solid var(--border); }

/* ── Form ────────────────────────────────────────────────────────────── */
.form-group { margin-bottom:12px; }
.form-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:4px; }
.req { color:#f87171; }
.form-input { background:var(--surface-2); border:1px solid var(--border); border-radius:7px; padding:7px 10px; font-size:13px; color:var(--fg); font-family:inherit; outline:none; width:100%; transition:border-color .15s; box-sizing:border-box; }
.form-input:focus { border-color:var(--orange-400); }
.form-select { cursor:pointer; }
.inp-err { border-color:#f87171 !important; }
.form-err { font-size:11.5px; color:#f87171; margin-top:3px; }
.form-hint { font-size:11px; color:var(--fg-dim); margin-top:3px; }

/* ── Identifier preview ──────────────────────────────────────────────── */
.id-preview {
  margin-top:6px; padding:5px 10px; border-radius:6px;
  background:rgba(249,115,22,.1); border:1px solid rgba(249,115,22,.2);
  font-size:13px; font-weight:700; color:#fb923c; font-family:monospace;
  display:inline-block;
}

/* ── Toggle ──────────────────────────────────────────────────────────── */
.toggle-row { display:flex; align-items:center; gap:10px; cursor:pointer; }
.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
.toggle-track { width:38px; height:21px; border-radius:11px; background:var(--border-2); position:relative; transition:background .2s; flex-shrink:0; }
.toggle-track.on { background:var(--orange-500); }
.toggle-thumb { position:absolute; top:3px; left:3px; width:15px; height:15px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.toggle-track.on .toggle-thumb { transform:translateX(17px); }

/* ── Import modal extras ─────────────────────────────────────────────── */
.import-steps { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
.import-step { display:flex; align-items:flex-start; gap:10px; }
.step-num { width:22px; height:22px; background:linear-gradient(180deg, var(--orange-400), var(--orange-500)); color:#fff; border-radius:50%; display:grid; place-items:center; font-size:11px; font-weight:700; flex-shrink:0; }
.import-format { background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:12px 14px; margin-bottom:4px; }
.fmt-title { font-size:11px; font-weight:700; opacity:.5; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.fmt-list { font-size:12px; opacity:.7; margin:0; padding-left:16px; line-height:1.8; }
.import-errors { margin-top:10px; }
.import-err-row { font-size:12px; color:#f87171; padding:3px 0; border-bottom:1px solid var(--border); }
</style>
