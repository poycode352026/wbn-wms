<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
    departments: Object, filters: Object, stats: Object, allUsers: Array,
})

const search       = ref(props.filters?.search ?? '')
const filterStatus = ref(props.filters?.status ?? '')
const sortCol      = ref(props.filters?.sort   ?? '')
const sortDir      = ref(props.filters?.dir    ?? 'asc')

function params() {
    return {
        search: search.value        || undefined,
        status: filterStatus.value  || undefined,
        sort:   sortCol.value       || undefined,
        dir:    sortCol.value ? sortDir.value : undefined,
    }
}
const go = (extra = {}) =>
    router.get(route('departments.index'), { ...params(), ...extra }, { preserveState: true, preserveScroll: true, replace: true })

function setStatus(v) { filterStatus.value = v; go() }
function clearFilters() {
    search.value = ''; filterStatus.value = ''
    router.get(route('departments.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}
let st = null
watch(search, () => { clearTimeout(st); st = setTimeout(() => go(), 350) })

function doSort(col) {
    if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    else { sortCol.value = col; sortDir.value = 'asc' }
    go()
}
function sortIcon(col) { return sortCol.value === col ? (sortDir.value === 'asc' ? '↑' : '↓') : '↕' }

// action dropdown
const activeMenu    = ref(null)
const menuDept      = ref(null)
const dropdownStyle = ref({})

function openMenu(event, dept) {
    event.stopPropagation()
    if (activeMenu.value === dept.id) { closeMenu(); return }
    const rect = event.currentTarget.getBoundingClientRect()
    dropdownStyle.value = {
        top:   (rect.bottom + 6) + 'px',
        right: (window.innerWidth - rect.right) + 'px',
    }
    activeMenu.value = dept.id
    menuDept.value   = dept
}
function closeMenu() { activeMenu.value = null; menuDept.value = null }
function onEsc(e) { if (e.key === 'Escape') closeMenu() }
onMounted(() => {
    document.addEventListener('click', closeMenu)
    document.addEventListener('keydown', onEsc)
    document.addEventListener('scroll', closeMenu, true)
})
onUnmounted(() => {
    document.removeEventListener('click', closeMenu)
    document.removeEventListener('keydown', onEsc)
    document.removeEventListener('scroll', closeMenu, true)
})

// modal
const isOpen    = ref(false)
const isEdit    = ref(false)
const editingId = ref(null)
const form = useForm({ code: '', name: '', is_active: true })

function openAdd() {
    isEdit.value = false; editingId.value = null
    form.reset(); form.is_active = true
    isOpen.value = true; closeMenu()
}
function openEdit(dept) {
    isEdit.value = true; editingId.value = dept.id
    Object.assign(form, {
        code: dept.code, name: dept.name, is_active: dept.is_active,
    })
    form.clearErrors(); isOpen.value = true; closeMenu()
}
function closeModal() { isOpen.value = false; form.reset(); form.clearErrors() }
function submitForm() {
    isEdit.value
        ? form.put(route('departments.update', editingId.value), { onSuccess: closeModal })
        : form.post(route('departments.store'), { onSuccess: closeModal })
}
function deleteDept(d) {
    closeMenu()
    if (!confirm(`${t('dm.confirmDelete')} "${d.name}"?`)) return
    router.delete(route('departments.destroy', d.id), { preserveScroll: true })
}
function toggleActive(d) {
    closeMenu()
    router.put(route('departments.update', d.id), {
        code: d.code, name: d.name, is_active: !d.is_active,
    }, { preserveScroll: true })
}

// admin modal
const isAdminModal = ref(false)
const adminDeptId  = ref(null)
const addAdminForm = useForm({ user_id: null, role: 'admin_dept' })

const adminDept = computed(() =>
    adminDeptId.value ? props.departments.data.find(d => d.id === adminDeptId.value) : null
)
const availableUsers = computed(() => {
    if (!adminDept.value) return props.allUsers ?? []
    const ids = new Set(adminDept.value.admins.map(a => a.id))
    return (props.allUsers ?? []).filter(u => !ids.has(u.id))
})

function openAdminModal(dept) {
    adminDeptId.value = dept.id
    addAdminForm.reset()
    isAdminModal.value = true
    closeMenu()
}
function closeAdminModal() { isAdminModal.value = false; adminDeptId.value = null }
function submitAddAdmin() {
    if (!addAdminForm.user_id) return
    addAdminForm.post(route('departments.assignAdmin', adminDeptId.value), {
        preserveScroll: true,
        onSuccess: () => addAdminForm.reset(),
    })
}
function doRemoveAdmin(userId) {
    if (!confirm(t('dm.confirmRemoveAdmin'))) return
    router.delete(route('departments.removeAdmin', adminDeptId.value), {
        data: { user_id: userId },
        preserveScroll: true,
    })
}

// avatar colors for dept initials
const AC = [
    {bg:'rgba(59,130,246,.22)',  color:'#60a5fa'},
    {bg:'rgba(249,115,22,.22)',  color:'#fb923c'},
    {bg:'rgba(139,92,246,.22)',  color:'#a78bfa'},
    {bg:'rgba(16,185,129,.22)',  color:'#34d399'},
    {bg:'rgba(234,179,8,.22)',   color:'#facc15'},
    {bg:'rgba(239,68,68,.22)',   color:'#f87171'},
]
function avc(name) { let h=0; for (const c of (name||'')) h=(h*31+c.charCodeAt(0))%AC.length; return AC[h] }
function ini(name) { const p=(name||'?').trim().split(' '); return p.length>=2?p[0][0]+p[1][0]:(p[0]||'?').substring(0,2) }
</script>
<template>
  <AppLayout>
    <template #title>{{ $t('menu.departments') }}</template>
    <template #breadcrumb>{{ $t('menu.departments') }}</template>

    <!-- page header -->
    <div class="ph">
      <div>
        <h1 class="pt">{{ $t('dm.title') }}</h1>
        <p class="ps">{{ $t('dm.total', { n: stats?.total ?? 0 }) }}</p>
      </div>
      <button class="btn-add" @click="openAdd" type="button">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        {{ $t('dm.addDept') }}
      </button>
    </div>

    <!-- stat cards -->
    <div class="stats-row">
      <div class="sc sc-b">
        <div class="sc-ico ic-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.total ?? 0 }}</div>
          <div class="sc-l">{{ $t('dm.totalDepts') }}</div>
        </div>
      </div>
      <div class="sc sc-g">
        <div class="sc-ico ic-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.active ?? 0 }}</div>
          <div class="sc-l">{{ $t('dm.activeDepts') }}</div>
        </div>
      </div>
      <div class="sc sc-r">
        <div class="sc-ico ic-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.inactive ?? 0 }}</div>
          <div class="sc-l">{{ $t('dm.inactiveDepts') }}</div>
        </div>
      </div>
      <div class="sc sc-o">
        <div class="sc-ico ic-orange">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.users ?? 0 }}</div>
          <div class="sc-l">{{ $t('dm.totalUsers') }}</div>
        </div>
      </div>
    </div>

    <!-- toolbar -->
    <div class="tlb">
      <div class="srch-w">
        <svg class="srch-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input v-model="search" class="srch-in" type="search" :placeholder="$t('dm.search')" autocomplete="off" />
      </div>
      <div class="tabs">
        <button class="tbtn" :class="{ ton: !filterStatus }" @click="setStatus('')" type="button">
          {{ $t('dm.tabAll') }}<span class="tct">{{ stats?.total ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'active' }" @click="setStatus('active')" type="button">
          <span class="tdot tdg"></span>{{ $t('status.active') }}<span class="tct">{{ stats?.active ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'inactive' }" @click="setStatus('inactive')" type="button">
          <span class="tdot tds"></span>{{ $t('status.inactive') }}<span class="tct">{{ stats?.inactive ?? 0 }}</span>
        </button>
      </div>
      <div class="drops">
        <button v-if="search || filterStatus" class="btn-clr" @click="clearFilters" type="button">×</button>
      </div>
    </div>

    <!-- table card -->
    <div class="card">
      <div class="tw">
        <table class="dt">
          <thead>
            <tr>
              <th class="th-rn">#</th>
              <th class="ths" @click="doSort('code')">{{ $t('dm.colCode') }} <span class="si">{{ sortIcon('code') }}</span></th>
              <th class="ths" @click="doSort('name')">{{ $t('dm.colName') }} <span class="si">{{ sortIcon('name') }}</span></th>
              <th class="th-adm">{{ $t('dm.colAdmins') }}</th>
              <th class="th-num">{{ $t('dm.colUsers') }}</th>
              <th>{{ $t('dm.colStatus') }}</th>
              <th class="th-a"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!departments.data.length">
              <td colspan="7" class="empty-td">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;color:var(--fg-dim);margin-bottom:10px"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4"/></svg>
                <div>{{ $t('dm.noResults') }}</div>
              </td>
            </tr>
            <tr v-for="(d, di) in departments.data" :key="d.id" class="dr">
              <td class="td-rn">{{ (departments.from ?? 1) + di }}</td>
              <td>
                <span class="code-badge" :style="{ background: avc(d.code).bg, color: avc(d.code).color }">{{ d.code }}</span>
              </td>
              <td class="td-u">
                <div class="av" :style="{ background: avc(d.name).bg, color: avc(d.name).color }">{{ ini(d.name) }}</div>
                <div class="un">{{ d.name }}</div>
              </td>
              <td class="td-admins">
                <div v-if="d.admins?.length" class="adm-stack">
                  <div v-for="(a, ai) in d.admins.slice(0, 3)" :key="a.id"
                    class="av av-sm" :style="{ background: avc(a.name).bg, color: avc(a.name).color, zIndex: 10 - ai }">
                    {{ ini(a.name) }}
                  </div>
                  <div v-if="d.admins.length > 3" class="av av-sm av-more">+{{ d.admins.length - 3 }}</div>
                </div>
                <span v-else class="no-adm">—</span>
              </td>
              <td class="td-num">
                <span class="user-count">{{ d.admins.length }}</span>
              </td>
              <td>
                <span class="sb" :class="d.is_active ? 'sb-on' : 'sb-off'">
                  <i class="sd"></i>{{ d.is_active ? $t('status.active') : $t('status.inactive') }}
                </span>
              </td>
              <td class="td-a">
                <button class="mb" @click="openMenu($event, d)" type="button"
                  :class="{ 'mb-open': activeMenu === d.id }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="pb">
        <span class="pi">{{ $t('dm.showing', { from: departments.from ?? 0, to: departments.to ?? 0, total: departments.total }) }}</span>
        <div v-if="departments.last_page > 1" class="pl">
          <Link v-for="lk in departments.links" :key="lk.label" :href="lk.url ?? '#'"
            :class="['pbn', { 'pb-on': lk.active, 'pb-off': !lk.url }]"
            preserve-scroll v-html="lk.label" />
        </div>
      </div>
    </div>

    <!-- action dropdown — teleported to body -->
    <Teleport to="body">
      <div v-if="activeMenu !== null && menuDept" class="md-tp" :style="dropdownStyle" @click.stop>
        <button class="mi" @click="openEdit(menuDept)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
          {{ $t('btn.edit') }}
        </button>
        <button class="mi" @click="openAdminModal(menuDept)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          {{ $t('dm.manageAdmins') }}
        </button>
        <button class="mi" @click="toggleActive(menuDept)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
          {{ menuDept.is_active ? $t('dm.deactivate') : $t('dm.activate') }}
        </button>
        <div class="msep"></div>
        <button class="mi mi-del" @click="deleteDept(menuDept)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          {{ $t('btn.delete') }}
        </button>
      </div>
    </Teleport>

    <!-- modal -->
    <Transition name="modal">
      <div v-if="isOpen" class="mo" @click.self="closeModal">
        <div class="modal">
          <div class="mh">
            <div class="mhl">
              <div class="mico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4"/></svg>
              </div>
              <div>
                <div class="mt2">{{ isEdit ? $t('dm.editDept') : $t('dm.addDept') }}</div>
                <div class="ms2">{{ isEdit ? $t('dm.editDeptSub') : $t('dm.addDeptSub') }}</div>
              </div>
            </div>
            <button class="mcl" @click="closeModal" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="mb2">
            <!-- Code -->
            <div class="fg half">
              <label class="fl">{{ $t('dm.code') }} <span class="req">*</span></label>
              <input v-model="form.code" class="fi" :class="{'fi-e':form.errors.code}" type="text"
                :placeholder="$t('dm.codePh')" autocomplete="off" style="text-transform:uppercase"/>
              <p v-if="form.errors.code" class="fe">{{ form.errors.code }}</p>
            </div>
            <!-- Active toggle -->
            <div class="fg half">
              <label class="fl">{{ $t('dm.isActive') }}</label>
              <div class="trow">
                <button class="tbn" :class="{'ton':form.is_active}" @click="form.is_active=!form.is_active" type="button"><span class="tk"></span></button>
                <span class="tl">{{ form.is_active ? $t('status.active') : $t('status.inactive') }}</span>
              </div>
            </div>
            <!-- Name -->
            <div class="fg full">
              <label class="fl">{{ $t('dm.name') }} <span class="req">*</span></label>
              <input v-model="form.name" class="fi" :class="{'fi-e':form.errors.name}" type="text"
                :placeholder="$t('dm.namePh')" autocomplete="off"/>
              <p v-if="form.errors.name" class="fe">{{ form.errors.name }}</p>
            </div>
          </div>
          <div class="mf">
            <button class="bcn" @click="closeModal" type="button">{{ $t('btn.cancel') }}</button>
            <button class="bsb" @click="submitForm" :disabled="form.processing" type="button">
              <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <svg v-else class="sp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              {{ form.processing ? $t('dm.saving') : $t('btn.save') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- admin modal -->
    <Transition name="modal">
      <div v-if="isAdminModal" class="mo" @click.self="closeAdminModal">
        <div class="modal modal-adm">
          <div class="mh">
            <div class="mhl">
              <div class="mico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div>
                <div class="mt2">{{ $t('dm.adminModalTitle') }}</div>
                <div class="ms2">{{ adminDept?.name }} — {{ $t('dm.adminModalSub') }}</div>
              </div>
            </div>
            <button class="mcl" @click="closeAdminModal" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="adm-body">
            <!-- Current admins -->
            <div class="adm-sec-label">{{ $t('dm.currentAdmins') }}</div>
            <div v-if="!adminDept?.admins?.length" class="adm-empty">{{ $t('dm.noAdmins') }}</div>
            <div v-for="a in adminDept?.admins" :key="a.id" class="adm-item">
              <div class="av av-sm" :style="{ background: avc(a.name).bg, color: avc(a.name).color }">{{ ini(a.name) }}</div>
              <span class="adm-name">{{ a.name }}</span>
              <span class="adm-role-badge" :class="a.role === 'manager_dept' ? 'arb-mgr' : 'arb-adm'">
                {{ a.role === 'manager_dept' ? $t('dm.roleManagerDept') : $t('dm.roleAdminDept') }}
              </span>
              <button class="adm-rm" @click="doRemoveAdmin(a.id)" type="button" title="Remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <!-- Add member -->
            <div class="adm-sec-label" style="margin-top:20px">{{ $t('dm.addAdmin') }}</div>
            <div class="adm-add-row">
              <select v-model="addAdminForm.user_id" class="fi fis adm-sel">
                <option :value="null">{{ $t('dm.selectUser') }}</option>
                <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                  {{ u.name }} ({{ u.employee_id }})
                </option>
              </select>
              <select v-model="addAdminForm.role" class="fi fis adm-role-sel">
                <option value="admin_dept">{{ $t('dm.roleAdminDept') }}</option>
                <option value="manager_dept">{{ $t('dm.roleManagerDept') }}</option>
              </select>
              <button class="bsb adm-add-btn" @click="submitAddAdmin" :disabled="!addAdminForm.user_id || addAdminForm.processing" type="button">
                <svg v-if="!addAdminForm.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                <svg v-else class="sp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                {{ $t('btn.add') }}
              </button>
            </div>
            <p v-if="addAdminForm.errors.user_id" class="fe">{{ addAdminForm.errors.user_id }}</p>
          </div>
          <div class="mf">
            <button class="bcn" @click="closeAdminModal" type="button">{{ $t('btn.close') }}</button>
          </div>
        </div>
      </div>
    </Transition>

  </AppLayout>
</template>
<style scoped>
/* layout */
.ph{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.pt{font-size:22px;font-weight:700;letter-spacing:-.02em;color:var(--fg);margin:0}
.ps{font-size:13px;color:var(--fg-2);margin:4px 0 0}
.btn-add{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9px;border:0;cursor:pointer;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13.5px;font-weight:600;font-family:inherit;box-shadow:0 4px 14px -4px rgba(249,115,22,.55);transition:opacity 180ms ease;white-space:nowrap}
.btn-add:hover{opacity:.88}

/* stat cards */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm);border-left:3px solid transparent}
.sc-b{border-left-color:#3b82f6}.sc-g{border-left-color:#10b981}.sc-r{border-left-color:#ef4444}.sc-o{border-left-color:#f97316}
.sc-ico{width:44px;height:44px;border-radius:11px;display:grid;place-items:center;flex-shrink:0}
.sc-ico svg{width:22px;height:22px}
.ic-blue{background:rgba(59,130,246,.15);color:#60a5fa}
.ic-green{background:rgba(16,185,129,.15);color:#10b981}
.ic-red{background:rgba(239,68,68,.15);color:#ef4444}
.ic-orange{background:rgba(249,115,22,.15);color:#fb923c}
.sc-n{font-size:26px;font-weight:800;letter-spacing:-.03em;color:var(--fg);line-height:1}
.sc-l{font-size:12px;color:var(--fg-2);font-weight:500;margin-top:4px}

/* toolbar */
.tlb{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.srch-w{position:relative;flex:0 0 280px}
.srch-i{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--fg-dim);pointer-events:none}
.srch-in{width:100%;padding:9px 12px 9px 36px;box-sizing:border-box;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--fg);font-size:13px;font-family:inherit;transition:border-color 180ms ease}
.srch-in:focus{outline:none;border-color:var(--blue-500)}.srch-in::placeholder{color:var(--fg-dim)}
.tabs{display:inline-flex;background:var(--surface-2);border:1px solid var(--border);border-radius:9px;padding:3px;gap:2px}
.tbtn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:7px;border:0;background:transparent;color:var(--fg-2);font-size:12.5px;font-weight:600;font-family:inherit;cursor:pointer;transition:all 160ms;white-space:nowrap}
.tbtn:hover{color:var(--fg)}.ton{background:var(--surface);color:var(--fg);box-shadow:0 1px 4px rgba(0,0,0,.25)}
.tdot{width:7px;height:7px;border-radius:50%;flex-shrink:0}.tdg{background:#10b981}.tds{background:var(--fg-dim)}
.tct{font-size:11px;background:var(--surface-3);color:var(--fg-dim);padding:1px 6px;border-radius:999px;font-weight:700}
.drops{display:flex;align-items:center;gap:8px;margin-left:auto}
.btn-clr{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--fg-2);cursor:pointer;font-size:18px;transition:all 160ms}.btn-clr:hover{background:var(--hover);color:var(--fg)}

/* table */
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow-sm)}
.tw{overflow-x:auto}
.dt{width:100%;border-collapse:collapse;font-size:13.5px}
.dt th{text-align:left;padding:12px 16px;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--fg-dim);border-bottom:1px solid var(--border);background:var(--surface-2);white-space:nowrap}
.th-rn{width:50px;text-align:center;padding-left:8px;padding-right:8px}.th-a{width:52px}.th-num{width:80px;text-align:center}.ths{cursor:pointer;user-select:none}.ths:hover{color:var(--fg-2)}
.si{font-size:10px;opacity:.6;margin-left:4px}
.dt td{padding:13px 16px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
.dt tbody tr:last-child td{border-bottom:0}.dr{transition:background 120ms}.dr:hover td{background:var(--hover)}
.td-rn{text-align:center;color:var(--fg-dim);font-size:12px;font-weight:500;padding-left:8px;padding-right:8px}
.tdd{color:var(--fg-2);font-size:13px}
.td-u{display:flex;align-items:center;gap:12px}
.td-num{text-align:center}
.av{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;letter-spacing:.02em;flex-shrink:0;text-transform:uppercase}
.un{font-weight:600;color:var(--fg);font-size:13.5px}
.code-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:6px;font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}
.user-count{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:22px;padding:0 7px;background:rgba(59,130,246,.12);color:#60a5fa;border-radius:6px;font-size:12px;font-weight:700}
.sb{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500}
.sb-on{color:var(--emerald)}.sb-off{color:var(--fg-dim)}
.sd{width:7px;height:7px;border-radius:50%;background:currentColor;flex-shrink:0}
.empty-td{text-align:center;padding:60px 16px;color:var(--fg-2);display:flex;flex-direction:column;align-items:center}

/* action menu */
.td-a{text-align:center;width:52px}
.mb{appearance:none;border:1px solid transparent;background:transparent;width:30px;height:30px;border-radius:7px;cursor:pointer;color:var(--fg-dim);display:grid;place-items:center;transition:all 160ms;margin:0 auto}
.mb:hover,.mb-open{background:var(--hover);border-color:var(--border);color:var(--fg)}
.md-tp{position:fixed;min-width:168px;background:#1e2535;border:1px solid #2a3550;border-radius:10px;padding:6px;z-index:9999;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04)}
.mi{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:12.5px;color:#94a3b8;background:transparent;border:0;width:100%;text-align:left;font-family:inherit;transition:all 160ms}
.mi:hover{background:rgba(59,130,246,.08);color:#e6edf7}.mi-del{color:#ef4444}.mi-del:hover{background:rgba(239,68,68,.1);color:#ef4444}
.msep{height:1px;background:#1e2535;margin:4px 0}

/* pagination */
.pb{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px}
.pi{font-size:12.5px;color:var(--fg-2)}.pl{display:flex;gap:4px;flex-wrap:wrap}
.pbn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;font-weight:500;color:var(--fg-2);text-decoration:none;cursor:pointer;transition:all 160ms}
.pbn:hover:not(.pb-off):not(.pb-on){background:var(--hover);color:var(--fg)}
.pb-on{background:var(--orange-500);border-color:var(--orange-500);color:#fff;font-weight:700}.pb-off{opacity:.35;cursor:default;pointer-events:none}

/* modal — always dark */
.mo{position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.72);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:24px;--surface:#0d1117;--surface-2:#111827;--surface-3:#1e2535;--border:#1e2535;--border-2:#2a3550;--fg:#e6edf7;--fg-2:#94a3b8;--fg-dim:#64748b;--hover:rgba(59,130,246,.08);--emerald:#10b981;--rose:#ef4444;--blue-500:#3b82f6;--orange-400:#fb923c;--orange-500:#f97316}
.modal{background:#0d1117;border:1px solid #2a3550;border-radius:16px;width:100%;max-width:520px;box-shadow:0 32px 80px rgba(0,0,0,.65),0 0 0 1px rgba(255,255,255,.04);display:flex;flex-direction:column;max-height:90vh;overflow:visible}
.mh{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #1e2535;flex-shrink:0;background:#0d1117;border-radius:16px 16px 0 0}
.mhl{display:flex;align-items:center;gap:14px}
.mico{width:40px;height:40px;border-radius:10px;flex-shrink:0;background:rgba(249,115,22,.12);color:var(--orange-400);display:grid;place-items:center}
.mico svg{width:20px;height:20px}
.mt2{font-size:16px;font-weight:700;color:var(--fg)}.ms2{font-size:12px;color:var(--fg-2);margin-top:3px}
.mcl{appearance:none;border:1px solid var(--border);background:transparent;width:32px;height:32px;border-radius:8px;cursor:pointer;color:var(--fg-2);display:grid;place-items:center;transition:all 160ms}.mcl:hover{background:var(--hover);color:var(--fg)}
.mb2{overflow-y:auto;overflow-x:visible;padding:24px;flex:1;display:grid;grid-template-columns:1fr 1fr;gap:18px 16px}
.fg{display:flex;flex-direction:column;gap:6px}.fg.full{grid-column:1/-1}.fg.half{grid-column:span 1}
.fl{font-size:11.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--fg-2);display:flex;align-items:center;gap:5px}
.req{color:var(--rose)}
.fi{padding:10px 12px;background:var(--surface-2);border:1px solid var(--border);border-radius:9px;color:var(--fg);font-size:13.5px;font-family:inherit;transition:border-color 180ms ease,box-shadow 180ms ease}
.fi:focus{outline:none;border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(59,130,246,.12)}.fi-e{border-color:var(--rose)}
.fis{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:32px;cursor:pointer}
.fe{font-size:12px;color:var(--rose)}

/* toggle */
.trow{display:flex;align-items:center;gap:10px;height:44px}
.tbn{position:relative;width:44px;height:24px;border-radius:12px;border:0;background:rgba(100,116,139,.2);cursor:pointer;transition:background 200ms ease;flex-shrink:0}
.tbn.ton{background:var(--orange-500)}
.tk{position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.3);transition:transform 200ms ease;pointer-events:none}
.tbn.ton .tk{transform:translateX(20px)}
.tl{font-size:13px;color:var(--fg-2)}

/* modal footer */
.mf{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #1e2535;flex-shrink:0;background:#0d1117;border-radius:0 0 16px 16px}
.bcn{padding:9px 18px;border-radius:9px;border:1px solid #2a3550;background:transparent;color:#94a3b8;font-size:13.5px;font-family:inherit;cursor:pointer;transition:all 160ms}.bcn:hover{background:rgba(255,255,255,.05);color:#e6edf7}
.bsb{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:9px;border:0;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13.5px;font-weight:600;font-family:inherit;cursor:pointer;box-shadow:0 4px 14px -4px rgba(249,115,22,.55);transition:opacity 180ms ease}.bsb:hover{opacity:.88}.bsb:disabled{opacity:.45;cursor:not-allowed}
.sp{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* admin modal */
.modal-adm{max-width:460px}
.adm-body{padding:20px 24px;display:flex;flex-direction:column;gap:6px;overflow-y:auto;max-height:400px}
.adm-sec-label{font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--fg-dim);margin-bottom:2px}
.adm-empty{font-size:13px;color:var(--fg-dim);padding:10px 0}
.adm-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;background:rgba(59,130,246,.05);border:1px solid var(--border)}
.adm-name{flex:1;font-size:13.5px;font-weight:600;color:var(--fg)}
.adm-rm{appearance:none;border:0;background:transparent;padding:4px;cursor:pointer;color:var(--fg-dim);display:grid;place-items:center;border-radius:5px;transition:all 160ms}.adm-rm:hover{background:rgba(239,68,68,.12);color:#ef4444}
.adm-add-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.adm-sel{flex:2;min-width:0}
.adm-role-sel{flex:1;min-width:120px}
.adm-add-btn{padding:9px 16px;white-space:nowrap;flex-shrink:0}
.adm-role-badge{font-size:10.5px;font-weight:700;letter-spacing:.04em;padding:2px 7px;border-radius:5px;white-space:nowrap;flex-shrink:0}
.arb-adm{background:rgba(59,130,246,.15);color:#60a5fa}
.arb-mgr{background:rgba(139,92,246,.15);color:#a78bfa}

/* table admin column */
.th-adm{width:120px}
.td-admins{vertical-align:middle}
.adm-stack{display:flex;align-items:center}
.av-sm{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:9.5px;font-weight:800;letter-spacing:.02em;text-transform:uppercase;flex-shrink:0;margin-left:-6px;border:2px solid var(--surface);box-sizing:border-box}
.adm-stack .av-sm:first-child{margin-left:0}
.av-more{background:var(--surface-3);color:var(--fg-2);font-size:9.5px;border-color:var(--surface)}
.no-adm{color:var(--fg-dim);font-size:13px}

/* modal transition */
.modal-enter-active,.modal-leave-active{transition:all 200ms cubic-bezier(.22,1,.36,1)}
.modal-enter-from,.modal-leave-to{opacity:0}.modal-enter-from .modal{transform:scale(.96) translateY(10px)}.modal-leave-to .modal{transform:scale(.96) translateY(10px)}
</style>
