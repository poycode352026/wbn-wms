<?php

namespace App\Exports;

use App\Models\ItemCategory;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ItemsImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        $cats    = ItemCategory::where('is_active', true)->orderBy('code')->get(['code', 'prefix']);
        $catList = $cats->map(fn ($c) => "{$c->code} → WBN-{$c->prefix}-???")->join('  |  ');

        return [
            // Row 2: info / comment row (skipped during import because it starts with #)
            ["# Kategori: {$catList}"],

            // Row 3–4: sample item 1 — Laptop, 2 variants
            ['IT', 'LTDL', 'Laptop Dell XPS 13', 'Laptop Dell XPS 13', '戴尔笔记本',
             'Laptop for office use', 'unit', '', '', 5,
             'no', '', '', 'yes', 'yes', 'Dell', 'XPS 13 9310', '13.4 inch', 'Silver', 'yes'],
            ['IT', 'LTDL', '', '', '', '', '', '', '', null,
             '', '', '', '', '', 'Dell', 'XPS 13 9310', '13.4 inch', 'Black', 'yes'],

            // Row 5: sample item 2 — Ballpoint pen with cooldown
            ['ATK', 'BLPT', 'Ballpoint Pen', 'Pulpen', '圆珠笔',
             '', 'pcs', 'box', 12, 100,
             'yes', 30, 'employee_id', 'no', 'yes', '', '', '', '', 'yes'],

            // Row 6: sample item 3 — PPE helmet
            ['PPE', 'HLMT', 'Safety Helmet', 'Helm Safety', '安全帽',
             'Standard safety helmet', 'unit', '', '', 20,
             'no', '', '', 'no', 'yes', '', '', 'L', 'Yellow', 'yes'],
        ];
    }

    public function headings(): array
    {
        return [
            'category_code', 'part_suffix', 'name_en', 'name_id', 'name_zh',
            'description', 'base_uom', 'alt_uom', 'alt_uom_conversion',
            'minimum_stock', 'has_cooldown(yes/no)', 'cooldown_days',
            'cooldown_track_by(lv_number|employee_id)', 'photo_required(yes/no)',
            'is_active(yes/no)', 'variant_brand', 'variant_model',
            'variant_size', 'variant_color', 'variant_is_active(yes/no)',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header row (row 1): dark bg, white bold
                $sheet->getStyle('A1:T1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF374151']],
                ]);

                // Info/comment row (row 2): light grey, italic
                $sheet->getStyle('A2:T2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['argb' => 'FF6B7280']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF3F4F6']],
                ]);

                // Sample data rows (3–6): light warm background
                $sheet->getStyle('A3:T6')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFFF7ED']],
                ]);

                // Freeze top row
                $sheet->freezePane('A2');
            },
        ];
    }
}
