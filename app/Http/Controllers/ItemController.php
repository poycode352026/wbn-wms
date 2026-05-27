<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemStoreRequest;
use App\Http\Requests\ItemUpdateRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['part_number', 'name_en', 'minimum_stock'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $items = Item::query()
            ->with([
                'category:id,code,name_id,name_en,name_zh',
                'warehouse:id,code,name',
                'variants' => fn ($q) => $q->orderBy('id'),
            ])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('part_number', 'like', "%{$s}%")
                       ->orWhere('name_en', 'like', "%{$s}%")
                       ->orWhere('name_id', 'like', "%{$s}%")
                       ->orWhere('name_zh', 'like', "%{$s}%")
                ))
            ->when($request->warehouse_id, fn ($q) =>
                $q->where('warehouse_id', $request->warehouse_id)
            )
            ->when($request->category_id, fn ($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('part_number'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($i) => [
                'id'                 => $i->id,
                'part_number'        => $i->part_number,
                'name_id'            => $i->name_id,
                'name_en'            => $i->name_en,
                'name_zh'            => $i->name_zh,
                'description'        => $i->description,
                'base_uom'           => $i->base_uom,
                'alt_uom'            => $i->alt_uom,
                'alt_uom_conversion' => $i->alt_uom_conversion,
                'minimum_stock'      => $i->minimum_stock,
                'has_cooldown'       => $i->has_cooldown,
                'cooldown_days'      => $i->cooldown_days,
                'cooldown_track_by'  => $i->cooldown_track_by,
                'is_active'          => $i->is_active,
                'category'           => $i->category ? [
                    'id'      => $i->category->id,
                    'code'    => $i->category->code,
                    'name_id' => $i->category->name_id,
                    'name_en' => $i->category->name_en,
                    'name_zh' => $i->category->name_zh,
                ] : null,
                'warehouse' => $i->warehouse ? [
                    'id'   => $i->warehouse->id,
                    'code' => $i->warehouse->code,
                    'name' => $i->warehouse->name,
                ] : null,
                'variants' => $i->variants->map(fn ($v) => [
                    'id'        => $v->id,
                    'brand'     => $v->brand,
                    'model'     => $v->model,
                    'size'      => $v->size,
                    'color'     => $v->color,
                    'sku'       => $v->sku,
                    'is_active' => $v->is_active,
                ])->values(),
            ]);

        $categories = ItemCategory::query()
            ->with('warehouse:id,code,name')
            ->withCount('items')
            ->when($request->cat_search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name_en', 'like', "%{$s}%")
                       ->orWhere('name_id', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%")
                ))
            ->when($request->cat_warehouse, fn ($q) =>
                $q->where('warehouse_id', $request->cat_warehouse)
            )
            ->orderBy('warehouse_id')
            ->orderBy('code')
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'warehouse_id' => $c->warehouse_id,
                'code'        => $c->code,
                'name_id'     => $c->name_id,
                'name_en'     => $c->name_en,
                'name_zh'     => $c->name_zh,
                'is_active'   => $c->is_active,
                'items_count' => $c->items_count,
                'warehouse'   => $c->warehouse ? [
                    'id'   => $c->warehouse->id,
                    'code' => $c->warehouse->code,
                    'name' => $c->warehouse->name,
                ] : null,
            ])
            ->values();

        return Inertia::render('Master/Items/Index', [
            'items'      => $items,
            'categories' => $categories,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters'    => $request->only([
                'search', 'warehouse_id', 'category_id', 'status',
                'sort', 'dir', 'cat_search', 'cat_warehouse', 'tab',
            ]),
            'stats' => [
                'totalItems'      => Item::count(),
                'activeItems'     => Item::where('is_active', true)->count(),
                'totalCategories' => ItemCategory::count(),
                'totalVariants'   => ItemVariant::count(),
            ],
        ]);
    }

    public function store(ItemStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['name_id'] = $data['name_id'] ?? $data['name_en'];
        $data['name_zh'] = $data['name_zh'] ?? $data['name_en'];
        $item = Item::create($data);
        return back()->with('success', 'Item created successfully.')->with('newItemId', $item->id);
    }

    public function update(ItemUpdateRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();
        $data['name_id'] = $data['name_id'] ?? $data['name_en'];
        $data['name_zh'] = $data['name_zh'] ?? $data['name_en'];
        $item->update($data);
        return back()->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->variants()->delete();
        $item->delete();
        return back()->with('success', 'Item deleted.');
    }

    // ── Variants ──────────────────────────────────────────────────────────

    public function storeVariant(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'brand'     => ['nullable', 'string', 'max:100'],
            'model'     => ['nullable', 'string', 'max:100'],
            'size'      => ['nullable', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:100'],
            'sku'       => ['nullable', 'string', 'max:100', 'unique:item_variants,sku'],
            'is_active' => ['boolean'],
        ]);
        $item->variants()->create($request->only(['brand', 'model', 'size', 'color', 'sku', 'is_active']));
        return back()->with('success', 'Variant added.');
    }

    public function updateVariant(Request $request, Item $item, ItemVariant $itemVariant): RedirectResponse
    {
        $request->validate([
            'brand'     => ['nullable', 'string', 'max:100'],
            'model'     => ['nullable', 'string', 'max:100'],
            'size'      => ['nullable', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:100'],
            'sku'       => ['nullable', 'string', 'max:100', 'unique:item_variants,sku,' . $itemVariant->id],
            'is_active' => ['boolean'],
        ]);
        $itemVariant->update($request->only(['brand', 'model', 'size', 'color', 'sku', 'is_active']));
        return back()->with('success', 'Variant updated.');
    }

    public function destroyVariant(Item $item, ItemVariant $itemVariant): RedirectResponse
    {
        $itemVariant->delete();
        return back()->with('success', 'Variant deleted.');
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'code'         => ['required', 'string', 'max:20', Rule::unique('item_categories')->where('warehouse_id', $request->warehouse_id)],
            'name_id'      => ['required', 'string', 'max:150'],
            'name_en'      => ['required', 'string', 'max:150'],
            'name_zh'      => ['required', 'string', 'max:150'],
            'is_active'    => ['boolean'],
        ]);
        ItemCategory::create($request->only(['warehouse_id', 'code', 'name_id', 'name_en', 'name_zh', 'is_active']));
        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'code'         => ['required', 'string', 'max:20', Rule::unique('item_categories')->where('warehouse_id', $request->warehouse_id)->ignore($itemCategory->id)],
            'name_id'      => ['required', 'string', 'max:150'],
            'name_en'      => ['required', 'string', 'max:150'],
            'name_zh'      => ['required', 'string', 'max:150'],
            'is_active'    => ['boolean'],
        ]);
        $itemCategory->update($request->only(['warehouse_id', 'code', 'name_id', 'name_en', 'name_zh', 'is_active']));
        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(ItemCategory $itemCategory): RedirectResponse
    {
        if ($itemCategory->items()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete: category has items assigned.']);
        }
        $itemCategory->delete();
        return back()->with('success', 'Category deleted.');
    }
}