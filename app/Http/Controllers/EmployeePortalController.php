<?php

namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\Item;
use App\Models\WmsNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePortalController extends Controller
{
    public function dashboard(): Response
    {
        $user     = auth()->user();
        $employee = $user->employee()
            ->with(['department:id,name,code'])
            ->first();

        if (!$employee) {
            abort(403, 'Employee record not found for this account.');
        }

        $today = now()->toDateString();

        // Load driven vehicles (driver_id must be in select so Eloquent can match records)
        $employee->load([
            'drivenVehicles:id,lv_code,lv_number,department_id,driver_id',
            'drivenVehicles.department:id,name,code',
        ]);

        // ── Mandatory employee PPE status ─────────────────────────────────
        $mandatoryEmpItems = Item::where('is_mandatory', true)
            ->where('has_cooldown', true)
            ->where('cooldown_track_by', 'employee_id')
            ->with('variants:id,item_id')
            ->get(['id', 'name_en', 'name_id', 'name_zh', 'cooldown_days', 'has_cooldown', 'cooldown_track_by']);

        $mandatoryStatus = [];
        foreach ($mandatoryEmpItems as $item) {
            $variantIds = $item->variants->pluck('id');
            $latestLog  = CooldownLog::whereIn('item_variant_id', $variantIds)
                ->where('employee_id', $employee->id)
                ->orderByDesc('cooldown_until')
                ->first(['taken_at', 'cooldown_until']);

            $pendingRequest = EmployeeRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->whereHas('items', fn ($q) => $q->where('item_id', $item->id))
                ->exists();

            $overdue = !$latestLog || $latestLog->cooldown_until < $today;

            $mandatoryStatus[] = [
                'item'            => $item->only(['id', 'name_en', 'name_id', 'name_zh', 'cooldown_days']),
                'taken_at'        => $latestLog?->taken_at,
                'cooldown_until'  => $latestLog?->cooldown_until,
                'overdue'         => $overdue,
                'pending_request' => $pendingRequest,
            ];
        }

        // ── Mandatory LV equipment status (if driver) ─────────────────────
        $lvMandatoryStatus = [];
        if ($employee->drivenVehicles->isNotEmpty()) {
            $mandatoryLvItems = Item::where('is_mandatory', true)
                ->where('has_cooldown', true)
                ->where('cooldown_track_by', 'lv_number')
                ->with('variants:id,item_id')
                ->get(['id', 'name_en', 'name_id', 'name_zh', 'cooldown_days', 'has_cooldown', 'cooldown_track_by']);

            foreach ($employee->drivenVehicles as $vehicle) {
                $vehicleItemStatus = [];
                foreach ($mandatoryLvItems as $item) {
                    $variantIds = $item->variants->pluck('id');
                    $latestLog  = CooldownLog::whereIn('item_variant_id', $variantIds)
                        ->where('lv_id', $vehicle->id)
                        ->orderByDesc('cooldown_until')
                        ->first(['taken_at', 'cooldown_until']);

                    $pendingRequest = EmployeeRequest::where('employee_id', $employee->id)
                        ->where('status', 'pending')
                        ->whereHas('items', fn ($q) => $q->where('item_id', $item->id))
                        ->exists();

                    $overdue = !$latestLog || $latestLog->cooldown_until < $today;

                    $vehicleItemStatus[] = [
                        'item'            => $item->only(['id', 'name_en', 'name_id', 'name_zh', 'cooldown_days']),
                        'taken_at'        => $latestLog?->taken_at,
                        'cooldown_until'  => $latestLog?->cooldown_until,
                        'overdue'         => $overdue,
                        'pending_request' => $pendingRequest,
                    ];
                }
                $lvMandatoryStatus[] = [
                    'vehicle' => [
                        'id'         => $vehicle->id,
                        'lv_number'  => $vehicle->lv_number,
                        'full_number'=> $vehicle->full_number,
                    ],
                    'items'   => $vehicleItemStatus,
                ];
            }
        }

        // ── Recent pickups (last 3 for home preview) ─────────────────────
        $vehicleIds   = $employee->drivenVehicles->pluck('id');
        $recentLogs   = CooldownLog::where(function ($q) use ($employee, $vehicleIds) {
                $q->where('employee_id', $employee->id)
                  ->orWhereIn('lv_id', $vehicleIds);
            })
            ->with(['variant:id,item_id,sku', 'variant.item:id,name_en,name_id,name_zh', 'lv:id,lv_code,lv_number'])
            ->orderByDesc('taken_at')
            ->limit(3)
            ->get();

        return Inertia::render('EmployeePortal/Dashboard', [
            'employee'          => $employee->makeHidden('drivenVehicles'),
            'drivenVehicles'    => $employee->drivenVehicles,
            'today'             => $today,
            'mandatoryStatus'   => $mandatoryStatus,
            'lvMandatoryStatus' => $lvMandatoryStatus,
            'recentLogs'        => $recentLogs,
        ]);
    }

    public function submitRequest(Request $request): RedirectResponse
    {
        $user     = auth()->user();
        $employee = $user->employee;

        if (!$employee) {
            abort(403, 'Employee record not found.');
        }

        // Support both new structured format {items: [{item_id, lv_id}]}
        // and legacy flat format {item_ids: [id, ...]}
        if ($request->has('items')) {
            $data = $request->validate([
                'items'           => ['required', 'array', 'min:1'],
                'items.*.item_id' => ['required', 'exists:items,id'],
                'items.*.lv_id'   => ['nullable', 'exists:vehicles,id'],
                'notes'           => ['nullable', 'string', 'max:500'],
            ]);
            $itemsData = $data['items'];
        } else {
            $data = $request->validate([
                'item_ids'   => ['required', 'array', 'min:1'],
                'item_ids.*' => ['required', 'exists:items,id'],
                'notes'      => ['nullable', 'string', 'max:500'],
            ]);
            $itemsData = array_map(fn($id) => ['item_id' => $id, 'lv_id' => null], $data['item_ids']);
        }

        $empRequest = EmployeeRequest::create([
            'employee_id'   => $employee->id,
            'department_id' => $employee->department_id,
            'notes'         => $data['notes'] ?? null,
            'status'        => 'pending',
        ]);

        foreach ($itemsData as $itemData) {
            $empRequest->items()->create([
                'item_id' => $itemData['item_id'],
                'lv_id'   => $itemData['lv_id'] ?? null,
                'qty'     => 1,
            ]);
        }

        // Notify admin_dept users in same department
        $admins = \App\Models\User::where('role', 'admin_dept')
            ->where('department_id', $employee->department_id)
            ->where('is_active', true)
            ->get();

        $itemCount = count($itemsData);
        foreach ($admins as $admin) {
            WmsNotification::create([
                'user_id' => $admin->id,
                'type'    => 'EMPLOYEE_PPE_REQUEST',
                'title'   => "Mandatory Item Request: {$employee->name}",
                'message' => "{$employee->name} has submitted a mandatory item request ({$itemCount} item(s)). Please review and create a Goods Issue.",
                'data'    => ['employee_id' => $employee->id, 'route' => '/employees'],
                'is_read' => false,
            ]);
        }

        return back()->with('success', 'Pengajuan berhasil dikirim ke Admin Dept.');
    }

    public function history(): Response
    {
        $user     = auth()->user();
        $employee = $user->employee()->with('department:id,name')->first();

        if (!$employee) {
            abort(403, 'Employee record not found for this account.');
        }

        $vehicleIds = \App\Models\Vehicle::where('driver_id', $employee->id)->pluck('id');

        $logs = CooldownLog::where(function ($q) use ($employee, $vehicleIds) {
                $q->where('employee_id', $employee->id)
                  ->orWhereIn('lv_id', $vehicleIds);
            })
            ->with([
                'variant:id,item_id,sku',
                'variant.item:id,name_en,name_id,name_zh',
                'lv:id,lv_code,lv_number',
            ])
            ->orderByDesc('taken_at')
            ->paginate(20);

        return Inertia::render('EmployeePortal/History', [
            'employee' => $employee->only(['id', 'employee_id', 'name', 'department']),
            'logs'     => $logs,
            'today'    => now()->toDateString(),
        ]);
    }

    public function profile(): Response
    {
        $user     = auth()->user();
        $employee = $user->employee()->with('department:id,name')->first();

        if (!$employee) {
            abort(403, 'Employee record not found for this account.');
        }

        return Inertia::render('EmployeePortal/Profile', [
            'employee' => $employee,
            'user'     => $user->only(['id', 'employee_id', 'name', 'is_active']),
        ]);
    }
}
