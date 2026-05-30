<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { locale } = useI18n()

const props = defineProps({
    location:  Object,
    items:     Array,
    scannedAt: String,
})

// Same palette as all other pages — warehouse ID → color
const WH_COLORS = [
    { bg:'rgba(59,130,246,.15)',  color:'#60a5fa', grad:'linear-gradient(135deg,#3b82f6,#1d4ed8)' },
    { bg:'rgba(16,185,129,.15)', color:'#34d399', grad:'linear-gradient(135deg,#10b981,#059669)' },
    { bg:'rgba(249,115,22,.15)', color:'#fb923c', grad:'linear-gradient(135deg,#f97316,#ea580c)' },
    { bg:'rgba(139,92,246,.15)', color:'#a78bfa', grad:'linear-gradient(135deg,#8b5cf6,#7c3aed)' },
    { bg:'rgba(234,179,8,.15)',  color:'#fbbf24', grad:'linear-gradient(135deg,#eab308,#d97706)' },
]
const whColor = computed(() => {
    const id = props.location?.warehouse?.id ?? 1
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

const totalItems = computed(() => props.items.length)
const totalQty   = computed(() => props.items.reduce((s, i) => s + i.qty_on_hand, 0))
</script>

<template>
<AppLayout>
  <template #title>Rak {{ location.code }}</template>
  <template #breadcrumb>Rak {{ location.code }}</template>

  <!-- Rack header card — warehouse color -->
  <div class="rv-rack-card"
    :style="{ background: whColor.bg, borderColor: whColor.color + '44' }">
    <div class="rv-rack-icon"
      :style="{ background: whColor.bg, color: whColor.color }">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="6" rx="1.5"/>
        <rect x="3" y="11" width="18" height="6" rx="1.5"/>
        <line x1="3" y1="21" x2="21" y2="21"/>
      </svg>
    </div>
    <div class="rv-rack-info">
      <div class="rv-rack-code">{{ location.code }}</div>
      <div class="rv-rack-name">{{ location.name }}</div>
      <div class="rv-rack-wh" v-if="location.warehouse">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg>
        {{ location.warehouse.code }} — {{ location.warehouse.name }}
      </div>
    </div>
    <div class="rv-rack-stats">
      <div class="rv-stat">
        <div class="rv-stat-n">{{ totalItems }}</div>
        <div class="rv-stat-l">SKU</div>
      </div>
      <div class="rv-stat-sep"></div>
      <div class="rv-stat">
        <div class="rv-stat-n">{{ totalQty.toLocaleString() }}</div>
        <div class="rv-stat-l">Total Qty</div>
      </div>
    </div>
  </div>

  <!-- scanned at -->
  <div class="rv-scanned">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Terakhir dipindai: {{ scannedAt }}
  </div>

  <!-- item list -->
  <div v-if="!items.length" class="rv-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="40" height="40"><path d="m7.5 4.27 9 5.15"/><path d="M21 8L12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
    <p>Rak ini kosong</p>
  </div>

  <div v-else class="rv-list">
    <div class="rv-item" v-for="entry in items" :key="entry.id">
      <!-- foto / placeholder -->
      <div class="rv-img-wrap">
        <img v-if="entry.variant?.photo_path" :src="entry.variant.photo_path" :alt="entry.variant.sku" class="rv-img" />
        <div v-else class="rv-img-ph" :style="{ background: whColor.grad }">WBN</div>
      </div>

      <!-- info -->
      <div class="rv-item-info">
        <div class="rv-item-top">
          <span class="rv-cat" v-if="entry.variant?.item?.category">{{ entry.variant.item.category.code }}</span>
          <span class="rv-sku"
            :style="{ color: whColor.color, background: whColor.bg, borderColor: whColor.color + '33' }">
            {{ entry.variant?.sku ?? '—' }}
          </span>
        </div>
        <div class="rv-item-name">{{ itemName(entry.variant?.item) }}</div>
        <div class="rv-item-variant" v-if="entry.variant && variantLabel(entry.variant)">
          {{ variantLabel(entry.variant) }}
        </div>
        <div class="rv-item-pn" v-if="entry.variant?.item?.part_number">
          {{ entry.variant.item.part_number }}
        </div>
      </div>

      <!-- qty -->
      <div class="rv-qty-block">
        <div class="rv-qty-on-hand">{{ entry.qty_on_hand.toLocaleString() }}</div>
        <div class="rv-qty-uom">{{ entry.variant?.item?.base_uom ?? '' }}</div>
        <div class="rv-qty-avail" v-if="entry.qty_reserved > 0">
          <span class="rv-reserved">{{ entry.qty_reserved }} reserved</span>
          <span class="rv-avail">{{ entry.qty_available }} avail</span>
        </div>
      </div>
    </div>
  </div>

</AppLayout>
</template>

<style scoped>
/* rack header card */
.rv-rack-card {
  border: 1px solid transparent;
  border-radius: 16px; padding: 20px 24px;
  display: flex; align-items: center; gap: 18px;
}
.rv-rack-icon {
  width: 52px; height: 52px; border-radius: 12px; flex-shrink: 0;
  display: grid; place-items: center;
}
.rv-rack-icon svg { width: 26px; height: 26px }
.rv-rack-info { flex: 1; min-width: 0 }
.rv-rack-code { font-size: 22px; font-weight: 800; letter-spacing: -.02em; color: var(--fg) }
.rv-rack-name { font-size: 14px; color: var(--fg-2); margin-top: 2px }
.rv-rack-wh {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; color: var(--fg-dim); margin-top: 4px;
}
.rv-rack-stats { display: flex; align-items: center; gap: 16px; flex-shrink: 0 }
.rv-stat { text-align: center }
.rv-stat-n { font-size: 22px; font-weight: 800; color: var(--fg) }
.rv-stat-l { font-size: 11px; color: var(--fg-dim); font-weight: 600; text-transform: uppercase; letter-spacing: .06em }
.rv-stat-sep { width: 1px; height: 32px; background: var(--border-2) }

/* scanned at */
.rv-scanned {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--fg-dim);
}

/* empty */
.rv-empty {
  text-align: center; padding: 60px 20px;
  color: var(--fg-dim); display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.rv-empty p { font-size: 14px }

/* item list */
.rv-list { display: flex; flex-direction: column; gap: 10px }
.rv-item {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 14px 16px;
  display: flex; align-items: center; gap: 14px;
  transition: box-shadow 150ms;
}
.rv-item:hover { box-shadow: var(--shadow-sm) }

/* foto */
.rv-img-wrap { flex-shrink: 0 }
.rv-img { width: 52px; height: 52px; object-fit: cover; border-radius: 10px; border: 1px solid var(--border) }
.rv-img-ph {
  width: 52px; height: 52px; border-radius: 10px;
  display: grid; place-items: center;
  font-size: 11px; font-weight: 800; color: #fff; letter-spacing: .05em;
}

/* info */
.rv-item-info { flex: 1; min-width: 0 }
.rv-item-top { display: flex; align-items: center; gap: 6px; margin-bottom: 3px }
.rv-cat {
  padding: 2px 7px; border-radius: 5px; font-size: 10.5px; font-weight: 700;
  background: rgba(59,130,246,.12); color: #60a5fa;
}
.rv-sku {
  font-family: monospace; font-size: 12px; font-weight: 700;
  padding: 2px 7px; border-radius: 5px; border: 1px solid transparent;
}
.rv-item-name { font-size: 14px; font-weight: 600; color: var(--fg) }
.rv-item-variant { font-size: 12px; color: var(--fg-2); margin-top: 2px }
.rv-item-pn { font-size: 11px; color: var(--fg-dim); font-family: monospace; margin-top: 2px }

/* qty */
.rv-qty-block { text-align: right; flex-shrink: 0 }
.rv-qty-on-hand { font-size: 22px; font-weight: 800; color: var(--fg); line-height: 1 }
.rv-qty-uom { font-size: 11px; color: var(--fg-dim); font-weight: 600; text-transform: uppercase; margin-top: 2px }
.rv-qty-avail { margin-top: 4px; display: flex; flex-direction: column; gap: 1px }
.rv-reserved { font-size: 10.5px; color: #f87171}
.rv-avail { font-size: 10.5px; color: #34d399 }
</style>
