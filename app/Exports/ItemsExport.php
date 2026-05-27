<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ItemsExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    protected array $data;

    public function __construct()
    {
        $items = Item::query()
            ->with(['category:id,code,prefix', 'variants' => fn ($q) => $q->orderBy('id')])
            ->orderBy('part_number')
            ->get();

        $this->data = [];

        foreach ($items as $item) {
            $cat    = $item->category;
            $prefix = $cat ? "WBN-{$cat->prefix}-" : 'WBN-???-';
            $suffix = $cat ? substr($item->part_number, strlen($prefix)) : $item->part_number;

            $base = [
                $cat?->code ?? '',
                $suffix,
                $item->name_en,
                $item->name_id ?? '',
                $item->name_zh ?? '',
                $item->description ?? '',
                $item->base_uom,
                $item->alt_uom ?? '',
                $item->alt_uom_conversion ?? '',
                $item->minimum_stock,
                $item->has_cooldown ? 'yes' : 'no',
                $item->cooldown_days ?? '',
                $item->cooldown_track_by ?? '',
                $item->photo_required ? 'yes' : 'no',
                $item->is_active ? 'yes' : 'no',
            ];

            if ($item->variants->isEmpty()) {
                $this->data[] = array_merge($base, ['', '', '', '', '', 'yes']);
            } else {
                foreach ($item->variants as $v) {
                    $this->data[] = array_merge($base, [
                        $v->brand ?? '',
                        $v->model ?? '',
                        $v->size  ?? '',
                        $v->color ?? '',
                        $v->sku,
                        $v->is_active ? 'yes' : 'no',
                    ]);
                }
            }
        }
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'category_code', 'part_suffix', 'name_en', 'name_id', 'name_zh',
            'description', 'base_uom', 'alt_uom', 'alt_uom_conversion',
            'minimum_stock', 'has_cooldown', 'cooldown_days', 'cooldown_track_by',
            'photo_required', 'is_active',
            'variant_brand', 'variant_model', 'variant_size', 'variant_color',
            'variant_sku', 'variant_is_active',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                // Header row: dark bg, white bold
                $sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF374151']],
                ]);
                // Freeze top row
                $sheet->freezePane('A2');
            },
        ];
    }
}
