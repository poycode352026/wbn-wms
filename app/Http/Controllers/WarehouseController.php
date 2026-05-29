<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseStoreRequest;
use App\Http\Requests\WarehouseUpdateRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['code', 'name'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $warehouses = Warehouse::query()
            ->withCount('locations')
            ->when($request->search, function ($q, $s) {
                $tokens = array_filter(explode(' ', trim($s)));
                foreach ($tokens as $token) {
                    $t = "%{$token}%";
                    $q->where(fn ($q2) =>
                        $q2->where('name',     'like', $t)
                           ->orWhere('code',     'like', $t)
                           ->orWhere('location', 'like', $t)
                    );
                }
            })
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('name'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($w) => [
                'id'              => $w->id,
                'code'            => $w->code,
                'name'            => $w->name,
                'location'        => $w->location,
                'locations_count' => $w->locations_count,
                'is_active'       => $w->is_active,
            ]);

        return Inertia::render('Master/Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters'    => $request->only(['search', 'status', 'sort', 'dir']),
            'stats'      => [
                'total'    => Warehouse::count(),
                'active'   => Warehouse::where('is_active', true)->count(),
                'inactive' => Warehouse::where('is_active', false)->count(),
                'racks'    => Location::count(),
            ],
        ]);
    }

    public function store(WarehouseStoreRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());
        return back()->with('success', 'Warehouse created successfully.');
    }

    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());
        return back()->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->locations()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete: warehouse has racks assigned.']);
        }
        $warehouse->delete();
        return back()->with('success', 'Warehouse deleted successfully.');
    }

    public function warehouseView(string $code): Response
    {
        $warehouse = Warehouse::where('code', $code)->firstOrFail();

        // Load ALL racks (empty ones included) — total rack count must reflect reality
        $locations = $warehouse->locations()
            ->orderBy('code')
            ->with([
                'stockLedgers'                        => fn ($q) => $q->where('qty_on_hand', '>', 0),
                'stockLedgers.variant:id,sku,brand,model,size,color,item_id,photo_path',
                'stockLedgers.variant.item:id,name_id,name_en,name_zh,base_uom,part_number,category_id',
                'stockLedgers.variant.item.category:id,code',
            ])
            ->get()
            ->map(fn ($l) => [
                'id'    => $l->id,
                'code'  => $l->code,
                'name'  => $l->name,
                'items' => $l->stockLedgers->map(fn ($sl) => [
                    'id'             => $sl->id,
                    'qty_on_hand'    => (float) $sl->qty_on_hand,
                    'qty_reserved'   => (float) $sl->qty_reserved,
                    'qty_available'  => (float) $sl->qty_available,
                    'variant' => $sl->variant ? [
                        'id'         => $sl->variant->id,
                        'sku'        => $sl->variant->sku,
                        'brand'      => $sl->variant->brand,
                        'model'      => $sl->variant->model,
                        'size'       => $sl->variant->size,
                        'color'      => $sl->variant->color,
                        'photo_path' => $sl->variant->photo_path
                            ? asset('storage/' . $sl->variant->photo_path)
                            : null,
                        'item' => $sl->variant->item ? [
                            'name_id'     => $sl->variant->item->name_id,
                            'name_en'     => $sl->variant->item->name_en,
                            'name_zh'     => $sl->variant->item->name_zh,
                            'part_number' => $sl->variant->item->part_number,
                            'base_uom'    => $sl->variant->item->base_uom,
                            'category'    => $sl->variant->item->category
                                ? ['code' => $sl->variant->item->category->code]
                                : null,
                        ] : null,
                    ] : null,
                ])->values(),
            ])
            ->values();

        $totalRacks = $locations->count();                                          // ALL racks
        $totalItems = $locations->sum(fn ($l) => count($l['items']));              // only those with stock
        $totalQty   = $locations->sum(fn ($l) => collect($l['items'])->sum('qty_on_hand'));

        return Inertia::render('Warehouse/View', [
            'warehouse' => [
                'id'       => $warehouse->id,
                'code'     => $warehouse->code,
                'name'     => $warehouse->name,
                'location' => $warehouse->location,
            ],
            'locations'  => $locations,
            'totalItems' => $totalItems,
            'totalQty'   => $totalQty,
            'totalRacks' => $totalRacks,
            'scannedAt'  => now()->format('d M Y, H:i'),
        ]);
    }
}