<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransactionChart from '@/Components/TransactionChart.vue'

const { t } = useI18n()
const page  = usePage()
const user  = computed(() => page.props.auth?.user ?? { name: 'User' })
const firstName = computed(() => (user.value.name || 'User').split(' ')[0])

const greeting = computed(() => {
    const h = new Date().getHours()
    if (h < 12) return t('dash.goodMorning')
    if (h < 17) return t('dash.goodAfternoon')
    return t('dash.goodEvening')
})

const dateStr = computed(() => {
    const d  = new Date()
    const wk = ['SUN','MON','TUE','WED','THU','FRI','SAT'][d.getDay()]
    const mo = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'][d.getMonth()]
    return `${wk} · ${String(d.getDate()).padStart(2,'0')} ${mo} ${d.getFullYear()}`
})
</script>
<template>
  <AppLayout>
    <template #title>{{ $t('dash.title') }}</template>
    <template #breadcrumb>{{ $t('dash.title') }}</template>

    <!-- page header -->
    <div class="page-head">
      <div>
        <h1>{{ $t('dash.title') }}</h1>
        <div class="greeting">{{ greeting }}, {{ firstName }} 👋</div>
      </div>
      <div class="dt">{{ dateStr }}</div>
    </div>

    <!-- Row 1: stat cards -->
    <section class="dash-row cards4">
      <div class="stat b-orange">
        <div class="stat-top">
          <div class="stat-ico orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8L12 13 3 8l9-5 9 5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg></div>
          <span class="stat-trend up"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>+12</span>
        </div>
        <div class="stat-num">1,284</div>
        <div class="stat-lbl">{{ $t('dash.totalItems') }}</div>
        <div class="stat-sub"><strong>+12</strong> {{ $t('dash.totalItemsSub') }}</div>
      </div>

      <div class="stat b-blue">
        <div class="stat-top">
          <div class="stat-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21V12h6v9"/></svg></div>
          <span class="stat-trend up"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>+3.4%</span>
        </div>
        <div class="stat-num">48,320<span class="stat-unit">{{ $t('dash.units') }}</span></div>
        <div class="stat-lbl">{{ $t('dash.totalStock') }}</div>
        <div class="stat-sub">{{ $t('dash.totalStockSub', { n: 3 }) }}</div>
      </div>

      <div class="stat b-amber">
        <div class="stat-top">
          <div class="stat-ico amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
          <span class="stat-trend up"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>+8</span>
        </div>
        <div class="stat-num">47</div>
        <div class="stat-lbl">{{ $t('dash.transToday') }}</div>
        <div class="stat-sub">{{ $t('dash.transTodaySub', { gr: 23, gi: 18, tr: 6 }) }}</div>
      </div>

      <div class="stat b-red">
        <div class="stat-top">
          <div class="stat-ico red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
          <span class="stat-trend dn"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>-2</span>
        </div>
        <div class="stat-num">8 <span class="stat-unit">{{ $t('dash.items') }}</span></div>
        <div class="stat-lbl">{{ $t('dash.lowStock') }}</div>
        <div class="stat-sub">{{ $t('dash.lowStockSub') }}</div>
      </div>
    </section>

    <!-- Row 2: chart + recent transactions -->
    <section class="dash-row split-60-40">
      <TransactionChart />

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">{{ $t('dash.recentTx') }}</span>
          <a class="panel-link" href="#">{{ $t('dash.viewAll') }}</a>
        </div>
        <table class="tbl">
          <thead><tr>
            <th>{{ $t('dash.colType') }}</th>
            <th>{{ $t('dash.colDoc') }}</th>
            <th class="right">{{ $t('dash.colItems') }}</th>
            <th>{{ $t('dash.colStatus') }}</th>
            <th class="right">{{ $t('dash.colTime') }}</th>
          </tr></thead>
          <tbody>
            <tr><td><span class="pill gr">GR</span></td><td class="mono">GR-20260509-014</td><td class="right num">28</td><td><span class="pill pn">{{ $t('status.pending') }}</span></td><td class="right dim sm">12m ago</td></tr>
            <tr><td><span class="pill gi">GI</span></td><td class="mono">GI-20260509-031</td><td class="right num">12</td><td><span class="pill ok">{{ $t('status.approved') }}</span></td><td class="right dim sm">28m ago</td></tr>
            <tr><td><span class="pill trf">TRF</span></td><td class="mono">TRF-1180</td><td class="right num">6</td><td><span class="pill ok">{{ $t('status.approved') }}</span></td><td class="right dim sm">1h ago</td></tr>
            <tr><td><span class="pill gr">GR</span></td><td class="mono">GR-20260509-013</td><td class="right num">42</td><td><span class="pill ok">{{ $t('status.approved') }}</span></td><td class="right dim sm">2h ago</td></tr>
            <tr><td><span class="pill gi">GI</span></td><td class="mono">GI-20260509-030</td><td class="right num">9</td><td><span class="pill dr">{{ $t('status.draft') }}</span></td><td class="right dim sm">3h ago</td></tr>
            <tr><td><span class="pill trf">TRF</span></td><td class="mono">TRF-1179</td><td class="right num">14</td><td><span class="pill ok">{{ $t('status.approved') }}</span></td><td class="right dim sm">4h ago</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Row 3: warehouse capacity + low stock -->
    <section class="dash-row split-50">
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">{{ $t('dash.whCapacity') }}</span>
          <a class="panel-link" href="#">{{ $t('dash.manage') }}</a>
        </div>
        <div class="wh-card">
          <div class="wh-row"><div><div class="wh-name">WH-01 · Main Storage</div><div class="wh-loc">Halmahera Tengah · Block A</div></div><div class="wh-pct" style="color:#f59e0b">85%</div></div>
          <div class="wh-bar warn"><i style="width:85%"></i></div>
          <div class="wh-racks">A-01: <span class="warn">85%</span> · A-02: 42% · A-03: 67% · A-04: 71%</div>
        </div>
        <div class="wh-card">
          <div class="wh-row"><div><div class="wh-name">WH-02 · Spare Parts</div><div class="wh-loc">Lelilef · Workshop Yard</div></div><div class="wh-pct" style="color:#ef4444">91%</div></div>
          <div class="wh-bar crit"><i style="width:91%"></i></div>
          <div class="wh-racks">B-01: <span class="crit">91%</span> · B-02: 88% · B-03: 76% · B-04: 54%</div>
        </div>
        <div class="wh-card" style="margin-bottom:0">
          <div class="wh-row"><div><div class="wh-name">WH-03 · Heavy Equipment</div><div class="wh-loc">Pit-South · Open Yard</div></div><div class="wh-pct" style="color:#10b981">58%</div></div>
          <div class="wh-bar"><i style="width:58%"></i></div>
          <div class="wh-racks">C-01: 62% · C-02: 51% · C-03: 60%</div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div style="display:flex;align-items:center;gap:8px">
            <span class="panel-title">{{ $t('dash.lowStockTable') }}</span>
            <span class="pill" style="background:rgba(239,68,68,.14);color:#ef4444">8 {{ $t('dash.items') }}</span>
          </div>
          <a class="panel-link" href="#">{{ $t('dash.viewAll') }}</a>
        </div>
        <table class="tbl">
          <thead><tr>
            <th>{{ $t('dash.colCode') }}</th>
            <th>{{ $t('dash.colItem') }}</th>
            <th class="right">{{ $t('dash.colSoh') }}</th>
            <th class="right">{{ $t('dash.colMin') }}</th>
            <th>{{ $t('dash.colRack') }}</th>
            <th></th>
          </tr></thead>
          <tbody>
            <tr><td class="mono">SP-0421</td><td>Hydraulic filter, 24V</td><td class="right low-soh num">3</td><td class="right num dim">15</td><td class="mono dim">B-01</td><td class="right"><button class="reorder-btn">{{ $t('dash.reorder') }}</button></td></tr>
            <tr><td class="mono">SP-1187</td><td>Bearing, taper roller 32314</td><td class="right low-soh num">5</td><td class="right num dim">20</td><td class="mono dim">B-02</td><td class="right"><button class="reorder-btn">{{ $t('dash.reorder') }}</button></td></tr>
            <tr><td class="mono">CN-0033</td><td>Steel cable, 16mm × 50m</td><td class="right low-soh num">2</td><td class="right num dim">10</td><td class="mono dim">A-04</td><td class="right"><button class="reorder-btn">{{ $t('dash.reorder') }}</button></td></tr>
            <tr><td class="mono">PPE-0119</td><td>Safety helmet, hi-viz orange</td><td class="right low-soh num">12</td><td class="right num dim">50</td><td class="mono dim">A-02</td><td class="right"><button class="reorder-btn">{{ $t('dash.reorder') }}</button></td></tr>
            <tr><td class="mono">LB-0207</td><td>Engine oil 15W-40, 20L drum</td><td class="right low-soh num">7</td><td class="right num dim">25</td><td class="mono dim">B-03</td><td class="right"><button class="reorder-btn">{{ $t('dash.reorder') }}</button></td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppLayout>
