<?php
namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // ── Stat cards ─────────────────────────────────────────────────────────
        $totalItems      = Item::where('is_active', true)->count();
        $totalVariants   = ItemVariant::count();
        $totalStockQty   = (float) StockLedger::sum('qty_on_hand');
        $totalWarehouses = Warehouse::where('is_active', true)->count();

        $today   = Carbon::today();
        $grToday = GoodsReceipt::where('status', 'completed')->whereDate('updated_at', $today)->count();
        $giToday = GoodsIssue::where('status', 'completed')->whereDate('updated_at', $today)->count();

        // Low stock — per variant (each active variant checked independently)
        $allActiveItems = Item::where('is_active', true)->where('minimum_stock', '>', 0)
            ->with(['variants' => fn($q) => $q->where('is_active', true)
                ->select('id','item_id','sku','brand','model','size','color','is_active')])
            ->get();

        $lowStockCount = 0;

        // ── Warehouse capacity ─────────────────────────────────────────────────
        $warehouseStats = Warehouse::where('is_active', true)
            ->withCount('locations')
            ->get()
            ->map(fn($wh) => [
                'id'        => $wh->id,
                'code'      => $wh->code,
                'name'      => $wh->name,
                'location'  => $wh->location,
                'rackCount' => $wh->locations_count,
                'totalQty'  => (float) StockLedger::where('warehouse_id', $wh->id)->sum('qty_on_hand'),
            ]);

        // ── Chart data ─────────────────────────────────────────────────────────
        $chartData = [
            '7'  => $this->buildChart('day',   7),
            '30' => $this->buildChart('week',  4),
            '90' => $this->buildChart('month', 3),
        ];

        // ── Recent transactions (GR + GI mixed, latest 10 — completed only) ────
        $recentGR = GoodsReceipt::where('status', 'completed')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'gr_number', 'status', 'created_at'])
            ->map(fn($r) => [
                'type'   => 'GR',
                'doc'    => $r->gr_number,
                'items'  => $r->items_count,
                'status' => $r->status,
                'route'  => '/goods-receipts/' . $r->id,
                'date'   => $r->created_at->toISOString(),
                'ts'     => $r->created_at->timestamp,
            ]);

        $recentGI = GoodsIssue::where('status', 'completed')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'gi_number', 'status', 'created_at'])
            ->map(fn($r) => [
                'type'   => 'GI',
                'doc'    => $r->gi_number,
                'items'  => $r->items_count,
                'status' => $r->status,
                'route'  => '/goods-issues/' . $r->id,
                'date'   => $r->created_at->toISOString(),
                'ts'     => $r->created_at->timestamp,
            ]);

        $recentTx = $recentGR->concat($recentGI)
            ->sortByDesc('ts')
            ->take(10)
            ->values();

        // ── Low stock items — per variant ──────────────────────────────────────
        $lowStockItems = [];
        foreach ($allActiveItems as $item) {
            foreach ($item->variants as $variant) {
                $soh = (float) StockLedger::where('item_variant_id', $variant->id)->sum('qty_on_hand');
                if ($soh >= $item->minimum_stock) continue;

                $lowStockCount++;

                // Build variant detail string: Brand · Model · Size · Color
                $details = collect([
                    $variant->brand,
                    $variant->model,
                    $variant->size,
                    $variant->color,
                ])->filter()->join(' · ');

                $lowStockItems[] = [
                    'id'            => $item->id,
                    'variant_id'    => $variant->id,
                    'sku'           => $variant->sku ?? '—',
                    'name_en'       => $item->name_en,
                    'name_id'       => $item->name_id,
                    'name_zh'       => $item->name_zh,
                    'details'       => $details ?: null,
                    'brand'         => $variant->brand,
                    'model'         => $variant->model,
                    'size'          => $variant->size,
                    'color'         => $variant->color,
                    'soh'           => $soh,
                    'min'           => (float) $item->minimum_stock,
                    'suggested_qty' => (float) max(1, $item->minimum_stock - $soh),
                    'uom'           => $item->base_uom ?? '',
                ];
            }
        }
        // Sort by SOH ascending (most critical first), take top 10
        $lowStockItems = collect($lowStockItems)
            ->sortBy('soh')
            ->take(10)
            ->values();

        // ── Mandatory Distribution Overdue ────────────────────────────────────
        $user   = auth()->user();
        $role   = $user->role;
        $deptId = in_array($role, ['admin_dept', 'manager_dept']) ? $user->department_id : null;
        $showMandatory = in_array($role, ['super_admin', 'wh_admin', 'admin_dept', 'manager_dept', 'wh_manager']);
        $mandatoryOverdue = $showMandatory ? $this->getMandatoryOverdue($deptId) : null;

        // ── Admin Dept dashboard stats ─────────────────────────────────────────
        $adminDeptStats = null;
        if ($role === 'admin_dept' && $user->department_id) {
            $base = GoodsIssue::where('department_id', $user->department_id);
            $adminDeptStats = [
                'total'      => (clone $base)->count(),
                'completed'  => (clone $base)->where('status', 'completed')->count(),
                'inProgress' => (clone $base)->whereIn('status', [
                    'pending_manager_dept', 'pending_wh_manager', 'pending_wh_supervisor',
                    'assigned', 'in_picking', 'ready_to_pickup',
                ])->count(),
                'draft'    => (clone $base)->where('status', 'draft')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
                'pendingRequests' => EmployeeRequest::where('department_id', $user->department_id)
                    ->where('status', 'pending')
                    ->whereNull('goods_issue_id')
                    ->count(),
            ];
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'totalItems'      => $totalItems,
                'totalVariants'   => $totalVariants,
                'totalStockQty'   => $totalStockQty,
                'totalWarehouses' => $totalWarehouses,
                'lowStockCount'   => $lowStockCount,
                'grToday'         => $grToday,
                'giToday'         => $giToday,
            ],
            'warehouseStats'  => $warehouseStats,
            'chartData'       => $chartData,
            'recentTx'        => $recentTx,
            'lowStockItems'    => $lowStockItems,
            'mandatoryOverdue' => $mandatoryOverdue,
            'adminDeptStats'   => $adminDeptStats,
        ]);
    }

    // ── Mandatory Distribution Helper ──────────────────────────────────────────

    private function getMandatoryOverdue(?int $deptId): array
    {
        $today = now()->toDateString();

        // Employee-tracked mandatory items
        $empItems = Item::where('is_mandatory', true)
            ->where('has_cooldown', true)
            ->where('cooldown_track_by', 'employee_id')
            ->with('variants:id,item_id')
            ->get();

        $empOverdue = [];
        foreach ($empItems as $item) {
            $variantIds = $item->variants->pluck('id');
            $okIds = CooldownLog::whereIn('item_variant_id', $variantIds)
                ->where('cooldown_until', '>=', $today)
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->unique();

            $query = Employee::where('is_active', true)->whereNotIn('id', $okIds);
            if ($deptId) $query->where('department_id', $deptId);

            $overdue = $query->with('department:id,name,code')
                ->get(['id', 'employee_id', 'name', 'department_id']);

            if ($overdue->isNotEmpty()) {
                $empOverdue[] = [
                    'item'      => $item->only(['id', 'name_en', 'name_id']),
                    'employees' => $overdue->map(fn($e) => [
                        'id'          => $e->id,
                        'employee_id' => $e->employee_id,
                        'name'        => $e->name,
                        'dept'        => $e->department?->name,
                    ])->values(),
                ];
            }
        }

        // LV-tracked mandatory items
        $lvItems = Item::where('is_mandatory', true)
            ->where('has_cooldown', true)
            ->where('cooldown_track_by', 'lv_number')
            ->with('variants:id,item_id')
            ->get();

        $lvOverdue = [];
        foreach ($lvItems as $item) {
            $variantIds = $item->variants->pluck('id');
            $okIds = CooldownLog::whereIn('item_variant_id', $variantIds)
                ->where('cooldown_until', '>=', $today)
                ->whereNotNull('lv_id')
                ->pluck('lv_id')
                ->unique();

            $query = Vehicle::where('is_active', true)->whereNotIn('id', $okIds);
            if ($deptId) $query->where('department_id', $deptId);

            $overdue = $query->with('department:id,name,code')
                ->get(['id', 'lv_number', 'name', 'department_id']);

            if ($overdue->isNotEmpty()) {
                $lvOverdue[] = [
                    'item'     => $item->only(['id', 'name_en', 'name_id']),
                    'vehicles' => $overdue->map(fn($v) => [
                        'id'        => $v->id,
                        'lv_number' => $v->lv_number,
                        'name'      => $v->name,
                        'dept'      => $v->department?->name,
                    ])->values(),
                ];
            }
        }

        return [
            'employees' => $empOverdue,
            'vehicles'  => $lvOverdue,
        ];
    }

    // ── Chart builder ──────────────────────────────────────────────────────────

    private function buildChart(string $groupBy, int $periods): array
    {
        $gr = [];
        $gi = [];

        for ($i = $periods - 1; $i >= 0; $i--) {
            [$start, $end] = match ($groupBy) {
                'day'   => [Carbon::now()->subDays($i)->startOfDay(),   Carbon::now()->subDays($i)->endOfDay()],
                'week'  => [Carbon::now()->subWeeks($i)->startOfWeek(), Carbon::now()->subWeeks($i)->endOfWeek()],
                'month' => [Carbon::now()->subMonths($i)->startOfMonth(),Carbon::now()->subMonths($i)->endOfMonth()],
            };
            $gr[] = GoodsReceipt::where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count();
            $gi[] = GoodsIssue::where('status', 'completed')->whereBetween('updated_at',   [$start, $end])->count();
        }

        return ['gr' => $gr, 'gi' => $gi];
    }
}
