<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()
const props = defineProps({ roleCounts: Object, rolePermissions: Object })

const ROLES = [
    { code: 'super_admin',       locked: true  },
    { code: 'procurement_admin', locked: false },
    { code: 'wh_admin',          locked: false },
    { code: 'admin_dept',        locked: false },
    { code: 'manager_dept',      locked: false },
    { code: 'wh_manager',        locked: false },
    { code: 'wh_supervisor',     locked: false },
    { code: 'operator',          locked: false },
]

const MODULES = [
    { key: 'system',    cls: 'grp-blue',   items: ['users','departments','permissions'] },
    { key: 'warehouse', cls: 'grp-orange', items: ['warehouses','rackMgmt','fleetMgmt','employees'] },
    { key: 'inventory', cls: 'grp-green',  items: ['itemMaster','stockInput','goodsReceipt','goodsIssue','goodsRequest'] },
    { key: 'reports',   cls: 'grp-purple', items: ['inventoryReport','transactionLog','auditTrail'] },
]

// [view, create, edit, delete, approve] — null = N/A
const DEFAULTS = {
    super_admin:       { '*': [1,1,1,1,1] },
    procurement_admin: {
        '*':             [0,0,0,0,null],
        warehouses:      [1,0,0,0,null],
        rackMgmt:        [1,0,0,0,null],
        itemMaster:      [1,1,1,1,null],
        goodsReceipt:    [1,1,1,1,null],
        inventoryReport: [1,0,0,0,null],
    },
    wh_admin: {
        '*':             [0,0,0,0,null],
        departments:     [1,0,0,0,null],
        warehouses:      [1,0,0,0,null],
        rackMgmt:        [1,0,0,0,null],
        employees:       [1,1,1,1,null],
        stockInput:      [1,1,1,1,null],
        goodsReceipt:    [1,0,1,0,null],
        goodsIssue:      [1,0,1,0,null],
        goodsRequest:    [1,1,0,1,null],
        inventoryReport: [1,0,0,0,null],
        transactionLog:  [1,0,0,0,null],
    },
    admin_dept: {
        '*':             [0,0,0,0,null],
        departments:     [1,0,0,0,null],
        stockInput:      [1,0,0,0,null],
        employees:       [1,1,1,1,null],
        goodsIssue:      [1,1,1,1,null],
        inventoryReport: [1,0,0,0,null],
        transactionLog:  [1,0,0,0,null],
    },
    manager_dept: {
        '*':             [0,0,0,0,null],
        departments:     [1,0,0,0,null],
        stockInput:      [1,0,0,0,null],
        goodsReceipt:    [1,0,0,0,null],
        goodsIssue:      [1,0,0,0,1],
        goodsRequest:    [1,0,0,0,null],
        inventoryReport: [1,0,0,0,null],
        transactionLog:  [1,0,0,0,null],
    },
    wh_manager: {
        '*':             [0,0,0,0,null],
        users:           [1,0,0,0,null],
        departments:     [1,0,0,0,null],
        warehouses:      [1,0,0,0,null],
        rackMgmt:        [1,0,0,0,null],
        fleetMgmt:       [1,0,0,0,null],
        employees:       [1,0,0,0,null],
        stockInput:      [1,0,0,0,null],
        goodsReceipt:    [1,0,0,0,null],
        goodsIssue:      [1,0,0,0,1],
        goodsRequest:    [1,0,0,0,null],
        inventoryReport: [1,0,0,0,null],
        transactionLog:  [1,0,0,0,null],
        auditTrail:      [1,0,0,0,null],
    },
    wh_supervisor: {
        '*':             [0,0,0,0,null],
        departments:     [1,0,0,0,null],
        warehouses:      [1,0,0,0,null],
        rackMgmt:        [1,0,0,0,null],
        employees:       [1,0,0,0,null],
        stockInput:      [1,0,0,0,null],
        goodsReceipt:    [1,0,0,0,1],
        goodsIssue:      [1,0,0,0,1],
        goodsRequest:    [1,1,0,1,null],
        inventoryReport: [1,0,0,0,null],
        transactionLog:  [1,0,0,0,null],
    },
    operator: {
        '*':          [0,0,0,0,null],
        goodsReceipt: [1,0,0,1,null],
        goodsIssue:   [1,0,0,1,null],
    },
}

