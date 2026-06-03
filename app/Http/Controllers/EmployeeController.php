<?php

namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\Item;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $today      = now()->toDateString();
        $role       = auth()->user()->role;
        $userDeptId = auth()->user()->department_id;

        $query = Employee::with([
                'department:id,name,code',
                'user:id,email,is_active',
                'drivenVehicles:id,lv_code,lv_number,driver_id',
            ])
            ->withCount('cooldowns')
            ->with(['cooldowns' => fn ($q) =>
                $q->with('variant:id,item_id', 'variant.item:id,name_en,name_id')
                  ->latest('cooldown_until')
                  ->limit(1)
            ]);

        // Scope employee list to own department for admin_dept / manager_dept
        if (in_array($role, ['admin_dept', 'manager_dept'])) {
            $query->where('department_id', $userDeptId);
        }

        if ($request->search) {
            $s = $request->search;
            $query->where(fn ($q) =>
                $q->where('employee_id', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%")
                  ->orWhere('position', 'like', "%{$s}%")
            );
        }

        if ($request->department) {
            $query->where('department_id', $request->department);
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $employees   = $query->orderBy('name')->paginate(25)->withQueryString();
        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);

        // Add nearestCooldown = soonest active cooldown_until per employee
        $employees->through(function ($e) use ($today) {
            $e->nearestCooldown = CooldownLog::where('employee_id', $e->id)
                ->where('cooldown_until', '>=', $today)
                ->orderBy('cooldown_until')
                ->value('cooldown_until');
            return $e;
        });

        // Overdue mandatory PPE per employee
        $overdueEmpIds = $this->getOverdueEmployeeIds();

        // Overdue mandatory LV equipment per driver employee
        $overdueLvEmpIds = $this->getOverdueLvEmployeeIds();

        // Vehicles available for LV assignment — all active vehicles visible to all roles
        $vehiclesForDept = Vehicle::where('is_active', true)
            ->orderBy('lv_code')->orderBy('lv_number')
            ->get(['id', 'lv_code', 'lv_number', 'driver_id'])
            ->map(fn ($v) => [
                'id'        => $v->id,
                'lv_number' => $v->lv_code . '-' . $v->lv_number,
                'driver_id' => $v->driver_id,
            ])
            ->toArray();

        // Pending employee requests — exclude those already linked to a GI
        $requestsQuery = EmployeeRequest::where('status', 'pending')
            ->whereNull('goods_issue_id')
            ->with(['employee:id,employee_id,name', 'items.item:id,name_en,name_id'])
            ->orderByDesc('created_at');
        if (in_array($role, ['admin_dept', 'manager_dept'])) {
            $requestsQuery->where('department_id', $userDeptId);
        }
        $pendingRequests = $requestsQuery->get();

        return Inertia::render('Employees/Index', [
            'employees'        => $employees,
            'departments'      => $departments,
            'filters'          => $request->only(['search', 'department', 'status']),
            'today'            => $today,
            'overdueEmpIds'    => $overdueEmpIds,
            'overdueLvEmpIds'  => $overdueLvEmpIds,
            'vehiclesForDept'  => $vehiclesForDept,
            'pendingRequests'  => $pendingRequests,
            'userRole'         => $role,
            'userDeptId'       => $userDeptId,
        ]);
    }

    private function getOverdueEmployeeIds(): \Illuminate\Support\Collection
    {
        $today = now()->toDateString();
        $mandatoryItems = Item::where('is_mandatory', true)
            ->where('has_cooldown', true)
            ->where('cooldown_track_by', 'employee_id')
            ->with('variants:id,item_id')
            ->get();

        $overdueIds = collect();
        foreach ($mandatoryItems as $item) {
            $variantIds = $item->variants->pluck('id');
            $okIds = CooldownLog::whereIn('item_variant_id', $variantIds)
                ->where('cooldown_until', '>=', $today)
                ->whereNotNull('employee_id')
                ->pluck('employee_id');
            $overdue = Employee::where('is_active', true)
                ->whereNotIn('id', $okIds)
                ->pluck('id');
            $overdueIds = $overdueIds->merge($overdue);
        }
        return $overdueIds->unique()->values();
    }

    private function getOverdueLvEmployeeIds(): \Illuminate\Support\Collection
    {
        $today = now()->toDateString();
        $mandatoryLvItems = Item::where('is_mandatory', true)
            ->where('has_cooldown', true)
            ->where('cooldown_track_by', 'lv_number')
            ->with('variants:id,item_id')
            ->get();

        $overdueIds = collect();
        foreach ($mandatoryLvItems as $item) {
            $variantIds = $item->variants->pluck('id');
            $vehicles = Vehicle::where('is_active', true)->whereNotNull('driver_id')->get(['id', 'driver_id']);
            foreach ($vehicles as $v) {
                $ok = CooldownLog::whereIn('item_variant_id', $variantIds)
                    ->where('lv_id', $v->id)
                    ->where('cooldown_until', '>=', $today)
                    ->exists();
                if (!$ok) {
                    $overdueIds->push($v->driver_id);
                }
            }
        }
        return $overdueIds->unique()->values();
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'employee_id'   => ['required', 'string', 'max:50', 'unique:employees,employee_id'],
            'name'          => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:100'],
            'is_active'     => ['boolean'],
            'lv_id'         => ['nullable', 'integer', 'exists:vehicles,id'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // admin_dept can only create employees in their own department
        if ($user->role === 'admin_dept') {
            $data['department_id'] = $user->department_id;
        }

        $employee = Employee::create([
            'employee_id'   => $data['employee_id'],
            'name'          => $data['name'],
            'department_id' => $data['department_id'],
            'position'      => $data['position'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
        ]);

        // Assign to LV if provided (Driver position)
        if (!empty($data['lv_id'])) {
            Vehicle::where('id', $data['lv_id'])->update([
                'driver_id' => $employee->id,
            ]);
        }

        // Auto-create login account if password provided
        if (!empty($data['password'])) {
            $newUser = User::create([
                'name'          => $employee->name,
                'employee_id'   => $employee->employee_id,
                'email'         => !empty($data['email']) ? $data['email'] : ($employee->employee_id . '@internal.wbn'),
                'password'      => Hash::make($data['password']),
                'role'          => 'employee',
                'department_id' => $employee->department_id,
                'is_active'     => true,
            ]);
            $employee->update(['user_id' => $newUser->id]);
        }

        return back()->with('success', "Karyawan {$employee->name} berhasil ditambahkan.");
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $user = auth()->user();

        // admin_dept cannot edit employees from other departments
        if ($user->role === 'admin_dept' && $employee->department_id !== $user->department_id) {
            abort(403, 'Anda hanya bisa mengedit karyawan di departemen Anda.');
        }

        // Determine the unique ignore id for the linked user (for email uniqueness)
        $linkedUserId = $employee->user_id ?? 0;

        $data = $request->validate([
            'employee_id'   => ['required', 'string', 'max:50', "unique:employees,employee_id,{$employee->id}"],
            'name'          => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:100'],
            'is_active'     => ['boolean'],
            'email'         => ['nullable', 'email', 'max:255', "unique:users,email,{$linkedUserId}"],
            'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // admin_dept cannot move employees to a different department
        if ($user->role === 'admin_dept') {
            $data['department_id'] = $user->department_id;
        }

        $employee->update([
            'employee_id'   => $data['employee_id'],
            'name'          => $data['name'],
            'department_id' => $data['department_id'],
            'position'      => $data['position'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
        ]);

        // Handle password update/create
        if (!empty($data['password'])) {
            $employee->refresh()->load('user');
            if ($employee->user) {
                // Update existing user password
                $employee->user->update(['password' => Hash::make($data['password'])]);
            } else {
                // Create a new user account
                $newUser = User::create([
                    'name'          => $employee->name,
                    'employee_id'   => $employee->employee_id,
                    'email'         => !empty($data['email']) ? $data['email'] : ($employee->employee_id . '@internal.wbn'),
                    'password'      => Hash::make($data['password']),
                    'role'          => 'employee',
                    'department_id' => $employee->department_id,
                    'is_active'     => true,
                ]);
                $employee->update(['user_id' => $newUser->id]);
            }
        }

        return back()->with('success', "Karyawan {$employee->name} berhasil diperbarui.");
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $user = auth()->user();

        // admin_dept can only deactivate employees in their own department
        if ($user->role === 'admin_dept' && $employee->department_id !== $user->department_id) {
            abort(403, 'Anda hanya bisa menonaktifkan karyawan di departemen Anda.');
        }

        $employee->update(['is_active' => false]);

        return back()->with('success', "Karyawan {$employee->name} dinonaktifkan.");
    }

    public function assignLv(Request $request, Employee $employee): RedirectResponse
    {
        $user = auth()->user();

        // admin_dept can only assign LVs for employees in their own department
        if ($user->role === 'admin_dept' && $employee->department_id !== $user->department_id) {
            abort(403);
        }

        $data = $request->validate([
            'action'    => ['required', 'in:assign,unassign,create_and_assign'],
            'lv_id'     => ['nullable', 'integer', 'exists:vehicles,id'],
            'lv_number' => ['nullable', 'string', 'max:50'],
            'type'      => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['action'] === 'unassign') {
            $lv = Vehicle::findOrFail($data['lv_id']);
            if ($lv->driver_id === $employee->id) {
                $lv->update(['driver_id' => null]);
            }
            return back()->with('success', "LV {$lv->lv_code}-{$lv->lv_number} berhasil dilepas dari {$employee->name}.");
        }

        if ($data['action'] === 'assign') {
            $request->validate(['lv_id' => ['required']]);
            $lv = Vehicle::findOrFail($data['lv_id']);
            $lv->update(['driver_id' => $employee->id]);
            return back()->with('success', "LV {$lv->lv_code}-{$lv->lv_number} berhasil ditetapkan ke {$employee->name}.");
        }

        if ($data['action'] === 'create_and_assign') {
            // create_and_assign is no longer used — LV creation is done in LV Management
            return back()->withErrors(['lv' => 'Gunakan halaman LV Management untuk membuat LV baru.']);
        }

        return back()->withErrors(['lv' => 'Aksi tidak valid.']);
    }

    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee_import_template.csv"',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employee_id', 'name', 'department_code', 'position']);
            fputcsv($out, ['EMP-001', 'Budi Santoso', 'MINE', 'Foreman']);
            fputcsv($out, ['EMP-002', 'Siti Rahayu', 'HRD', 'Staff']);
            fclose($out);
        }, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $path   = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return back()->withErrors(['file' => 'Tidak bisa membaca file.']);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return back()->withErrors(['file' => 'File kosong atau format tidak valid.']);
        }

        $headers   = array_map('trim', $headers);
        $headerMap = array_flip($headers);

        $deptMap  = Department::pluck('id', 'code')->all();
        $imported = 0;
        $errors   = [];
        $row      = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (empty(array_filter($data))) continue;
            if (str_starts_with(trim($data[0] ?? ''), '#')) continue;

            $empId    = trim($data[$headerMap['employee_id']     ?? 0] ?? '');
            $name     = trim($data[$headerMap['name']             ?? 1] ?? '');
            $deptCode = trim($data[$headerMap['department_code']  ?? 2] ?? '') ?: null;
            $position = trim($data[$headerMap['position']         ?? 3] ?? '') ?: null;

            if (!$empId) { $errors[] = "Baris {$row}: employee_id kosong."; continue; }
            if (!$name)  { $errors[] = "Baris {$row}: name kosong."; continue; }

            $deptId = $deptCode ? ($deptMap[$deptCode] ?? null) : null;

            Employee::updateOrCreate(
                ['employee_id' => $empId],
                [
                    'name'          => $name,
                    'department_id' => $deptId ?? array_values($deptMap)[0] ?? null,
                    'position'      => $position,
                    'is_active'     => true,
                ]
            );
            $imported++;
        }

        fclose($handle);

        if ($errors) {
            return back()
                ->with('success', "{$imported} karyawan berhasil diimport.")
                ->withErrors(['import' => implode(' | ', array_slice($errors, 0, 5))]);
        }

        return back()->with('success', "{$imported} karyawan berhasil diimport.");
    }

    public function createLogin(Request $request, Employee $employee): RedirectResponse
    {
        if ($employee->user_id) {
            return back()->withErrors(['login' => 'Karyawan ini sudah memiliki akun login.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Check employee_id is not already taken as a user login
        if (User::where('employee_id', $employee->employee_id)->exists()) {
            return back()->withErrors(['login' => 'Employee ID ini sudah terdaftar sebagai akun login.']);
        }

        $user = User::create([
            'name'          => $employee->name,
            'employee_id'   => $employee->employee_id,
            'email'         => $employee->employee_id . '@wbn-wms.local',
            'password'      => Hash::make($data['password']),
            'role'          => 'employee',
            'department_id' => $employee->department_id,
            'is_active'     => true,
        ]);

        $employee->update(['user_id' => $user->id]);

        return back()->with('success', "Akun login untuk {$employee->name} berhasil dibuat.");
    }

    public function processRequest(EmployeeRequest $employeeRequest): RedirectResponse
    {
        $employeeRequest->update(['status' => 'processed']);
        return back()->with('success', 'Pengajuan berhasil ditandai selesai.');
    }

    public function revokeLogin(Employee $employee): RedirectResponse
    {
        if (!$employee->user_id) {
            return back()->withErrors(['login' => 'Karyawan ini tidak memiliki akun login.']);
        }

        $user = $employee->user;
        $employee->update(['user_id' => null]);

        if ($user) {
            $user->delete();
        }

        return back()->with('success', "Akses login {$employee->name} berhasil dicabut.");
    }
}