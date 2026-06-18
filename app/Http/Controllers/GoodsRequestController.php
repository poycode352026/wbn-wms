<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use App\Models\Warehouse;
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

        return Inertia::render('GoodsRequest/Create', [
            'departments' => $departments,
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
            // Use first item's warehouse as the GRQ's primary warehouse
            $primaryWarehouseId = $data['items'][0]['warehouse_id'];

            $grq = GoodsRequest::create([
                'grq_number'       => GoodsRequest::generateGrqNumber(),
                'warehouse_id'     => $primaryWarehouseId,
                'requester_name'   => $data['requester_name'],
                'requester_emp_id' => $data['requester_emp_id'] ?? null,
                'department_id'    => $data['department_id'] ?? null,
                'remark'           => $data['remark'] ?? null,
                'recorded_by'      => $request->user()->id,
                'status'           => 'completed',
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

                // Deduct from specific warehouse + location ledger row
                $remaining = (float) $row['base_qty'];
                StockLedger::where('item_variant_id', $row['variant_id'])
                    ->where('warehouse_id', $row['warehouse_id'])
                    ->when($row['location_id'] ?? null, fn ($q) => $q->where('location_id', $row['location_id']))
                    ->orderByDesc('qty_on_hand')
                    ->each(function ($ledger) use (&$remaining) {
                        if ($remaining <= 0) return false;
                        $deduct = min($remaining, (float) $ledger->qty_on_hand);
                        if ($deduct > 0) {
                            $ledger->decrement('qty_on_hand', $deduct);
                            $remaining -= $deduct;
                        }
                    });
            }

            session()->flash('grq_id', $grq->id);
        });

        return redirect()->route('grq.index')->with('success', 'Goods Request recorded successfully.');
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(GoodsRequest $grq): Response
    {
        $grq->load([
            'warehouse:id,code,name',
            'department:id,name,code',
            'recordedBy:id,name',
            'cancelledBy:id,name',
            'items.variant.item:id,name_en,name_id,name_zh,base_uom',
            'items.warehouse:id,code',
            'items.location:id,code',
        ]);

        return Inertia::render('GoodsRequest/Show', [
            'grq' => $grq,
        ]);
    }

    // ── Cancel ─────────────────────────────────────────────────────────────────

    public function cancel(Request $request, GoodsRequest $grq): RedirectResponse
    {
        if ($grq->status !== 'completed') {
            return back()->with('error', 'Only completed requests can be cancelled.');
        }

        $allowedRoles = ['super_admin', 'wh_admin', 'wh_supervisor'];
        if (!in_array($request->user()->role, $allowedRoles)) {
            abort(403);
        }

        DB::transaction(function () use ($grq, $request) {
            // Restore stock to the exact warehouse + location it was deducted from
            foreach ($grq->items as $item) {
                StockLedger::where('item_variant_id', $item->item_variant_id)
                    ->where('warehouse_id', $item->warehouse_id ?? $grq->warehouse_id)
                    ->when($item->location_id, fn ($q) => $q->where('location_id', $item->location_id))
                    ->orderByDesc('qty_on_hand')
                    ->first()
                    ?->increment('qty_on_hand', (float) $item->qty_in_base_uom);
            }

            $grq->update([
                'status'       => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
            ]);
        });

        return back()->with('success', 'Request cancelled and stock restored.');
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
