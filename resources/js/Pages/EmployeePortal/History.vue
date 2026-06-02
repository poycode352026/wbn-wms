<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    employee: Object,
    logs:     Object,   // paginated
    today:    String,
})

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function logItemName(log) {
    const item = log.variant?.item
    if (!item) return '—'
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}

function logType(log) {
    if (log.lv) return t('portal.typeLv').replace('{lv}', log.lv.full_number ?? log.lv.lv_number)
    return t('portal.typePersonal')
}

function isOverdue(log) {
    if (!log.cooldown_until) return false
    return log.cooldown_until < props.today
}

function goPage(url) {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true })
}
</script>

<template>
  <EmployeeLayout>
    <template #title>{{ $t('portal.historyTitle') }}</template>

    <div class="history-header">
      <h2 class="history-title">{{ $t('portal.historyTitle') }}</h2>
      <div class="history-sub">{{ employee?.name }} · {{ employee?.employee_id }}</div>
    </div>

    <!-- Empty state -->
    <div v-if="!logs.data?.length" class="empty-card">
      <div class="empty-icon">📦</div>
      <div class="empty-msg">{{ $t('portal.historyEmpty') }}</div>
    </div>

    <!-- Log list -->
    <div v-else class="log-list">
      <div v-for="log in logs.data" :key="log.id" class="log-card" :class="{ overdue: isOverdue(log) }">
        <div class="log-card-top">
          <div class="log-item-name">{{ logItemName(log) }}</div>
          <span class="log-type-badge">{{ logType(log) }}</span>
        </div>
        <div class="log-card-meta">
          <div v-if="log.variant?.sku" class="log-sku">SKU: {{ log.variant.sku }}</div>
          <div class="log-dates">
            <span>{{ $t('portal.colTakenDate') }}: <strong>{{ fmtDate(log.taken_at) }}</strong></span>
            <span v-if="log.cooldown_until">
              {{ $t('portal.colValidUntil') }}: <strong :class="{ 'text-red': isOverdue(log) }">{{ fmtDate(log.cooldown_until) }}</strong>
            </span>
          </div>
        </div>
        <div v-if="isOverdue(log)" class="overdue-chip">⚠️ {{ $t('portal.overdueChip') }}</div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="logs.last_page > 1" class="pag-row">
      <button
        class="pag-btn"
        :disabled="!logs.prev_page_url"
        @click="goPage(logs.prev_page_url)"
        type="button">← Prev</button>
      <span class="pag-info">{{ logs.current_page }} / {{ logs.last_page }}</span>
      <button
        class="pag-btn"
        :disabled="!logs.next_page_url"
        @click="goPage(logs.next_page_url)"
        type="button">Next →</button>
    </div>

  </EmployeeLayout>
</template>

<style scoped>
.history-header { margin-bottom: 16px; }
.history-title { font-size: 18px; font-weight: 700; color: var(--fg); margin: 0 0 4px; }
.history-sub { font-size: 12.5px; color: var(--fg-2); }

.empty-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 40px 16px; text-align: center; }
.empty-icon { font-size: 36px; margin-bottom: 10px; }
.empty-msg { font-size: 14px; color: var(--fg-2); }

.log-list { display: flex; flex-direction: column; gap: 10px; }

.log-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 13px 14px;
    box-shadow: 0 1px 6px rgba(0,0,0,.04);
}
.log-card.overdue { border-left: 3px solid #ef4444; }

.log-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.log-item-name { font-size: 14px; font-weight: 700; color: var(--fg); flex: 1; line-height: 1.3; }
.log-type-badge { font-size: 10px; font-weight: 700; background: var(--surface-2); color: var(--fg-2); border: 1px solid var(--border); padding: 2px 8px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }

.log-card-meta { display: flex; flex-direction: column; gap: 4px; }
.log-sku { font-size: 11.5px; color: var(--fg-2); font-family: 'JetBrains Mono', monospace; }
.log-dates { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: var(--fg-2); }
.log-dates strong { color: var(--fg); }
.text-red { color: #ef4444 !important; }

.overdue-chip { margin-top: 8px; font-size: 11px; font-weight: 700; color: #ef4444; background: rgba(239,68,68,.1); padding: 3px 10px; border-radius: 999px; display: inline-block; }

.pag-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 20px; padding-bottom: 8px; }
.pag-btn { appearance: none; border: 1px solid var(--border); background: var(--surface); color: var(--fg); font-size: 13px; font-weight: 600; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-family: inherit; transition: background 150ms; }
.pag-btn:disabled { opacity: .4; cursor: not-allowed; }
.pag-btn:not(:disabled):hover { background: var(--hover); }
.pag-info { font-size: 13px; color: var(--fg-2); }
</style>