</template>
<style scoped>
.page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:4px}
.page-head h1{margin:0;font-size:26px;font-weight:700;letter-spacing:-.025em;color:var(--fg)}
.greeting{font-size:14px;color:var(--fg-2);margin-top:6px}
.dt{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--fg-2);background:var(--surface);border:1px solid var(--border);padding:6px 12px;border-radius:8px;letter-spacing:.04em;white-space:nowrap}
.dash-row{display:grid;gap:18px}
.cards4{grid-template-columns:repeat(4,minmax(0,1fr))}
.split-60-40{grid-template-columns:minmax(0,3fr) minmax(0,2fr)}
.split-50{grid-template-columns:1fr 1fr}
.stat{background:var(--surface);border:1px solid var(--border);border-left:4px solid #3b82f6;border-radius:14px;padding:18px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;transition:transform 220ms ease,box-shadow 220ms ease}
.stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stat.b-orange{border-left-color:#f97316}
.stat.b-blue{border-left-color:#3b82f6}
.stat.b-amber{border-left-color:#f59e0b}
.stat.b-red{border-left-color:#ef4444;box-shadow:var(--shadow-sm),0 0 0 1px rgba(239,68,68,.15),0 12px 30px -12px rgba(239,68,68,.25)}
.stat-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px}
.stat-ico{width:42px;height:42px;border-radius:10px;display:grid;place-items:center}
.stat-ico svg{width:20px;height:20px}
.stat-ico.orange{background:rgba(249,115,22,.12);color:#f97316}
.stat-ico.blue{background:rgba(59,130,246,.12);color:#3b82f6}
.stat-ico.amber{background:rgba(245,158,11,.14);color:#f59e0b}
.stat-ico.red{background:rgba(239,68,68,.12);color:#ef4444}
.stat-trend{font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px}
.stat-trend.up{background:rgba(16,185,129,.12);color:#10b981}
.stat-trend.dn{background:rgba(239,68,68,.12);color:#ef4444}
.stat-num{font-size:30px;font-weight:700;letter-spacing:-.025em;line-height:1;color:var(--fg)}
.stat-unit{font-size:14px;font-weight:600;color:var(--fg-2);margin-left:6px;letter-spacing:0}
.stat-lbl{font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--fg-2);margin-top:8px}
.stat-sub{font-size:12px;color:var(--fg-2);margin-top:6px}
.stat-sub strong{color:var(--fg)}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:var(--shadow-sm)}
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap}
.panel-title{font-size:15px;font-weight:700;letter-spacing:-.01em;color:var(--fg)}
.panel-meta{font-size:11.5px;color:var(--fg-2);font-weight:500;margin-left:8px}
.panel-link{color:#f97316;font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap}
.panel-link:hover{text-decoration:underline}
.tabs{display:inline-flex;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:3px;gap:2px}
.tabs button{appearance:none;border:0;background:transparent;color:var(--fg-2);padding:4px 10px;font-size:11.5px;font-weight:600;border-radius:6px;cursor:pointer;font-family:inherit;transition:color 200ms ease,background 200ms ease}
.tabs button.active{background:var(--surface);color:var(--fg);box-shadow:var(--shadow-sm)}
.tabs button:hover:not(.active){color:var(--fg)}
.legend{display:inline-flex;gap:14px;font-size:11.5px;color:var(--fg-2);align-items:center}
.legend span{display:flex;align-items:center;gap:5px}
.legend i{display:inline-block;width:10px;height:10px;border-radius:3px;flex-shrink:0}
.lg-blue{background:#3b82f6}
.lg-orange{background:#f97316}
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.tbl th{text-align:left;font-size:10.5px;font-weight:700;color:var(--fg-2);letter-spacing:.1em;text-transform:uppercase;padding:8px 10px;border-bottom:1px solid var(--border-soft);white-space:nowrap}
.tbl td{padding:11px 10px;border-bottom:1px solid var(--border-soft);color:var(--fg)}
.tbl tr:last-child td{border-bottom:0}
.tbl tr:hover td{background:var(--hover)}
.tbl .mono{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--fg-2)}
.tbl .dim{color:var(--fg-2)}
.tbl .num{font-variant-numeric:tabular-nums}
.tbl .right{text-align:right}
.tbl .sm{font-size:12px}
.pill{display:inline-flex;align-items:center;font-size:10.5px;font-weight:700;letter-spacing:.04em;padding:3px 8px;border-radius:999px;text-transform:uppercase}
.pill.gr{background:rgba(59,130,246,.14);color:#3b82f6}
.pill.gi{background:rgba(249,115,22,.14);color:#f97316}
.pill.trf{background:var(--surface-2);color:var(--fg-2);border:1px solid var(--border)}
.pill.ok{background:rgba(16,185,129,.14);color:#10b981}
.pill.pn{background:rgba(234,179,8,.18);color:#a16207}
.pill.dr{background:var(--surface-2);color:var(--fg-2);border:1px solid var(--border)}
.wh-card{padding:14px;border:1px solid var(--border-soft);border-radius:10px;margin-bottom:10px;background:var(--surface)}
.wh-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.wh-name{font-size:13.5px;font-weight:700;color:var(--fg)}
.wh-loc{font-size:11.5px;color:var(--fg-2);margin-top:2px}
.wh-pct{font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
.wh-bar{height:8px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:999px;overflow:hidden}
.wh-bar > i{display:block;height:100%;border-radius:999px;background:#10b981;transition:width 380ms ease}
.wh-bar.warn > i{background:#f59e0b}
.wh-bar.crit > i{background:#ef4444}
.wh-racks{margin-top:8px;font-size:11.5px;color:var(--fg-2);font-family:'JetBrains Mono',monospace;letter-spacing:.02em}
.wh-racks .crit{color:#ef4444}
.wh-racks .warn{color:#f59e0b}
.low-soh{color:#ef4444;font-weight:700}
.reorder-btn{appearance:none;border:1px solid #f97316;background:transparent;color:#f97316;font-size:11px;font-weight:700;letter-spacing:.04em;padding:5px 10px;border-radius:6px;cursor:pointer;font-family:inherit;transition:background 200ms ease,color 200ms ease}
.reorder-btn:hover{background:#f97316;color:#fff}
@media(max-width:1100px){
  .cards4{grid-template-columns:repeat(2,1fr)}
  .split-60-40,.split-50{grid-template-columns:1fr}
}
@media(max-width:760px){
  .cards4{grid-template-columns:1fr 1fr}
  .page-head{flex-direction:column;align-items:flex-start}
}
</style>