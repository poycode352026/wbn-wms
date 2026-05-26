<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationStoreRequest;
use App\Http\Requests\LocationUpdateRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['code', 'name'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $locations = Location::query()
            ->with('warehouse:id,code,name')
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('code', 'like', "%{$s}%")
                       ->orWhere('name', 'like', "%{$s}%")
                ))
            ->when($request->filled('warehouse_id'), fn ($q) =>
                $q->where('warehouse_id', $request->warehouse_id)
            )
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('code'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($l) => [
                'id'           => $l->id,
                'code'         => $l->code,
                'name'         => $l->name,
                'description'  => $l->description,
                'warehouse_id' => $l->warehouse_id,
                'warehouse'    => $l->warehouse
                    ? ['id' => $l->warehouse->id, 'code' => $l->warehouse->code, 'name' => $l->warehouse->name]
                    : null,
                'is_active'    => $l->is_active,
            ]);

        return Inertia::render('Master/Locations/Index', [
            'locations'  => $locations,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters'    => $request->only(['search', 'warehouse_id', 'status', 'sort', 'dir']),
            'stats'      => [
                'total'    => Location::count(),
                'active'   => Location::where('is_active', true)->count(),
                'inactive' => Location::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(LocationStoreRequest $request): RedirectResponse
    {
        Location::create($request->validated());
        return back()->with('success', 'Rack created successfully.');
    }

    public function update(LocationUpdateRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());
        return back()->with('success', 'Rack updated successfully.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();
        return back()->with('success', 'Rack deleted successfully.');
    }
}