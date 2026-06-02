<script setup>
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'

const { t } = useI18n()

const props = defineProps({
    employee: Object,
    user:     Object,
})

function logout() {
    router.post(route('logout'))
}

function initials(name) {
    if (!name) return 'E'
    return name.trim().split(' ').slice(0, 2).map(s => s[0]).join('').toUpperCase()
}
</script>

<template>
  <EmployeeLayout>
    <template #title>{{ $t('portal.profileTitle') }}</template>

    <!-- Avatar + Name card -->
    <div class="profile-hero">
      <div class="profile-avatar">{{ initials(employee?.name) }}</div>
      <div class="profile-name">{{ employee?.name }}</div>
      <div class="profile-id-badge">{{ employee?.employee_id }}</div>
    </div>

    <!-- Info card -->
    <div class="info-card">
      <div v-if="employee?.position" class="info-row">
        <span class="info-label">{{ $t('portal.position') }}</span>
        <span class="info-value">{{ employee.position }}</span>
      </div>
      <div v-if="employee?.department" class="info-row">
        <span class="info-label">{{ $t('portal.department') }}</span>
        <span class="info-value">{{ employee.department.name }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">{{ $t('portal.loginId') }}</span>
        <span class="info-value mono">{{ user?.name ?? employee?.employee_id }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">{{ $t('common.active') }}</span>
        <span class="info-value">
          <span class="status-chip">✓ Aktif</span>
        </span>
      </div>
    </div>

    <!-- Logout -->
    <div class="logout-wrap">
      <button class="btn-logout" @click="logout" type="button">
        {{ $t('portal.logoutBtn') }}
      </button>
    </div>

  </EmployeeLayout>
</template>

<style scoped>
/* ── Hero ─────────────────────────────────────────────────────────── */
.profile-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 28px 16px 20px;
    margin-bottom: 14px;
}
.profile-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--orange-400);
    color: #fff;
    font-size: 26px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    text-transform: uppercase;
    box-shadow: 0 4px 16px rgba(249,115,22,.28);
}
.profile-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--fg);
    text-align: center;
    margin-bottom: 6px;
}
.profile-id-badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    background: rgba(249,115,22,.12);
    color: var(--orange-500);
    padding: 3px 12px;
    border-radius: 999px;
}

/* ── Info card ────────────────────────────────────────────────────── */
.info-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 4px 14px;
    margin-bottom: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-soft);
}
.info-row:last-child { border-bottom: 0; }
.info-label {
    font-size: 12.5px;
    color: var(--fg-2);
    flex-shrink: 0;
}
.info-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--fg);
    text-align: right;
}
.info-value.mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
}
.status-chip {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    background: rgba(16,185,129,.12);
    color: #10b981;
    padding: 2px 10px;
    border-radius: 999px;
}

/* ── Logout ───────────────────────────────────────────────────────── */
.logout-wrap {
    padding: 0 0 8px;
}
.btn-logout {
    width: 100%;
    appearance: none;
    border: 1px solid #ef4444;
    background: transparent;
    color: #ef4444;
    font-size: 14px;
    font-weight: 700;
    padding: 13px;
    border-radius: 12px;
    cursor: pointer;
    font-family: inherit;
    transition: background 180ms, color 180ms;
}
.btn-logout:hover { background: #ef4444; color: #fff; }
</style>