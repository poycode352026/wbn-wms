<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\GoodsReceiptPhoto;
use App\Models\ItemVariant;
use App\Models\Location;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role;

        $query = GoodsReceipt::query()
            ->with([
                'warehouse:id,code,name',
                'createdBy:id,name',
                'inspectedBy:id,name',
                'approvedBy:id,name',
            ])
            ->withCount('items');

        // Role-based visibility
        if ($role === 'procurement_admin') {
            // Procurement admin only sees GRs they created
            $query->where('created_by', $user->id);
        } elseif ($role === 'wh_admin') {
            // WH admin sees: their own drafts + all arrived/assigned/inspecting/completed
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereIn('status', ['arrived', 'assigned', 'pending_supervisor', 'completed']);
            });
        } elseif ($role === 'wh_supervisor') {
            $query->whereIn('status', ['pending_supervisor', 'completed']);
        } elseif ($role === 'operator') {
            // Operator only sees GRs assigned to them
            $query->where('assigned_to', $user->id)
                  ->whereIn('status', ['assigned', 'pending_supervisor', 'completed']);
        }
        // super_admin: semua

        // Filters
        $query
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q2) =>
                    $q2->where('gr_number', 'like', "%{$s}%")
                       ->orWhere('pr_number', 'like', "%{$s}%")
                       ->orWhere('po_number', 'like', "%{$s}%")
                );
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id));

        $grs = $query->orderByDesc('created_at')->paginate(20)->withQueryString()
            ->through(fn ($gr) => $this->formatGr($gr));

        // Stats
        $baseQuery = GoodsReceipt::query();
        if ($role === 'procurement_admin') $baseQuery->where('created_by', $user->id);

        $stats = [
            'total'              => (clone $baseQuery)->count(),
            'draft'              => (clone $baseQuery)->where('status', 'draft')->count(),
            'arrived'            => (clone $baseQuery)->where('status', 'arrived')->count(),
            'assigned'           => (clone $baseQuery)->where('status', 'assigned')->count(),
            'pending_supervisor' => (clone $baseQuery)->where('status', 'pending_supervisor')->count(),
            'completed'          => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('GoodsReceipt/Index', [
            'grs'        => $grs,
            'stats'      => $stats,
            'warehouses' => $warehouses,
            'filters'    => $request->only(['search', 'status', 'warehouse_id']),
            'userRole'   => $role,
            'userId'     => $user->id,
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function create(Request $request): Response
    {
        $this->authorizeRole($request, ['procurement_admin', 'wh_admin', 'super_admin']);

        // Pre-fill items from reorder (e.g. ?reorder=5:12,8:15 → variantId:suggestedQty)
        $preselectedItems = [];
        $reorderParam = trim($request->get('reorder', ''));
        if ($reorderParam) {
            foreach (explode(',', $reorderParam) as $pair) {
                [$variantId, $qty] = array_pad(explode(':', trim($pair), 2), 2, 1);
                $variant = ItemVariant::with('item:id,name_en,name_id,name_zh,base_uom,alt_uom,alt_uom_conversion')
                    ->find((int) $variantId);
                if ($variant) {
                    $preselectedItems[] = [
                        'variant_id'          => $variant->id,
                        'sku'                 => $variant->sku,
                        'name_en'             => $variant->item->name_en,
                        'name_id'             => $variant->item->name_id,
                        'name_zh'             => $variant->item->name_zh,
                        'base_uom'            => $variant->item->base_uom,
                        'alt_uom'             => $variant->item->alt_uom,
                        'alt_uom_conversion'  => $variant->item->alt_uom_conversion,
                        'suggested_qty'       => max(1, (float) $qty),
                    ];
                }
            }
        }

        return Inertia::render('GoodsReceipt/Create', [
            'allVariants'      => $this->getAllVariants(),
            'preselectedItems' => $preselectedItems,
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole($request, ['procurement_admin', 'wh_admin', 'super_admin']);

        $data = $request->validate([
            'gr_type'                 => ['nullable', 'in:procurement,external'],
            'pr_number'               => ['nullable', 'string', 'max:100'],
            'po_number'               => ['nullable', 'string', 'max:100'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.variant_id'      => ['required', 'exists:item_variants,id'],
            'items.*.expected_qty'    => ['required', 'numeric', 'min:0.01'],
            'items.*.uom'             => ['required', 'string', 'max:50'],
        ]);

        $gr = DB::transaction(function () use ($data, $request) {
            $gr = GoodsReceipt::create([
                'gr_number'  => GoodsReceipt::generateGrNumber(),
                'pr_number'  => $data['pr_number'] ?? null,
                'po_number'  => $data['po_number'] ?? null,
                'created_by' => $request->user()->id,
                'notes'      => $data['notes'] ?? null,
                'status'     => 'draft',
            ]);

            foreach ($data['items'] as $item) {
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'item_variant_id'  => $item['variant_id'],
                    'expected_qty'     => $item['expected_qty'],
                    'uom'              => $item['uom'],
                    'condition'        => 'good',
                ]);
            }

            return $gr;
        });

        return redirect()->route('gr.show', $gr)->with('success', "GR {$gr->gr_number} berhasil dibuat.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(Request $request, GoodsReceipt $gr): Response
    {
        $user = $request->user();
        $role = $user->role;

        // Authorization: allow wh roles + procurement_admin + operator (only if assigned)
        $allowedRoles = ['procurement_admin', 'wh_admin', 'wh_supervisor', 'super_admin'];
        if (!in_array($role, $allowedRoles)) {
            if ($role === 'operator' && $gr->assigned_to === $user->id) {
                // Operator can view their assigned GR — allowed
            } else {
                abort(403, 'Access denied.');
            }
        }

        $gr->load([
            'warehouse:id,code,name',
            'createdBy:id,name',
            'inspectedBy:id,name',
            'approvedBy:id,name',
            'assignedTo:id,name',
            'items.variant.item.category',
            'items.location.warehouse',
            'photos.uploader:id,name',
        ]);

        // Build warehouses+locations for rack-placement form (wh_admin, arrived status only)
        $warehouses = [];
        if (in_array($role, ['wh_admin', 'super_admin']) && $gr->status === 'arrived') {
            $warehouses = Warehouse::where('is_active', true)->orderBy('name')
                ->with(['locations' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
                ->get()
                ->map(fn ($wh) => [
                    'id'        => $wh->id,
                    'code'      => $wh->code,
                    'name'      => $wh->name,
                    'locations' => $wh->locations->map(fn ($l) => [
                        'id'           => $l->id,
                        'code'         => $l->code,
                        'name'         => $l->name,
                        'warehouse_id' => $l->warehouse_id,
                    ])->values()->all(),
                ]);
        }

        // Load operator list for assignment (wh_admin, arrived status only)
        $operators = [];
        if (in_array($role, ['wh_admin', 'super_admin']) && $gr->status === 'arrived') {
            $operators = User::where('role', 'operator')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray();
        }

        // Auto-approve countdown (hours remaining)
        $hoursUntilAutoApprove = null;
        if ($gr->status === 'pending_supervisor' && $gr->inspected_at) {
            $deadline = $gr->inspected_at->addHours(24);
            $hoursUntilAutoApprove = max(0, round(now()->diffInMinutes($deadline, false) / 60, 1));
        }

        return Inertia::render('GoodsReceipt/Show', [
            'gr'         => $this->formatGrDetail($gr),
            'warehouses' => $warehouses,
            'operators'  => $operators,
            'userRole'   => $role,
            'userId'     => $user->id,
            'hoursUntilAutoApprove' => $hoursUntilAutoApprove,
        ]);
    }

    // ── Assign (arrived → assigned) — WH Admin sets rack + assigns operator ─────

    public function assign(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $this->authorizeRole($request, ['wh_admin', 'super_admin']);

        if ($gr->status !== 'arrived') {
            return back()->with('error', 'GR harus dalam status arrived untuk dapat di-assign.');
        }

        $data = $request->validate([
            'operator_id'         => ['required', 'exists:users,id'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.id'          => ['required', 'exists:goods_receipt_items,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'photos'              => ['nullable', 'array', 'max:10'],
            'photos.*'            => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::transaction(function () use ($gr, $data, $request) {
            $firstLocationId = null;

            foreach ($data['items'] as $itemData) {
                $item = GoodsReceiptItem::where('id', $itemData['id'])
                    ->where('goods_receipt_id', $gr->id)
                    ->firstOrFail();

                $item->update(['location_id' => $itemData['location_id']]);
                $firstLocationId ??= $itemData['location_id'];
            }

            // Auto-set warehouse from first item's location
            $warehouseId = $gr->warehouse_id;
            if (!$warehouseId && $firstLocationId) {
                $loc = Location::find($firstLocationId);
                $warehouseId = $loc?->warehouse_id;
            }

            $gr->update([
                'status'       => 'assigned',
                'assigned_to'  => $data['operator_id'],
                'assigned_at'  => now(),
                'warehouse_id' => $warehouseId,
            ]);
        });

        // Optional photos for rack placement
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('gr-photos', 'public');
                GoodsReceiptPhoto::create([
                    'goods_receipt_id' => $gr->id,
                    'path'             => $path,
                    'original_name'    => $photo->getClientOriginalName(),
                    'stage'            => 'inspection',
                    'uploaded_by'      => $request->user()->id,
                ]);
            }
        }

        // Notify assigned operator
        NotificationService::send(
            $data['operator_id'],
            'gr_assigned',
            "GR Assigned — {$gr->gr_number}",
            "You have been assigned to inspect GR {$gr->gr_number}. Please verify the actual quantities and conditions.",
            ['gr_id' => $gr->id, 'gr_number' => $gr->gr_number, 'route' => 'gr.show']
        );

        return back()->with('success', "GR {$gr->gr_number} berhasil di-assign ke operator.");
    }

    // ── Update (draft only — edit PR/PO/notes) ─────────────────────────────────

    public function update(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $this->authorizeRole($request, ['procurement_admin', 'wh_admin', 'super_admin']);

        if ($gr->status !== 'draft') {
            return back()->with('error', 'GR can only be edited while in draft status.');
        }
        if ($gr->created_by !== $request->user()->id && $request->user()->role !== 'super_admin') {
            abort(403);
        }

        $data = $request->validate([
            'pr_number' => ['nullable', 'string', 'max:100'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);

        $gr->update($data);

        return back()->with('success', "GR {$gr->gr_number} details have been updated.");
    }

    // ── Destroy (draft only) ───────────────────────────────────────────────────

    public function destroy(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $this->authorizeRole($request, ['procurement_admin', 'wh_admin', 'super_admin']);

        if ($gr->status !== 'draft') {
            return back()->with('error', 'Only draft GRs can be deleted.');
        }
        if ($gr->created_by !== $request->user()->id && $request->user()->role !== 'super_admin') {
            abort(403);
        }

        // Delete physical photo files from storage
        foreach ($gr->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $grNumber = $gr->gr_number;
        $gr->delete(); // cascades to gr_items and gr_photos records

        return redirect()->route('gr.index')
            ->with('success', "GR {$grNumber} has been deleted.");
    }

    // ── Submit (draft → arrived) ───────────────────────────────────────────────

    public function submit(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $this->authorizeRole($request, ['procurement_admin', 'wh_admin', 'super_admin']);

        if ($gr->status !== 'draft') {
            return back()->with('error', 'GR sudah tidak dalam status draft.');
        }

        if ($gr->created_by !== $request->user()->id && $request->user()->role !== 'super_admin') {
            return back()->with('error', 'Anda tidak berhak melakukan aksi ini.');
        }

        $request->validate([
            'photos'   => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $gr->update([
            'status'       => 'arrived',
            'submitted_at' => now(),
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('gr-photos', 'public');
                GoodsReceiptPhoto::create([
                    'goods_receipt_id' => $gr->id,
                    'path'             => $path,
                    'original_name'    => $photo->getClientOriginalName(),
                    'stage'            => 'arrived',
                    'uploaded_by'      => $request->user()->id,
                ]);
            }
        }

        // Notify wh_admin (and super_admin) to proceed with physical inspection
        // Exclude the submitter so they don't receive their own notification
        $submitterId = $request->user()->id;
        NotificationService::sendToRoleExcept(
            ['wh_admin', 'super_admin'],
            $submitterId,
            'gr_arrived',
            "Goods Arrived — {$gr->gr_number}",
            "GR {$gr->gr_number} has arrived at the warehouse. Please proceed with physical inspection.",
            ['gr_id' => $gr->id, 'gr_number' => $gr->gr_number, 'route' => 'gr.show']
        );

        return back()->with('success', "GR {$gr->gr_number} ditandai sebagai sudah tiba.");
    }

    // ── Inspect (assigned → pending_supervisor) — Operator verifies qty & condition

    public function inspect(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role;

        // Authorization: wh_admin/super_admin always allowed; operator only if assigned to them
        if ($role === 'operator') {
            if ($gr->assigned_to !== $user->id) {
                return back()->with('error', 'Akses tidak diizinkan.');
            }
        } elseif (!in_array($role, ['wh_admin', 'super_admin'])) {
            return back()->with('error', 'Akses tidak diizinkan.');
        }

        if ($gr->status !== 'assigned') {
            return back()->with('error', 'GR harus dalam status assigned untuk dapat diinspeksi.');
        }

        $data = $request->validate([
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.id'              => ['required', 'exists:goods_receipt_items,id'],
            'items.*.actual_qty'      => ['required', 'numeric', 'min:0'],
            'items.*.condition'       => ['required', 'in:good,damaged,broken,other'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:500'],
            'photos'                  => ['nullable', 'array', 'max:10'],
            'photos.*'                => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Extra: condition_notes wajib jika actual_qty < expected_qty
        $gr->load('items:id,goods_receipt_id,expected_qty');
        $itemExpMap = $gr->items->pluck('expected_qty', 'id');

        foreach ($data['items'] as $itemData) {
            $expected = (float) ($itemExpMap[$itemData['id']] ?? 0);
            $actual   = (float) $itemData['actual_qty'];
            if ($actual < $expected && empty(trim($itemData['condition_notes'] ?? ''))) {
                return back()
                    ->with('error', 'Catatan kondisi wajib diisi untuk item yang qty aktualnya kurang dari qty yang diharapkan.')
                    ->withInput();
            }
        }

        DB::transaction(function () use ($gr, $data, $user) {
            foreach ($data['items'] as $itemData) {
                $item = GoodsReceiptItem::where('id', $itemData['id'])
                    ->where('goods_receipt_id', $gr->id)
                    ->firstOrFail();

                $item->update([
                    'actual_qty'      => $itemData['actual_qty'],
                    'condition'       => $itemData['condition'],
                    'condition_notes' => $itemData['condition_notes'] ?? null,
                ]);
            }

            $gr->update([
                'status'       => 'pending_supervisor',
                'inspected_by' => $user->id,
                'inspected_at' => now(),
            ]);
        });

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('gr-photos', 'public');
                GoodsReceiptPhoto::create([
                    'goods_receipt_id' => $gr->id,
                    'path'             => $path,
                    'original_name'    => $photo->getClientOriginalName(),
                    'stage'            => 'inspection',
                    'uploaded_by'      => $user->id,
                ]);
            }
        }

        // Notify wh_supervisor + super_admin to review and approve
        NotificationService::sendToRole(
            ['wh_supervisor', 'super_admin'],
            'gr_inspected',
            "Inspection Done — {$gr->gr_number}",
            "GR {$gr->gr_number} physical inspection completed by {$user->name}. Awaiting your approval.",
            ['gr_id' => $gr->id, 'gr_number' => $gr->gr_number, 'route' => 'gr.show']
        );

        // Also notify the GR creator (procurement_admin) that inspection is done
        if ($gr->created_by && $gr->created_by !== $user->id) {
            NotificationService::send(
                $gr->created_by,
                'gr_inspected',
                "Inspection Done — {$gr->gr_number}",
                "GR {$gr->gr_number} has been inspected and is awaiting supervisor approval.",
                ['gr_id' => $gr->id, 'gr_number' => $gr->gr_number, 'route' => 'gr.show']
            );
        }

        return redirect()->route('gr.show', $gr)
            ->with('success', "Inspeksi GR {$gr->gr_number} berhasil disubmit. Menunggu approval supervisor.");
    }

    // ── Approve (pending_supervisor → completed) ───────────────────────────────

    public function approve(Request $request, GoodsReceipt $gr): RedirectResponse
    {
        $this->authorizeRole($request, ['wh_supervisor', 'super_admin']);

        if ($gr->status !== 'pending_supervisor') {
            return back()->with('error', 'GR tidak dalam status pending approval.');
        }

        $gr->load('items.location');
        $this->doApprove($gr, $request->user(), false);

        return redirect()->route('gr.index')
            ->with('success', "GR {$gr->gr_number} berhasil di-approve. Stok telah diperbarui.");
    }

    // ── Internal: do approve + stock update ────────────────────────────────────

    public static function doApprove(GoodsReceipt $gr, ?\App\Models\User $approver, bool $autoApproved): void
    {
        DB::transaction(function () use ($gr, $approver, $autoApproved) {
            foreach ($gr->items as $item) {
                if (!$item->location_id || !$item->actual_qty) continue;

                $ledger = StockLedger::firstOrNew([
                    'item_variant_id' => $item->item_variant_id,
                    'location_id'     => $item->location_id,
                ]);

                $ledger->warehouse_id    = $item->location->warehouse_id;
                $ledger->qty_on_hand     = ($ledger->qty_on_hand ?? 0) + $item->actual_qty;
                $ledger->last_updated_at = now();
                $ledger->save();
            }

            $gr->update([
                'status'        => 'completed',
                'approved_by'   => $approver?->id,
                'auto_approved' => $autoApproved,
                'completed_at'  => now(),
            ]);
        });

        // Notify wh_manager + super_admin that stock has been updated
        $approvedBy = $autoApproved ? 'system (auto-approved)' : ($approver?->name ?? 'wh_supervisor');
        $notifData  = ['gr_id' => $gr->id, 'gr_number' => $gr->gr_number, 'route' => 'gr.show'];

        NotificationService::sendToRole(
            ['wh_manager', 'super_admin'],
            'gr_completed',
            "GR Completed — {$gr->gr_number}",
            "GR {$gr->gr_number} approved by {$approvedBy}. Stock updated.",
            $notifData
        );

        // Notify GR creator (procurement_admin / wh_admin) that their receipt is complete
        if ($gr->created_by) {
            NotificationService::send(
                $gr->created_by,
                'gr_completed',
                "GR Completed — {$gr->gr_number}",
                "GR {$gr->gr_number} has been approved by {$approvedBy}. Stock has been updated.",
                $notifData
            );
        }

        // Notify the operator who did the inspection
        if ($gr->inspected_by && $gr->inspected_by !== $gr->created_by) {
            NotificationService::send(
                $gr->inspected_by,
                'gr_completed',
                "GR Completed — {$gr->gr_number}",
                "GR {$gr->gr_number} you inspected has been approved. Stock updated.",
                $notifData
            );
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeRole(Request $request, array $roles): void
    {
        if (!in_array($request->user()->role, $roles)) {
            abort(403, 'Akses tidak diizinkan.');
        }
    }

    private function getAllVariants(): array
    {
        return ItemVariant::where('is_active', true)
            ->with('item:id,name_en,name_id,name_zh,base_uom,alt_uom,alt_uom_conversion')
            ->orderBy('sku')
            ->get(['id', 'sku', 'brand', 'model', 'size', 'color', 'item_id'])
            ->map(fn ($v) => [
                'id'                 => $v->id,
                'sku'                => $v->sku,
                'brand'              => $v->brand,
                'model'              => $v->model,
                'size'               => $v->size,
                'color'              => $v->color,
                'name_en'            => $v->item?->name_en,
                'name_id'            => $v->item?->name_id,
                'name_zh'            => $v->item?->name_zh,
                'base_uom'           => $v->item?->base_uom,
                'alt_uom'            => $v->item?->alt_uom,
                'alt_uom_conversion' => $v->item?->alt_uom_conversion
                    ? (float) $v->item->alt_uom_conversion : null,
            ])
            ->values()->all();
    }

    private function formatGr(GoodsReceipt $gr): array
    {
        return [
            'id'          => $gr->id,
            'gr_number'   => $gr->gr_number,
            'pr_number'   => $gr->pr_number,
            'po_number'   => $gr->po_number,
            'status'      => $gr->status,
            'auto_approved' => $gr->auto_approved,
            'items_count' => $gr->items_count ?? 0,
            'warehouse'   => $gr->warehouse ? [
                'id'   => $gr->warehouse->id,
                'code' => $gr->warehouse->code,
                'name' => $gr->warehouse->name,
            ] : null,
            'created_by'   => $gr->createdBy   ? ['id' => $gr->createdBy->id,   'name' => $gr->createdBy->name]   : null,
            'inspected_by' => $gr->inspectedBy ? ['id' => $gr->inspectedBy->id, 'name' => $gr->inspectedBy->name] : null,
            'approved_by'  => $gr->approvedBy  ? ['id' => $gr->approvedBy->id,  'name' => $gr->approvedBy->name]  : null,
            'assigned_to'  => $gr->assignedTo  ? ['id' => $gr->assignedTo->id,  'name' => $gr->assignedTo->name]  : null,
            'submitted_at'  => $gr->submitted_at?->toISOString(),
            'inspected_at'  => $gr->inspected_at?->toISOString(),
            'assigned_at'   => $gr->assigned_at?->toISOString(),
            'completed_at'  => $gr->completed_at?->toISOString(),
            'created_at'    => $gr->created_at?->toISOString(),
        ];
    }

    private function formatGrDetail(GoodsReceipt $gr): array
    {
        $base = $this->formatGr($gr);
        $base['notes'] = $gr->notes;
        $base['items'] = $gr->items->map(fn ($item) => [
            'id'              => $item->id,
            'expected_qty'    => $item->expected_qty,
            'actual_qty'      => $item->actual_qty,
            'uom'             => $item->uom,
            'condition'       => $item->condition,
            'condition_notes' => $item->condition_notes,
            'location_id'     => $item->location_id,
            'location'        => $item->location ? [
                'id'           => $item->location->id,
                'code'         => $item->location->code,
                'name'         => $item->location->name,
                'warehouse_id' => $item->location->warehouse_id,
                'warehouse'    => $item->location->warehouse ? [
                    'id'   => $item->location->warehouse->id,
                    'code' => $item->location->warehouse->code,
                    'name' => $item->location->warehouse->name,
                ] : null,
            ] : null,
            'variant' => $item->variant ? [
                'id'       => $item->variant->id,
                'sku'      => $item->variant->sku,
                'brand'    => $item->variant->brand,
                'model'    => $item->variant->model,
                'size'     => $item->variant->size,
                'color'    => $item->variant->color,
                'photo_path' => $item->variant->photo_path
                    ? asset('storage/' . $item->variant->photo_path) : null,
                'item' => $item->variant->item ? [
                    'id'          => $item->variant->item->id,
                    'name_en'     => $item->variant->item->name_en,
                    'name_id'     => $item->variant->item->name_id,
                    'name_zh'     => $item->variant->item->name_zh,
                    'base_uom'    => $item->variant->item->base_uom,
                    'category'    => $item->variant->item->category ? [
                        'id'      => $item->variant->item->category->id,
                        'code'    => $item->variant->item->category->code,
                        'name_en' => $item->variant->item->category->name_en,
                    ] : null,
                ] : null,
            ] : null,
        ])->values()->all();

        $base['photos'] = $gr->photos->map(fn ($p) => [
            'id'            => $p->id,
            'url'           => asset('storage/' . $p->path),
            'original_name' => $p->original_name,
            'stage'         => $p->stage,
            'uploaded_by'   => $p->uploader?->name ?? '—',
            'created_at'    => $p->created_at,
        ])->values()->all();

        return $base;
    }
}
