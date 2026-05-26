<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseStoreRequest;
use App\Http\Requests\WarehouseUpdateRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['code', 'name'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $warehouses = Warehouse::query()
            ->withCount('locations')
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%")
                       ->orWhere('location', 'like', "%{$s}%")
                ))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('name'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($w) => [
                'id'              => $w->id,
                'code'            => $w->code,
                'name'            => $w->name,
                'location'        => $w->location,
                'locations_count' => $w->locations_count,
                'is_active'       => $w->is_active,
            ]);

        return Inertia::render('Master/Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters'    => $request->only(['search', 'status', 'sort', 'dir']),
            'stats'      => [
                'total'    => Warehouse::count(),
                'active'   => Warehouse::where('is_active', true)->count(),
                'inactive' => Warehouse::where('is_active', false)->count(),
                'racks'    => Location::count(),
            ],
        ]);
    }

    public function store(WarehouseStoreRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());
        return back()->with('success', 'Warehouse created successfully.');
    }

    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());
        return back()->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->locations()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete: warehouse has racks assigned.']);
        }
        $warehouse->delete();
        return back()->with('success', 'Warehouse deleted successfully.');
    }
}