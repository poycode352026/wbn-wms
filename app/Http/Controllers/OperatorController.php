<?php

namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\EmployeeRequest;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssuePhoto;
use App\Models\StockLedger;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    // ── Scan List: operator / wh_admin sees their assigned GI ─────────────────

    public function scanList(Request $request): Response
    {
        $this->authorizeOp($request);

        $user = $request->user();

        $gis = GoodsIssue::query()
            ->with([
                'warehouse:id,code,name',
                'department:id,name,code',
                'requestedBy:id,name',
                'items:id,goods_issue_id,item_variant_id,requested_qty,requested_uom',
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

    // ── Scan Detail: GI details + location/rack info ───────────────────────────

    public function scanDetail(Request $request, GoodsIssue $goodsIssue): Response
    {
        $this->authorizeOp($request);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id) {
            abort(403);
        }

        $goodsIssue->load([
            'warehouse:id,code,name',
            'department:id,name,code',
            'requestedBy:id,name',
            'items.variant:id,sku,item_id',
            'items.variant.item:id,name_en,name_id,name_zh,base_uom',
            'items.itemWarehouse:id,code,name',
            'items.lv:id,lv_number',
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
                    'warehouse_id'   => $whId,
                    'warehouse_code' => $gItem->itemWarehouse?->code ?? $goodsIssue->warehouse?->code ?? '—',
                    'warehouse_name' => $gItem->itemWarehouse?->name ?? $goodsIssue->warehouse?->name ?? '—',
                    'location_code'  => $ledger->location?->code,
                    'location_name'  => $ledger->location?->name,
                    'qty_on_hand'    => (float) $ledger->qty_on_hand,
                    'qty_reserved'   => (float) $ledger->qty_reserved,
                    'available'      => max(0, (float) $ledger->qty_on_hand - (float) $ledger->qty_reserved),
                ];
            }
        }

        return Inertia::render('Operator/ScanDetail', [
            'gi'          => $goodsIssue,
            'locationMap' => $locationMap,
        ]);
    }

    // ── Start Picking: assigned → in_picking ───────────────────────────────────

    public function startPicking(Request $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorizeOp($request);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id) {
            abort(403);
        }

        if ($goodsIssue->status !== 'assigned') {
            return back()->with('error', 'GI tidak dalam status assigned.');
        }

        $goodsIssue->update([
            'status'    => 'in_picking',
            'picked_by' => $user->id,
            'picked_at' => now(),
        ]);

        return redirect()
            ->route('operator.scan-detail', $goodsIssue->id)
            ->with('success', 'Picking dimulai! Siapkan semua barang.');
    }

    // ── Submit Pickup: in_picking + actual qty per item → ready_to_pickup ──────

    public function submitPickup(Request $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorizeOp($request);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id && $goodsIssue->picked_by !== $user->id) {
            abort(403);
        }

        if ($goodsIssue->status !== 'in_picking') {
            return back()->with('error', 'GI tidak dalam status in_picking. Mulai proses picking terlebih dahulu.');
        }

        $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'exists:goods_issue_items,id'],
            'items.*.actual_qty' => ['required', 'numeric', 'min:0'],
            'items.*.status'     => ['nullable', 'in:ready,rejected'],
            'items.*.notes'      => ['nullable', 'string', 'max:500'],
        ]);

        // Rejected items MUST have a reason
        foreach ($request->input('items', []) as $itemData) {
            if (($itemData['status'] ?? 'ready') === 'rejected' && empty(trim($itemData['notes'] ?? ''))) {
                return back()->with('error', 'Alasan penolakan wajib diisi untuk setiap item yang ditolak.');
            }
        }

        DB::transaction(function () use ($request, $goodsIssue) {
            foreach ($request->input('items') as $itemData) {
                // Operator can explicitly set status; fallback: qty>0 → ready, else → rejected
                $status = $itemData['status'] ?? ($itemData['actual_qty'] > 0 ? 'ready' : 'rejected');
                GoodsIssueItem::where('id', $itemData['id'])
                    ->where('goods_issue_id', $goodsIssue->id)
                    ->update([
                        'actual_qty' => $itemData['actual_qty'],
                        'status'     => $status,
                        'notes'      => $itemData['notes'] ?? null,
                    ]);
            }

            $goodsIssue->update(['status' => 'ready_to_pickup']);
        });

        // Notify Admin Dept requester
        NotificationService::send(
            $goodsIssue->requested_by,
            'GI_READY_PICKUP',
            "Barang Siap: {$goodsIssue->gi_number}",
            "Barang Anda sudah disiapkan di staging area. Datang ke gudang dan tunjukkan barcode.",
            [
                'gi_id'     => $goodsIssue->id,
                'gi_number' => $goodsIssue->gi_number,
                'route'     => '/goods-issues/' . $goodsIssue->id,
            ]
        );

        return redirect()
            ->route('operator.scan-detail', $goodsIssue->id)
            ->with('success', 'Barang siap! Informasikan ke requester untuk datang ambil.');
    }

    // ── Confirm Pickup: ready_to_pickup + photo evidence → completed ───────────

    public function confirmPickup(Request $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorizeOp($request);

        $user = $request->user();

        if ($goodsIssue->assigned_to !== $user->id && $goodsIssue->picked_by !== $user->id) {
            abort(403);
        }

        if ($goodsIssue->status !== 'ready_to_pickup') {
            return back()->with('error', 'GI belum dalam status ready_to_pickup.');
        }

        $request->validate([
            'photos'   => ['required', 'array', 'min:1'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:8192'],
        ]);

        $goodsIssue->load(['items.variant.item']);

        DB::transaction(function () use ($request, $goodsIssue, $user) {
            // Upload evidence photos (stage = 'pickup')
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

            foreach ($goodsIssue->items()->where('status', 'ready')->get() as $gItem) {
                $qty  = (float) ($gItem->actual_qty ?? $gItem->qty_in_base_uom);
                $whId = $gItem->item_warehouse_id ?? $goodsIssue->warehouse_id;

                // Deduct stock + clear reservation
                $ledgers = StockLedger::where('item_variant_id', $gItem->item_variant_id)
                    ->where('warehouse_id', $whId)
                    ->orderByDesc('qty_reserved')
                    ->get();

                foreach ($ledgers as $ledger) {
                    if ($qty <= 0) break;
                    $clearRes = min((float) $gItem->qty_in_base_uom, (float) $ledger->qty_reserved);
                    $deduct   = min($qty, (float) $ledger->qty_on_hand);
                    if ($clearRes > 0) $ledger->decrement('qty_reserved', $clearRes);
                    if ($deduct > 0)   $ledger->decrement('qty_on_hand', $deduct);
                    $qty -= $deduct;
                }

                // Cooldown logging
                $item = $gItem->variant?->item;
                if ($item?->has_cooldown && $item->cooldown_days > 0) {
                    $takenAt       = now()->toDateString();
                    $cooldownUntil = now()->addDays($item->cooldown_days)->toDateString();

                    CooldownLog::create([
                        'item_id'         => $item->id,
                        'item_variant_id' => $gItem->item_variant_id,
                        'track_type'      => $item->cooldown_track_by,
                        'lv_id'           => $gItem->lv_id,
                        'employee_id'     => $gItem->employee_id,
                        'goods_issue_id'  => $goodsIssue->id,
                        'taken_at'        => $takenAt,
                        'cooldown_until'  => $cooldownUntil,
                    ]);

                    $gItem->update(['cooldown_until' => $cooldownUntil]);
                }
            }

            $goodsIssue->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Auto-process linked employee requests
            EmployeeRequest::where('goods_issue_id', $goodsIssue->id)
                ->update(['status' => 'processed']);
        });

        // Notify requester
        NotificationService::send(
            $goodsIssue->requested_by,
            'GI_COMPLETED',
            "GI Selesai: {$goodsIssue->gi_number}",
            "Barang GI {$goodsIssue->gi_number} telah berhasil diambil. Terima kasih.",
            [
                'gi_id'     => $goodsIssue->id,
                'gi_number' => $goodsIssue->gi_number,
                'route'     => '/goods-issues/' . $goodsIssue->id,
            ]
        );

        return redirect()
            ->route('operator.scan-list')
            ->with('success', "GI {$goodsIssue->gi_number} selesai — barang diserahkan!");
    }

    // ── History: completed/rejected GIs handled by this operator ──────────────

    public function history(Request $request): Response
    {
        $this->authorizeOp($request);

        $user = $request->user();

        $gis = GoodsIssue::query()
            ->with(['department:id,name,code', 'warehouse:id,code,name'])
            ->withCount('items')
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('picked_by', $user->id))
            ->whereIn('status', ['completed', 'rejected'])
            ->latest('updated_at')
            ->paginate(20);

        return Inertia::render('Operator/History', ['gis' => $gis]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function authorizeOp(Request $request): void
    {
        $user = $request->user();
        $ok   = $user && $user->is_active && (
            in_array($user->role, ['operator', 'wh_admin']) ||
            !empty(array_intersect(['operator', 'wh_admin'], $user->extra_roles ?? []))
        );
        if (!$ok) abort(403);
    }
}
