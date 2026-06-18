<?php

namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueApproval;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssuePhoto;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GoodsIssueController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role;

        $query = GoodsIssue::query()
            ->with([
                'warehouse:id,code,name',
                'department:id,name,code',
                'requestedBy:id,name',
            ])
            ->withCount('items');

        // Role-based visibility
        if ($role === 'admin_dept') {
            $query->where('requested_by', $user->id);
        } elseif ($role === 'manager_dept') {
            $query->where('department_id', $user->department_id)
                  ->whereIn('status', ['pending_manager_dept', 'pending_supervisor', 'pending_manager_wh', 'approved', 'assigned', 'in_picking', 'ready_to_pickup', 'completed', 'rejected']);
        } elseif ($role === 'wh_supervisor') {
            $query->whereIn('status', ['pending_supervisor', 'approved', 'assigned', 'in_picking', 'ready_to_pickup', 'completed', 'rejected']);
        } elseif ($role === 'wh_manager') {
            $query->whereIn('status', ['pending_manager_wh', 'pending_supervisor', 'approved', 'assigned', 'in_picking', 'ready_to_pickup', 'completed', 'rejected']);
        } elseif ($role === 'wh_admin') {
            $query->whereIn('status', ['approved', 'assigned', 'in_picking', 'ready_to_pickup', 'completed']);
        } elseif ($role === 'operator') {
            $query->where(fn ($q) =>
                $q->where('assigned_to', $user->id)
                  ->orWhere(fn ($q2) => $q2->where('status', 'approved'))
            );
        }
        // super_admin: all

        // Filters
        $query
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('gi_number', 'like', "%{$s}%")
                       ->orWhere('purpose', 'like', "%{$s}%")
                       ->orWhere('usage_location', 'like', "%{$s}%")
                       ->orWhere('project', 'like', "%{$s}%")
                )
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->warehouse, fn ($q, $w) => $q->where('warehouse_id', $w));

        $gis    = $query->latest()->paginate(20)->withQueryString();
        $counts = $this->getCounts($user);

        $warehouses = Warehouse::where('is_active', true)->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('GoodsIssue/Index', [
            'gis'        => $gis,
            'counts'     => $counts,
            'warehouses' => $warehouses,
            'userRole'   => $role,
            'filters'    => $request->only(['search', 'status', 'warehouse']),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function create(Request $request): Response
    {
        $this->authorizeRole($request, ['admin_dept', 'super_admin']);

        $user = $request->user();

        $warehouses  = Warehouse::where('is_active', true)->orderBy('sort_order')->orderBy('code')
            ->get(['id', 'code', 'name', 'sort_order']);

        $allVariants = $this->getAllVariants();

        $vehicles = Vehicle::where('is_active', true)->orderBy('lv_number')
            ->get(['id', 'lv_code', 'lv_number']);

        $employees = Employee::where('is_active', true)
            ->where('department_id', $user->department_id)
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name']);

        // ── Pre-fill items from employee requests (batch GI) ─────────────────
        $requestIds     = array_filter((array) $request->input('request_ids', []));
        $prefilledItems = [];

        if (!empty($requestIds)) {
            $empRequests = EmployeeRequest::whereIn('id', $requestIds)
                ->where('department_id', $user->department_id)
                ->where('status', 'pending')
                ->with([
                    'items' => fn ($q) => $q->with([
                        'item.variants' => fn ($q2) => $q2->where('is_active', true)->orderBy('id')->limit(1),
                    ]),
                ])
                ->get();

            // Merge by variant, carry lv_id + employee_id from request context
            $rowsMap = [];
            foreach ($empRequests as $req) {
                foreach ($req->items as $ri) {
                    $variant = $ri->item->variants->first();
                    if (!$variant) continue;
                    $key = $variant->id;
                    if (!isset($rowsMap[$key])) {
                        $rowsMap[$key] = [
                            'variantId'  => $variant->id,
                            'qty'        => 0,
                            'lvId'       => null,
                            'employeeId' => null,
                            'trackType'  => null,
                        ];
                    }
                    $rowsMap[$key]['qty'] += ($ri->qty ?? 1);
                    // Prefer lv_id stored on the request item
                    if ($ri->lv_id && !$rowsMap[$key]['lvId']) {
                        $rowsMap[$key]['lvId'] = $ri->lv_id;
                    }
                    // Employee = the requesting employee
                    if (!$rowsMap[$key]['employeeId']) {
                        $rowsMap[$key]['employeeId'] = $req->employee_id;
                    }
                    // Capture track type (item already loaded, no extra query)
                    if (!$rowsMap[$key]['trackType']) {
                        $rowsMap[$key]['trackType'] = $ri->item->cooldown_track_by;
                    }
                }
            }

            // Fallback: for lv_number-tracked items with no lv_id, use the employee's first vehicle
            $employeeIds = array_filter(array_unique(array_column(array_values($rowsMap), 'employeeId')));
            $vehicleByEmployee = [];
            if (!empty($employeeIds)) {
                $vehicleByEmployee = Vehicle::whereIn('driver_id', $employeeIds)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(['id', 'driver_id'])
                    ->groupBy('driver_id')
                    ->map(fn ($vs) => $vs->first()->id)
                    ->toArray();
            }
            foreach ($rowsMap as &$row) {
                if ($row['lvId'] === null
                    && ($row['trackType'] ?? null) === 'lv_number'
                    && !empty($row['employeeId'])) {
                    $row['lvId'] = $vehicleByEmployee[$row['employeeId']] ?? null;
                }
            }
            unset($row);

            // Strip internal trackType key before passing to frontend
            $prefilledItems = array_values(array_map(function ($r) {
                unset($r['trackType']);
                return $r;
            }, $rowsMap));
        }

        return Inertia::render('GoodsIssue/Create', [
            'warehouses'       => $warehouses,
            'allVariants'      => $allVariants,
            'userDeptId'       => $user->department_id,
            'userDeptName'     => $user->department?->name,
            'stockMap'         => $this->getStockMap(),
            'cooldownData'     => $this->getCooldownData(),
            'vehicles'         => $vehicles,
            'employees'        => $employees,
            'prefilledItems'   => $prefilledItems,
            'sourceRequestIds' => array_values($requestIds),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole($request, ['admin_dept', 'super_admin']);

        $user = $request->user();

        $data = $request->validate([
            'warehouse_id'             => ['nullable', 'exists:warehouses,id'],
            'project'                  => ['nullable', 'string', 'max:255'],
            'business_function'        => ['nullable', 'string', 'max:255'],
            'purpose'                  => ['nullable', 'string', 'max:2000'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.variant_id'       => ['required', 'exists:item_variants,id'],
            'items.*.requested_qty'    => ['required', 'numeric', 'min:0.01'],
            'items.*.uom'              => ['required', 'string', 'max:50'],
            'items.*.base_qty'         => ['required', 'numeric', 'min:0.01'],
            'items.*.lv_id'            => ['nullable', 'exists:vehicles,id'],
            'items.*.employee_id'      => ['nullable', 'exists:employees,id'],
            'items.*.store_to'         => ['nullable', 'string', 'max:255'],
            'items.*.item_reason'      => ['nullable', 'string', 'max:1000'],
            'items.*.notes'            => ['nullable', 'string', 'max:500'],
            'items.*.item_warehouse_id'=> ['nullable', 'exists:warehouses,id'],
            'photos'                   => ['nullable', 'array', 'max:10'],
            'photos.*'                 => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'source_request_ids'       => ['nullable', 'array'],
            'source_request_ids.*'     => ['exists:employee_requests,id'],
        ]);

        // ── Server-side cooldown validation ────────────────────────────────────
        $hasCooldownItem = false;
        foreach ($data['items'] as $item) {
            $variant = ItemVariant::with('item:id,has_cooldown,cooldown_track_by,cooldown_days')
                ->find($item['variant_id']);
            if (!$variant?->item?->has_cooldown) continue;

            $hasCooldownItem = true;
            $trackBy = $variant->item->cooldown_track_by;

            $cdQuery = CooldownLog::where('item_variant_id', $item['variant_id'])
                ->where('track_type', $trackBy)
                ->where('cooldown_until', '>=', now()->toDateString());

            if ($trackBy === 'lv_number' && !empty($item['lv_id'])) {
                $cdQuery->where('lv_id', $item['lv_id']);
            } elseif ($trackBy === 'employee_id' && !empty($item['employee_id'])) {
                $cdQuery->where('employee_id', $item['employee_id']);
            }

            if ($cdQuery->exists()) {
                $log = $cdQuery->first();
                return back()
                    ->withErrors(['items' => "Salah satu item masih dalam masa cooldown hingga {$log->cooldown_until->format('d M Y')}."])
                    ->withInput();
            }
        }

        // ── Foto wajib saat submit (bukan draft) jika ada item cooldown ──────────
        // (Hanya enforce saat submit dari show page, bukan saat simpan draft awal)

        $gi = DB::transaction(function () use ($data, $user, $request) {
            // Auto-derive warehouse_id from the most-used item warehouse, or fall back to first warehouse
            $warehouseId = $data['warehouse_id'] ?? null;
            if (!$warehouseId) {
                $itemWhIds = collect($data['items'])->pluck('item_warehouse_id')->filter()->countBy();
                $warehouseId = $itemWhIds->sortDesc()->keys()->first()
                    ?? \App\Models\Warehouse::orderBy('sort_order')->value('id');
            }

            $gi = GoodsIssue::create([
                'gi_number'      => GoodsIssue::generateGiNumber(),
                'warehouse_id'   => $warehouseId,
                'department_id'  => $user->department_id,
                'requested_by'   => $user->id,
                'project'           => $data['project'] ?? null,
                'business_function' => $data['business_function'] ?? null,
                'purpose'           => $data['purpose'] ?? null,
                'usage_location'    => $data['usage_location'] ?? '',
                'notes'             => $data['notes'] ?? null,
                'status'         => 'draft',
            ]);

            foreach ($data['items'] as $item) {
                GoodsIssueItem::create([
                    'goods_issue_id'   => $gi->id,
                    'item_variant_id'  => $item['variant_id'],
                    'item_warehouse_id'=> $item['item_warehouse_id'] ?? null,
                    'requested_qty'    => $item['requested_qty'],
                    'requested_uom'    => $item['uom'],
                    'qty_in_base_uom'  => $item['base_qty'],
                    'uom_used'         => $item['uom'],
                    'lv_id'            => $item['lv_id'] ?? null,
                    'employee_id'      => $item['employee_id'] ?? null,
                    'store_to'         => $item['store_to'] ?? null,
                    'item_reason'      => $item['item_reason'] ?? null,
                    'notes'            => $item['notes'] ?? null,
                    'status'           => 'pending',
                ]);
            }

            // Upload photos (stage=request)
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('gi-photos', 'public');
                    GoodsIssuePhoto::create([
                        'goods_issue_id' => $gi->id,
                        'path'           => $path,
                        'original_name'  => $photo->getClientOriginalName(),
                        'stage'          => 'request',
                        'uploaded_by'    => $user->id,
                    ]);
                }
            }

            // Link source employee requests to this GI
            $srcIds = array_filter($request->input('source_request_ids', []));
            if (!empty($srcIds)) {
                EmployeeRequest::whereIn('id', $srcIds)
                    ->update(['goods_issue_id' => $gi->id]);
            }

            return $gi;
        });

        return redirect()->route('gi.show', $gi)
            ->with('success', "GI {$gi->gi_number} berhasil dibuat.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(Request $request, GoodsIssue $gi): Response
    {
        $user = $request->user();

        $gi->load([
            'warehouse:id,code,name',
            'department:id,name,code',
            'requestedBy:id,name',
            'assignedTo:id,name',
            'pickedBy:id,name',
            'items.variant.item',
            'items.lv:id,lv_code,lv_number',
            'items.employee:id,employee_id,name',
            'items.itemWarehouse:id,code,name',
            'approvals.actedBy:id,name',
            'photos.uploader:id,name',
        ]);

        // Operators list — operators + wh_admin (who may handle picking themselves)
        $operators = ($gi->status === 'approved')
            ? User::whereIn('role', ['operator', 'wh_admin'])
                  ->where('is_active', true)
                  ->orderBy('name')
                  ->get(['id', 'name', 'role'])
            : collect();

        // Stock location map — for warehouse roles to see where each item is stored
        $locationMap = [];
        if (in_array($user->role, ['wh_admin', 'wh_supervisor', 'wh_manager', 'super_admin'])) {
            foreach ($gi->items as $gItem) {
                $whId = $gItem->item_warehouse_id ?? $gi->warehouse_id;
                $vid  = $gItem->item_variant_id;
                $ledgers = StockLedger::where('item_variant_id', $vid)
                    ->where('warehouse_id', $whId)
                    ->where('qty_on_hand', '>', 0)
                    ->with('location:id,code,name')
                    ->get(['item_variant_id', 'location_id', 'qty_on_hand', 'qty_reserved']);

                if (!isset($locationMap[$vid])) $locationMap[$vid] = [];
                foreach ($ledgers as $ledger) {
                    $locationMap[$vid][] = [
                        'warehouse_id'   => $whId,
                        'warehouse_code' => $gItem->itemWarehouse?->code ?? $gi->warehouse?->code ?? '—',
                        'warehouse_name' => $gItem->itemWarehouse?->name ?? $gi->warehouse?->name ?? '—',
                        'location_code'  => $ledger->location?->code,
                        'location_name'  => $ledger->location?->name,
                        'qty_on_hand'    => (float) $ledger->qty_on_hand,
                        'qty_reserved'   => (float) $ledger->qty_reserved,
                        'available'      => max(0, (float) $ledger->qty_on_hand - (float) $ledger->qty_reserved),
                    ];
                }
            }
        }

        // Per-variant warehouse stock map — only for wh_admin when assigning
        $warehouseStockMap = null;
        if ($gi->status === 'approved' && in_array($user->role, ['wh_admin', 'super_admin'])) {
            $variantIds = $gi->items->pluck('item_variant_id')->unique();
            $ledgers    = StockLedger::whereIn('item_variant_id', $variantIds)
                ->where('qty_on_hand', '>', 0)
                ->with('warehouse:id,code,name')
                ->get(['item_variant_id', 'warehouse_id', 'qty_on_hand', 'qty_reserved']);

            foreach ($ledgers as $l) {
                $warehouseStockMap[$l->item_variant_id][] = [
                    'warehouse_id'   => $l->warehouse_id,
                    'warehouse_code' => $l->warehouse?->code,
                    'warehouse_name' => $l->warehouse?->name,
                    'qty_on_hand'    => (float) $l->qty_on_hand,
                    'qty_reserved'   => (float) $l->qty_reserved,
                    'available'      => max(0, (float) $l->qty_on_hand - (float) $l->qty_reserved),
                ];
            }
        }

        return Inertia::render('GoodsIssue/Show', [
            'gi'                => $gi,
            'userRole'          => $user->role,
            'userId'            => $user->id,
            'operators'         => $operators,
            'locationMap'       => $locationMap,
            'warehouseStockMap' => $warehouseStockMap,
        ]);
    }

    // ── Submit (draft → pending_manager_dept) ──────────────────────────────────

    public function submit(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $this->authorizeRole($request, ['admin_dept', 'super_admin']);

        if ($gi->status !== 'draft') {
            return back()->with('error', 'GI tidak dalam status draft.');
        }

        $gi->load(['items', 'department:id,name']);

        DB::transaction(function () use ($gi) {
            // Reserve stock per item (uses item-level warehouse if set)
            foreach ($gi->items as $gItem) {
                $remaining = (float) $gItem->qty_in_base_uom;
                $whId = $gItem->item_warehouse_id ?? $gi->warehouse_id;
                StockLedger::where('item_variant_id', $gItem->item_variant_id)
                    ->where('warehouse_id', $whId)
                    ->orderByDesc(DB::raw('qty_on_hand - qty_reserved'))
                    ->each(function ($ledger) use (&$remaining) {
                        if ($remaining <= 0) return false;
                        $available = max(0, (float) $ledger->qty_on_hand - (float) $ledger->qty_reserved);
                        $toReserve = min($remaining, $available);
                        if ($toReserve > 0) {
                            $ledger->increment('qty_reserved', $toReserve);
                            $remaining -= $toReserve;
                        }
                    });
            }

            $gi->update(['status' => 'pending_manager_dept', 'submitted_at' => now()]);
        });

        // Notify manager_dept in same department
        $submittedBy = $request->user();
        if ($gi->department_id) {
            NotificationService::sendToDeptRole(
                'manager_dept', $gi->department_id,
                'GI_SUBMITTED',
                "New GI Request: {$gi->gi_number}",
                "{$submittedBy->name} has submitted a goods issue request. Please review.",
                ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
            );
        }

        return back()->with('success', "GI {$gi->gi_number} berhasil disubmit.");
    }

    // ── Approve ────────────────────────────────────────────────────────────────
    // Flow: pending_manager_dept → pending_manager_wh → pending_supervisor → approved

    public function approve(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        $transitions = [
            'pending_manager_dept' => [
                'roles'       => ['manager_dept', 'super_admin'],
                'next'        => 'pending_manager_wh',
                'step'        => 'manager_dept',
                'notifyRole'  => 'wh_manager',
                'notifyType'  => 'GI_APPROVED_MGR',
                'notifyTitle' => "GI Awaiting Review: {$gi->gi_number}",
                'notifyMsg'   => "GI {$gi->gi_number} from {$gi->department?->name} has been approved by Dept Manager. Your review is required.",
            ],
            'pending_manager_wh' => [
                'roles'       => ['wh_manager', 'super_admin'],
                'next'        => 'pending_supervisor',
                'step'        => 'wh_manager',
                'notifyRole'  => 'wh_supervisor',
                'notifyType'  => 'GI_APPROVED_WH_MGR',
                'notifyTitle' => "GI Awaiting Supervisor Review: {$gi->gi_number}",
                'notifyMsg'   => "GI {$gi->gi_number} has been approved by WH Manager. Your review is required.",
            ],
            'pending_supervisor' => [
                'roles'       => ['wh_supervisor', 'super_admin'],
                'next'        => 'approved',
                'step'        => 'wh_supervisor',
                'notifyRole'  => 'wh_admin',
                'notifyType'  => 'GI_APPROVED_ALL',
                'notifyTitle' => "GI Approved: {$gi->gi_number}",
                'notifyMsg'   => "GI {$gi->gi_number} has been fully approved. Please assign an operator.",
            ],
        ];

        if (!isset($transitions[$gi->status])) {
            return back()->with('error', 'GI tidak bisa di-approve pada status ini.');
        }

        $t = $transitions[$gi->status];
        if (!in_array($user->role, $t['roles'])) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $gi->load('department:id,name');

        GoodsIssueApproval::create([
            'goods_issue_id' => $gi->id,
            'step'           => $t['step'],
            'action'         => 'approved',
            'acted_by'       => $user->id,
            'reason'         => null,
            'acted_at'       => now(),
        ]);

        $gi->update(['status' => $t['next']]);

        // Notify next role
        NotificationService::sendToRole(
            $t['notifyRole'], $t['notifyType'], $t['notifyTitle'], $t['notifyMsg'],
            ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
        );

        // If final approval → also notify admin_dept requester
        if ($t['next'] === 'approved') {
            NotificationService::send(
                $gi->requested_by,
                'GI_APPROVED_ALL',
                "GI Approved: {$gi->gi_number}",
                "Your GI request {$gi->gi_number} has been fully approved and is being processed.",
                ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
            );
        }

        return back()->with('success', "GI {$gi->gi_number} berhasil di-approve → {$t['next']}.");
    }

    // ── Reject ─────────────────────────────────────────────────────────────────

    public function reject(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        $allowedStatuses = ['pending_manager_dept', 'pending_manager_wh', 'pending_supervisor'];
        $allowedRoles    = [
            'pending_manager_dept' => ['manager_dept', 'super_admin'],
            'pending_manager_wh'   => ['wh_manager', 'super_admin'],
            'pending_supervisor'   => ['wh_supervisor', 'super_admin'],
        ];
        $stepMap = [
            'pending_manager_dept' => 'manager_dept',
            'pending_manager_wh'   => 'wh_manager',
            'pending_supervisor'   => 'wh_supervisor',
        ];

        if (!in_array($gi->status, $allowedStatuses)) {
            return back()->with('error', 'GI tidak bisa di-reject pada status ini.');
        }

        if (!in_array($user->role, $allowedRoles[$gi->status])) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $gi->load('items');

        GoodsIssueApproval::create([
            'goods_issue_id' => $gi->id,
            'step'           => $stepMap[$gi->status],
            'action'         => 'rejected',
            'acted_by'       => $user->id,
            'reason'         => $data['reason'],
            'acted_at'       => now(),
        ]);

        $gi->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);

        // Unlink employee requests so they can be re-submitted
        EmployeeRequest::where('goods_issue_id', $gi->id)
            ->update(['goods_issue_id' => null]);

        // Return stock reservation
        $this->clearReservation($gi);

        // Notify requester
        NotificationService::send(
            $gi->requested_by,
            'GI_REJECTED',
            "GI Ditolak: {$gi->gi_number}",
            "Request GI Anda ditolak. Alasan: {$data['reason']}",
            ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
        );

        return back()->with('success', "GI {$gi->gi_number} telah ditolak.");
    }

    // ── Assign Operator (wh_admin → approved → assigned) ──────────────────────

    public function assign(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        // wh_admin/super_admin assigns anyone (including themselves), OR operator self-assigns
        $isWhAdmin   = in_array($user->role, ['wh_admin', 'super_admin']);
        $isOperator  = $user->role === 'operator';

        if (!$isWhAdmin && !$isOperator) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($gi->status !== 'approved') {
            return back()->with('error', 'GI tidak dalam status approved.');
        }

        $data = $request->validate([
            'operator_id'                       => ['required', 'exists:users,id'],
            'item_warehouses'                   => ['nullable', 'array'],
            'item_warehouses.*.item_id'         => ['required', 'exists:goods_issue_items,id'],
            'item_warehouses.*.warehouse_id'    => ['nullable', 'exists:warehouses,id'],
        ]);

        // Operator can only assign themselves; wh_admin can assign anyone
        if ($isOperator && $data['operator_id'] != $user->id) {
            abort(403, 'Operator hanya bisa assign diri sendiri.');
        }

        // Update per-item warehouse if provided (wh_admin only)
        if ($isWhAdmin && !empty($data['item_warehouses'])) {
            foreach ($data['item_warehouses'] as $iw) {
                GoodsIssueItem::where('id', $iw['item_id'])
                    ->where('goods_issue_id', $gi->id)
                    ->update(['item_warehouse_id' => $iw['warehouse_id'] ?: null]);
            }
        }

        $gi->update([
            'status'      => 'assigned',
            'assigned_to' => $data['operator_id'],
        ]);

        // Notify the assigned operator
        if ($data['operator_id'] != $user->id) {
            NotificationService::send(
                $data['operator_id'],
                'GI_ASSIGNED',
                "Job Picking: {$gi->gi_number}",
                "Anda telah di-assign untuk mempersiapkan barang GI {$gi->gi_number}.",
                ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
            );
        }

        // Notify admin_dept requester
        NotificationService::send(
            $gi->requested_by,
            'GI_ASSIGNED',
            "GI Sedang Diproses: {$gi->gi_number}",
            "Request GI Anda sedang dipersiapkan oleh operator.",
            ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
        );

        return back()->with('success', "Operator berhasil di-assign untuk GI {$gi->gi_number}.");
    }

    // ── Start Picking (assigned → in_picking) ──────────────────────────────────

    public function startPicking(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'wh_admin']) && $gi->assigned_to != $user->id) {
            abort(403, 'Hanya operator yang di-assign yang bisa memulai picking.');
        }

        if ($gi->status !== 'assigned') {
            return back()->with('error', 'GI tidak dalam status assigned.');
        }

        $gi->update([
            'status'     => 'in_picking',
            'picked_by'  => $user->id,
            'picked_at'  => now(),
        ]);

        return back()->with('success', "Picking GI {$gi->gi_number} dimulai.");
    }

    // ── Submit Picking (in_picking → ready_to_pickup) ─────────────────────────

    public function submitPicking(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $gi->picked_by != $user->id) {
            abort(403, 'Hanya operator yang melakukan picking yang bisa submit.');
        }

        if ($gi->status !== 'in_picking') {
            return back()->with('error', 'GI tidak dalam status in_picking.');
        }

        $data = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'exists:goods_issue_items,id'],
            'items.*.actual_qty'=> ['required', 'numeric', 'min:0'],
            'items.*.status'   => ['required', 'in:ready,rejected'],
            'items.*.notes'    => ['nullable', 'string', 'max:500'],
            'photos'           => ['nullable', 'array', 'max:10'],
            'photos.*'         => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::transaction(function () use ($data, $gi, $user, $request) {
            foreach ($data['items'] as $itemData) {
                GoodsIssueItem::where('id', $itemData['id'])
                    ->where('goods_issue_id', $gi->id)
                    ->update([
                        'actual_qty' => $itemData['actual_qty'],
                        'status'     => $itemData['status'],
                        'notes'      => $itemData['notes'] ?? null,
                    ]);
            }

            // Upload photos (stage=picking)
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('gi-photos', 'public');
                    GoodsIssuePhoto::create([
                        'goods_issue_id' => $gi->id,
                        'path'           => $path,
                        'original_name'  => $photo->getClientOriginalName(),
                        'stage'          => 'picking',
                        'uploaded_by'    => $user->id,
                    ]);
                }
            }

            $gi->update(['status' => 'ready_to_pickup']);
        });

        // Notify admin_dept requester
        NotificationService::send(
            $gi->requested_by,
            'GI_READY_PICKUP',
            "Barang Siap: {$gi->gi_number}",
            "Barang Anda sudah siap di staging area gudang. Tunjukkan barcode GI untuk pengambilan.",
            ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
        );

        return back()->with('success', "GI {$gi->gi_number} siap untuk diambil.");
    }

    // ── Pickup Confirmation (ready_to_pickup → completed) ─────────────────────

    public function pickup(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['wh_supervisor', 'wh_manager', 'wh_admin', 'super_admin'])) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($gi->status !== 'ready_to_pickup') {
            return back()->with('error', 'GI tidak dalam status ready_to_pickup.');
        }

        // Validate barcode confirmation
        $data = $request->validate([
            'gi_number' => ['required', 'string'],
        ]);

        if (trim($data['gi_number']) !== $gi->gi_number) {
            return back()->with('error', 'GI Number tidak cocok. Pastikan barcode yang benar.');
        }

        $gi->load(['items.variant.item', 'items.lv', 'items.employee']);

        DB::transaction(function () use ($gi) {
            foreach ($gi->items()->where('status', 'ready')->get() as $gItem) {
                $qty = (float) ($gItem->actual_qty ?? $gItem->qty_in_base_uom);

                // 1. Clear reservation & deduct stock (uses item-level warehouse if set)
                $whId = $gItem->item_warehouse_id ?? $gi->warehouse_id;
                $ledgers = StockLedger::where('item_variant_id', $gItem->item_variant_id)
                    ->where('warehouse_id', $whId)
                    ->orderByDesc('qty_reserved')
                    ->get();

                foreach ($ledgers as $ledger) {
                    if ($qty <= 0) break;
                    $clearRes = min((float) $gItem->qty_in_base_uom, (float) $ledger->qty_reserved);
                    $deduct   = min($qty, (float) $ledger->qty_on_hand);
                    if ($clearRes > 0) $ledger->decrement('qty_reserved', $clearRes);
                    if ($deduct > 0)   $ledger->decrement('qty_on_hand', $deduct);
                    $qty -= $deduct;
                }

                // 2. Cooldown logging
                $item = $gItem->variant?->item;
                if ($item?->has_cooldown && $item->cooldown_days > 0) {
                    $takenAt       = now()->toDateString();
                    $cooldownUntil = now()->addDays($item->cooldown_days)->toDateString();

                    CooldownLog::create([
                        'item_id'         => $item->id,
                        'item_variant_id' => $gItem->item_variant_id,
                        'track_type'      => $item->cooldown_track_by,
                        'lv_id'           => $gItem->lv_id,
                        'employee_id'     => $gItem->employee_id,
                        'goods_issue_id'  => $gi->id,
                        'taken_at'        => $takenAt,
                        'cooldown_until'  => $cooldownUntil,
                    ]);

                    $gItem->update(['cooldown_until' => $cooldownUntil]);
                }
            }

            $gi->update(['status' => 'completed', 'completed_at' => now()]);

            // Auto-process linked employee requests
            EmployeeRequest::where('goods_issue_id', $gi->id)
                ->update(['status' => 'processed']);
        });

        // Notify requester
        NotificationService::send(
            $gi->requested_by,
            'GI_COMPLETED',
            "GI Selesai: {$gi->gi_number}",
            "Barang GI {$gi->gi_number} telah berhasil diambil. Terima kasih.",
            ['gi_id' => $gi->id, 'gi_number' => $gi->gi_number, 'route' => '/goods-issues/' . $gi->id]
        );

        return back()->with('success', "GI {$gi->gi_number} telah selesai. Barang berhasil diambil.");
    }

    // ── Destroy GI (draft only) ────────────────────────────────────────────────

    public function destroy(Request $request, GoodsIssue $gi): RedirectResponse
    {
        $this->authorizeRole($request, ['admin_dept', 'super_admin']);

        if ($gi->status !== 'draft') {
            return back()->with('error', 'Hanya GI berstatus draft yang dapat dihapus.');
        }

        $user = $request->user();
        if ($gi->requested_by !== $user->id && $user->role !== 'super_admin') {
            abort(403);
        }

        // Delete physical photo files
        foreach ($gi->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $giNumber = $gi->gi_number;
        $gi->delete(); // cascades to items and photos

        return redirect()->route('gi.index')
            ->with('success', "GI {$giNumber} telah dihapus.");
    }

    // ── Delete Photo ───────────────────────────────────────────────────────────

    public function deletePhoto(Request $request, GoodsIssuePhoto $photo): RedirectResponse
    {
        $user = $request->user();
        $gi   = GoodsIssue::find($photo->goods_issue_id);

        if (!$gi) {
            abort(404);
        }

        // Nobody can delete photos from a completed GI
        if ($gi->status === 'completed') {
            return back()->with('error', 'Foto tidak bisa dihapus dari GI yang sudah selesai.');
        }

        // Allow: super_admin always, admin_dept only for their own GI in draft/pending states
        $canDelete = $user->role === 'super_admin'
            || ($user->role === 'admin_dept' && $gi->requested_by === $user->id
                && in_array($gi->status, ['draft', 'pending_manager_dept']));

        if (!$canDelete) {
            abort(403, 'Tidak diizinkan menghapus foto ini.');
        }

        // Delete file from storage
        if (Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }

        $photo->delete();

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function getCounts($user): array
    {
        $base = GoodsIssue::query();
        if ($user->role === 'admin_dept') {
            $base->where('requested_by', $user->id);
        } elseif ($user->role === 'manager_dept') {
            $base->where('department_id', $user->department_id);
        }
        $rows = $base->selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status')->all();
        return [
            'total'               => array_sum($rows),
            'draft'               => $rows['draft'] ?? 0,
            'pending_manager_dept'=> $rows['pending_manager_dept'] ?? 0,
            'pending_supervisor'  => $rows['pending_supervisor'] ?? 0,
            'pending_manager_wh'  => $rows['pending_manager_wh'] ?? 0,
            'approved'            => $rows['approved'] ?? 0,
            'in_progress'         => ($rows['assigned'] ?? 0) + ($rows['in_picking'] ?? 0),
            'ready_to_pickup'     => $rows['ready_to_pickup'] ?? 0,
            'completed'           => $rows['completed'] ?? 0,
            'rejected'            => $rows['rejected'] ?? 0,
        ];
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        if (!in_array($request->user()->role, $roles)) {
            abort(403, 'Akses tidak diizinkan.');
        }
    }

    private function clearReservation(GoodsIssue $gi): void
    {
        foreach ($gi->items as $gItem) {
            $whId = $gItem->item_warehouse_id ?? $gi->warehouse_id;
            StockLedger::where('item_variant_id', $gItem->item_variant_id)
                ->where('warehouse_id', $whId)
                ->where('qty_reserved', '>', 0)
                ->decrement('qty_reserved', (float) $gItem->qty_in_base_uom);
        }
    }

    private function getAllVariants(): array
    {
        return ItemVariant::where('is_active', true)
            ->with('item:id,name_en,name_id,name_zh,base_uom,alt_uom,alt_uom_conversion,has_cooldown,cooldown_track_by,cooldown_days')
            ->orderBy('sku')
            ->get(['id', 'sku', 'brand', 'model', 'size', 'color', 'item_id'])
            ->map(fn ($v) => [
                'id'                 => $v->id,
                'sku'                => $v->sku,
                'brand'              => $v->brand,
                'model'              => $v->model,
                'size'               => $v->size,
                'color'              => $v->color,
                'name_en'            => $v->item?->name_en,
                'name_id'            => $v->item?->name_id,
                'name_zh'            => $v->item?->name_zh,
                'base_uom'           => $v->item?->base_uom,
                'alt_uom'            => $v->item?->alt_uom,
                'alt_uom_conversion' => $v->item?->alt_uom_conversion
                    ? (float) $v->item->alt_uom_conversion : null,
                'has_cooldown'       => (bool) $v->item?->has_cooldown,
                'cooldown_track_by'  => $v->item?->cooldown_track_by,
                'cooldown_days'      => $v->item?->cooldown_days,
            ])
            ->all();
    }

    private function getStockMap(): array
    {
        return StockLedger::selectRaw('warehouse_id, item_variant_id, SUM(qty_on_hand - qty_reserved) as available')
            ->groupBy('warehouse_id', 'item_variant_id')
            ->get()
            ->groupBy('warehouse_id')
            ->map(fn ($rows) => $rows->pluck('available', 'item_variant_id'))
            ->toArray();
    }

    private function getCooldownData(): array
    {
        return CooldownLog::where('cooldown_until', '>=', now()->toDateString())
            ->get(['item_variant_id', 'track_type', 'lv_id', 'employee_id', 'cooldown_until'])
            ->map(fn ($cd) => [
                'variant_id'    => $cd->item_variant_id,
                'track_type'    => $cd->track_type,
                'lv_id'         => $cd->lv_id,
                'employee_id'   => $cd->employee_id,
                'cooldown_until'=> $cd->cooldown_until,
            ])
            ->toArray();
    }
}
