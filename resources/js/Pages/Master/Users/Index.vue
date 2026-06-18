<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, useForm, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()
const page = usePage()
const currentUser = computed(() => page.props.auth?.user ?? {})
const isSuperAdmin = computed(() => currentUser.value.role === 'super_admin')

const props = defineProps({
    users: Object, departments: Array, filters: Object, stats: Object,
})

const search       = ref(props.filters?.search        ?? '')
const filterRole   = ref(props.filters?.role          ?? '')
const filterDept   = ref(props.filters?.department_id ?? '')
const filterStatus = ref(props.filters?.status        ?? '')
const sortCol      = ref(props.filters?.sort          ?? '')
const sortDir      = ref(props.filters?.dir           ?? 'asc')

function params() {
    return {
        search:        search.value       || undefined,
        role:          filterRole.value   || undefined,
        department_id: filterDept.value   || undefined,
        status:        filterStatus.value || undefined,
        sort:          sortCol.value      || undefined,
        dir:           sortCol.value ? sortDir.value : undefined,
    }
}
const go = (extra = {}) =>
    router.get(route('users.index'), { ...params(), ...extra }, { preserveState: true, preserveScroll: true, replace: true })

function applyFilters() { go() }
function setStatus(v)   { filterStatus.value = v; go() }
function clearFilters() {
    search.value = ''; filterRole.value = ''; filterDept.value = ''; filterStatus.value = ''
    router.get(route('users.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}
let st = null
watch(search, () => { clearTimeout(st); st = setTimeout(applyFilters, 350) })
watch([filterRole, filterDept], applyFilters)

function doSort(col) {
    if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    else { sortCol.value = col; sortDir.value = 'asc' }
    go()
}
function sortIcon(col) { return sortCol.value === col ? (sortDir.value === 'asc' ? '↑' : '↓') : '↕' }

const activeMenu   = ref(null)
const menuUser     = ref(null)
const dropdownStyle = ref({})

function openMenu(event, user) {
    event.stopPropagation()
    if (activeMenu.value === user.id) { closeMenu(); return }
    const rect = event.currentTarget.getBoundingClientRect()
    const dropdownH  = 180
    const spaceBelow = window.innerHeight - rect.bottom
    if (spaceBelow < dropdownH + 12) {
        dropdownStyle.value = {
            bottom: (window.innerHeight - rect.top + 6) + 'px',
            right:  (window.innerWidth - rect.right) + 'px',
            top:    'auto',
        }
    } else {
        dropdownStyle.value = {
            top:    (rect.bottom + 6) + 'px',
            right:  (window.innerWidth - rect.right) + 'px',
            bottom: 'auto',
        }
    }
    activeMenu.value = user.id
    menuUser.value   = user
}
function closeMenu() { activeMenu.value = null; menuUser.value = null }
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

const isOpen = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = useForm({ name: '', employee_id: '', email: '', password: '', role: 'user', extra_roles: [], is_active: true })

function openAdd() {
    isEdit.value = false; editingId.value = null
    form.reset(); form.role = 'user'; form.extra_roles = []; form.is_active = true
    isOpen.value = true; closeMenu()
}
function openEdit(user) {
    isEdit.value = true; editingId.value = user.id
    Object.assign(form, { name: user.name, employee_id: user.employee_id ?? '', email: user.email ?? '',
        password: '', role: user.role, extra_roles: user.extra_roles ?? [], is_active: user.is_active })
    form.clearErrors(); isOpen.value = true; closeMenu()
}
function closeModal() { isOpen.value = false; form.reset(); form.clearErrors() }
function submitForm() {
    isEdit.value
        ? form.put(route('users.update', editingId.value), { onSuccess: closeModal })
        : form.post(route('users.store'), { onSuccess: closeModal })
}
function deleteUser(u) {
    closeMenu()
    if (!confirm(`${t('um.confirmDelete')} "${u.name}"?`)) return
    router.delete(route('users.destroy', u.id), { preserveScroll: true })
}
function toggleActive(u) {
    closeMenu()
    router.put(route('users.update', u.id), {
        name: u.name, employee_id: u.employee_id, email: u.email, password: '', role: u.role,
        is_active: !u.is_active,
    }, { preserveScroll: true })
}
function impersonateUser(u) {
    closeMenu()
    router.post(route('users.impersonate', u.id))
}

// Roles shown in create/edit dropdown (admin_dept/manager_dept set via Departments only)
const ROLES = [
    'super_admin',
    'procurement_admin',
    'wh_admin',
    'wh_manager',
    'wh_supervisor',
    'operator',
    'user',
]
const RC = {
    super_admin:        { bg:'rgba(249,115,22,.15)',  color:'#fb923c', lbl:'SUPER ADMIN'   },
    procurement_admin:  { bg:'rgba(14,165,233,.15)',  color:'#38bdf8', lbl:'PROC ADMIN'    },
    wh_admin:           { bg:'rgba(16,185,129,.15)',  color:'#34d399', lbl:'WH ADMIN'      },
    admin_dept:         { bg:'rgba(59,130,246,.15)',  color:'#60a5fa', lbl:'DEPT ADMIN'    },
    manager_dept:       { bg:'rgba(139,92,246,.15)',  color:'#a78bfa', lbl:'DEPT MANAGER'  },
    wh_manager:         { bg:'rgba(249,115,22,.15)',  color:'#fb923c', lbl:'WH MANAGER'    },
    wh_supervisor:      { bg:'rgba(234,179,8,.15)',   color:'#facc15', lbl:'WH SUPERVISOR' },
    operator:           { bg:'rgba(100,116,139,.12)', color:'#94a3b8', lbl:'OPERATOR'      },
    user:               { bg:'rgba(100,116,139,.10)', color:'#64748b', lbl:'USER'          },
}
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
    <template #title>{{ $t('menu.users') }}</template>
    <template #breadcrumb>{{ $t('menu.users') }}</template>

    <!-- page header -->
    <div class="ph">
      <div>
        <h1 class="pt">{{ $t('um.title') }}</h1>
        <p class="ps">{{ $t('um.total', { n: stats?.total ?? 0 }) }}</p>
      </div>
      <button class="btn-add" @click="openAdd" type="button">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        {{ $t('um.addUser') }}
      </button>
    </div>

    <!-- stat cards -->
    <div class="stats-row">
      <div class="sc sc-b">
        <div class="sc-ico ic-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.total ?? 0 }}</div>
          <div class="sc-l">{{ $t('um.totalUsers') }}</div>
        </div>
      </div>
      <div class="sc sc-g">
        <div class="sc-ico ic-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.active ?? 0 }}</div>
          <div class="sc-l">{{ $t('um.activeUsers') }}</div>
        </div>
      </div>
      <div class="sc sc-r">
        <div class="sc-ico ic-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.inactive ?? 0 }}</div>
          <div class="sc-l">{{ $t('um.inactiveUsers') }}</div>
        </div>
      </div>
      <div class="sc sc-p">
        <div class="sc-ico ic-purple">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4"/></svg>
        </div>
        <div>
          <div class="sc-n">{{ stats?.departments ?? 0 }}</div>
          <div class="sc-l">{{ $t('menu.departments') }}</div>
        </div>
      </div>
    </div>

    <!-- toolbar -->
    <div class="tlb">
      <div class="srch-w">
        <svg class="srch-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input v-model="search" class="srch-in" type="search" :placeholder="$t('um.search')" autocomplete="off" />
      </div>
      <div class="tabs">
        <button class="tbtn" :class="{ ton: !filterStatus }" @click="setStatus('')" type="button">
          {{ $t('um.tabAll') }}<span class="tct">{{ stats?.total ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'active' }" @click="setStatus('active')" type="button">
          <span class="tdot tdg"></span>{{ $t('status.active') }}<span class="tct">{{ stats?.active ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'inactive' }" @click="setStatus('inactive')" type="button">
          <span class="tdot tds"></span>{{ $t('status.inactive') }}<span class="tct">{{ stats?.inactive ?? 0 }}</span>
        </button>
      </div>
      <div class="drops">
        <select v-model="filterRole" class="fsel">
          <option value="">{{ $t('um.filterRole') }}</option>
          <option v-for="r in ROLES" :key="r" :value="r">{{ $t(`role.${r}`) }}</option>
        </select>
        <select v-model="filterDept" class="fsel">
          <option value="">{{ $t('um.filterDept') }}</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <button v-if="search||filterRole||filterDept||filterStatus" class="btn-clr" @click="clearFilters" type="button">×</button>
      </div>
    </div>
    <!-- table card -->
    <div class="card">
      <div class="tw">
        <table class="dt">
          <thead>
            <tr>
              <th class="th-rn">#</th>
              <th class="ths" @click="doSort('name')">{{ $t('um.colName') }} <span class="si">{{ sortIcon('name') }}</span></th>
              <th class="ths" @click="doSort('role')">{{ $t('um.colRole') }} <span class="si">{{ sortIcon('role') }}</span></th>
              <th>{{ $t('um.colDept') }}</th>
              <th>{{ $t('um.colStatus') }}</th>
              <th>{{ $t('um.colLastLogin') }}</th>
              <th class="th-a"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!users.data.length">
              <td colspan="7" class="empty-td" style="text-align:center;padding:60px 16px;color:var(--fg-2);display:flex;flex-direction:column;align-items:center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;color:var(--fg-dim);margin-bottom:10px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <div>{{ $t('um.noResults') }}</div>
              </td>
            </tr>
            <tr v-for="(u, ui) in users.data" :key="u.id" class="dr">
              <td class="td-rn">{{ (users.from ?? 1) + ui }}</td>
              <td class="td-u">
                <div class="av" :style="{ background: avc(u.name).bg, color: avc(u.name).color }">{{ ini(u.name) }}</div>
                <div><div class="un">{{ u.name }}</div><div class="ue">{{ u.email }}</div></div>
              </td>
              <td><span class="rb" :style="{ background: RC[u.role]?.bg, color: RC[u.role]?.color }">{{ RC[u.role]?.lbl }}</span></td>
              <td class="tdd">
                <template v-if="u.role === 'super_admin'">
                  <span class="sys-dept">System Management</span>
                </template>
                <template v-else>{{ u.department ?? '—' }}</template>
              </td>
              <td>
                <span class="sb" :class="u.is_active ? 'sb-on' : 'sb-off'">
                  <i class="sd"></i>{{ u.is_active ? $t('status.active') : $t('status.inactive') }}
                </span>
              </td>
              <td class="tdd tdsm">{{ u.last_login_at ?? $t('um.never') }}</td>
              <td class="td-a">
                <div v-if="u.role === 'super_admin'" class="mb-lock" title="Protected account">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <button v-else class="mb" @click="openMenu($event, u)" type="button"
                  :class="{ 'mb-open': activeMenu === u.id }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="pb">
        <span class="pi">{{ $t('um.showing', { from: users.from ?? 0, to: users.to ?? 0, total: users.total }) }}</span>
        <div v-if="users.last_page > 1" class="pl">
          <Link v-for="lk in users.links" :key="lk.label" :href="lk.url ?? '#'"
            :class="['pbn', { 'pb-on': lk.active, 'pb-off': !lk.url }]"
            preserve-scroll v-html="lk.label" />
        </div>
      </div>
    </div>
    <!-- action dropdown — teleported to body to escape table overflow clip -->
    <Teleport to="body">
      <div v-if="activeMenu !== null && menuUser" class="md-tp" :style="dropdownStyle" @click.stop>
        <button class="mi" @click="openEdit(menuUser)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
          {{ $t('btn.edit') }}
        </button>
        <button class="mi" @click="toggleActive(menuUser)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
          {{ menuUser.is_active ? $t('um.deactivate') : $t('um.activate') }}
        </button>
        <template v-if="isSuperAdmin && menuUser.id !== currentUser.id">
          <div class="msep"></div>
          <button class="mi mi-imp" @click="impersonateUser(menuUser)" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Impersonate
          </button>
        </template>
        <div class="msep"></div>
        <button class="mi mi-del" @click="deleteUser(menuUser)" type="button">
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <div>
                <div class="mt2">{{ isEdit ? $t('um.editUser') : $t('um.addUser') }}</div>
                <div class="ms2">{{ isEdit ? $t('um.editUserSub') : $t('um.addUserSub') }}</div>
              </div>
            </div>
            <button class="mcl" @click="closeModal" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="mb2">
            <!-- Full Name -->
            <div class="fg full">
              <label class="fl">{{ $t('um.name') }} <span class="req">*</span></label>
              <input v-model="form.name" class="fi" :class="{'fi-e':form.errors.name}" type="text" :placeholder="$t('um.namePh')" autocomplete="off"/>
              <p v-if="form.errors.name" class="fe">{{ form.errors.name }}</p>
            </div>
            <!-- Employee ID -->
            <div class="fg full">
              <label class="fl">{{ $t('um.employeeId') }} <span class="req">*</span></label>
              <input v-model="form.employee_id" class="fi" :class="{'fi-e':form.errors.employee_id}" type="text" :placeholder="$t('um.employeeIdPh')" autocomplete="off"/>
              <p v-if="form.errors.employee_id" class="fe">{{ form.errors.employee_id }}</p>
            </div>
            <!-- Email (optional) -->
            <div class="fg full">
              <label class="fl">{{ $t('um.email') }} <span class="fopt">({{ $t('um.optional') }})</span></label>
              <input v-model="form.email" class="fi" :class="{'fi-e':form.errors.email}" type="email" placeholder="user@wbn.co.id" autocomplete="off"/>
              <p v-if="form.errors.email" class="fe">{{ form.errors.email }}</p>
              <p v-else class="fhint">{{ $t('um.emailHint') }}</p>
            </div>
            <!-- Password -->
            <div class="fg full">
              <label class="fl">{{ $t('um.password') }} <span v-if="!isEdit" class="req">*</span><span v-else class="fopt">({{ $t('um.optional') }})</span></label>
              <input v-model="form.password" class="fi" :class="{'fi-e':form.errors.password}" type="password" :placeholder="isEdit?$t('um.passwordHint'):''" autocomplete="new-password"/>
              <p v-if="form.errors.password" class="fe">{{ form.errors.password }}</p>
            </div>
            <!-- Role -->
            <div class="fg half">
              <label class="fl">{{ $t('um.role') }} <span class="req">*</span></label>
              <select v-model="form.role" class="fi fis" :class="{'fi-e':form.errors.role}">
                <option v-for="r in ROLES" :key="r" :value="r">{{ $t(`role.${r}`) }}</option>
              </select>
              <p v-if="form.errors.role" class="fe">{{ form.errors.role }}</p>
            </div>
            <!-- Extra roles (operator access) -->
            <div class="fg full" v-if="form.role !== 'operator'">
              <label class="fl">{{ $t('um.extraRoles') }}</label>
              <div class="extra-roles-wrap">
                <label class="extra-role-check">
                  <input type="checkbox" value="operator"
                    :checked="(form.extra_roles ?? []).includes('operator')"
                    @change="e => { const r = form.extra_roles ?? []; e.target.checked ? form.extra_roles = [...r, 'operator'] : form.extra_roles = r.filter(x => x !== 'operator') }" />
                  <span>📱 Operator Portal Access</span>
                  <small>Can access mobile Scan & Pickup page</small>
                </label>
              </div>
            </div>
            <!-- Active toggle -->
            <div class="fg half">
              <label class="fl">{{ $t('um.isActive') }}</label>
              <div class="trow">
                <button class="tbn" :class="{'ton':form.is_active}" @click="form.is_active=!form.is_active" type="button"><span class="tk"></span></button>
                <span class="tl">{{ form.is_active ? $t('status.active') : $t('status.inactive') }}</span>
              </div>
            </div>
          </div>
          <div class="mf">
            <button class="bcn" @click="closeModal" type="button">{{ $t('btn.cancel') }}</button>
            <button class="bsb" @click="submitForm" :disabled="form.processing" type="button">
              <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <svg v-else class="sp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              {{ form.processing ? $t('um.saving') : $t('btn.save') }}
            </button>
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
.sc-b{border-left-color:#3b82f6}.sc-g{border-left-color:#10b981}.sc-r{border-left-color:#ef4444}.sc-p{border-left-color:#8b5cf6}
.sc-ico{width:44px;height:44px;border-radius:11px;display:grid;place-items:center;flex-shrink:0}
.sc-ico svg{width:22px;height:22px}
.ic-blue{background:rgba(59,130,246,.15);color:#60a5fa}
.ic-green{background:rgba(16,185,129,.15);color:#10b981}
.ic-red{background:rgba(239,68,68,.15);color:#ef4444}
.ic-purple{background:rgba(139,92,246,.15);color:#8b5cf6}
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
.fsel{padding:9px 30px 9px 12px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--fg);font-size:13px;font-family:inherit;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;transition:border-color 180ms ease}
.fsel:focus{outline:none;border-color:var(--blue-500)}
.btn-clr{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--fg-2);cursor:pointer;font-size:18px;transition:all 160ms}.btn-clr:hover{background:var(--hover);color:var(--fg)}

/* table */
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow-sm)}
.tw{overflow-x:auto}
.dt{width:100%;border-collapse:collapse;font-size:13.5px}
.dt th{text-align:left;padding:12px 16px;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--fg-dim);border-bottom:1px solid var(--border);background:var(--surface-2);white-space:nowrap}
.th-rn{width:50px;text-align:center;padding-left:8px;padding-right:8px}.th-a{width:52px}.ths{cursor:pointer;user-select:none}.ths:hover{color:var(--fg-2)}
.si{font-size:10px;opacity:.6;margin-left:4px}
.dt td{padding:13px 16px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
.dt tbody tr:last-child td{border-bottom:0}.dr{transition:background 120ms}.dr:hover td{background:var(--hover)}
.td-rn{text-align:center;color:var(--fg-dim);font-size:12px;font-weight:500;padding-left:8px;padding-right:8px}.tdd{color:var(--fg-2);font-size:13px}.tdsm{font-size:12.5px;white-space:nowrap}
.td-u{display:flex;align-items:center;gap:12px}
.av{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;letter-spacing:.02em;flex-shrink:0;text-transform:uppercase}
.un{font-weight:600;color:var(--fg);font-size:13.5px}.ue{font-size:12px;color:var(--fg-2);margin-top:2px}
.rb{display:inline-flex;align-items:center;padding:3px 8px;border-radius:5px;font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;white-space:nowrap}
.sb{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500}
.sb-on{color:var(--emerald)}.sb-off{color:var(--fg-dim)}
.sd{width:7px;height:7px;border-radius:50%;background:currentColor;flex-shrink:0}
.empty-td{text-align:center;padding:60px 16px;color:var(--fg-2);display:flex;flex-direction:column;align-items:center}

/* action menu */
.td-a{text-align:center;width:52px}
.mb{appearance:none;border:1px solid transparent;background:transparent;width:30px;height:30px;border-radius:7px;cursor:pointer;color:var(--fg-dim);display:grid;place-items:center;transition:all 160ms;margin:0 auto}
.mb:hover,.mb-open{background:var(--hover);border-color:var(--border);color:var(--fg)}
.mb-lock{width:30px;height:30px;border-radius:7px;display:grid;place-items:center;margin:0 auto;color:var(--fg-dim);opacity:.4;cursor:default}
.sys-dept{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#fb923c;background:rgba(249,115,22,.1);padding:2px 8px;border-radius:5px;white-space:nowrap}
/* teleported dropdown — rendered in <body>, always on top */
.md-tp{position:fixed;min-width:168px;background:#1e2535;border:1px solid #2a3550;border-radius:10px;padding:6px;z-index:9999;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04)}
.mi{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:12.5px;color:#94a3b8;background:transparent;border:0;width:100%;text-align:left;font-family:inherit;transition:all 160ms}
.mi:hover{background:rgba(59,130,246,.08);color:#e6edf7}.mi-del{color:#ef4444}.mi-del:hover{background:rgba(239,68,68,.1);color:#ef4444}
.mi-imp{color:#f59e0b}.mi-imp:hover{background:rgba(245,158,11,.1);color:#f59e0b}
.msep{height:1px;background:#1e2535;margin:4px 0}

/* pagination */
.pb{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px}
.pi{font-size:12.5px;color:var(--fg-2)}.pl{display:flex;gap:4px;flex-wrap:wrap}
.pbn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;font-weight:500;color:var(--fg-2);text-decoration:none;cursor:pointer;transition:all 160ms}
.pbn:hover:not(.pb-off):not(.pb-on){background:var(--hover);color:var(--fg)}
.pb-on{background:var(--orange-500);border-color:var(--orange-500);color:#fff;font-weight:700}.pb-off{opacity:.35;cursor:default;pointer-events:none}

/* modal */
.mo{
  position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
  display:flex;align-items:center;justify-content:center;padding:24px;
}
.modal{
  background:var(--surface);border:1px solid var(--border-2);
  border-radius:16px;width:100%;max-width:560px;
  box-shadow:var(--shadow-lg);
  display:flex;flex-direction:column;max-height:90vh;overflow:visible;
}
.mh{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--surface);border-radius:16px 16px 0 0}
.mhl{display:flex;align-items:center;gap:14px}
.mico{width:40px;height:40px;border-radius:10px;flex-shrink:0;background:rgba(249,115,22,.12);color:var(--orange-400);display:grid;place-items:center}
.mico svg{width:20px;height:20px}
.mt2{font-size:16px;font-weight:700;color:var(--fg)}.ms2{font-size:12px;color:var(--fg-2);margin-top:3px}
.mcl{appearance:none;border:1px solid var(--border);background:transparent;width:32px;height:32px;border-radius:8px;cursor:pointer;color:var(--fg-2);display:grid;place-items:center;transition:all 160ms}.mcl:hover{background:var(--hover);color:var(--fg)}
.mb2{overflow-y:auto;overflow-x:visible;padding:24px;flex:1;display:grid;grid-template-columns:1fr 1fr;gap:18px 16px}
.fhint{font-size:11.5px;color:var(--fg-dim);margin-top:3px}
.fg{display:flex;flex-direction:column;gap:6px}.fg.full{grid-column:1/-1}.fg.half{grid-column:span 1}
.fl{font-size:11.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--fg-2);display:flex;align-items:center;gap:5px}
.req{color:var(--rose)}.fopt{font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;color:var(--fg-dim)}
.fi{padding:10px 12px;background:var(--surface-2);border:1px solid var(--border);border-radius:9px;color:var(--fg);font-size:13.5px;font-family:inherit;transition:border-color 180ms ease,box-shadow 180ms ease}
.fi:focus{outline:none;border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(59,130,246,.12)}.fi-e{border-color:var(--rose)}
.fe{font-size:12px;color:var(--rose)}
.fis{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:34px;width:100%}
.trow{display:flex;align-items:center;gap:10px;padding:8px 0}
.tbn{appearance:none;border:0;cursor:pointer;padding:0;flex-shrink:0;width:44px;height:24px;border-radius:999px;background:var(--border-2);position:relative;transition:background 200ms}.ton{background:var(--emerald)}
.tk{position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:999px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.3);transition:transform 200ms;pointer-events:none}.ton .tk{transform:translateX(20px)}
.tl{font-size:13px;font-weight:500;color:var(--fg-2)}
.mf{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid var(--border);flex-shrink:0;background:var(--surface);border-radius:0 0 16px 16px}
.bcn{display:inline-flex;align-items:center;padding:10px 18px;border-radius:8px;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--fg-2);font-size:13px;font-weight:500;font-family:inherit;transition:all 160ms}.bcn:hover{background:var(--hover);color:var(--fg)}
.bsb{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:8px;border:0;cursor:pointer;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13px;font-weight:600;font-family:inherit;box-shadow:0 4px 12px -4px rgba(249,115,22,.5);transition:opacity 160ms}.bsb:hover:not(:disabled){opacity:.9}.bsb:disabled{opacity:.5;cursor:not-allowed}
@keyframes spin{to{transform:rotate(360deg)}}.sp{animation:spin .8s linear infinite}

/* modal transition */
.modal-enter-active{transition:opacity 200ms ease}.modal-leave-active{transition:opacity 180ms ease}
.modal-enter-from,.modal-leave-to{opacity:0}
.modal-enter-active .modal{animation:mIn 260ms cubic-bezier(.34,1.3,.64,1) forwards}
.modal-leave-active .modal{animation:mOut 160ms ease forwards}
@keyframes mIn{from{transform:translateY(-16px) scale(.96);opacity:0}to{transform:none;opacity:1}}
@keyframes mOut{from{transform:none;opacity:1}to{transform:translateY(-10px) scale(.97);opacity:0}}

@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
.extra-roles-wrap{display:flex;flex-direction:column;gap:6px}
.extra-role-check{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:border-color 150ms,background 150ms}
.extra-role-check:hover{border-color:var(--orange-500);background:rgba(249,115,22,.05)}
.extra-role-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--orange-500);cursor:pointer;flex-shrink:0}
.extra-role-check span{font-size:13px;font-weight:600;color:var(--fg);flex:1}
.extra-role-check small{font-size:11px;color:var(--fg-2)}
@media(max-width:640px){
  .stats-row{grid-template-columns:1fr 1fr}
  .tlb{gap:8px}.srch-w{flex:1 1 100%}
  .mo{padding:0;align-items:flex-end}.modal{border-radius:16px 16px 0 0;max-height:92vh;max-width:100%}
  .mb2{grid-template-columns:1fr}.fg.half{grid-column:1/-1}
  .modal-enter-active .modal{animation:mSlide 280ms cubic-bezier(.34,1.2,.64,1) forwards}
  .modal-leave-active .modal{animation:mSlideOut 160ms ease forwards}
  @keyframes mSlide{from{transform:translateY(100%)}to{transform:none}}
  @keyframes mSlideOut{from{transform:none}to{transform:translateY(100%)}}
}
</style>