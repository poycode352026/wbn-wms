<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { locale, t } = useI18n()

const props = defineProps({
    warehouse:  Object,
    locations:  Array,
    totalItems: Number,
    totalQty:   Number,
    totalRacks: Number,
    scannedAt:  String,
})

// Same palette as Locations page — warehouse ID → color
const WH_COLORS = [
    { bg:'rgba(59,130,246,.15)',  color:'#60a5fa' },
    { bg:'rgba(16,185,129,.15)', color:'#34d399' },
    { bg:'rgba(249,115,22,.15)', color:'#fb923c' },
    { bg:'rgba(139,92,246,.15)', color:'#a78bfa' },
    { bg:'rgba(234,179,8,.15)',  color:'#fbbf24' },
]
const whColor = computed(() => {
    const id = props.warehouse?.id ?? 1
    return WH_COLORS[(id - 1) % WH_COLORS.length]
})

function itemName(item) {
    if (!item) return '—'
    if (locale.value === 'zh' && item.name_zh) return item.name_zh
    if (locale.value === 'id' && item.name_id) return item.name_id
    return item.name_en ?? item.name_id ?? '—'
}

function variantLabel(v) {
    return [v.brand, v.model, v.size, v.color].filter(Boolean).join(' · ') || null
}
</script>

<template>
<AppLayout>
  <template #title>{{ warehouse.code }}</template>
  <template #breadcrumb>{{ warehouse.code }}</template>

  <!-- Warehouse header card -->
  <div class="wv-card">
    <div class="wv-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/>
      </svg>
    </div>
    <div class="wv-info">
      <div class="wv-code">{{ warehouse.code }}</div>
      <div class="wv-name">{{ warehouse.name }}</div>
      <div class="wv-loc" v-if="warehouse.location">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        {{ warehouse.location }}
      </div>
    </div>
    <div class="wv-stats">
      <div class="wv-stat">
        <div class="wv-stat-n">{{ totalRacks }}</div>
        <div class="wv-stat-l">{{ $t('wv.racks') }}</div>
      </div>
      <div class="wv-stat-sep"></div>
      <div class="wv-stat">
        <div class="wv-stat-n">{{ totalItems }}</div>
        <div class="wv-stat-l">SKU</div>
      </div>
      <div class="wv-stat-sep"></div>
      <div class="wv-stat">
        <div class="wv-stat-n">{{ totalQty.toLocaleString() }}</div>
        <div class="wv-stat-l">{{ $t('wv.qty') }}</div>
      </div>
    </div>
  </div>

  <!-- scanned at -->
  <div class="wv-scanned">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    {{ $t('wv.scannedAt') }}: {{ scannedAt }}
  </div>

  <!-- empty state: no racks at all -->
  <div v-if="!locations.length" class="wv-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="40" height="40"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg>
    <p>{{ $t('wv.noRacks') }}</p>
  </div>

  <!-- grouped by location — shows ALL racks, including empty ones -->
  <div v-else class="wv-sections">
    <div class="wv-section" v-for="loc in locations" :key="loc.id">
      <!-- rack header -->
      <div class="wv-rack-head">
        <div class="wv-rack-badge" :style="{ background: whColor.bg, color: whColor.color, borderColor: whColor.color + '44' }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
          {{ loc.code }}
        </div>
        <div class="wv-rack-name">{{ loc.name }}</div>
        <div class="wv-rack-ct" :class="{ 'wv-rack-ct-empty': !loc.items.length }">
          {{ loc.items.length ? loc.items.length + ' SKU' : $t('wv.emptyRack') }}
        </div>
      </div>

      <!-- empty rack -->
      <div v-if="!loc.items.length" class="wv-rack-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><rect x="3" y="3" width="18" height="6" rx="1.5"/><rect x="3" y="11" width="18" height="6" rx="1.5"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
        {{ $t('wv.empty') }}
      </div>

      <!-- items in this rack -->
      <div v-else class="wv-items">
        <div class="wv-item" v-for="entry in loc.items" :key="entry.id">
          <!-- foto / placeholder -->
          <div class="wv-img-wrap">
            <img v-if="entry.variant?.photo_path" :src="entry.variant.photo_path" :alt="entry.variant.sku" class="wv-img" />
            <div v-else class="wv-img-ph">WBN</div>
          </div>
          <!-- info -->
          <div class="wv-item-info">
            <div class="wv-item-top">
              <span class="wv-cat" v-if="entry.variant?.item?.category">{{ entry.variant.item.category.code }}</span>
              <span class="wv-sku">{{ entry.variant?.sku ?? '—' }}</span>
            </div>
            <div class="wv-item-name">{{ itemName(entry.variant?.item) }}</div>
            <div class="wv-item-variant" v-if="entry.variant && variantLabel(entry.variant)">
              {{ variantLabel(entry.variant) }}
            </div>
            <div class="wv-item-pn" v-if="entry.variant?.item?.part_number">
              {{ entry.variant.item.part_number }}
            </div>
          </div>
          <!-- qty -->
          <div class="wv-qty-block">
            <div class="wv-qty-on-hand">{{ entry.qty_on_hand.toLocaleString() }}</div>
            <div class="wv-qty-uom">{{ entry.variant?.item?.base_uom ?? '' }}</div>
            <div class="wv-qty-avail" v-if="entry.qty_reserved > 0">
              <span class="wv-reserved">{{ entry.qty_reserved }} reserved</span>
              <span class="wv-avail">{{ entry.qty_available }} avail</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</AppLayout>
