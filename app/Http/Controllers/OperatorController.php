<?php

namespace App\Http\Controllers;

use App\Models\GoodsIssue;
use App\Models\GoodsIssuePhoto;
use App\Models\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    // ── Scan List: operator / wh_admin sees their assigned GI ──────────────────

    public function scanList(Request $request): Response
    {
        $this->authorize($request, ['operator', 'wh_admin']);

        $user = $request->user();

        $gis = GoodsIssue::query()
            ->with([
                'warehouse:id,code,name',
                'department:id,name,code',
                'requestedBy:id,name',
                'items:id,goods_issue_id,item_variant_id,requested_qty,requested_uom,actual_qty',
                'items.variant:id,sku,item_id',
                'items.variant.item:id,name_en,name_id,name_zh',
            ])
            ->withCount('items')
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['assigned', 'in_picking', 'ready_to_pickup'])
            ->latest()
            ->get();

        return Inertia::render('Operator/ScanList', [
            'gis' => $gis->values(),
        ]);
    }

    // ── Scan Detail: GI details + location/rack + photo upload ─────────────────

    public function scanDetail(Request $request, GoodsIssue $goodsIssue): Response
    {
        $this->authorize($request, ['operator', 'wh_admin']);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id) {
            abort(403);
        }

        $goodsIssue->load([
            'warehouse:id,code,name',
            'department:id,name,code',
            'requestedBy:id,name',
            'items.variant:id,sku,item_id,base_uom,alt_uom',
            'items.variant.item:id,name_en,name_id,name_zh',
            'items.itemWarehouse:id,code,name',
            'items.lv:id,lv_code,lv_number',
            'items.employee:id,employee_id,name',
            'photos',
        ]);

        // Build location map — show operator WHERE to get each item
        $locationMap = [];
        foreach ($goodsIssue->items as $gItem) {
            $whId = $gItem->item_warehouse_id ?? $goodsIssue->warehouse_id;
            $vid  = $gItem->item_variant_id;

            $ledgers = StockLedger::where('item_variant_id', $vid)
                ->where('warehouse_id', $whId)
                ->where('qty_on_hand', '>', 0)
                ->with('location:id,code,name')
                ->orderByDesc('qty_on_hand')
                ->get(['item_variant_id', 'location_id', 'qty_on_hand', 'qty_reserved']);

            if (!isset($locationMap[$vid])) $locationMap[$vid] = [];
            foreach ($ledgers as $ledger) {
                $locationMap[$vid][] = [
                    'location_code' => $ledger->location?->code,
                    'location_name' => $ledger->location?->name,
                    'qty_on_hand'   => (float) $ledger->qty_on_hand,
                    'qty_reserved'  => (float) $ledger->qty_reserved,
                    'available'     => max(0, (float) $ledger->qty_on_hand - (float) $ledger->qty_reserved),
                ];
            }
        }

        return Inertia::render('Operator/ScanDetail', [
            'gi'          => $goodsIssue,
            'locationMap' => $locationMap,
        ]);
    }

    // ── Submit Pickup: upload photo, mark GI completed ──────────────────────────

    public function submitPickup(Request $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorize($request, ['operator', 'wh_admin']);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id) {
            abort(403);
        }

        $request->validate([
            'photos'   => ['required', 'array', 'min:1'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Upload proof photos
        foreach ($request->file('photos') as $file) {
            $path = $file->store('gi-photos', 'public');
            GoodsIssuePhoto::create([
                'goods_issue_id' => $goodsIssue->id,
                'path'           => $path,
                'original_name'  => $file->getClientOriginalName(),
                'stage'          => 'pickup',
                'uploaded_by'    => $user->id,
            ]);
        }

        // Mark GI as completed
        $goodsIssue->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'picked_by'    => $user->id,
            'picked_at'    => now(),
        ]);

        return redirect()
            ->route('operator.scan-list')
            ->with('success', "GI {$goodsIssue->gi_number} selesai — barang diserahkan.");
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function authorize(Request $request, array $roles): void
    {
        $user = $request->user();
        $ok   = in_array($user->role, $roles) ||
                !empty(array_intersect($roles, $user->extra_roles ?? []));
        if (!$ok) abort(403);
    }
}
