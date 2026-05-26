<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
    locations: Object, warehouses: Array, filters: Object, stats: Object,
})

const search       = ref(props.filters?.search       ?? '')
const filterWh     = ref(props.filters?.warehouse_id ?? '')
const filterStatus = ref(props.filters?.status       ?? '')
const sortCol      = ref(props.filters?.sort         ?? '')
const sortDir      = ref(props.filters?.dir          ?? 'asc')

function params() {
    return {
        search:       search.value       || undefined,
        warehouse_id: filterWh.value     || undefined,
        status:       filterStatus.value || undefined,
        sort:         sortCol.value      || undefined,
        dir:          sortCol.value ? sortDir.value : undefined,
    }
}
const go = (extra = {}) =>
    router.get(route('locations.index'), { ...params(), ...extra }, { preserveState: true, preserveScroll: true, replace: true })

function setStatus(v) { filterStatus.value = v; go() }
function clearFilters() {
    search.value = ''; filterWh.value = ''; filterStatus.value = ''
    router.get(route('locations.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
}
let st = null
watch(search, () => { clearTimeout(st); st = setTimeout(() => go(), 350) })
watch(filterWh, () => go())

function doSort(col) {
    if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    else { sortCol.value = col; sortDir.value = 'asc' }
    go()
}
function sortIcon(col) { return sortCol.value === col ? (sortDir.value === 'asc' ? '↑' : '↓') : '↕' }

// action dropdown
const activeMenu    = ref(null)
const menuLoc       = ref(null)
const dropdownStyle = ref({})

function openMenu(event, loc) {
    event.stopPropagation()
    if (activeMenu.value === loc.id) { closeMenu(); return }
    const rect = event.currentTarget.getBoundingClientRect()
    dropdownStyle.value = {
        top:   (rect.bottom + 6) + 'px',
        right: (window.innerWidth - rect.right) + 'px',
    }
    activeMenu.value = loc.id
    menuLoc.value    = loc
}
function closeMenu() { activeMenu.value = null; menuLoc.value = null }
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
const form = useForm({ warehouse_id: '', code: '', name: '', description: '', is_active: true })

function openAdd() {
    isEdit.value = false; editingId.value = null
    form.reset(); form.is_active = true
    if (filterWh.value) form.warehouse_id = filterWh.value
    isOpen.value = true; closeMenu()
}
function openEdit(loc) {
    isEdit.value = true; editingId.value = loc.id
    Object.assign(form, {
        warehouse_id: loc.warehouse_id,
        code: loc.code,
        name: loc.name,
        description: loc.description ?? '',
        is_active: loc.is_active,
    })
    form.clearErrors(); isOpen.value = true; closeMenu()
}
function closeModal() { isOpen.value = false; form.reset(); form.clearErrors() }
function submitForm() {
    isEdit.value
        ? form.put(route('locations.update', editingId.value), { onSuccess: closeModal })
        : form.post(route('locations.store'), { onSuccess: closeModal })
}
function deleteLoc(loc) {
    closeMenu()
    if (!confirm(`${t('lm.confirmDelete')} "${loc.code}"?`)) return
    router.delete(route('locations.destroy', loc.id), { preserveScroll: true })
}
function toggleActive(loc) {
    closeMenu()
    router.put(route('locations.update', loc.id), {
        warehouse_id: loc.warehouse_id,
        code: loc.code,
        name: loc.name,
        description: loc.description,
        is_active: !loc.is_active,
    }, { preserveScroll: true })
}

const WH_COLORS = [
    { bg:'rgba(59,130,246,.15)',  color:'#60a5fa' },
    { bg:'rgba(16,185,129,.15)', color:'#34d399' },
    { bg:'rgba(249,115,22,.15)', color:'#fb923c' },
    { bg:'rgba(139,92,246,.15)', color:'#a78bfa' },
    { bg:'rgba(234,179,8,.15)',  color:'#fbbf24' },
]
function whColor(id) { return WH_COLORS[(id - 1) % WH_COLORS.length] }
</script>
<template>
  <AppLayout>
    <template #title>{{ $t('menu.rackManagement') }}</template>
    <template #breadcrumb>{{ $t('menu.rackManagement') }}</template>

    <!-- page header -->
    <div class="ph">
      <div>
        <h1 class="pt">{{ $t('lm.title') }}</h1>
        <p class="ps">{{ $t('lm.total', { n: stats?.total ?? 0 }) }}</p>
      </div>
      <button class="btn-add" @click="openAdd" type="button">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        {{ $t('lm.addRack') }}
      </button>
    </div>

    <!-- stat cards -->
    <div class="stats-row">
      <div class="sc sc-b">
        <div class="sc-ico ic-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
        </div>
        <div><div class="sc-n">{{ stats?.total ?? 0 }}</div><div class="sc-l">{{ $t('lm.totalRacks') }}</div></div>
      </div>
      <div class="sc sc-g">
        <div class="sc-ico ic-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div><div class="sc-n">{{ stats?.active ?? 0 }}</div><div class="sc-l">{{ $t('lm.activeRacks') }}</div></div>
      </div>
      <div class="sc sc-r">
        <div class="sc-ico ic-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div><div class="sc-n">{{ stats?.inactive ?? 0 }}</div><div class="sc-l">{{ $t('lm.inactiveRacks') }}</div></div>
      </div>
      <div class="sc sc-o">
        <div class="sc-ico ic-orange">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg>
        </div>
        <div><div class="sc-n">{{ warehouses?.length ?? 0 }}</div><div class="sc-l">{{ $t('menu.warehouses') }}</div></div>
      </div>
    </div>

    <!-- toolbar -->
    <div class="tlb">
      <div class="srch-w">
        <svg class="srch-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input v-model="search" class="srch-in" type="search" :placeholder="$t('lm.search')" autocomplete="off" />
      </div>
      <div class="tabs">
        <button class="tbtn" :class="{ ton: !filterStatus }" @click="setStatus('')" type="button">
          {{ $t('lm.tabAll') }}<span class="tct">{{ stats?.total ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'active' }" @click="setStatus('active')" type="button">
          <span class="tdot tdg"></span>{{ $t('status.active') }}<span class="tct">{{ stats?.active ?? 0 }}</span>
        </button>
        <button class="tbtn" :class="{ ton: filterStatus === 'inactive' }" @click="setStatus('inactive')" type="button">
          <span class="tdot tds"></span>{{ $t('status.inactive') }}<span class="tct">{{ stats?.inactive ?? 0 }}</span>
        </button>
      </div>
      <div class="drops">
        <select v-model="filterWh" class="fsel">
          <option value="">{{ $t('lm.filterWh') }}</option>
          <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} — {{ wh.name }}</option>
        </select>
        <button v-if="search || filterWh || filterStatus" class="btn-clr" @click="clearFilters" type="button">×</button>
      </div>
    </div>

    <!-- table card -->
    <div class="card">
      <div class="tw">
        <table class="dt">
          <thead>
            <tr>
              <th class="th-rn">#</th>
              <th class="ths" @click="doSort('code')">{{ $t('lm.colCode') }} <span class="si">{{ sortIcon('code') }}</span></th>
              <th class="ths" @click="doSort('name')">{{ $t('lm.colName') }} <span class="si">{{ sortIcon('name') }}</span></th>
              <th>{{ $t('lm.colWarehouse') }}</th>
              <th>{{ $t('lm.colDesc') }}</th>
              <th>{{ $t('lm.colStatus') }}</th>
              <th class="th-a"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!locations.data.length">
              <td colspan="7" class="empty-td">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;color:var(--fg-dim);margin-bottom:10px"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
                <div>{{ $t('lm.noResults') }}</div>
              </td>
            </tr>
            <tr v-for="(loc, i) in locations.data" :key="loc.id" class="dr">
              <td class="td-rn">{{ (locations.from ?? 1) + i }}</td>
              <td><span class="code-badge">{{ loc.code }}</span></td>
              <td class="td-name">{{ loc.name }}</td>
              <td>
                <span v-if="loc.warehouse" class="wh-badge" :style="{ background: whColor(loc.warehouse.id).bg, color: whColor(loc.warehouse.id).color }">
                  {{ loc.warehouse.code }}
                </span>
              </td>
              <td class="tdd tddesc">{{ loc.description || '—' }}</td>
              <td>
                <span class="sb" :class="loc.is_active ? 'sb-on' : 'sb-off'">
                  <i class="sd"></i>{{ loc.is_active ? $t('status.active') : $t('status.inactive') }}
                </span>
              </td>
              <td class="td-a">
                <button class="mb" @click="openMenu($event, loc)" type="button" :class="{ 'mb-open': activeMenu === loc.id }">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="pb">
        <span class="pi">{{ $t('lm.showing', { from: locations.from ?? 0, to: locations.to ?? 0, total: locations.total }) }}</span>
        <div v-if="locations.last_page > 1" class="pl">
          <Link v-for="lk in locations.links" :key="lk.label" :href="lk.url ?? '#'"
            :class="['pbn', { 'pb-on': lk.active, 'pb-off': !lk.url }]"
            preserve-scroll v-html="lk.label" />
        </div>
      </div>
    </div>

    <!-- teleported dropdown -->
    <Teleport to="body">
      <div v-if="activeMenu !== null && menuLoc" class="md-tp" :style="dropdownStyle" @click.stop>
        <button class="mi" @click="openEdit(menuLoc)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/></svg>
          {{ $t('btn.edit') }}
        </button>
        <button class="mi" @click="toggleActive(menuLoc)" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
          {{ menuLoc.is_active ? $t('status.inactive') : $t('status.active') }}
        </button>
        <div class="msep"></div>
        <button class="mi mi-del" @click="deleteLoc(menuLoc)" type="button">
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
              </div>
              <div>
                <div class="mt2">{{ isEdit ? $t('lm.editRack') : $t('lm.addRack') }}</div>
                <div class="ms2">{{ isEdit ? $t('lm.editRackSub') : $t('lm.addRackSub') }}</div>
              </div>
            </div>
            <button class="mcl" @click="closeModal" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="mb2">
            <div class="fg full">
              <label class="fl">{{ $t('lm.warehouse') }} <span class="req">*</span></label>
              <select v-model="form.warehouse_id" class="fi fis" :class="{'fi-e':form.errors.warehouse_id}">
                <option value="">— Select Warehouse</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} — {{ wh.name }}</option>
              </select>
              <p v-if="form.errors.warehouse_id" class="fe">{{ form.errors.warehouse_id }}</p>
            </div>
            <div class="fg half">
              <label class="fl">{{ $t('lm.code') }} <span class="req">*</span></label>
              <input v-model="form.code" class="fi" :class="{'fi-e':form.errors.code}" type="text" :placeholder="$t('lm.codePh')" autocomplete="off" style="text-transform:uppercase"/>
              <p v-if="form.errors.code" class="fe">{{ form.errors.code }}</p>
            </div>
            <div class="fg half">
              <label class="fl">{{ $t('lm.isActive') }}</label>
              <div class="trow">
                <button class="tbn" :class="{'ton':form.is_active}" @click="form.is_active=!form.is_active" type="button"><span class="tk"></span></button>
                <span class="tl">{{ form.is_active ? $t('status.active') : $t('status.inactive') }}</span>
              </div>
            </div>
            <div class="fg full">
              <label class="fl">{{ $t('lm.name') }} <span class="req">*</span></label>
              <input v-model="form.name" class="fi" :class="{'fi-e':form.errors.name}" type="text" :placeholder="$t('lm.namePh')" autocomplete="off"/>
              <p v-if="form.errors.name" class="fe">{{ form.errors.name }}</p>
            </div>
            <div class="fg full">
              <label class="fl">{{ $t('lm.description') }}</label>
              <textarea v-model="form.description" class="fi fi-ta" :placeholder="$t('lm.descPh')" rows="2"></textarea>
            </div>
          </div>
          <div class="mf">
            <button class="bcn" @click="closeModal" type="button">{{ $t('btn.cancel') }}</button>
            <button class="bsb" @click="submitForm" :disabled="form.processing" type="button">
              <svg v-if="!form.processing" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              <svg v-else class="sp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="15" height="15"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              {{ form.processing ? $t('lm.saving') : $t('btn.save') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>
<style scoped>
.ph{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.pt{font-size:22px;font-weight:700;letter-spacing:-.02em;color:var(--fg);margin:0}
.ps{font-size:13px;color:var(--fg-2);margin:4px 0 0}
.btn-add{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9px;border:0;cursor:pointer;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13.5px;font-weight:600;font-family:inherit;box-shadow:0 4px 14px -4px rgba(249,115,22,.55);transition:opacity 180ms ease;white-space:nowrap}
.btn-add:hover{opacity:.88}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm);border-left:3px solid transparent}
.sc-b{border-left-color:#3b82f6}.sc-g{border-left-color:#10b981}.sc-r{border-left-color:#ef4444}.sc-o{border-left-color:#f97316}
.sc-ico{width:44px;height:44px;border-radius:11px;display:grid;place-items:center;flex-shrink:0}
.sc-ico svg{width:22px;height:22px}
.ic-blue{background:rgba(59,130,246,.15);color:#60a5fa}.ic-green{background:rgba(16,185,129,.15);color:#10b981}
.ic-red{background:rgba(239,68,68,.15);color:#ef4444}.ic-orange{background:rgba(249,115,22,.15);color:#fb923c}
.sc-n{font-size:26px;font-weight:800;letter-spacing:-.03em;color:var(--fg);line-height:1}
.sc-l{font-size:12px;color:var(--fg-2);font-weight:500;margin-top:4px}
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
.drops{margin-left:auto;display:flex;align-items:center;gap:8px}
.fsel{padding:9px 30px 9px 12px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--fg);font-size:13px;font-family:inherit;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;transition:border-color 180ms ease}
.fsel:focus{outline:none;border-color:var(--blue-500)}
.btn-clr{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--fg-2);cursor:pointer;font-size:18px;transition:all 160ms}.btn-clr:hover{background:var(--hover);color:var(--fg)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow-sm)}
.tw{overflow-x:auto}
.dt{width:100%;border-collapse:collapse;font-size:13.5px}
.dt th{text-align:left;padding:12px 16px;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--fg-dim);border-bottom:1px solid var(--border);background:var(--surface-2);white-space:nowrap}
.th-rn{width:50px;text-align:center;padding-left:8px;padding-right:8px}.th-a{width:52px}.ths{cursor:pointer;user-select:none}.ths:hover{color:var(--fg-2)}
.si{font-size:10px;opacity:.6;margin-left:4px}
.dt td{padding:13px 16px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
.dt tbody tr:last-child td{border-bottom:0}.dr{transition:background 120ms}.dr:hover td{background:var(--hover)}
.td-rn{text-align:center;color:var(--fg-dim);font-size:12px;font-weight:500;padding-left:8px;padding-right:8px}
.tdd{color:var(--fg-2);font-size:13px}.tddesc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.td-name{font-weight:600;color:var(--fg)}
.code-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:5px;font-size:11px;font-weight:700;letter-spacing:.08em;font-family:monospace;background:rgba(59,130,246,.12);color:#60a5fa;border:1px solid rgba(59,130,246,.2)}
.wh-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:5px;font-size:10.5px;font-weight:700;letter-spacing:.06em;white-space:nowrap}
.sb{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500}
.sb-on{color:var(--emerald)}.sb-off{color:var(--fg-dim)}
.sd{width:7px;height:7px;border-radius:50%;background:currentColor;flex-shrink:0}
.empty-td{text-align:center;padding:60px 16px;color:var(--fg-2);display:flex;flex-direction:column;align-items:center}
.td-a{text-align:center;width:52px}
.mb{appearance:none;border:1px solid transparent;background:transparent;width:30px;height:30px;border-radius:7px;cursor:pointer;color:var(--fg-dim);display:grid;place-items:center;transition:all 160ms;margin:0 auto}
.mb:hover,.mb-open{background:var(--hover);border-color:var(--border);color:var(--fg)}
.md-tp{position:fixed;min-width:168px;background:#1e2535;border:1px solid #2a3550;border-radius:10px;padding:6px;z-index:9999;box-shadow:0 12px 36px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04)}
.mi{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:12.5px;color:#94a3b8;background:transparent;border:0;width:100%;text-align:left;font-family:inherit;transition:all 160ms}
.mi:hover{background:rgba(59,130,246,.08);color:#e6edf7}.mi-del{color:#ef4444}.mi-del:hover{background:rgba(239,68,68,.1);color:#ef4444}
.msep{height:1px;background:#1e2535;margin:4px 0}
.pb{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px}
.pi{font-size:12.5px;color:var(--fg-2)}.pl{display:flex;gap:4px;flex-wrap:wrap}
.pbn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;font-weight:500;color:var(--fg-2);text-decoration:none;cursor:pointer;transition:all 160ms}
.pbn:hover:not(.pb-off):not(.pb-on){background:var(--hover);color:var(--fg)}
.pb-on{background:var(--orange-500);border-color:var(--orange-500);color:#fff;font-weight:700}.pb-off{opacity:.35;cursor:default;pointer-events:none}
.mo{position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.72);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:24px;--surface:#0d1117;--surface-2:#111827;--surface-3:#1e2535;--border:#1e2535;--border-2:#2a3550;--fg:#e6edf7;--fg-2:#94a3b8;--fg-dim:#64748b;--hover:rgba(59,130,246,.08);--emerald:#10b981;--rose:#ef4444;--blue-500:#3b82f6;--orange-400:#fb923c;--orange-500:#f97316}
.modal{background:#0d1117;border:1px solid #2a3550;border-radius:16px;width:100%;max-width:520px;box-shadow:0 32px 80px rgba(0,0,0,.65),0 0 0 1px rgba(255,255,255,.04);display:flex;flex-direction:column;max-height:90vh}
.mh{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #1e2535;flex-shrink:0;background:#0d1117;border-radius:16px 16px 0 0}
.mhl{display:flex;align-items:center;gap:14px}
.mico{width:40px;height:40px;border-radius:10px;flex-shrink:0;background:rgba(59,130,246,.12);color:#60a5fa;display:grid;place-items:center}
.mico svg{width:20px;height:20px}
.mt2{font-size:16px;font-weight:700;color:var(--fg)}.ms2{font-size:12px;color:var(--fg-2);margin-top:3px}
.mcl{appearance:none;border:1px solid var(--border);background:transparent;width:32px;height:32px;border-radius:8px;cursor:pointer;color:var(--fg-2);display:grid;place-items:center;transition:all 160ms}.mcl:hover{background:var(--hover);color:var(--fg)}
.mb2{overflow-y:auto;padding:24px;flex:1;display:grid;grid-template-columns:1fr 1fr;gap:18px 16px}
.fg{display:flex;flex-direction:column;gap:6px}.fg.full{grid-column:1/-1}.fg.half{grid-column:span 1}
.fl{font-size:11.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--fg-2);display:flex;align-items:center;gap:5px}
.req{color:var(--rose)}
.fi{padding:10px 12px;background:var(--surface-2);border:1px solid var(--border);border-radius:9px;color:var(--fg);font-size:13.5px;font-family:inherit;transition:border-color 180ms ease,box-shadow 180ms ease}
.fi:focus{outline:none;border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(59,130,246,.12)}.fi-e{border-color:var(--rose)}
.fi-ta{resize:vertical;min-height:64px}
.fis{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:34px;width:100%}
.fe{font-size:12px;color:var(--rose)}
.trow{display:flex;align-items:center;gap:10px;padding:8px 0}
.tbn{appearance:none;border:0;cursor:pointer;padding:0;flex-shrink:0;width:44px;height:24px;border-radius:999px;background:var(--border-2);position:relative;transition:background 200ms}.ton{background:var(--emerald)}
.tk{position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:999px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.3);transition:transform 200ms;pointer-events:none}.ton .tk{transform:translateX(20px)}
.tl{font-size:13px;font-weight:500;color:var(--fg-2)}
.mf{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #1e2535;flex-shrink:0;background:#111827;border-radius:0 0 16px 16px}
.bcn{display:inline-flex;align-items:center;padding:10px 18px;border-radius:8px;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--fg-2);font-size:13px;font-weight:500;font-family:inherit;transition:all 160ms}.bcn:hover{background:var(--hover);color:var(--fg)}
.bsb{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:8px;border:0;cursor:pointer;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13px;font-weight:600;font-family:inherit;box-shadow:0 4px 12px -4px rgba(249,115,22,.5);transition:opacity 160ms}.bsb:hover:not(:disabled){opacity:.9}.bsb:disabled{opacity:.5;cursor:not-allowed}
@keyframes spin{to{transform:rotate(360deg)}}.sp{animation:spin .8s linear infinite}
.modal-enter-active{transition:opacity 200ms ease}.modal-leave-active{transition:opacity 180ms ease}
.modal-enter-from,.modal-leave-to{opacity:0}
.modal-enter-active .modal{animation:mIn 260ms cubic-bezier(.34,1.3,.64,1) forwards}
.modal-leave-active .modal{animation:mOut 160ms ease forwards}
@keyframes mIn{from{transform:translateY(-16px) scale(.96);opacity:0}to{transform:none;opacity:1}}
@keyframes mOut{from{transform:none;opacity:1}to{transform:translateY(-10px) scale(.97);opacity:0}}
@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.mo{padding:0;align-items:flex-end}.modal{border-radius:16px 16px 0 0;max-height:92vh;max-width:100%}.mb2{grid-template-columns:1fr}.fg.half{grid-column:1/-1}}
</style>
