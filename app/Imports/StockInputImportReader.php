<?php

namespace App\Imports;

use App\Models\ItemVariant;
use App\Models\Location;
use App\Models\StockLedger;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class StockInputImportReader implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;
    public int $skipped  = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +2 karena heading di row 1

            $sku      = trim($row['sku']          ?? '');
            $whCode   = strtoupper(trim($row['warehouse_code'] ?? ''));
            $locCode  = strtoupper(trim($row['location_code']  ?? ''));
            $qty      = $row['qty']      ?? null;
            $inputUom = trim($row['input_uom']    ?? '');
            $baseUom  = trim($row['base_uom']     ?? '');
            $altUom   = trim($row['alt_uom']      ?? '');
            $altConv  = $row['alt_uom_conversion'] ?? null;

            // Skip baris kosong (tidak ada sku atau qty)
            if ($sku === '' || $qty === '' || $qty === null) {
                $this->skipped++;
                continue;
            }

            // Validasi: qty harus numerik dan >= 0
            if (!is_numeric($qty) || (float) $qty < 0) {
                $this->errors[] = "Baris {$rowNum}: qty '{$qty}' tidak valid.";
                $this->skipped++;
                continue;
            }

            // Cari variant berdasarkan SKU
            $variant = ItemVariant::where('sku', $sku)->where('is_active', true)->first();
            if (!$variant) {
                $this->errors[] = "Baris {$rowNum}: SKU '{$sku}' tidak ditemukan.";
                $this->skipped++;
                continue;
            }

            // Cari lokasi berdasarkan warehouse_code + location_code
            $location = Location::whereHas('warehouse', fn ($q) => $q->where('code', $whCode))
                ->where('code', $locCode)
                ->where('is_active', true)
                ->first();

            if (!$location) {
                $this->errors[] = "Baris {$rowNum}: Lokasi '{$whCode} / {$locCode}' tidak ditemukan.";
                $this->skipped++;
                continue;
            }

            // Konversi qty ke base UOM
            $qtyBase = (float) $qty;

            if ($inputUom !== '' && $altUom !== '' && $inputUom === $altUom) {
                // Input dalam alt UOM → konversi ke base
                $conv = (float) ($altConv ?? $variant->item?->alt_uom_conversion ?? 0);
                if ($conv <= 0) {
                    $this->errors[] = "Baris {$rowNum}: alt_uom_conversion tidak valid untuk SKU '{$sku}'.";
                    $this->skipped++;
                    continue;
                }
                $qtyBase = $qtyBase * $conv;
            }
            // Kalau input_uom == base_uom atau kosong → pakai langsung

            StockLedger::updateOrCreate(
                [
                    'item_variant_id' => $variant->id,
                    'location_id'     => $location->id,
                ],
                [
                    'warehouse_id'    => $location->warehouse_id,
                    'qty_on_hand'     => $qtyBase,
                    'last_updated_at' => now(),
                ]
            );

            $this->imported++;
        }
    }
}
