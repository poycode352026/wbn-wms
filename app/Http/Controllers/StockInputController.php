<?php

namespace App\Http\Controllers;

use App\Models\ItemVariant;
use App\Models\Location;
use App\Models\StockLedger;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockInputController extends Controller
{
    public function index(Request $request): Response
    {
        $variants = ItemVariant::query()
            ->with([
                'item:id,part_number,name_en,name_id,name_zh,base_uom,alt_uom,alt_uom_conversion,category_id',
                'item.category:id,code,name_en,name_id,name_zh',
                'stockLedgers.location:id,code,name,warehouse_id',
                'stockLedgers.location.warehouse:id,code,name',
            ])
            ->where('is_active', true)
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('sku', 'like', "%{$s}%")
                       ->orWhereHas('item', fn ($qi) =>
                           $qi->where('part_number', 'like', "%{$s}%")
                              ->orWhere('name_en', 'like', "%{$s}%")
                              ->orWhere('name_id', 'like', "%{$s}%")
                        )
                )
            )
            ->when($request->filled('category_id'), fn ($q) =>
                $q->whereHas('item', fn ($qi) =>
                    $qi->where('category_id', $request->category_id)
                )
            )
            ->orderBy('sku')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($v) => [
                'id'         => $v->id,
                'sku'        => $v->sku,
                'brand'      => $v->brand,
                'model'      => $v->model,
                'size'       => $v->size,
                'color'      => $v->color,
                'photo_path' => $v->photo_path ? asset('storage/' . $v->photo_path) : null,
                'item'      => $v->item ? [
                    'id'          => $v->item->id,
                    'part_number' => $v->item->part_number,
                    'name_en'     => $v->item->name_en,
                    'name_id'     => $v->item->name_id,
                    'name_zh'     => $v->item->name_zh,
                    'base_uom'           => $v->item->base_uom,
                    'alt_uom'            => $v->item->alt_uom,
                    'alt_uom_conversion' => $v->item->alt_uom_conversion ? (float) $v->item->alt_uom_conversion : null,
                    'category'    => $v->item->category ? [
                        'id'      => $v->item->category->id,
                        'code'    => $v->item->category->code,
                        'name_en' => $v->item->category->name_en,
                        'name_id' => $v->item->category->name_id,
                        'name_zh' => $v->item->category->name_zh,
                    ] : null,
                ] : null,
                'stock_ledgers' => $v->stockLedgers->map(fn ($sl) => [
                    'id'           => $sl->id,
                    'qty_on_hand'  => (float) $sl->qty_on_hand,
                    'qty_reserved' => (float) $sl->qty_reserved,
                    'qty_available'=> (float) $sl->qty_available,
                    'location_id'  => $sl->location_id,
                    'warehouse_id' => $sl->warehouse_id,
                    'location'     => $sl->location ? [
                        'id'           => $sl->location->id,
                        'code'         => $sl->location->code,
                        'name'         => $sl->location->name,
                        'warehouse_id' => $sl->location->warehouse_id,
                        'warehouse'    => $sl->location->warehouse ? [
                            'id'   => $sl->location->warehouse->id,
                            'code' => $sl->location->warehouse->code,
                            'name' => $sl->location->warehouse->name,
                        ] : null,
                    ] : null,
                ])->values()->all(),
            ]);

        // categories for filter
        $categories = \App\Models\ItemCategory::orderBy('code')->get(['id', 'code', 'name_en', 'name_id', 'name_zh']);

        // warehouses + their active locations (for the modal form)
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')
            ->with(['locations' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->get()
            ->map(fn ($wh) => [
                'id'        => $wh->id,
                'code'      => $wh->code,
                'name'      => $wh->name,
                'locations' => $wh->locations->map(fn ($l) => [
                    'id'   => $l->id,
                    'code' => $l->code,
                    'name' => $l->name,
                ])->values()->all(),
            ]);

        // totals for stat cards
        $totalVariants = ItemVariant::where('is_active', true)->count();
        $withStock     = StockLedger::distinct('item_variant_id')->count('item_variant_id');
        $totalQty      = StockLedger::sum('qty_on_hand');

        return Inertia::render('Master/StockInput/Index', [
            'variants'   => $variants,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'filters'    => $request->only(['search', 'category_id']),
            'stats'      => [
                'totalVariants' => $totalVariants,
                'withStock'     => $withStock,
                'noStock'       => $totalVariants - $withStock,
                'totalQty'      => (float) $totalQty,
            ],
        ]);
    }

    public function upsert(Request $request, ItemVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'qty_on_hand' => ['required', 'numeric', 'min:0'],
        ]);

        $location    = Location::findOrFail($data['location_id']);
        $warehouseId = $location->warehouse_id;

        StockLedger::updateOrCreate(
            [
                'item_variant_id' => $variant->id,
                'location_id'     => $data['location_id'],
            ],
            [
                'warehouse_id'    => $warehouseId,
                'qty_on_hand'     => $data['qty_on_hand'],
                'last_updated_at' => now(),
            ]
        );

        return back()->with('success', 'Stock updated successfully.');
    }

    public function destroy(StockLedger $stockLedger): RedirectResponse
    {
        $stockLedger->delete();
        return back()->with('success', 'Stock entry removed.');
    }
}