function getDefault(roleCode, itemKey) {
    const d = DEFAULTS[roleCode] ?? {}
    return [...(d[itemKey] ?? d['*'] ?? [0,0,0,0,null])]
}

const selectedRole = ref('super_admin')
const permState    = ref({})
const saved        = ref(false)
const saving       = ref(false)

function buildState(roleCode) {
    const dbPerms = props.rolePermissions?.[roleCode] ?? {}
    const s = {}
    MODULES.forEach(m => m.items.forEach(item => {
        // Use DB value if it exists, otherwise fall back to hardcoded defaults
        if (dbPerms[item] !== undefined) {
            s[item] = [...dbPerms[item]]
        } else {
            s[item] = getDefault(roleCode, item)
        }
    }))
    return s
}

watch(selectedRole, (v) => { permState.value = buildState(v); saved.value = false }, { immediate: true })

function toggle(item, idx) {
    if (ROLES.find(r => r.code === selectedRole.value)?.locked) return
    if (permState.value[item]?.[idx] === null) return
    permState.value[item][idx] = permState.value[item][idx] ? 0 : 1
}

const stats = computed(() => {
    let v=0, c=0, e=0, d=0
    Object.values(permState.value).forEach(p => {
        if (p[0]) v++; if (p[1]) c++; if (p[2]) e++; if (p[3]) d++
    })
    return { v, c, e, d }
})

function saveChanges() {
    if (ROLES.find(r => r.code === selectedRole.value)?.locked) return
    saving.value = true
    router.post(route('permissions.save', selectedRole.value), {
        permissions: permState.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true
            saving.value = false
            setTimeout(() => { saved.value = false }, 2500)
        },
        onError: () => { saving.value = false },
    })
}

function resetToDefault() {
    // Reset to hardcoded defaults (in-memory only — does not touch DB until Save is pressed)
    const s = {}
    MODULES.forEach(m => m.items.forEach(item => { s[item] = getDefault(selectedRole.value, item) }))
    permState.value = s
    saved.value = false
}

