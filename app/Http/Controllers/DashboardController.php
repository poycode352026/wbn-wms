<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\Location;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalItems    = Item::where('is_active', true)->count();
        $totalVariants = ItemVariant::count();
        $totalStockQty = (float) StockLedger::sum('qty_on_hand');
        $totalWarehouses = Warehouse::where('is_active', true)->count();

        // Low stock: items with minimum_stock > 0 where sum of all variant stock < minimum_stock
        $lowStockCount = Item::where('is_active', true)
            ->where('minimum_stock', '>', 0)
            ->get()
            ->filter(function ($item) {
                $totalStock = StockLedger::whereIn(
                    'item_variant_id',
                    $item->variants()->pluck('id')
                )->sum('qty_on_hand');
                return $totalStock < $item->minimum_stock;
            })
            ->count();

        // Warehouse stock summary for capacity panel
        $warehouseStats = Warehouse::where('is_active', true)
            ->withCount('locations')
            ->get()
            ->map(function ($wh) {
                $totalQty = StockLedger::where('warehouse_id', $wh->id)->sum('qty_on_hand');
                $rackCount = $wh->locations_count;
                return [
                    'id'        => $wh->id,
                    'code'      => $wh->code,
                    'name'      => $wh->name,
                    'location'  => $wh->location,
                    'rackCount' => $rackCount,
                    'totalQty'  => (float) $totalQty,
                ];
            });

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'totalItems'     => $totalItems,
                'totalVariants'  => $totalVariants,
                'totalStockQty'  => $totalStockQty,
                'totalWarehouses'=> $totalWarehouses,
                'lowStockCount'  => $lowStockCount,
            ],
            'warehouseStats' => $warehouseStats,
        ]);
    }
}
