<?php

namespace App\Exports;

use App\Models\ItemVariant;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockInputExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    protected array $data;
    protected int   $dataRows;

    /*
     * Row layout (WithHeadings puts headings at row 1):
     *   Row 1  : column headers  ← dikontrol WithHeadings
     *   Row 2+ : data
     *
     * Column layout:
     *   A–L  REF (grey)   : sku, part_number, kategori, nama_id, nama_en,
     *                        brand, model, size, color, base_uom, alt_uom, alt_uom_conversion
     *   M–P  EDIT (yellow): warehouse_code, location_code, qty, input_uom
     */

    public function __construct()
    {
        $variants = ItemVariant::query()
            ->where('is_active', true)
            ->with([
                'item:id,part_number,name_id,name_en,base_uom,alt_uom,alt_uom_conversion,category_id',
                'item.category:id,code,name_id',
                'stockLedgers.location:id,code,warehouse_id',
                'stockLedgers.location.warehouse:id,code',
            ])
            ->orderBy('sku')
            ->get();

        $this->data = [];

        foreach ($variants as $v) {
            $item = $v->item;
            $cat  = $item?->category;

            $ref = [
                $v->sku,
                $item?->part_number ?? '',
                $cat ? "{$cat->code} — {$cat->name_id}" : '',
                $item?->name_id ?? '',
                $item?->name_en ?? '',
                $v->brand  ?? '',
                $v->model  ?? '',
                $v->size   ?? '',
                $v->color  ?? '',
                $item?->base_uom ?? '',
                $item?->alt_uom  ?? '',
                $item?->alt_uom_conversion !== null ? (float) $item->alt_uom_conversion : '',
            ];

            if ($v->stockLedgers->isEmpty()) {
                $this->data[] = array_merge($ref, [
                    '', '', '', $item?->base_uom ?? '',
                ]);
            } else {
                foreach ($v->stockLedgers as $sl) {
                    $this->data[] = array_merge($ref, [
                        $sl->location?->warehouse?->code ?? '',
                        $sl->location?->code             ?? '',
                        (float) $sl->qty_on_hand,
                        $item?->base_uom ?? '',
                    ]);
                }
            }
        }

        $this->dataRows = count($this->data);
    }

    // ── Headings di row 1 ────────────────────────────────────────────────────
    public function headings(): array
    {
        return [
            // ── REF (A–L) ─────────────────────────
            'SKU',
            'Part Number',
            'Kategori',
            'Nama Barang (ID)',
            'Nama Barang (EN)',
            'Brand',
            'Model',
            'Size',
            'Color',
            'Base UOM',
            'Alt UOM',
            'Alt UOM Conversion',
            // ── EDIT (M–P) ────────────────────────
            'Warehouse Code  ✎',
            'Location Code  ✎',
            'Qty  ✎',
            'Input UOM  ✎',
        ];
    }

    public function array(): array
    {
        return $this->data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $this->dataRows + 1; // row 1 = heading, row 2..N+1 = data

                // ── Header row (row 1) ──────────────────────────────────────
                // REF cols A–L → slate/grey
                $sheet->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF4B5563']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                // EDIT cols M–P → amber/orange
                $sheet->getStyle('M1:P1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFD97706']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);

                // ── Data rows (row 2 … lastRow) ─────────────────────────────
                if ($this->dataRows > 0) {
                    // REF → latar abu-abu terang, teks gelap
                    $sheet->getStyle("A2:L{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF9FAFB']],
                        'font' => ['color' => ['argb' => 'FF374151']],
                    ]);
                    // EDIT → latar kuning, teks gelap
                    $sheet->getStyle("M2:P{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFFF9C4']],
                        'font' => ['color' => ['argb' => 'FF374151']],
                    ]);
                    // Garis semua sel
                    $sheet->getStyle("A1:P{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ]],
                    ]);
                    // Garis tebal antara kolom REF dan EDIT (antara L dan M)
                    $sheet->getStyle("M1:M{$lastRow}")->applyFromArray([
                        'borders' => ['left' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['argb' => 'FFD97706'],
                        ]],
                    ]);
                    // Kolom Qty → rata kanan
                    $sheet->getStyle("O2:O{$lastRow}")->getAlignment()
                          ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Zebra striping pada REF cols agar lebih mudah dibaca
                    for ($r = 2; $r <= $lastRow; $r += 2) {
                        $sheet->getStyle("A{$r}:L{$r}")->applyFromArray([
                            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF3F4F6']],
                        ]);
                    }
                }

                // ── Freeze header row ───────────────────────────────────────
                $sheet->freezePane('A2');

                // ── Lebar kolom ─────────────────────────────────────────────
                $widths = [
                    'A' => 18, 'B' => 16, 'C' => 22, 'D' => 28, 'E' => 28,
                    'F' => 13, 'G' => 13, 'H' => 10, 'I' => 10,
                    'J' => 10, 'K' => 10, 'L' => 16,
                    'M' => 16, 'N' => 14, 'O' => 10, 'P' => 13,
                ];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                // ── Legenda di bawah data ───────────────────────────────────
                $noteRow = $lastRow + 2;
                $sheet->mergeCells("A{$noteRow}:P{$noteRow}");
                $sheet->setCellValue("A{$noteRow}",
                    '📌  Kolom abu-abu (A–L) = referensi, JANGAN diubah.  ' .
                    'Kolom kuning (M–P) = silakan isi / edit.  ' .
                    'input_uom harus persis sama dengan nilai base_uom atau alt_uom di kolom J / K.'
                );
                $sheet->getStyle("A{$noteRow}")->applyFromArray([
                    'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6B7280']],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFEF9C3']],
                    'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                $sheet->getRowDimension($noteRow)->setRowHeight(22);

                // ── Tab / sheet name ────────────────────────────────────────
                $sheet->getParent()->getActiveSheet()->setTitle('Stock Input');
            },
        ];
    }
}
