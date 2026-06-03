<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()
const page = usePage()

// ── Position options ──────────────────────────────────────────────────────
const POSITION_OPTIONS = ['General', 'Driver', 'Supervisor', 'Foreman', 'Technician', 'Helper', 'Staff']

const props = defineProps({
    employees:       Object,
    departments:     Array,
    filters:         Object,
    today:           String,
    overdueEmpIds:   Array,   // IDs of employees with overdue mandatory PPE
    overdueLvEmpIds: Array,   // IDs of driver employees with overdue LV mandatory equipment
    pendingRequests: Array,   // pending employee requests
    vehiclesForDept: Array,   // vehicles available for LV assignment
    userRole:        String,
    userDeptId:      Number,
})

// ── Permissions (from Inertia shared props) ───────────────────────────────
const canCreate = computed(() => {
    const p = page.props.auth?.permissions
    return p?.__all || p?.employees?.create || false
})
const canEdit = computed(() => {
    const p = page.props.auth?.permissions
    return p?.__all || p?.employees?.edit || false
})

// ── Filters ──────────────────────────────────────────────────────────────
const search       = ref(props.filters?.search     ?? '')
const filterDept   = ref(props.filters?.department ?? '')
const filterStatus = ref(props.filters?.status     ?? '')

function params() {
    return {
        search:     search.value      || undefined,
        department: filterDept.value  || undefined,
        status:     filterStatus.value || undefined,
    }
}
const go = (extra = {}) =>
    router.get(route('employees.index'), { ...params(), ...extra },
        { preserveState: true, preserveScroll: true, replace: true })

let st = null
watch(search, () => { clearTimeout(st); st = setTimeout(() => go(), 350) })
watch([filterDept, filterStatus], () => go())

