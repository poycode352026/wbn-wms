<script setup>
import { ref, computed } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import OperatorLayout from '@/Layouts/OperatorLayout.vue'

const { t, locale } = useI18n()

const props = defineProps({
    gis: Array,
})

const searchQuery = ref('')
const scanInput = ref('')

// Filter GI by search
const filteredGis = computed(() => {
    if (!searchQuery.value.trim()) return props.gis ?? []
    const q = searchQuery.value.toLowerCase()
    return (props.gis ?? []).filter(gi =>
        gi.gi_number.toLowerCase().includes(q) ||
        gi.purpose.toLowerCase().includes(q) ||
        gi.department?.name.toLowerCase().includes(q)
    )
})

// Handle barcode scan (QR codes scan as text, e.g., "GI-2025-001")
function handleScan(e) {
    const barcode = scanInput.value.trim()
    if (!barcode) return

    // Find GI by gi_number
    const gi = (props.gis ?? []).find(g => g.gi_number === barcode)
    if (gi) {
        router.visit(route('operator.scan-detail', gi.id))
    } else {
        alert(`GI "${barcode}" tidak ditemukan atau tidak diasignkan ke Anda`)
    }
    scanInput.value = ''
}

function itemName(variant) {
    const item = variant?.item ?? variant
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? '—'
}
</script>

<template>
<OperatorLayout>
  <template #title>{{ $t('operator.scanTitle') }}</template>

  <div class="op-wrap">
    <!-- Header -->
    <div class="op-hdr">
      <div>
        <h2 class="op-title">📦 {{ $t('operator.scanTitle') }}</h2>
        <p class="op-sub">{{ $t('operator.scanSub') }}</p>
      </div>
    </div>

    <!-- Scan input -->
    <div class="op-card op-scan-card">
      <div class="op-scan-label">{{ $t('operator.scanInput') }}</div>
      <input
        v-model="scanInput"
        @keydown.enter="handleScan"
        type="text"
        class="op-scan-input"
        placeholder="GI-2025-001..."
        autocomplete="off"
      />
      <span class="op-scan-hint">{{ $t('operator.scanHint') }}</span>
    </div>

    <!-- Search filter -->
    <div class="op-card op-filter-card">
      <input
        v-model="searchQuery"
        type="text"
        class="op-search-input"
        :placeholder="$t('operator.searchPlaceholder')"
      />
    </div>

    <!-- GI List -->
    <div v-if="filteredGis.length" class="op-list">
      <div v-for="gi in filteredGis" :key="gi.id" class="op-list-item">
        <Link :href="route('operator.scan-detail', gi.id)" class="op-item-link">
          <div class="op-item-header">
            <span class="op-gi-number">{{ gi.gi_number }}</span>
            <span class="op-item-count">{{ gi.items_count }} {{ $t('operator.items') }}</span>
          </div>
          <div class="op-item-details">
            <span class="op-dept">{{ gi.department?.name }}</span>
            <span class="op-purpose">{{ gi.purpose }}</span>
          </div>
          <div class="op-item-footer">
            <span class="op-status-badge" :class="`op-status-${gi.status}`">
              {{ $t(`gi.status.${gi.status}`) }}
            </span>
          </div>
        </Link>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="op-empty">
      <div class="op-empty-icon">📭</div>
      <div class="op-empty-text">{{ $t('operator.noGi') }}</div>
    </div>
  </div>
</OperatorLayout>
</template>

<style scoped>
.op-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
}

.op-hdr {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.op-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--fg);
  letter-spacing: -0.02em;
}

.op-sub {
  font-size: 13px;
  color: var(--fg-2);
  margin-top: 3px;
}

.op-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  box-shadow: var(--shadow-sm);
}

.op-scan-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 2px solid var(--orange-500);
  background: rgba(249, 115, 22, 0.03);
}

.op-scan-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--orange-500);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.op-scan-input {
  padding: 12px 14px;
  font-size: 16px;
  font-weight: 600;
  background: var(--surface-2);
  border: 1px solid var(--orange-400);
  border-radius: 10px;
  color: var(--fg);
  outline: none;
  font-family: 'JetBrains Mono', monospace;
  transition: border-color 180ms;
}

.op-scan-input:focus {
  border-color: var(--orange-500);
}

.op-scan-hint {
  font-size: 11px;
  color: var(--fg-2);
}

.op-filter-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.op-search-input {
  padding: 10px 12px;
  font-size: 14px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--fg);
  outline: none;
  font-family: inherit;
  transition: border-color 180ms;
}

.op-search-input:focus {
  border-color: var(--orange-500);
}

.op-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.op-list-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow 150ms, border-color 150ms;
}

.op-list-item:hover {
  border-color: var(--orange-500);
  box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
}

.op-item-link {
  display: block;
  padding: 14px 16px;
  text-decoration: none;
  color: inherit;
}

.op-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.op-gi-number {
  font-size: 14px;
  font-weight: 700;
  color: var(--fg);
  font-family: 'JetBrains Mono', monospace;
}

.op-item-count {
  font-size: 12px;
  font-weight: 600;
  background: rgba(249, 115, 22, 0.1);
  color: var(--orange-500);
  padding: 2px 8px;
  border-radius: 6px;
}

.op-item-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-bottom: 8px;
}

.op-dept {
  font-size: 12px;
  font-weight: 600;
  color: var(--fg-2);
}

.op-purpose {
  font-size: 13px;
  color: var(--fg);
  line-height: 1.4;
  word-break: break-word;
}

.op-item-footer {
  display: flex;
  align-items: center;
  gap: 6px;
}

.op-status-badge {
  display: inline-flex;
  align-items: center;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.op-status-assigned {
  background: rgba(59, 130, 246, 0.15);
  color: #3b82f6;
}

.op-status-in_picking {
  background: rgba(249, 115, 22, 0.15);
  color: var(--orange-500);
}

.op-status-ready_to_pickup {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}

.op-status-completed {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.op-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
}

.op-empty-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.op-empty-text {
  font-size: 14px;
  color: var(--fg-2);
  font-weight: 500;
}

@media (max-width: 640px) {
  .op-wrap {
    max-width: 100%;
    padding: 12px;
  }

  .op-scan-input {
    font-size: 18px;
  }

  .op-gi-number {
    font-size: 13px;
  }

  .op-purpose {
    font-size: 12px;
  }
}
</style>
