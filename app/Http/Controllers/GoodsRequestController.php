<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\GoodsRequestPhoto;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GoodsRequestController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = GoodsRequest::query()
            ->with([
                'warehouse:id,code,name',
                'department:id,name,code',
                'recordedBy:id,name',
            ])
            ->withCount('items');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('grq_number', 'like', "%{$search}%")
                  ->orWhere('requester_name', 'like', "%{$search}%")
                  ->orWhere('requester_emp_id', 'like', "%{$search}%");
            });
        }

        if ($whId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $whId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $grqs = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $warehouses = Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('GoodsRequest/Index', [
            'grqs'       => $grqs,
            'warehouses' => $warehouses,
            'filters'    => $request->only(['search', 'warehouse_id', 'status']),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $departments = Department::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'code']);

        $operators = User::where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'operator')
                  ->orWhere('role', 'wh_admin');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return Inertia::render('GoodsRequest/Create', [
            'departments' => $departments,
            'operators'   => $operators,
            'allVariants' => $this->getAllVariants(),
            'stockMap'    => $this->getStockMap(),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requester_name'  => 'required|string|max:255',
            'requester_emp_id'=> 'nullable|string|max:100',
            'department_id'   => 'nullable|exists:departments,id',
            'remark'          => 'nullable|string|max:2000',
            'items'           => 'required|array|min:1',
            'items.*.variant_id'  => 'required|exists:item_variants,id',
            'items.*.warehouse_id'=> 'required|exists:warehouses,id',
            'items.*.location_id' => 'nullable|exists:locations,id',
            'items.*.requested_qty' => 'required|numeric|min:0.01',
            'items.*.uom'           => 'required|string|max:50',
            'items.*.base_qty'      => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($data, $request) {
            $primaryWarehouseId = $data['items'][0]['warehouse_id'];

            $grq = GoodsRequest::create([
                'grq_number'       => GoodsRequest::generateGrqNumber(),
                'warehouse_id'     => $primaryWarehouseId,
                'requester_name'   => $data['requester_name'],
                'requester_emp_id' => $data['requester_emp_id'] ?? null,
                'department_id'    => $data['department_id'] ?? null,
                'remark'           => $data['remark'] ?? null,
                'recorded_by'      => $request->user()->id,
                'status'           => 'pending',
            ]);

            foreach ($data['items'] as $row) {
                GoodsRequestItem::create([
                    'goods_request_id' => $grq->id,
                    'item_variant_id'  => $row['variant_id'],
                    'warehouse_id'     => $row['warehouse_id'],
                    'location_id'      => $row['location_id'] ?? null,
                    'requested_qty'    => $row['requested_qty'],
                    'uom_used'         => $row['uom'],
                    'qty_in_base_uom'  => $row['base_qty'],
                ]);
            }

            session()->flash('grq_id', $grq->id);
        });

        return redirect()->route('grq.index')->with('success', 'Goods Request created. Please assign an operator.');
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(GoodsRequest $grq): Response
    {
        $grq->load([
            'warehouse:id,code,name',
            'department:id,name,code',
            'recordedBy:id,name',
            'assignedTo:id,name',
            'pickedBy:id,name',
            'cancelledBy:id,name',
            'items.variant.item:id,name_en,name_id,name_zh,base_uom',
            'items.warehouse:id,code',
            'items.location:id,code',
            'photos',
        ]);

        $operators = User::where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'operator')
                  ->orWhere('role', 'wh_admin');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return Inertia::render('GoodsRequest/Show', [
            'grq'       => $grq,
            'operators' => $operators,
        ]);
    }

    // ── Assign Operator ────────────────────────────────────────────────────────

    public function assign(Request $request, GoodsRequest $grq): RedirectResponse
    {
        $allowedRoles = ['super_admin', 'wh_admin', 'wh_supervisor'];
        if (!in_array($request->user()->role, $allowedRoles)) {
            abort(403);
        }

        if ($grq->status !== 'pending') {
            return back()->with('error', 'GRQ can only be assigned when status is pending.');
        }

        $request->validate([
            'operator_id' => ['required', 'exists:users,id'],
        ]);

        $grq->update([
            'assigned_to' => $request->operator_id,
            'assigned_at' => now(),
            'status'      => 'assigned',
        ]);

        $operator = User::find($request->operator_id);

        NotificationService::send(
            $request->operator_id,
            'GRQ_ASSIGNED',
            "GRQ Assigned: {$grq->grq_number}",
            "You have been assigned to prepare items for GRQ {$grq->grq_number}. Requester: {$grq->requester_name}.",
            [
                'grq_id'     => $grq->id,
                'grq_number' => $grq->grq_number,
                'route'      => '/operator/scan-grq/' . $grq->id,
            ]
        );

        return back()->with('success', "GRQ assigned to {$operator?->name}.");
    }

    // ── Cancel ─────────────────────────────────────────────────────────────────

    public function cancel(Request $request, GoodsRequest $grq): RedirectResponse
    {
        if (!in_array($grq->status, ['pending', 'assigned'])) {
            return back()->with('error', 'GRQ can only be cancelled when status is pending or assigned.');
        }

        $allowedRoles = ['super_admin', 'wh_admin', 'wh_supervisor'];
        if (!in_array($request->user()->role, $allowedRoles)) {
            abort(403);
        }

        // Stock has not been deducted yet — no restoration needed
        $grq->update([
            'status'       => 'cancelled',
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Request cancelled.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function getAllVariants(): array
    {
        return ItemVariant::where('is_active', true)
            ->with('item:id,name_en,name_id,name_zh,base_uom,alt_uom,alt_uom_conversion')
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
            ])
            ->all();
    }

    private function getStockMap(): array
    {
        $ledgers = StockLedger::with('warehouse:id,code', 'location:id,code')
            ->where('qty_on_hand', '>', 0)
            ->get(['item_variant_id', 'warehouse_id', 'location_id', 'qty_on_hand', 'qty_reserved']);

        $map = [];
        foreach ($ledgers as $row) {
            $avail = max(0, (float) $row->qty_on_hand - (float) $row->qty_reserved);
            if ($avail <= 0) continue;

            $vid = $row->item_variant_id;
            $map[$vid][] = [
                'warehouse_id'   => $row->warehouse_id,
                'warehouse_code' => $row->warehouse->code ?? '',
                'location_id'    => $row->location_id,
                'location_code'  => $row->location?->code,
                'qty'            => $avail,
            ];
        }

        foreach ($map as $vid => &$entries) {
            usort($entries, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        }
        unset($entries);

        return $map;
    }
}