function clearFilters() {
    search.value = ''; filterDept.value = ''; filterStatus.value = ''
    router.get(route('employees.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}

function goPage(url) {
    if (url) router.get(url, params(), { preserveState: true, preserveScroll: true })
}

// ── Action dropdown ───────────────────────────────────────────────────────
const openDropId  = ref(null)
const dropStyle   = ref({})
const dropEmployee= ref(null)

function toggleDrop(emp, ev) {
    ev.stopPropagation()
    if (openDropId.value === emp.id) { openDropId.value = null; return }
    const r = ev.currentTarget.getBoundingClientRect()
    dropStyle.value = {
        position: 'fixed',
        top:  `${r.bottom + 4}px`,
        right:`${window.innerWidth - r.right}px`,
        minWidth: '160px',
    }
    openDropId.value = emp.id
    dropEmployee.value = emp
}
function closeDrop() { openDropId.value = null; dropEmployee.value = null }

function onEsc(e) {
    if (e.key === 'Escape') {
        closeDrop()
        showModal.value        = false
        showImportModal.value  = false
        showLoginModal.value   = false
    }
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
    employee_id:           '',
    name:                  '',
    position:              '',
    department_id:         '',
    is_active:             true,
    lv_id:                 null,
    email:                 '',
    password:              '',
    password_confirmation: '',
})

function openAdd() {
    editingId.value = null
    form.reset()
    form.is_active    = true
    form.lv_id        = null
    // Auto-set department for admin_dept
    if (props.userRole === 'admin_dept' && props.userDeptId) {
        form.department_id = props.userDeptId
    }

    lvForm.reset()
    showModal.value = true; closeDrop()
}
function openEdit(emp) {
    editingId.value = emp.id
    Object.assign(form, {
        employee_id:           emp.employee_id   ?? '',
        name:                  emp.name          ?? '',
        position:              emp.position      ?? '',
        department_id:         emp.department_id ?? '',
        is_active:             emp.is_active,
        email:                 emp.user?.email   ?? '',
        password:              '',
        password_confirmation: '',
    })
    form.clearErrors()
    lvForm.reset()
    lvForm.clearErrors()

    showModal.value = true; closeDrop()
}
function closeModal() {
    showModal.value = false
    form.reset(); form.clearErrors()
    lvForm.reset(); lvForm.clearErrors()

}
function submitForm() {
    const opts = { onSuccess: closeModal }
    editingId.value
        ? form.patch(route('employees.update', editingId.value), opts)
        : form.post(route('employees.store'), opts)
}
function doDisable(emp) {
    closeDrop()
    if (!confirm(`${t('emp.confirmDisable')} "${emp.name}"?`)) return
    router.delete(route('employees.destroy', emp.id), { preserveScroll: true })
}

// ── LV Assignment (inside edit modal) ────────────────────────────────────
const lvForm = useForm({
    action:    '',
    lv_id:     null,
})

const currentEmpLvs = computed(() => {
    if (!editingId.value) return []
    const emp = props.employees?.data?.find(e => e.id === editingId.value)
    return emp?.driven_vehicles ?? emp?.drivenVehicles ?? []
})

const selectedLvHasOtherDriver = computed(() => {
    if (!lvForm.lv_id || !props.vehiclesForDept) return false
    const lv = props.vehiclesForDept.find(v => v.id === Number(lvForm.lv_id))
    return lv && lv.driver_id && lv.driver_id !== editingId.value
})

function unassignLv(lvId) {
    lvForm.action = 'unassign'
    lvForm.lv_id  = lvId
    lvForm.post(route('employees.assignLv', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { lvForm.reset() },
    })
}
function assignExistingLv() {
    if (!lvForm.lv_id) return
    lvForm.action = 'assign'
    lvForm.post(route('employees.assignLv', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { lvForm.reset() },
    })
}
// ── Batch GI from pending requests ────────────────────────────────────────
const selectedRequestIds = ref([])

function toggleRequestSelection(id) {
    const idx = selectedRequestIds.value.indexOf(id)
    if (idx === -1) selectedRequestIds.value.push(id)
    else selectedRequestIds.value.splice(idx, 1)
}
function selectAllRequests() {
    if (selectedRequestIds.value.length === props.pendingRequests?.length) {
        selectedRequestIds.value = []
    } else {
        selectedRequestIds.value = (props.pendingRequests ?? []).map(r => r.id)
    }
}
function batchCreateGI() {
    if (!selectedRequestIds.value.length) return
    router.get(route('gi.create'), { request_ids: selectedRequestIds.value })
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
    importForm.post(route('employees.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeImportModal,
        onError: errs => { importErrors.value = Object.values(errs) },
    })
}

// ── Create / Revoke Login ─────────────────────────────────────────────────
const showLoginModal  = ref(false)
const loginTargetEmp  = ref(null)
const loginForm       = useForm({
    password:              '', // legacy — kept for revokeLogin only
    password_confirmation: '',
})

function openCreateLogin(emp) {
    // Now handled inline inside the edit modal — just open edit
    openEdit(emp)
    closeDrop()
}
function closeLoginModal() {
    showLoginModal.value = false
    loginForm.reset(); loginForm.clearErrors()
    loginTargetEmp.value = null
}
function submitCreateLogin() {
    loginForm.post(route('employees.createLogin', loginTargetEmp.value.id), {
        preserveScroll: true,
        onSuccess: () => { closeLoginModal(); loginForm.reset() },
    })
}
function doRevokeLogin(emp) {
    closeDrop()
    if (!confirm(`${t('emp.revokeLoginConfirm')} "${emp.name}"?`)) return
    router.delete(route('employees.revokeLogin', emp.id), { preserveScroll: true })
}

function processRequest(requestId) {
    if (!confirm('Tandai pengajuan ini sebagai selesai?')) return
    router.patch(route('employee-requests.process', requestId), {}, { preserveScroll: true })
}

// ── Cooldown helpers ──────────────────────────────────────────────────────
function cooldownStatus(emp) {
    const cd = emp.cooldowns?.[0]
    if (!cd?.cooldown_until) return null
    const until = new Date(cd.cooldown_until)
    if (until > new Date()) {
        const ds = until.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
        return { locked: true, label: t('emp.cooldownLocked').replace('{date}', ds) }
    }
    return { locked: false, label: t('emp.cooldownOk') }
}

function lastTaken(emp) {
    const cd = emp.cooldowns?.[0]
    if (!cd) return null
    const name = cd.variant?.item?.name_en ?? cd.variant?.item?.name_id ?? '—'
    const d = cd.cooldown_until
        ? new Date(cd.cooldown_until).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
        : '—'
    return `${name} — ${d}`
}

// ── Available LVs (filter out those already assigned to this employee) ────
const availableLvsForAssign = computed(() => {
    if (!props.vehiclesForDept) return []
    const myLvIds = new Set(currentEmpLvs.value.map(v => v.id))
    return props.vehiclesForDept.filter(v => !myLvIds.has(v.id))
})

// ── Position / Login helpers ──────────────────────────────────────────────
const isDriverPosition = computed(() =>
    form.position.toLowerCase().includes('driver')
)
const currentEmpHasLogin = computed(() => {
    if (!editingId.value) return false
    return !!(props.employees?.data?.find(e => e.id === editingId.value)?.user_id)
})
</script>

<template>
  <AppLayout>
    <template #title>{{ $t('emp.title') }}</template>
    <template #breadcrumb>{{ $t('emp.title') }}</template>

    <!-- ── Page header ─────────────────────────────────────────────── -->
    <div class="page-head">
      <div>
        <h1 class="page-title">{{ $t('emp.title') }}</h1>
        <p class="page-sub">{{ $t('emp.sub') }}</p>
      </div>
      <div class="head-actions">
        <button class="btn-import" @click="openImportModal" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          {{ $t('emp.importBtn') }}
        </button>
        <button v-if="canCreate" class="btn-primary" @click="openAdd" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          {{ $t('emp.addBtn') }}
        </button>
      </div>
    </div>

    <!-- ── Pending Employee Requests Panel ──────────────────────────── -->
    <div v-if="pendingRequests?.length > 0" class="pending-requests-panel">
      <div class="pr-header">
        <span class="pr-title">📋 {{ $t('emp.pendingRequests') }}</span>
        <span class="pr-count-badge">{{ pendingRequests.length }}</span>
        <div class="pr-header-actions">
          <button v-if="selectedRequestIds.length > 0"
            class="btn-buat-gi"
            @click="batchCreateGI"
            type="button">
            📦 Buat GI ({{ selectedRequestIds.length }})
          </button>
        </div>
      </div>
      <table class="pr-table">
        <thead>
          <tr>
            <th style="width:36px">
              <input type="checkbox" class="pr-checkbox"
                :checked="selectedRequestIds.length === pendingRequests.length && pendingRequests.length > 0"
                :indeterminate="selectedRequestIds.length > 0 && selectedRequestIds.length < pendingRequests.length"
                @change="selectAllRequests" />
            </th>
            <th>{{ $t('emp.reqEmployee') }}</th>
            <th>{{ $t('emp.reqItems') }}</th>
            <th>{{ $t('emp.reqDate') }}</th>
            <th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in pendingRequests" :key="req.id"
            :class="{ 'pr-row-selected': selectedRequestIds.includes(req.id) }">
            <td>
              <input type="checkbox" class="pr-checkbox"
                :checked="selectedRequestIds.includes(req.id)"
                @change="toggleRequestSelection(req.id)" />
            </td>
            <td>
              <span class="cell-main">{{ req.employee?.name }}</span>
              <span class="mono-tag" style="font-size:11px">{{ req.employee?.employee_id }}</span>
            </td>
            <td>
              <span v-for="(ri, idx) in req.items" :key="ri.id" class="pr-item-chip">
                {{ ri.item?.name_en }}<template v-if="idx < req.items.length - 1">, </template>
              </span>
              <span v-if="!req.items?.length" class="cell-sub">—</span>
            </td>
            <td class="cell-sub" style="font-size:12px;white-space:nowrap">
              {{ new Date(req.created_at).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) }}
            </td>
            <td>
              <span class="cell-sub" style="font-size:11px;color:var(--fg-2)">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Toolbar ──────────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="search-wrap">
        <svg class="search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="search" class="search-input" type="text" :placeholder="$t('emp.searchPh')" />
        <button v-if="search" class="search-clr" @click="search = ''" type="button">✕</button>
      </div>
      <select v-if="userRole !== 'admin_dept'" v-model="filterDept" class="filter-sel">
        <option value="">{{ $t('emp.filterDept') }}</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <select v-model="filterStatus" class="filter-sel">
        <option value="">{{ $t('emp.filterStatus') }}</option>
        <option value="active">{{ $t('common.active') }}</option>
        <option value="inactive">{{ $t('common.inactive') }}</option>
      </select>
      <button v-if="search || filterDept || filterStatus" class="btn-clear" @click="clearFilters" type="button">
        {{ $t('common.clearFilter') }}
      </button>
    </div>

    <!-- ── Table ────────────────────────────────────────────────────── -->
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('emp.colEmpId') }}</th>
            <th>{{ $t('emp.colName') }}</th>
            <th>{{ $t('emp.colPosition') }}</th>
            <th>{{ $t('emp.colDept') }}</th>
            <th>{{ $t('emp.colLastTaken') }}</th>
            <th>{{ $t('emp.colCooldown') }}</th>
            <th>{{ $t('emp.loginStatus') }}</th>
            <th>{{ $t('emp.colStatus') }}</th>
            <th style="width:48px;"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!employees.data.length">
            <td colspan="9" class="empty-row">{{ $t('emp.noResults') }}</td>
          </tr>
          <tr v-for="emp in employees.data" :key="emp.id">
            <td>
              <span class="mono-tag">{{ emp.employee_id }}</span>
            </td>
            <td>
              <div class="cell-main">{{ emp.name }}</div>
              <div class="badge-row">
                <span v-if="overdueEmpIds?.includes(emp.id)" class="chip-overdue-ppe" :title="$t('emp.overdueEmpBadge')">
                  ⚠ {{ $t('emp.overdueEmpBadge') }}
                </span>
                <span v-if="overdueLvEmpIds?.includes(emp.id)" class="chip-overdue-lv" :title="$t('emp.overdueLvBadge')">
                  🚗 {{ $t('emp.overdueLvBadge') }}
                </span>
              </div>
            </td>
            <td>
              <div v-if="emp.position" class="cell-sub">{{ emp.position }}</div>
              <span v-else class="cell-sub">—</span>
            </td>
            <td>
              <div v-if="emp.department" class="dept-pill">
                <span class="dept-code">{{ emp.department.code }}</span>
                {{ emp.department.name }}
              </div>
              <span v-else class="cell-sub">—</span>
            </td>
            <td>
              <div v-if="lastTaken(emp)" class="cell-sub" style="font-size:11.5px;line-height:1.4">{{ lastTaken(emp) }}</div>
              <span v-else class="cell-sub">{{ $t('emp.neverTaken') }}</span>
            </td>
            <td>
              <template v-if="emp.nearestCooldown">
                <span class="chip-locked" :title="'Cooldown aktif s/d ' + new Date(emp.nearestCooldown).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })">
                  🔒 s/d {{ new Date(emp.nearestCooldown).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) }}
                </span>
              </template>
              <span v-else class="chip-ok">✅ OK</span>
            </td>
            <td>
              <div v-if="emp.user_id && emp.user">
                <span class="chip-login-ok">✓ {{ $t('emp.hasLogin') }}</span>
                <div class="cell-sub" style="font-size:11px;margin-top:2px">{{ emp.user.email }}</div>
              </div>
              <span v-else class="chip-login-none">— {{ $t('emp.noLogin') }}</span>
            </td>
            <td>
              <span :class="emp.is_active ? 'chip-on' : 'chip-off'">
                {{ emp.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <button class="icon-btn" @click.stop="toggleDrop(emp, $event)" type="button">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination ────────────────────────────────────────────────── -->
    <div v-if="employees.last_page > 1" class="pag-row">
      <button class="pag-btn" :disabled="!employees.prev_page_url" @click="goPage(employees.prev_page_url)">‹</button>
      <span class="pag-info">{{ employees.current_page }} / {{ employees.last_page }}</span>
      <button class="pag-btn" :disabled="!employees.next_page_url" @click="goPage(employees.next_page_url)">›</button>
    </div>

    <!-- ── Action Dropdown ───────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="openDropId" class="action-drop" :style="dropStyle" @click.stop>
        <button v-if="canEdit" @click="openEdit(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
          {{ $t('common.edit') }}
        </button>
        <button v-if="!canEdit" @click="openEdit(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          {{ $t('common.view') }}
        </button>
        <button v-if="!dropEmployee?.user_id" @click="openCreateLogin(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>
          {{ $t('emp.createLogin') }}
        </button>
        <button v-if="dropEmployee?.user_id" class="danger" @click="doRevokeLogin(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>
          {{ $t('emp.revokeLogin') }}
        </button>
        <button v-if="dropEmployee?.is_active" class="danger" @click="doDisable(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          {{ $t('common.disable') }}
        </button>
        <button v-else @click="openEdit(dropEmployee)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          {{ $t('common.reactivate') }}
        </button>
      </div>
    </Teleport>

    <!-- ── Add / Edit Modal ──────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-box" style="max-width:540px">
          <div class="modal-header">
            <h2>{{ editingId ? $t('emp.editTitle') : $t('emp.addTitle') }}</h2>
            <button class="icon-btn" @click="closeModal" type="button">✕</button>
          </div>
          <form @submit.prevent="submitForm" class="modal-body">
            <!-- Employee ID -->
            <div class="form-group">
              <label class="form-label">{{ $t('emp.formEmpId') }} <span class="req">*</span></label>
              <input v-model="form.employee_id" class="form-input" type="text"
                :placeholder="$t('emp.formEmpIdPh')"
                :class="{ 'inp-err': form.errors.employee_id }"
                :disabled="!!editingId" />
              <div v-if="form.errors.employee_id" class="form-err">{{ form.errors.employee_id }}</div>
            </div>
            <!-- Name -->
            <div class="form-group">
              <label class="form-label">{{ $t('emp.formName') }} <span class="req">*</span></label>
              <input v-model="form.name" class="form-input" type="text"
                :placeholder="$t('emp.formNamePh')"
                :class="{ 'inp-err': form.errors.name }" />
              <div v-if="form.errors.name" class="form-err">{{ form.errors.name }}</div>
            </div>
            <!-- Position -->
            <div class="form-group">
              <label class="form-label">{{ $t('emp.formPosition') }}</label>
              <input v-model="form.position" class="form-input" type="text"
                list="position-list"
                :placeholder="$t('emp.formPositionPh')" />
              <datalist id="position-list">
                <option v-for="p in POSITION_OPTIONS" :key="p" :value="p" />
              </datalist>
              <div v-if="form.errors.position" class="form-err">{{ form.errors.position }}</div>
            </div>
            <!-- Department -->
            <div class="form-group">
              <label class="form-label">{{ $t('emp.formDept') }} <span class="req">*</span></label>
              <select v-model="form.department_id" class="form-input form-select"
                :class="{ 'inp-err': form.errors.department_id }"
                :disabled="userRole === 'admin_dept'">
                <option value="">— {{ $t('emp.formDept') }} —</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }} ({{ d.code }})</option>
              </select>
              <div v-if="form.errors.department_id" class="form-err">{{ form.errors.department_id }}</div>
            </div>
            <!-- Active toggle (edit only) -->
            <div v-if="editingId" class="form-group">
              <label class="toggle-row">
                <input type="checkbox" v-model="form.is_active" class="sr-only" />
                <span class="toggle-track" :class="{ on: form.is_active }">
                  <span class="toggle-thumb"></span>
                </span>
                <span class="form-label" style="margin:0">{{ $t('emp.formActive') }}</span>
              </label>
            </div>

            <!-- ── LV Assignment Section ──────────────────────────────── -->
            <!-- CREATE mode: simple dropdown to optionally assign to an LV -->
            <div v-if="!editingId && isDriverPosition" class="lv-section">
              <div class="lv-section-title">{{ $t('emp.formLv') }}</div>
              <div class="form-group" style="margin-bottom:0">
                <select v-model="form.lv_id" class="form-input form-select">
                  <option :value="null">— {{ $t('emp.lvSelectPh') }} —</option>
                  <option v-for="v in vehiclesForDept" :key="v.id" :value="v.id">
                    {{ v.lv_number }}{{ v.driver_id ? (' (' + $t('emp.lvHasDriver') + ')') : '' }}
                  </option>
                </select>
                <div class="form-hint">{{ $t('emp.lvAssignHint') }}</div>
              </div>
            </div>

            <!-- EDIT mode: full LV management (assign, unassign, create new) -->
            <div v-if="editingId && isDriverPosition" class="lv-section">
              <div class="lv-section-title">{{ $t('emp.formLv') }}</div>

              <!-- Current LVs -->
              <div v-if="currentEmpLvs.length" class="lv-current-list">
                <div v-for="lv in currentEmpLvs" :key="lv.id" class="lv-tag">
                  <span class="lv-tag-number">{{ lv.lv_code ? `${lv.lv_code}-${lv.lv_number}` : lv.lv_number }}</span>
                  <button type="button" class="lv-remove-btn"
                    @click="unassignLv(lv.id)"
                    :disabled="lvForm.processing"
                    :title="$t('emp.lvUnassign')">✕</button>
                </div>
              </div>
              <div v-else class="lv-none">{{ $t('emp.lvNone') }}</div>

              <!-- Assign existing LV -->
              <div class="lv-assign-row">
                <select v-model="lvForm.lv_id" class="form-input form-select lv-select">
                  <option :value="null">{{ $t('emp.lvSelectPh') }}</option>
                  <option v-for="v in availableLvsForAssign" :key="v.id" :value="v.id">
                    {{ v.lv_number }}
                    <template v-if="v.driver_id"> ({{ $t('emp.lvHasDriver') }})</template>
                  </option>
                </select>
                <button type="button" class="btn-ghost lv-assign-btn"
                  @click="assignExistingLv"
                  :disabled="!lvForm.lv_id || lvForm.processing">
                  {{ $t('emp.lvAssignBtn') }}
                </button>
              </div>

              <!-- Warning: LV already has a driver -->
              <div v-if="selectedLvHasOtherDriver" class="form-warn">
                ⚠ {{ $t('emp.lvDriverConflict') }}
              </div>

              <!-- LV creation is done in LV Management page -->
              <div class="form-hint" style="margin-top:4px;font-size:11px;opacity:.55">
                {{ $t('emp.lvCreateNote') }}
              </div>
            </div>
            <!-- ─────────────────────────────────────────────────────── -->

            <!-- ── Login Account Section ─────────────────────────────── -->
            <div class="form-section-divider">{{ $t('emp.loginSectionTitle') }}</div>

            <!-- Status line for existing employees with login -->
            <div v-if="editingId && currentEmpHasLogin" class="login-inline-status">
              ✓ {{ $t('emp.hasLogin') }}
              <span class="login-email-tag">{{ form.email }}</span>
            </div>

            <!-- Email (only if no existing login) -->
            <div v-if="!currentEmpHasLogin" class="form-group">
              <label class="form-label">{{ $t('emp.formEmail') }}</label>
              <input v-model="form.email" class="form-input" type="email"
                :placeholder="$t('emp.formEmailPh')"
                :class="{ 'inp-err': form.errors.email }" />
              <div v-if="form.errors.email" class="form-err">{{ form.errors.email }}</div>
              <div class="form-hint">{{ $t('emp.formEmailHint') }}</div>
            </div>

            <!-- Password -->
            <div class="form-group">
              <label class="form-label">
                {{ editingId ? $t('emp.formPasswordNew') : $t('emp.formPassword') }}
              </label>
              <input v-model="form.password" class="form-input" type="password"
                :placeholder="editingId ? $t('emp.formPasswordNewPh') : 'Min. 8 karakter'"
                :class="{ 'inp-err': form.errors.password }" />
              <div v-if="form.errors.password" class="form-err">{{ form.errors.password }}</div>
            </div>

            <!-- Confirm password (only shown when typing a password) -->
            <div v-if="form.password" class="form-group">
              <label class="form-label">{{ $t('emp.formPasswordConfirm') }}</label>
              <input v-model="form.password_confirmation" class="form-input" type="password"
                placeholder="Ulangi password" />
            </div>
            <!-- ─────────────────────────────────────────────────────── -->

            <div class="modal-footer">
              <button type="button" class="btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
              <button v-if="canEdit || !editingId" type="submit" class="btn-primary" :disabled="form.processing">
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
            <h2>{{ $t('emp.importTitle') }}</h2>
            <button class="icon-btn" @click="closeImportModal" type="button">✕</button>
          </div>
          <div class="modal-body">
            <!-- Steps -->
            <div class="import-steps">
              <div class="import-step">
                <span class="step-num">1</span>
                <div>
                  <div style="font-weight:600;font-size:13px">{{ $t('emp.importStep1') }}</div>
                  <div style="font-size:12px;opacity:.55">{{ $t('emp.importStep1Sub') }}</div>
                </div>
              </div>
              <div class="import-step">
                <span class="step-num">2</span>
                <div style="font-size:13px;font-weight:600">{{ $t('emp.importStep2') }}</div>
              </div>
              <div class="import-step">
                <span class="step-num">3</span>
                <div style="font-size:13px;font-weight:600">{{ $t('emp.importStep3') }}</div>
              </div>
            </div>

            <!-- Download template -->
            <a :href="route('employees.importTemplate')"
              class="btn-ghost" style="display:inline-flex;align-items:center;gap:7px;margin-bottom:18px;width:100%;justify-content:center">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              {{ $t('emp.importTemplateBtn') }}
            </a>

            <!-- Format guide -->
            <div class="import-format">
              <div class="fmt-title">Format</div>
              <ul class="fmt-list">
                <li>{{ $t('emp.importFmt1') }}</li>
                <li>{{ $t('emp.importFmt2') }}</li>
                <li>{{ $t('emp.importFmt3') }}</li>
              </ul>
            </div>

            <!-- Errors from previous submit -->
            <div v-if="importErrors.length" class="import-errors">
              <div v-for="(e, i) in importErrors" :key="i" class="import-err-row">⚠ {{ e }}</div>
            </div>

            <form @submit.prevent="submitImport" style="margin-top:16px">
              <div class="form-group">
                <label class="form-label">{{ $t('emp.importFile') }} <span class="req">*</span></label>
                <input ref="importFileRef" type="file" accept=".csv,.xlsx,.xls,.txt"
                  class="form-input"
                  @change="e => importForm.file = e.target.files[0]" />
                <div style="font-size:11px;opacity:.45;margin-top:3px">{{ $t('emp.importFileHint') }}</div>
                <div v-if="importForm.errors.file" class="form-err">{{ importForm.errors.file }}</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn-ghost" @click="closeImportModal">{{ $t('common.cancel') }}</button>
                <button type="submit" class="btn-primary"
                  :disabled="importForm.processing || !importForm.file">
                  {{ importForm.processing ? $t('emp.importing') : $t('emp.importBtn') }}
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
.page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:12px; }
.page-title { font-size:21px; font-weight:700; color:var(--fg); margin:0 0 4px; }
.page-sub { font-size:13px; color:var(--fg-2); margin:0; }
.head-actions { display:flex; gap:10px; align-items:center; flex-shrink:0; }
.btn-primary { display:inline-flex; align-items:center; gap:6px; background:linear-gradient(180deg, var(--orange-400), var(--orange-500)); color:#fff; border:none; border-radius:7px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; white-space:nowrap; transition:opacity .15s; }
.btn-primary:hover { opacity:.88; }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-ghost { display:inline-flex; align-items:center; gap:6px; background:transparent; color:var(--fg-2); border:1px solid var(--border); border-radius:7px; padding:7px 14px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit; text-decoration:none; transition:background .15s, color .15s; }
.btn-ghost:hover { background:var(--hover); color:var(--fg); }
.btn-ghost:disabled { opacity:.4; cursor:not-allowed; }
.btn-import { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; white-space:nowrap; text-decoration:none; transition:background .15s, border-color .15s; background:rgba(59,130,246,.12); color:#60a5fa; border:1px solid rgba(59,130,246,.25); }
.btn-import:hover { background:rgba(59,130,246,.2); border-color:rgba(59,130,246,.4); }
.btn-clear { font-size:12px; color:var(--fg-dim); background:transparent; border:none; cursor:pointer; text-decoration:underline; }
.toolbar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.search-wrap { position:relative; flex:1; min-width:180px; max-width:300px; }
.search-ico { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--fg-dim); pointer-events:none; }
.search-input { width:100%; padding:7px 30px 7px 34px; background:var(--surface-2); border:1px solid var(--border); border-radius:7px; color:var(--fg); font-size:13px; outline:none; box-sizing:border-box; font-family:inherit; }
.search-input:focus { border-color:var(--orange-400); }
.search-clr { position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:11px; color:var(--fg-dim); }
.filter-sel { padding:7px 10px; background:var(--surface-2); border:1px solid var(--border); border-radius:7px; color:var(--fg); font-size:13px; cursor:pointer; outline:none; font-family:inherit; }
.filter-sel:focus { border-color:var(--orange-400); }
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden; box-shadow:var(--shadow-sm); }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table th { background:var(--surface-2); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--fg-2); padding:10px 14px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
.data-table td { padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover { background:var(--hover); }
.empty-row { text-align:center; color:var(--fg-dim); padding:52px !important; }
.cell-main { color:var(--fg); }
.cell-sub { font-size:12px; color:var(--fg-2); }
.badge-row { display:flex; flex-wrap:wrap; gap:4px; margin-top:3px; }
.mono-tag { font-family:monospace; font-size:12px; background:var(--surface-2); border:1px solid var(--border); padding:2px 8px; border-radius:5px; font-weight:600; color:var(--fg); }
.dept-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--fg); }
.dept-code { font-size:10px; font-weight:700; background:rgba(99,102,241,.18); color:#818cf8; padding:1px 6px; border-radius:4px; }
.chip-on { font-size:11px; font-weight:600; background:rgba(16,185,129,.15); color:#34d399; padding:3px 10px; border-radius:20px; white-space:nowrap; }
.chip-off { font-size:11px; font-weight:600; background:rgba(100,116,139,.15); color:var(--fg-dim); padding:3px 10px; border-radius:20px; white-space:nowrap; }
.chip-ok { font-size:11px; font-weight:600; background:rgba(16,185,129,.15); color:#34d399; padding:3px 9px; border-radius:20px; white-space:nowrap; }
.chip-locked { font-size:11px; font-weight:600; background:rgba(239,68,68,.15); color:#f87171; padding:3px 9px; border-radius:20px; white-space:nowrap; }
.pag-row { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:18px; }
.pag-btn { background:var(--surface); border:1px solid var(--border); border-radius:7px; padding:5px 14px; cursor:pointer; font-size:17px; color:var(--fg-2); transition:background .12s; }
.pag-btn:hover:not(:disabled) { background:var(--hover); color:var(--fg); }
.pag-btn:disabled { opacity:.4; cursor:default; }
.pag-info { font-size:13px; color:var(--fg-2); }
.action-drop { z-index:9999; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.3); overflow:hidden; }
.action-drop button { display:flex; align-items:center; gap:8px; width:100%; text-align:left; padding:9px 14px; font-size:13px; background:transparent; border:none; cursor:pointer; color:var(--fg); transition:background .12s; font-family:inherit; }
.action-drop button:hover { background:var(--surface-2); }
.action-drop button.danger { color:#f87171; }
.action-drop button.danger:hover { background:rgba(239,68,68,.08); }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:200; padding:16px; }
.modal-box { background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-lg); width:100%; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; color:var(--fg); }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); flex-shrink:0; }
.modal-header h2 { font-size:15px; font-weight:700; margin:0; color:var(--fg); }
.modal-body { padding:20px; overflow-y:auto; flex:1; }
.modal-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; margin-top:8px; border-top:1px solid var(--border); }
.form-group { margin-bottom:12px; }
.form-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:4px; }
.req { color:#f87171; }
.form-input { background:var(--surface-2); border:1px solid var(--border); border-radius:7px; padding:7px 10px; font-size:13px; color:var(--fg); font-family:inherit; outline:none; width:100%; transition:border-color .15s; box-sizing:border-box; }
.form-input:focus { border-color:var(--orange-400); }
.form-select { cursor:pointer; }
.form-input:disabled { opacity:.45; cursor:not-allowed; }
.inp-err { border-color:#f87171 !important; }
.form-err { font-size:11.5px; color:#f87171; margin-top:3px; }
.form-warn { font-size:11.5px; color:#f59e0b; background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25); border-radius:6px; padding:6px 10px; margin-top:6px; }
.toggle-row { display:flex; align-items:center; gap:10px; cursor:pointer; }
.sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
.toggle-track { width:38px; height:21px; border-radius:11px; background:var(--border-2); position:relative; transition:background .2s; flex-shrink:0; }
.toggle-track.on { background:var(--orange-500); }
.toggle-thumb { position:absolute; top:3px; left:3px; width:15px; height:15px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.toggle-track.on .toggle-thumb { transform:translateX(17px); }
.import-steps { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
.import-step { display:flex; align-items:flex-start; gap:10px; }
.step-num { width:22px; height:22px; background:linear-gradient(180deg, var(--orange-400), var(--orange-500)); color:#fff; border-radius:50%; display:grid; place-items:center; font-size:11px; font-weight:700; flex-shrink:0; }
.import-format { background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:12px 14px; margin-bottom:4px; }
.fmt-title { font-size:11px; font-weight:700; opacity:.5; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.fmt-list { font-size:12px; opacity:.7; margin:0; padding-left:16px; line-height:1.8; }
.import-errors { margin-top:10px; }
.import-err-row { font-size:12px; color:#f87171; padding:3px 0; border-bottom:1px solid var(--border); }
.chip-login-ok { font-size:11px; font-weight:600; background:rgba(16,185,129,.12); color:#34d399; padding:2px 8px; border-radius:20px; white-space:nowrap; }
.chip-login-none { font-size:11px; color:var(--fg-dim); padding:2px 0; }
.login-target-info { background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:10px 12px; margin-bottom:14px; }
.login-id-display { background:rgba(251,146,60,.08); border:1px solid rgba(251,146,60,.25); border-radius:8px; padding:10px 14px; margin-bottom:14px; }
.login-id-label { font-size:11px; font-weight:700; color:var(--fg-dim); text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px; }
.login-id-value { font-size:18px; font-weight:700; color:var(--orange-500); font-family:monospace; letter-spacing:.5px; }
.login-id-hint { font-size:11px; color:var(--fg-dim); margin-top:2px; }
.login-info-note { display:flex; align-items:flex-start; gap:6px; font-size:12px; color:var(--fg-dim); background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); border-radius:7px; padding:8px 10px; margin-top:4px; }
/* Overdue badges */
.chip-overdue-ppe { display:inline-block; font-size:10px; font-weight:700; background:rgba(245,158,11,.12); color:#d97706; padding:2px 6px; border-radius:12px; white-space:nowrap; }
.chip-overdue-lv  { display:inline-block; font-size:10px; font-weight:700; background:rgba(59,130,246,.12); color:#3b82f6; padding:2px 6px; border-radius:12px; white-space:nowrap; }
/* Pending requests panel */
.pending-requests-panel { background:rgba(245,158,11,.06); border:1px solid rgba(245,158,11,.25); border-radius:12px; padding:14px 16px; margin-bottom:16px; }
.pr-header { display:flex; align-items:center; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
.pr-title { font-size:14px; font-weight:700; color:var(--fg); }
.pr-count-badge { background:#f59e0b; color:#fff; font-size:11px; font-weight:700; padding:2px 7px; border-radius:999px; }
.pr-header-actions { margin-left:auto; }
.btn-buat-gi { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:7px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; white-space:nowrap; border:1px solid var(--orange-400); background:linear-gradient(180deg, var(--orange-400), var(--orange-500)); color:#fff; transition:opacity .15s; }
.btn-buat-gi:hover { opacity:.88; }
.pr-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.pr-table th { text-align:left; font-size:10.5px; font-weight:700; color:var(--fg-dim); text-transform:uppercase; letter-spacing:.06em; padding:6px 8px; border-bottom:1px solid var(--border); }
.pr-table td { padding:9px 8px; border-bottom:1px solid var(--border-soft); vertical-align:middle; color:var(--fg); }
.pr-table tr:last-child td { border-bottom:0; }
.pr-row-selected { background:rgba(245,158,11,.06); }
.pr-checkbox { width:15px; height:15px; cursor:pointer; accent-color:var(--orange-500); }
.pr-item-chip { font-size:12px; color:var(--fg); }
.btn-mark-done { appearance:none; border:1px solid #10b981; background:transparent; color:#10b981; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; cursor:pointer; font-family:inherit; white-space:nowrap; transition:background 200ms,color 200ms; }
.btn-mark-done:hover { background:#10b981; color:#fff; }
/* LV Section in Edit Modal */
.lv-section { margin-top:16px; padding-top:14px; border-top:1px solid var(--border); }
.lv-section-title { font-size:12px; font-weight:700; color:var(--fg-2); text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
.lv-current-list { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.lv-none { font-size:12px; color:var(--fg-dim); margin-bottom:10px; font-style:italic; }
.lv-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.25); color:#60a5fa; font-size:12px; font-weight:700; padding:4px 10px; border-radius:20px; }
.lv-tag-number { font-family:monospace; }
.lv-remove-btn { background:none; border:none; cursor:pointer; color:#f87171; font-size:12px; line-height:1; padding:0; transition:color .15s; }
.lv-remove-btn:hover { color:#ef4444; }
.lv-remove-btn:disabled { opacity:.4; cursor:not-allowed; }
.lv-assign-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.lv-select { flex:1; }
.lv-assign-btn { padding:7px 12px; font-size:12px; white-space:nowrap; }
.btn-create-lv-toggle { background:none; border:none; cursor:pointer; color:var(--orange-500); font-size:12px; font-weight:600; padding:4px 0; text-decoration:underline; font-family:inherit; }
.btn-create-lv-toggle:hover { color:var(--orange-400); }
.lv-create-form { background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:12px; margin-top:8px; }
.icon-btn { background:transparent; border:1px solid var(--border); border-radius:6px; padding:5px 7px; cursor:pointer; color:var(--fg-2); transition:background .12s; }
.icon-btn:hover { background:var(--hover); color:var(--fg); }
/* Login Section */
.form-section-divider { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--fg-dim); padding:14px 0 8px; border-top:1px solid var(--border); margin-top:8px; }
.form-hint { font-size:11px; color:var(--fg-dim); margin-top:3px; }
.login-inline-status { display:flex; align-items:center; gap:8px; font-size:12px; color:#34d399; padding:6px 10px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.2); border-radius:7px; margin-bottom:10px; }
.login-email-tag { font-family:monospace; font-size:11px; color:var(--fg-dim); }
</style>
