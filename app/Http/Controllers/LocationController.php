<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationStoreRequest;
use App\Http\Requests\LocationUpdateRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['code', 'name'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $locations = Location::query()
            ->with('warehouse:id,code,name')
            ->when($request->search, function ($q, $s) {
                $tokens = array_filter(explode(' ', trim($s)));
                foreach ($tokens as $token) {
                    $t = "%{$token}%";
                    $q->where(fn ($q2) =>
                        $q2->where('code', 'like', $t)
                           ->orWhere('name', 'like', $t)
                           ->orWhereHas('warehouse', fn ($qw) =>
                               $qw->where('code', 'like', $t)
                                  ->orWhere('name', 'like', $t)
                           )
                    );
                }
            })
            ->when($request->filled('warehouse_id'), fn ($q) =>
                $q->where('warehouse_id', $request->warehouse_id)
            )
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('code'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($l) => [
                'id'           => $l->id,
                'code'         => $l->code,
                'name'         => $l->name,
                'description'  => $l->description,
                'warehouse_id' => $l->warehouse_id,
                'warehouse'    => $l->warehouse
                    ? ['id' => $l->warehouse->id, 'code' => $l->warehouse->code, 'name' => $l->warehouse->name]
                    : null,
                'is_active'    => $l->is_active,
            ]);

        return Inertia::render('Master/Locations/Index', [
            'locations'  => $locations,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters'    => $request->only(['search', 'warehouse_id', 'status', 'sort', 'dir']),
            'stats'      => [
                'total'    => Location::count(),
                'active'   => Location::where('is_active', true)->count(),
                'inactive' => Location::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(LocationStoreRequest $request): RedirectResponse
    {
        Location::create($request->validated());
        return back()->with('success', 'Rack created successfully.');
    }

    public function update(LocationUpdateRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());
        return back()->with('success', 'Rack updated successfully.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();
        return back()->with('success', 'Rack deleted successfully.');
    }

    public function labelsData(Request $request): JsonResponse
    {
        $locations = Location::query()
            ->with('warehouse:id,code,name')
            ->where('is_active', true)
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->orderBy('code')
            ->get();

        return response()->json($locations->map(fn ($l) => [
            'id'        => $l->id,
            'code'      => $l->code,
            'name'      => $l->name,
            'warehouse' => $l->warehouse
                ? ['code' => $l->warehouse->code, 'name' => $l->warehouse->name]
                : null,
        ]));
    }

    public function rackView(string $code): Response
    {
        $location = Location::where('code', $code)
            ->with([
                'warehouse:id,code,name',
                'stockLedgers' => fn ($q) => $q->where('qty_on_hand', '>', 0),
                'stockLedgers.variant:id,sku,brand,model,size,color,item_id,photo_path',
                'stockLedgers.variant.item:id,name_id,name_en,name_zh,base_uom,part_number,category_id',
                'stockLedgers.variant.item.category:id,code',
            ])
            ->firstOrFail();

        $items = $location->stockLedgers->map(fn ($sl) => [
            'id'           => $sl->id,
            'qty_on_hand'  => (float) $sl->qty_on_hand,
            'qty_reserved' => (float) $sl->qty_reserved,
            'qty_available'=> (float) $sl->qty_available,
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
        ])->values();

        return Inertia::render('Rack/View', [
            'location' => [
                'id'        => $location->id,
                'code'      => $location->code,
                'name'      => $location->name,
                'description' => $location->description,
                'warehouse' => $location->warehouse
                    ? ['code' => $location->warehouse->code, 'name' => $location->warehouse->name]
                    : null,
            ],
            'items'    => $items,
            'scannedAt'=> now()->format('d M Y, H:i'),
        ]);
    }
}