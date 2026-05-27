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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        // Delete all variant photos from disk
        foreach ($item->variants as $v) {
            if ($v->photo_path) Storage::disk('public')->delete($v->photo_path);
        }
        // Delete the whole item folder
        Storage::disk('public')->deleteDirectory("variants/{$item->id}");
        $item->variants()->forceDelete();
        $item->forceDelete();
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
            'photo'     => [$item->photo_required ? 'required' : 'nullable', 'image', 'max:5120'],
            'is_active' => ['boolean'],
        ]);

        $seq  = $item->variants()->withTrashed()->count() + 1;
        $sku  = $item->part_number . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $data = $request->only(['brand', 'model', 'size', 'color', 'is_active']);
        $data['sku'] = $sku;

        if ($request->hasFile('photo')) {
            $ext  = $request->file('photo')->getClientOriginalExtension();
            $data['photo_path'] = $request->file('photo')
                ->storeAs("variants/{$item->id}", "{$sku}.{$ext}", 'public');
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
            'photo'     => ['nullable', 'image', 'max:5120'],
            'is_active' => ['boolean'],
        ]);

        $data = $request->only(['brand', 'model', 'size', 'color', 'is_active']);

        if ($request->hasFile('photo')) {
            if ($itemVariant->photo_path) {
                Storage::disk('public')->delete($itemVariant->photo_path);
            }
            $ext  = $request->file('photo')->getClientOriginalExtension();
            $data['photo_path'] = $request->file('photo')
                ->storeAs("variants/{$item->id}", "{$itemVariant->sku}.{$ext}", 'public');
        }

        $itemVariant->update($data);
        return back()->with('success', 'Variant updated.');
    }

    public function destroyVariant(Item $item, ItemVariant $itemVariant): RedirectResponse
    {
        if ($itemVariant->photo_path) {
            Storage::disk('public')->delete($itemVariant->photo_path);
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

    // ── Export ────────────────────────────────────────────────────────────

    public function export(): StreamedResponse
    {
        $items = Item::query()
            ->with(['category:id,code,prefix', 'variants' => fn ($q) => $q->orderBy('id')])
            ->orderBy('part_number')
            ->get();

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($out, [
                'category_code', 'part_suffix', 'name_en', 'name_id', 'name_zh',
                'description', 'base_uom', 'alt_uom', 'alt_uom_conversion',
                'minimum_stock', 'has_cooldown', 'cooldown_days', 'cooldown_track_by',
                'photo_required', 'is_active',
                'variant_brand', 'variant_model', 'variant_size', 'variant_color',
                'variant_sku', 'variant_is_active',
            ]);

            foreach ($items as $item) {
                $cat    = $item->category;
                $prefix = $cat ? "WBN-{$cat->prefix}-" : 'WBN-???-';
                $suffix = $cat ? substr($item->part_number, strlen($prefix)) : $item->part_number;

                $base = [
                    $cat?->code ?? '',
                    $suffix,
                    $item->name_en,
                    $item->name_id,
                    $item->name_zh,
                    $item->description ?? '',
                    $item->base_uom,
                    $item->alt_uom ?? '',
                    $item->alt_uom_conversion ?? '',
                    $item->minimum_stock,
                    $item->has_cooldown ? 'yes' : 'no',
                    $item->cooldown_days ?? '',
                    $item->cooldown_track_by ?? '',
                    $item->photo_required ? 'yes' : 'no',
                    $item->is_active ? 'yes' : 'no',
                ];

                if ($item->variants->isEmpty()) {
                    fputcsv($out, array_merge($base, ['', '', '', '', '', 'yes']));
                } else {
                    foreach ($item->variants as $v) {
                        fputcsv($out, array_merge($base, [
                            $v->brand ?? '',
                            $v->model ?? '',
                            $v->size  ?? '',
                            $v->color ?? '',
                            $v->sku,
                            $v->is_active ? 'yes' : 'no',
                        ]));
                    }
                }
            }

            fclose($out);
        }, 'items_export_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Import Template ───────────────────────────────────────────────────

    public function importTemplate(): StreamedResponse
    {
        $cats = ItemCategory::where('is_active', true)->orderBy('code')->get(['code', 'prefix']);

        return response()->streamDownload(function () use ($cats) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            // Header row
            fputcsv($out, [
                'category_code', 'part_suffix', 'name_en', 'name_id', 'name_zh',
                'description', 'base_uom', 'alt_uom', 'alt_uom_conversion',
                'minimum_stock', 'has_cooldown(yes/no)', 'cooldown_days',
                'cooldown_track_by(lv_number|employee_id)', 'photo_required(yes/no)',
                'is_active(yes/no)', 'variant_brand', 'variant_model',
                'variant_size', 'variant_color', 'variant_is_active(yes/no)',
            ]);

            // Info row: available categories
            $catList = $cats->map(fn ($c) => "{$c->code}→WBN-{$c->prefix}-???")->join(' | ');
            fputcsv($out, ["# Kategori tersedia: {$catList}"]);

            // Sample item 1: 2 variants
            fputcsv($out, ['IT', 'LTDL', 'Laptop Dell XPS 13', 'Laptop Dell XPS 13', '戴尔笔记本',
                'Laptop for office use', 'unit', '', '', '5',
                'no', '', '', 'yes', 'yes', 'Dell', 'XPS 13 9310', '13.4 inch', 'Silver', 'yes']);
            fputcsv($out, ['IT', 'LTDL', '', '', '', '', '', '', '', '',
                '', '', '', '', '', 'Dell', 'XPS 13 9310', '13.4 inch', 'Black', 'yes']);

            // Sample item 2: with cooldown, no variant attributes
            fputcsv($out, ['ATK', 'BLPT', 'Ballpoint Pen', 'Pulpen', '圆珠笔',
                '', 'pcs', 'box', '12', '100',
                'yes', '30', 'employee_id', 'no', 'yes', '', '', '', '', 'yes']);

            // Sample item 3: PPE, no variant
            fputcsv($out, ['PPE', 'HLMT', 'Safety Helmet', 'Helm Safety', '安全帽',
                'Standard safety helmet', 'unit', '', '', '20',
                'no', '', '', 'no', 'yes', '', '', 'L', 'Yellow', 'yes']);

            fclose($out);
        }, 'items_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Import ────────────────────────────────────────────────────────────

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $categories = ItemCategory::where('is_active', true)->get()->keyBy('code');

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // Read and normalize header (strip hints like "(yes/no)")
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            return back()->withErrors(['file' => 'File kosong atau format tidak valid.']);
        }
        $header = array_map(fn ($h) => trim(preg_replace('/\s*\(.*?\)\s*/', '', $h)), $rawHeader);
        $col    = array_flip($header);

        // Helper: get column value safely
        $get = fn (array $row, string $key, string $default = ''): string =>
            trim($row[$col[$key] ?? PHP_INT_MAX] ?? $default);

        // Read all data rows, skip comment/empty rows
        $allRows = [];
        $rowNum  = 2;
        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row) || str_starts_with(trim($row[0] ?? ''), '#')) {
                $rowNum++;
                continue;
            }
            $allRows[] = ['data' => $row, 'num' => $rowNum++];
        }
        fclose($handle);

        if (empty($allRows)) {
            return back()->withErrors(['file' => 'Tidak ada baris data dalam file.']);
        }

        // Group rows by item key (category_code + part_suffix)
        // Rows with blank category/suffix = continuation variant of previous item
        $groups     = [];
        $currentKey = null;

        foreach ($allRows as $r) {
            $catCode = strtoupper($get($r['data'], 'category_code'));
            $suffix  = strtoupper(preg_replace('/[^A-Z0-9]/', '', $get($r['data'], 'part_suffix')));

            if ($catCode && $suffix) {
                $currentKey = "{$catCode}|{$suffix}";
                if (!isset($groups[$currentKey])) {
                    $groups[$currentKey] = [
                        'itemRow'     => $r,
                        'variantRows' => [],
                        'catCode'     => $catCode,
                        'suffix'      => $suffix,
                    ];
                }
            }

            if ($currentKey !== null) {
                $groups[$currentKey]['variantRows'][] = $r;
            }
        }

        $errors          = [];
        $createdItems    = 0;
        $createdVariants = 0;

        DB::beginTransaction();
        try {
            foreach ($groups as $group) {
                ['catCode' => $catCode, 'suffix' => $suffix, 'itemRow' => $itemRow] = $group;
                $row    = $itemRow['data'];
                $rowNum = $itemRow['num'];

                // Validate category
                if (!isset($categories[$catCode])) {
                    $errors[] = "Row {$rowNum}: Kategori '{$catCode}' tidak ditemukan.";
                    continue;
                }
                $category   = $categories[$catCode];
                $partNumber = "WBN-{$category->prefix}-{$suffix}";

                // Validate required fields
                $nameEn  = $get($row, 'name_en');
                $baseUom = strtoupper($get($row, 'base_uom'));
                if (!$nameEn) { $errors[] = "Row {$rowNum}: name_en wajib diisi."; continue; }
                if (!$baseUom) { $errors[] = "Row {$rowNum}: base_uom wajib diisi."; continue; }

                // Check duplicate part_number
                if (Item::where('part_number', $partNumber)->exists()) {
                    $errors[] = "Row {$rowNum}: Part number '{$partNumber}' sudah ada, dilewati.";
                    continue;
                }

                $hasCooldown    = in_array(strtolower($get($row, 'has_cooldown')), ['yes', '1', 'true']);
                $photoRequired  = in_array(strtolower($get($row, 'photo_required')), ['yes', '1', 'true']);
                $isActive       = !in_array(strtolower($get($row, 'is_active')), ['no', '0', 'false']);
                $altUom         = strtoupper($get($row, 'alt_uom')) ?: null;
                $altConv        = $get($row, 'alt_uom_conversion') !== '' ? (float) $get($row, 'alt_uom_conversion') : null;
                $cooldownDays   = $get($row, 'cooldown_days') !== '' ? (int) $get($row, 'cooldown_days') : null;
                $trackBy        = $get($row, 'cooldown_track_by') ?: 'employee_id';

                $item = Item::create([
                    'category_id'        => $category->id,
                    'part_number'        => $partNumber,
                    'name_en'            => $nameEn,
                    'name_id'            => $get($row, 'name_id') ?: $nameEn,
                    'name_zh'            => $get($row, 'name_zh') ?: $nameEn,
                    'description'        => $get($row, 'description') ?: null,
                    'base_uom'           => $baseUom,
                    'alt_uom'            => $altUom,
                    'alt_uom_conversion' => $altConv,
                    'minimum_stock'      => (float) ($get($row, 'minimum_stock') ?: 0),
                    'has_cooldown'       => $hasCooldown,
                    'cooldown_days'      => $hasCooldown ? $cooldownDays : null,
                    'cooldown_track_by'  => $hasCooldown ? $trackBy : null,
                    'photo_required'     => $photoRequired,
                    'is_active'          => $isActive,
                ]);
                $createdItems++;

                // Create variants
                $seq = 0;
                foreach ($group['variantRows'] as $vr) {
                    $vd     = $vr['data'];
                    $brand  = $get($vd, 'variant_brand') ?: null;
                    $model  = $get($vd, 'variant_model') ?: null;
                    $size   = $get($vd, 'variant_size') ?: null;
                    $color  = $get($vd, 'variant_color') ?: null;
                    $vActive = !in_array(strtolower($get($vd, 'variant_is_active')), ['no', '0', 'false']);

                    // Skip variant-only rows with no data at all
                    if (!$brand && !$model && !$size && !$color && $seq > 0) continue;

                    $seq++;
                    $sku = $partNumber . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
                    $item->variants()->create([
                        'sku'       => $sku,
                        'brand'     => $brand,
                        'model'     => $model,
                        'size'      => $size,
                        'color'     => $color,
                        'is_active' => $vActive,
                    ]);
                    $createdVariants++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Import gagal: ' . $e->getMessage()]);
        }

        $msg = "✅ Import selesai: {$createdItems} item, {$createdVariants} variant berhasil dibuat.";
        if ($errors) {
            $msg .= ' ⚠ ' . implode(' · ', $errors);
        }

        return back()->with('success', $msg);
    }
}