</template>

<style scoped>
/* warehouse header card */
.wv-card {
  background: linear-gradient(135deg, rgba(59,130,246,.15), rgba(37,99,235,.08));
  border: 1px solid rgba(59,130,246,.3);
  border-radius: 16px; padding: 20px 24px;
  display: flex; align-items: center; gap: 18px;
}
.wv-icon {
  width: 52px; height: 52px; border-radius: 12px; flex-shrink: 0;
  background: rgba(59,130,246,.15); display: grid; place-items: center;
  color: #60a5fa;
}
.wv-icon svg { width: 26px; height: 26px }
.wv-info { flex: 1; min-width: 0 }
.wv-code { font-size: 22px; font-weight: 800; letter-spacing: -.02em; color: var(--fg) }
.wv-name { font-size: 14px; color: var(--fg-2); margin-top: 2px }
.wv-loc {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; color: var(--fg-dim); margin-top: 4px;
}
.wv-stats { display: flex; align-items: center; gap: 16px; flex-shrink: 0 }
.wv-stat { text-align: center }
.wv-stat-n { font-size: 22px; font-weight: 800; color: var(--fg) }
.wv-stat-l { font-size: 11px; color: var(--fg-dim); font-weight: 600; text-transform: uppercase; letter-spacing: .06em }
.wv-stat-sep { width: 1px; height: 32px; background: var(--border-2) }

/* scanned at */
.wv-scanned {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--fg-dim);
}

/* empty */
.wv-empty {
  text-align: center; padding: 60px 20px;
  color: var(--fg-dim); display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.wv-empty p { font-size: 14px }

/* sections */
.wv-sections { display: flex; flex-direction: column; gap: 16px }
.wv-section { display: flex; flex-direction: column; gap: 6px }

/* rack header */
.wv-rack-head {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 14px; background: var(--surface-2);
  border: 1px solid var(--border); border-radius: 10px;
}
.wv-rack-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 700;
  font-family: monospace; letter-spacing: .06em;
  border: 1px solid transparent;
}
.wv-rack-name { font-size: 13px; font-weight: 600; color: var(--fg); flex: 1 }
.wv-rack-ct {
  font-size: 11px; font-weight: 700; color: var(--fg-dim);
  background: var(--surface-3); padding: 2px 8px; border-radius: 999px;
  border: 1px solid var(--border);
}

/* empty rack row */
.wv-rack-ct-empty { background: transparent; color: var(--fg-dim); border-color: var(--border) }
.wv-rack-empty {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; margin-left: 8px;
  font-size: 12px; color: var(--fg-dim);
  background: var(--surface-2); border: 1px dashed var(--border);
  border-radius: 8px;
}

/* item list */
.wv-items { display: flex; flex-direction: column; gap: 8px; padding-left: 8px }
.wv-item {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 12px 14px;
  display: flex; align-items: center; gap: 12px;
  transition: box-shadow 150ms;
}
.wv-item:hover { box-shadow: var(--shadow-sm) }

/* foto */
.wv-img-wrap { flex-shrink: 0 }
.wv-img { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border) }
.wv-img-ph {
  width: 44px; height: 44px; border-radius: 8px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  display: grid; place-items: center;
  font-size: 10px; font-weight: 800; color: #fff; letter-spacing: .05em;
}

/* info */
.wv-item-info { flex: 1; min-width: 0 }
.wv-item-top { display: flex; align-items: center; gap: 6px; margin-bottom: 3px }
.wv-cat {
  padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;
  background: rgba(59,130,246,.12); color: #60a5fa;
}
.wv-sku {
  font-family: monospace; font-size: 11.5px; font-weight: 700;
  color: #60a5fa; background: rgba(59,130,246,.08);
  padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(59,130,246,.18);
}
.wv-item-name { font-size: 13.5px; font-weight: 600; color: var(--fg) }
.wv-item-variant { font-size: 12px; color: var(--fg-2); margin-top: 2px }
.wv-item-pn { font-size: 11px; color: var(--fg-dim); font-family: monospace; margin-top: 2px }

/* qty */
.wv-qty-block { text-align: right; flex-shrink: 0 }
.wv-qty-on-hand { font-size: 20px; font-weight: 800; color: var(--fg); line-height: 1 }
.wv-qty-uom { font-size: 11px; color: var(--fg-dim); font-weight: 600; text-transform: uppercase; margin-top: 2px }
.wv-qty-avail { margin-top: 4px; display: flex; flex-direction: column; gap: 1px }
.wv-reserved { font-size: 10px; color: #f87171 }
.wv-avail { font-size: 10px; color: #34d399 }
</style>
