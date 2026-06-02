<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
    Chart, LineElement, PointElement, LineController,
    CategoryScale, LinearScale, Filler, Tooltip, Legend,
} from 'chart.js'

Chart.register(
    LineElement, PointElement, LineController,
    CategoryScale, LinearScale, Filler, Tooltip, Legend
)


const { t, locale, tm } = useI18n()

const props = defineProps({
    chartData: { type: Object, default: () => ({}) },
})

function getChartLabels(range) {
    if (range === '7')  return [...tm('chart.week')]
    if (range === '30') return [...tm('chart.week30')]
    return [...tm('chart.week90')]
}

function getRangeData(range) {
    const d = props.chartData?.[range]
    if (d) return { inn: d.gr, out: d.gi }
    // fallback zeros
    const len = range === '7' ? 7 : range === '30' ? 4 : 3
    return { inn: Array(len).fill(0), out: Array(len).fill(0) }
}

const canvasRef  = ref(null)
const activeRange = ref('7')
const rangeLbl = computed(() => ({
    '7':  t('dash.last7'),
    '30': t('dash.last30'),
    '90': t('dash.last3m'),
}))
let   chart       = null

function themeColors() {
    const dark = document.documentElement.getAttribute('data-theme') !== 'light'
    return {
        grid:   dark ? 'rgba(148,184,255,.06)'  : 'rgba(15,23,42,.06)',
        tick:   dark ? '#64748b'                : '#94a3b8',
        tipBg:  dark ? '#1e2535'                : '#ffffff',
        tipFg:  dark ? '#e6edf7'                : '#0b1530',
        tipFg2: dark ? '#94a3b8'                : '#475569',
        tipBd:  dark ? '#2a3550'                : '#e5e7eb',
    }
}

function initChart() {
    if (!canvasRef.value) {
        console.warn('[TransactionChart] canvas not ready')
        return
    }
    if (chart) { chart.destroy(); chart = null }

    const d = getRangeData(activeRange.value)
    const c = themeColors()

    chart = new Chart(canvasRef.value, {
        type: 'line',
        data: {
            labels: getChartLabels(activeRange.value),
            datasets: [
                {
                    label: t('dash.grIn'),
                    data: [...d.inn],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#3b82f6',
                    tension: .4,
                    fill: true,
                },
                {
                    label: t('dash.giOut'),
                    data: [...d.out],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#f97316',
                    tension: .4,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeInOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: false,
                    labels: {
                        generateLabels: () => [
                            { text: t('dash.grIn'),  fillStyle: '#3b82f6', strokeStyle: '#3b82f6', lineWidth: 2, hidden: false, datasetIndex: 0 },
                            { text: t('dash.giOut'), fillStyle: '#f97316', strokeStyle: '#f97316', lineWidth: 2, hidden: false, datasetIndex: 1 },
                        ],
                    },
                },
                tooltip: {
                    backgroundColor: c.tipBg,
                    titleColor:      c.tipFg,
                    bodyColor:       c.tipFg2,
                    borderColor:     c.tipBd,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    boxWidth: 10,
                    boxHeight: 10,
                    callbacks: {
                        title: (items) => {
                            const labels = getChartLabels(activeRange.value)
                            return labels[items[0].dataIndex] ?? items[0].label
                        },
                        label: (item) => {
                            const label = item.datasetIndex === 0
                                ? t('dash.grIn')
                                : t('dash.giOut')
                            return ` ${label}: ${item.raw}`
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid:   { display: false },
                    ticks:  { color: c.tick, font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                    border: { display: false },
                },
                y: {
                    grid:   { color: c.grid },
                    ticks:  { color: c.tick, font: { size: 11, family: 'Inter, system-ui, sans-serif' } },
                    border: { display: false },
                },
            },
        },
    })
}

function switchRange(r) {
    activeRange.value = r
    if (!chart) return
    const d = getRangeData(r)
    chart.data.labels            = getChartLabels(r)
    chart.data.datasets[0].data  = [...d.inn]
    chart.data.datasets[1].data  = [...d.out]
    chart.update({ duration: 600, easing: 'easeInOutQuart' })
}

watch(locale, async () => {
    if (chart) { chart.destroy(); chart = null }
    await nextTick()
    initChart()
})

// Double nextTick: first tick lets AppLayout finish rendering its slot,
// second tick ensures the browser has laid out the canvas dimensions.
onMounted(async () => {
    await nextTick()
    await nextTick()
    initChart()
})
</script>

<template>
  <div class="tc-panel">
    <div class="tc-head">
      <div>
        <span class="tc-title">{{ t('dash.txOverview') }}</span>
        <span class="tc-meta">{{ rangeLbl[activeRange] }}</span>
      </div>
      <div class="tc-controls">
        <div class="tc-legend">
          <span><i class="dot-blue"></i>{{ t('dash.grIn') }}</span>
          <span><i class="dot-orange"></i>{{ t('dash.giOut') }}</span>
        </div>
        <div class="tc-tabs">
          <button
            v-for="r in ['7','30','90']"
            :key="r"
            :class="{ active: activeRange === r }"
            @click="switchRange(r)"
            type="button"
          >{{ { '7': t('dash.d7'), '30': t('dash.d30'), '90': t('dash.m3') }[r] }}</button>
        </div>
      </div>
    </div>

    <div class="tc-wrap">
      <canvas ref="canvasRef" style="display:block;width:100%;height:280px"></canvas>
    </div>
  </div>
</template>

<style scoped>
.tc-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
    box-shadow: var(--shadow-sm);
}
.tc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    gap: 12px;
    flex-wrap: wrap;
}
.tc-title {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -.01em;
    color: var(--fg);
}
.tc-meta {
    font-size: 11.5px;
    color: var(--fg-2);
    font-weight: 500;
    margin-left: 8px;
}
.tc-controls {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.tc-legend {
    display: inline-flex;
    gap: 14px;
    font-size: 11.5px;
    color: var(--fg-2);
    align-items: center;
}
.tc-legend span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.tc-legend i {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
}
.dot-blue   { background: #3b82f6; }
.dot-orange { background: #f97316; }
.tc-tabs {
    display: inline-flex;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 3px;
    gap: 2px;
}
.tc-tabs button {
    appearance: none;
    border: 0;
    background: transparent;
    color: var(--fg-2);
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    transition: color 200ms ease, background 200ms ease;
}
.tc-tabs button.active {
    background: var(--surface);
    color: var(--fg);
    box-shadow: var(--shadow-sm);
}
.tc-tabs button:hover:not(.active) { color: var(--fg); }
.tc-wrap {
    position: relative;
    height: 280px;
    width: 100%;
}
.tc-wrap canvas {
    position: absolute;
    inset: 0;
}
</style>