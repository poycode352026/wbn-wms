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
                'category:id,code,prefix,name_id,name_en,name_zh',
                'variants' => fn ($q) => $q->orderBy('id'),
            ])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('part_number', 'like', "%{$s}%")
                       ->orWhere('name_en', 'like', "%{$s}%")
                       ->orWhere('name_id', 'like', "%{$s}%")
                       ->orWhere('name_zh', 'like', "%{$s}%")
                ))
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
                    'prefix'  => $i->category->prefix,
                    'name_id' => $i->category->name_id,
                    'name_en' => $i->category->name_en,
                    'name_zh' => $i->category->name_zh,
                ] : null,
                'photo_required' => $i->photo_required,
                'variants' => $i->variants->map(fn ($v) => [
                    'id'         => $v->id,
                    'brand'      => $v->brand,
                    'model'      => $v->model,
                    'size'       => $v->size,
                    'color'      => $v->color,
                    'sku'        => $v->sku,
                    'photo_path' => $v->photo_path ? asset('storage/' . $v->photo_path) : null,
                    'is_active'  => $v->is_active,
                ])->values(),
            ]);

        $categories = ItemCategory::query()
            ->withCount('items')
            ->when($request->cat_search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name_en', 'like', "%{$s}%")
                       ->orWhere('name_id', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%")
                ))
            ->orderBy('code')
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'code'        => $c->code,
                'prefix'      => $c->prefix,
                'name_id'     => $c->name_id,
                'name_en'     => $c->name_en,
                'name_zh'     => $c->name_zh,
                'is_active'   => $c->is_active,
                'items_count' => $c->items_count,
            ])
            ->values();

        return Inertia::render('Master/Items/Index', [
            'items'      => $items,
            'categories' => $categories,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters'    => $request->only([
                'search', 'category_id', 'status',
                'sort', 'dir', 'cat_search', 'tab',
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
            'photo'     => [$item->photo_required ? 'required' : 'nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $seq  = $item->variants()->withTrashed()->count() + 1;
        $sku  = $item->part_number . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $data = $request->only(['brand', 'model', 'size', 'color', 'is_active']);
        $data['sku'] = $sku;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store("variants/{$item->id}", 'public');
        }

        $item->variants()->create($data);
        return back()->with('success', 'Variant added.');
    }

    public function updateVariant(Request $request, Item $item, ItemVariant $itemVariant): RedirectResponse
    {
        $request->validate([
            'brand'     => ['nullable', 'string', 'max:100'],
            'model'     => ['nullable', 'string', 'max:100'],
            'size'      => ['nullable', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:100'],
            'photo'     => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $data = $request->only(['brand', 'model', 'size', 'color', 'is_active']);

        if ($request->hasFile('photo')) {
            if ($itemVariant->photo_path) {
                \Storage::disk('public')->delete($itemVariant->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store("variants/{$item->id}", 'public');
        }

        $itemVariant->update($data);
        return back()->with('success', 'Variant updated.');
    }

    public function destroyVariant(Item $item, ItemVariant $itemVariant): RedirectResponse
    {
        if ($itemVariant->photo_path) {
            \Storage::disk('public')->delete($itemVariant->photo_path);
        }
        $itemVariant->delete();
        return back()->with('success', 'Variant deleted.');
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->validate([
            'code'      => ['required', 'string', 'max:20', 'unique:item_categories,code'],
            'prefix'    => ['required', 'string', 'max:10'],
            'name_id'   => ['required', 'string', 'max:150'],
            'name_en'   => ['required', 'string', 'max:150'],
            'name_zh'   => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);
        ItemCategory::create($request->only(['code', 'prefix', 'name_id', 'name_en', 'name_zh', 'is_active']));
        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $request->validate([
            'code'      => ['required', 'string', 'max:20', Rule::unique('item_categories', 'code')->ignore($itemCategory->id)],
            'prefix'    => ['required', 'string', 'max:10'],
            'name_id'   => ['required', 'string', 'max:150'],
            'name_en'   => ['required', 'string', 'max:150'],
            'name_zh'   => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);
        $itemCategory->update($request->only(['code', 'prefix', 'name_id', 'name_en', 'name_zh', 'is_active']));
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