<?php

namespace App\Http\Controllers;

use App\Models\CooldownLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Vehicle::withCount('cooldowns')
            ->with(['cooldowns' => fn ($q) =>
                $q->with('variant:id,item_id', 'variant.item:id,name_en,name_id')
                  ->latest('cooldown_until')
                  ->limit(1)
            ]);

        if ($request->search) {
            $s = $request->search;
            $query->where(fn ($q) =>
                $q->where('lv_number', 'like', "%{$s}%")
                  ->orWhere('lv_code', 'like', "%{$s}%")
                  ->orWhere(\DB::raw("CONCAT(lv_code, '-', lv_number)"), 'like', "%{$s}%")
            );
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $vehicles = $query->orderBy('lv_code')->orderBy('lv_number')->paginate(25)->withQueryString();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lv_code'   => ['required', 'string', 'max:20'],
            'lv_number' => ['required', 'string', 'max:50'],
            'type'      => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        // Ensure the full identifier is unique
        $exists = Vehicle::where('lv_code', $data['lv_code'])
            ->where('lv_number', $data['lv_number'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['lv_number' => 'Identifikasi LV sudah digunakan.']);
        }

        $vehicle = Vehicle::create($data);

        return back()->with('success', "{$vehicle->lv_code}-{$vehicle->lv_number} berhasil ditambahkan.");
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'lv_code'   => ['required', 'string', 'max:20'],
            'lv_number' => ['required', 'string', 'max:50'],
            'type'      => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        // Ensure unique (excluding self)
        $exists = Vehicle::where('lv_code', $data['lv_code'])
            ->where('lv_number', $data['lv_number'])
            ->where('id', '!=', $vehicle->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['lv_number' => 'Identifikasi LV sudah digunakan.']);
        }

        $vehicle->update($data);

        return back()->with('success', "{$vehicle->lv_code}-{$vehicle->lv_number} berhasil diperbarui.");
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update(['is_active' => false]);

        return back()->with('success', "{$vehicle->lv_code}-{$vehicle->lv_number} dinonaktifkan.");
    }

    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lv_import_template.csv"',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['lv_code', 'lv_number', 'type']);
            fputcsv($out, ['WBN-LV', 'S81', 'LV (Light Vehicle)']);
            fputcsv($out, ['WBN-LV', 'M89', 'LV (Light Vehicle)']);
            fputcsv($out, ['WBN-DT', 'C39', 'Dump Truck']);
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

        $imported = 0;
        $errors   = [];
        $row      = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (empty(array_filter($data))) continue;
            if (str_starts_with(trim($data[0] ?? ''), '#')) continue;

            $lvCode   = trim($data[$headerMap['lv_code']   ?? 0] ?? '') ?: 'WBN-LV';
            $lvNumber = trim($data[$headerMap['lv_number'] ?? 1] ?? '');
            $type     = trim($data[$headerMap['type']       ?? 2] ?? '') ?: null;

            if (!$lvNumber) {
                $errors[] = "Baris {$row}: lv_number kosong.";
                continue;
            }

            Vehicle::updateOrCreate(
                ['lv_code' => $lvCode, 'lv_number' => $lvNumber],
                [
                    'type'      => $type,
                    'is_active' => true,
                ]
            );
            $imported++;
        }

        fclose($handle);

        if ($errors) {
            return back()
                ->with('success', "{$imported} LV berhasil diimport.")
                ->withErrors(['import' => implode(' | ', array_slice($errors, 0, 5))]);
        }

        return back()->with('success', "{$imported} LV berhasil diimport.");
    }
}
