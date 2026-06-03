<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'

const page = usePage()
const { t, locale } = useI18n()

const props = defineProps({
    gis: Object,   // paginated
})

// Filter tab
const activeFilter = ref('all')   // 'all' | 'completed' | 'rejected'

const filtered = computed(() => {
    if (!props.gis?.data) return []
    if (activeFilter.value === 'all')       return props.gis.data
    return props.gis.data.filter(gi => gi.status === activeFilter.value)
})

function statusLabel(status) {
    if (status === 'completed')       return t('operator.filterCompleted')
    if (status === 'rejected')        return t('operator.filterRejected')
    if (status === 'ready_to_pickup') return t('operator.statusReady')
    return status
}

function statusClass(status) {
    if (status === 'completed')       return 'hist-badge-ok'
    if (status === 'rejected')        return 'hist-badge-rej'
    if (status === 'ready_to_pickup') return 'hist-badge-rdy'
    return 'hist-badge-dr'
}

function fmtDate(iso) {
    if (!iso) return '—'
    const loc = locale.value === 'zh' ? 'zh-CN' : locale.value === 'id' ? 'id-ID' : 'en-US'
    return new Date(iso).toLocaleDateString(loc, { day:'2-digit', month:'short', year:'numeric' })
}

function goDetail(gi) {
    if (['assigned','in_picking','ready_to_pickup'].includes(gi.status)) {
        router.visit(route('operator.scan-detail', gi.id))
    }
}
</script>

<template>
  <OperatorLayout>
    <template #title>{{ $t('operator.historyTitle') }}</template>

    <!-- Filter tabs -->
    <div class="hist-filters">
      <button
        v-for="f in [['all', $t('operator.filterAll')], ['completed', $t('operator.filterCompleted')], ['rejected', $t('operator.filterRejected')]]"
        :key="f[0]"
        class="hist-filter-btn"
        :class="{ active: activeFilter === f[0] }"
        @click="activeFilter = f[0]"
        type="button"
      >{{ f[1] }}</button>
    </div>

    <!-- List -->
    <div class="hist-list">
      <div v-if="!filtered.length" class="hist-empty">
        <div class="hist-empty-icon">📭</div>
        <div>{{ $t('operator.historyEmpty') }}</div>
      </div>

      <div
        v-for="gi in filtered"
        :key="gi.id"
        class="hist-card"
        :class="{ clickable: ['assigned','in_picking','ready_to_pickup'].includes(gi.status) }"
        @click="goDetail(gi)"
      >
        <div class="hist-card-top">
          <span class="hist-gi-number">{{ gi.gi_number }}</span>
          <span class="hist-badge" :class="statusClass(gi.status)">
            {{ statusLabel(gi.status) }}
          </span>
        </div>
        <div class="hist-card-mid">
          <span class="hist-dept">{{ gi.department?.name ?? '—' }}</span>
          <span class="hist-wh">🏭 {{ gi.warehouse?.code ?? '—' }}</span>
        </div>
        <div class="hist-card-bot">
          <span class="hist-items">{{ gi.items_count }} item{{ gi.items_count !== 1 ? 's' : '' }}</span>
          <span class="hist-date">{{ fmtDate(gi.updated_at) }}</span>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="props.gis && props.gis.last_page > 1" class="hist-pagination">
      <button
        class="hist-pg-btn"
        :disabled="!props.gis.prev_page_url"
        @click="router.visit(props.gis.prev_page_url)"
        type="button"
      >{{ $t('operator.prevPage') }}</button>
      <span class="hist-pg-info">{{ props.gis.current_page }} / {{ props.gis.last_page }}</span>
      <button
        class="hist-pg-btn"
        :disabled="!props.gis.next_page_url"
        @click="router.visit(props.gis.next_page_url)"
        type="button"
      >{{ $t('operator.nextPage') }}</button>
    </div>
  </OperatorLayout>
</template>

<style scoped>
.hist-filters {
  display: flex;
  gap: 8px;
  padding: 14px 14px 0;
  flex-wrap: wrap;
}
.hist-filter-btn {
  appearance: none;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg-2);
  font-size: 12.5px;
  font-weight: 600;
  padding: 6px 16px;
  border-radius: 20px;
  cursor: pointer;
  font-family: inherit;
  transition: all 150ms ease;
}
.hist-filter-btn.active {
  background: var(--orange-500);
  border-color: var(--orange-500);
  color: #fff;
}
.hist-list {
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hist-empty {
  text-align: center;
  padding: 48px 0;
  color: var(--fg-2);
  font-size: 14px;
}
.hist-empty-icon { font-size: 40px; margin-bottom: 10px; }
.hist-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.hist-card.clickable { cursor: pointer; }
.hist-card.clickable:active { opacity: .85; transform: scale(.99); }
.hist-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.hist-gi-number {
  font-family: 'JetBrains Mono', monospace;
  font-size: 14px;
  font-weight: 700;
  color: var(--fg);
}
.hist-badge {
  font-size: 10.5px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: .04em;
  flex-shrink: 0;
}
.hist-badge-ok  { background: rgba(16,185,129,.14); color: #10b981; }
.hist-badge-rej { background: rgba(239,68,68,.14);  color: #ef4444; }
.hist-badge-rdy { background: rgba(59,130,246,.14); color: #3b82f6; }
.hist-badge-dr  { background: var(--surface-2);     color: var(--fg-2); }
.hist-card-mid {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.hist-dept { font-size: 13px; color: var(--fg); font-weight: 500; }
.hist-wh   { font-size: 12px; color: var(--fg-2); }
.hist-card-bot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.hist-items { font-size: 12px; color: var(--fg-2); }
.hist-date  { font-size: 11.5px; color: var(--fg-2); }
.hist-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px 14px;
}
.hist-pg-btn {
  appearance: none;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--fg);
  font-size: 12px;
  font-weight: 600;
  padding: 7px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 150ms;
}
.hist-pg-btn:disabled { opacity: .4; cursor: not-allowed; }
.hist-pg-info { font-size: 12px; color: var(--fg-2); font-weight: 600; }
</style>
