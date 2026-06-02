<?php
namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LowStockController extends Controller
{
    public function index(Request $request): Response
    {
        $search   = trim($request->get('search', ''));
        $category = $request->get('category', '');

        // ── Build variant query ────────────────────────────────────────────────
        $variantQuery = ItemVariant::where('is_active', true)
            ->whereHas('item', fn($q) => $q
                ->where('is_active', true)
                ->where('minimum_stock', '>', 0)
            )
            ->with([
                'item' => fn($q) => $q
                    ->select('id','name_en','name_id','name_zh','base_uom','minimum_stock','category_id')
                    ->with('category:id,name_en,name_id,name_zh'),
            ])
            ->select('id', 'item_id', 'sku', 'brand', 'model', 'size', 'color');

        if ($search !== '') {
            $variantQuery->where(fn($q) =>
                $q->where('sku',   'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('size',  'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhereHas('item', fn($iq) =>
                      $iq->where('name_en', 'like', "%{$search}%")
                         ->orWhere('name_id', 'like', "%{$search}%")
                         ->orWhere('name_zh', 'like', "%{$search}%")
                  )
            );
        }

        if ($category !== '') {
            $variantQuery->whereHas('item', fn($q) => $q->where('category_id', $category));
        }

        $variants = $variantQuery->get();

        // ── Single SOH query for all variant IDs ───────────────────────────────
        $sohMap = StockLedger::whereIn('item_variant_id', $variants->pluck('id'))
            ->selectRaw('item_variant_id, SUM(qty_on_hand) as total_soh')
            ->groupBy('item_variant_id')
            ->pluck('total_soh', 'item_variant_id');

        // ── Filter & map to low-stock rows ─────────────────────────────────────
        $lowStockItems = $variants
            ->filter(function ($v) use ($sohMap) {
                $soh = (float) ($sohMap[$v->id] ?? 0);
                return $soh < (float) $v->item->minimum_stock;
            })
            ->map(function ($v) use ($sohMap) {
                $soh     = (float) ($sohMap[$v->id] ?? 0);
                $item    = $v->item;
                $details = collect([$v->brand, $v->model, $v->size, $v->color])
                    ->filter()->join(' · ');

                return [
                    'id'            => $item->id,
                    'variant_id'    => $v->id,
                    'sku'           => $v->sku ?? '—',
                    'name_en'       => $item->name_en,
                    'name_id'       => $item->name_id,
                    'name_zh'       => $item->name_zh,
                    'category_en'   => $item->category?->name_en,
                    'category_id_'  => $item->category?->name_id,
                    'details'       => $details ?: null,
                    'soh'           => $soh,
                    'min'           => (float) $item->minimum_stock,
                    'suggested_qty' => (float) max(1, $item->minimum_stock - $soh),
                    'uom'           => $item->base_uom ?? '',
                ];
            })
            ->sortBy('name_en')
            ->values();

        $categories = ItemCategory::orderBy('name_en')->get(['id', 'name_en', 'name_id', 'name_zh']);

        return Inertia::render('Reports/LowStock', [
            'lowStockItems' => $lowStockItems,
            'totalCount'    => $lowStockItems->count(),
            'filters'       => ['search' => $search, 'category' => $category],
            'categories'    => $categories,
        ]);
    }
}