function userCount(code) {
    return props.roleCounts?.[code] ?? 0
}
</script>
<template>
  <AppLayout>
    <template #title>{{ $t('menu.permissions') }}</template>
    <template #breadcrumb>{{ $t('menu.permissions') }}</template>

    <!-- page header -->
    <div class="ph">
      <div>
        <h1 class="pt">{{ $t('pm.title') }}</h1>
        <p class="ps">{{ $t('pm.sub') }}</p>
      </div>
    </div>

    <!-- two-column layout -->
    <div class="perm-grid">

      <!-- ── role list ──────────────────────────────────────────────────── -->
      <div class="role-list">
        <div class="rl-head">
          <h3 class="rl-title">{{ $t('pm.roles') }}</h3>
          <span class="rl-count">{{ ROLES.length }} {{ $t('pm.total') }}</span>
        </div>
        <div v-for="r in ROLES" :key="r.code"
          class="role-card" :class="{ active: selectedRole === r.code }"
          @click="selectedRole = r.code">
          <div class="rc-name">
            {{ $t(`role.${r.code}`) }}
            <span v-if="r.locked" class="lock-ico" title="System role">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </span>
          </div>
          <div class="rc-meta">
            <span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              {{ userCount(r.code) }} user{{ userCount(r.code) !== 1 ? 's' : '' }}
            </span>
            <span v-if="r.locked" class="sys-badge">System</span>
          </div>
        </div>
      </div>

      <!-- ── permission matrix ──────────────────────────────────────────── -->
      <div class="matrix">
        <!-- matrix header -->
        <div class="mx-head">
          <div>
            <h2 class="mx-title">
              <span v-if="ROLES.find(r=>r.code===selectedRole)?.locked" class="lock-ico mx-lock">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              </span>
              {{ $t(`role.${selectedRole}`) }}
            </h2>
            <p class="mx-desc">{{ $t(`pm.desc.${selectedRole}`) }}</p>
          </div>
          <div class="mx-acts">
            <button class="btn-reset" @click="resetToDefault" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
              {{ $t('pm.resetDefault') }}
            </button>
            <button class="btn-save" :class="{ saved }" @click="saveChanges"
              :disabled="saving || ROLES.find(r=>r.code===selectedRole)?.locked" type="button">
              <svg v-if="saved" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M20 6 9 17l-5-5"/></svg>
              <svg v-else-if="saving" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              {{ saved ? $t('pm.saved') : saving ? $t('pm.saving') : $t('pm.saveChanges') }}
            </button>
          </div>
        </div>

        <!-- stats pills -->
        <div class="stats-row">
          <div class="stat-pill sp-v"><div class="sv">{{ stats.v }}</div><div class="sl">{{ $t('pm.colView') }}</div></div>
          <div class="stat-pill sp-c"><div class="sv">{{ stats.c }}</div><div class="sl">{{ $t('pm.colCreate') }}</div></div>
          <div class="stat-pill sp-e"><div class="sv">{{ stats.e }}</div><div class="sl">{{ $t('pm.colEdit') }}</div></div>
          <div class="stat-pill sp-d"><div class="sv">{{ stats.d }}</div><div class="sl">{{ $t('pm.colDelete') }}</div></div>
        </div>

        <!-- permission table -->
        <div class="tbl-wrap">
          <table class="ptbl">
            <thead>
              <tr>
                <th class="th-mod">{{ $t('pm.module') }}</th>
                <th>{{ $t('pm.colView') }}</th>
                <th>{{ $t('pm.colCreate') }}</th>
                <th>{{ $t('pm.colEdit') }}</th>
                <th>{{ $t('pm.colDelete') }}</th>
                <th>{{ $t('pm.colApprove') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="m in MODULES" :key="m.key">
                <tr class="grp-row">
                  <td colspan="6">
                    <span class="grp-ico" :class="m.cls">
                      <svg v-if="m.key==='system'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                      <svg v-else-if="m.key==='warehouse'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-7h6v7"/></svg>
                      <svg v-else-if="m.key==='inventory'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M9 4v16"/></svg>
                      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-5"/></svg>
                    </span>
                    {{ $t(`pm.grp.${m.key}`) }}
                  </td>
                </tr>
                <tr v-for="item in m.items" :key="item" class="item-row">
                  <td class="td-item">{{ $t(`pm.item.${item}`) }}</td>
                  <td v-for="(perm, idx) in (permState[item] ?? [0,0,0,0,null])" :key="idx" class="td-ck">
                    <span v-if="perm === null" class="ck-na">—</span>
                    <button v-else class="ck-btn"
                      :class="{ on: perm, locked: ROLES.find(r=>r.code===selectedRole)?.locked }"
                      @click="toggle(item, idx)" type="button">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
<style scoped>
/* page head */
.ph{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.pt{font-size:22px;font-weight:700;letter-spacing:-.02em;color:var(--fg);margin:0}
.ps{font-size:13px;color:var(--fg-2);margin:4px 0 0}

/* two-column grid */
.perm-grid{display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start}
@media(max-width:1100px){.perm-grid{grid-template-columns:1fr}}

/* role list */
.role-list{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:10px;display:flex;flex-direction:column;gap:5px;box-shadow:var(--shadow-sm)}
.rl-head{display:flex;align-items:center;justify-content:space-between;padding:6px 8px 10px;border-bottom:1px solid var(--border);margin-bottom:4px}
.rl-title{margin:0;font-size:11.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--fg-2)}
.rl-count{font-size:11.5px;color:var(--fg-2)}
.role-card{padding:11px 12px;border-radius:10px;cursor:pointer;border:1.5px solid transparent;transition:background 150ms,border-color 150ms;position:relative}
.role-card:hover{background:var(--hover)}
.role-card.active{background:var(--active-bg);border-color:rgba(249,115,22,.4)}
.role-card.active::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:3px;background:var(--orange-500);border-radius:0 3px 3px 0}
.rc-name{font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px;color:var(--fg)}
.rc-meta{font-size:11.5px;color:var(--fg-2);margin-top:5px;display:flex;align-items:center;gap:10px}
.lock-ico{display:inline-flex;width:18px;height:18px;align-items:center;justify-content:center;color:var(--amber-500)}
.lock-ico svg{width:13px;height:13px}
.sys-badge{font-size:10px;font-weight:700;color:var(--amber-500);letter-spacing:.04em}

/* matrix */
.matrix{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:var(--shadow-sm)}
.mx-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.mx-title{margin:0 0 4px;font-size:19px;font-weight:700;letter-spacing:-.01em;display:flex;align-items:center;gap:8px}
.mx-lock{width:22px;height:22px;color:var(--amber-500)}
.mx-lock svg{width:16px;height:16px}
.mx-desc{margin:0;font-size:13px;color:var(--fg-2)}
.mx-acts{display:flex;gap:8px;flex-shrink:0}
.btn-reset{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--fg-2);font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;transition:all 160ms}
.btn-reset:hover{background:var(--hover);color:var(--fg)}
.btn-save{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:0;background:linear-gradient(180deg,var(--orange-400),var(--orange-500));color:#fff;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;box-shadow:0 4px 10px -3px rgba(249,115,22,.45);transition:all 160ms}
.btn-save:hover{filter:brightness(1.06)}
.btn-save.saved{background:linear-gradient(180deg,#34d399,var(--emerald));box-shadow:0 4px 10px -3px rgba(16,185,129,.45)}
.btn-save:disabled{opacity:.5;cursor:not-allowed;filter:none}
.spin{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* stats row */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.stat-pill{background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:10px 14px}
.sv{font-size:20px;font-weight:800}
.sl{font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--fg-2);margin-top:2px}
.sp-v .sv{color:var(--emerald)}
.sp-c .sv{color:var(--blue-500)}
.sp-e .sv{color:var(--amber-500)}
.sp-d .sv{color:var(--rose)}

/* table */
.tbl-wrap{border:1px solid var(--border);border-radius:12px;overflow:hidden}
.ptbl{width:100%;border-collapse:collapse;font-size:13px}
.ptbl thead th{padding:11px 14px;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--fg-2);background:var(--surface-2);border-bottom:1px solid var(--border);text-align:center;white-space:nowrap}
.ptbl thead th.th-mod{text-align:left;min-width:180px}
.grp-row td{background:var(--surface-2);font-weight:700;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--fg-2);padding:9px 14px;border-bottom:1px solid var(--border);display:table-cell}
.grp-ico{display:inline-flex;width:20px;height:20px;border-radius:5px;align-items:center;justify-content:center;margin-right:8px;vertical-align:middle}
.grp-ico svg{width:11px;height:11px}
.grp-blue{background:rgba(59,130,246,.15);color:#60a5fa}
.grp-orange{background:rgba(249,115,22,.15);color:#fb923c}
.grp-green{background:rgba(16,185,129,.15);color:#34d399}
.grp-purple{background:rgba(167,139,250,.15);color:#a78bfa}
.item-row td{padding:10px 14px;border-bottom:1px solid var(--border-soft);text-align:center;vertical-align:middle}
.item-row:last-child td{border-bottom:0}
.item-row:hover td{background:var(--hover)}
.td-item{text-align:left!important;font-weight:500;color:var(--fg)}
.td-ck{}
.ck-btn{display:inline-flex;width:24px;height:24px;border-radius:6px;border:1.5px solid var(--border-2);background:var(--surface);align-items:center;justify-content:center;cursor:pointer;transition:all 150ms}
.ck-btn:hover:not(.locked){border-color:var(--orange-500)}
.ck-btn svg{width:13px;height:13px;color:#fff;opacity:0;transform:scale(.5);transition:all 150ms}
.ck-btn.on{background:var(--orange-500);border-color:var(--orange-500)}
.ck-btn.on svg{opacity:1;transform:scale(1)}
.ck-btn.locked{cursor:not-allowed}
.ck-btn.on.locked{background:var(--orange-500);opacity:.7}
.ck-na{display:inline-flex;width:24px;height:24px;align-items:center;justify-content:center;color:var(--fg-dim);font-size:14px;font-weight:500}
</style>
