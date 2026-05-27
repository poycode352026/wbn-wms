<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseAndCategorySeeder extends Seeder
{
    public function run(): void
    {
        // ── Warehouses ────────────────────────────────────────────────────────
        $km17 = Warehouse::create([
            'code'      => 'KM17',
            'name'      => 'Warehouse KM 17',
            'location'  => 'KM 17, Area Tambang',
            'is_active' => true,
        ]);

        $whl2 = Warehouse::create([
            'code'      => 'WH-L2',
            'name'      => 'Whitehouse Lantai 2',
            'location'  => 'Gedung Whitehouse, Lantai 2 — Barang Office & Titipan KM17',
            'is_active' => true,
        ]);

        $whl1 = Warehouse::create([
            'code'      => 'WH-L1',
            'name'      => 'Whitehouse Lantai 1',
            'location'  => 'Gedung Whitehouse, Lantai 1 — Barang IT',
            'is_active' => true,
        ]);

        // ── Master category definitions ───────────────────────────────────────
        $all = [
            'IT'         => ['name_en' => 'IT',         'name_id' => 'IT',                       'name_zh' => 'IT设备'],
            'ATK'        => ['name_en' => 'ATK',        'name_id' => 'ATK (Alat Tulis Kantor)',  'name_zh' => '办公文具'],
            'PPE'        => ['name_en' => 'PPE',        'name_id' => 'APD (Alat Pelindung Diri)','name_zh' => '个人防护'],
            'TOOLS'      => ['name_en' => 'Tools',      'name_id' => 'Perkakas',                 'name_zh' => '工具'],
            'EQUIPMENT'  => ['name_en' => 'Equipment',  'name_id' => 'Peralatan',                'name_zh' => '设备'],
            'CONSUMABLE' => ['name_en' => 'Consumable', 'name_id' => 'Habis Pakai',              'name_zh' => '耗材'],
            'MECHANICAL' => ['name_en' => 'Mechanical', 'name_id' => 'Mekanikal',                'name_zh' => '机械'],
        ];

        // ── Categories: KM17 — semua kategori kecuali IT ─────────────────────
        foreach (['ATK','PPE','TOOLS','EQUIPMENT','CONSUMABLE','MECHANICAL'] as $code) {
            ItemCategory::create([
                'warehouse_id' => $km17->id,
                'code'         => $code,
                'name_en'      => $all[$code]['name_en'],
                'name_id'      => $all[$code]['name_id'],
                'name_zh'      => $all[$code]['name_zh'],
                'is_active'    => true,
            ]);
        }

        // ── Categories: Whitehouse Lantai 2 — Office ─────────────────────────
        foreach (['ATK','EQUIPMENT','CONSUMABLE'] as $code) {
            ItemCategory::create([
                'warehouse_id' => $whl2->id,
                'code'         => $code,
                'name_en'      => $all[$code]['name_en'],
                'name_id'      => $all[$code]['name_id'],
                'name_zh'      => $all[$code]['name_zh'],
                'is_active'    => true,
            ]);
        }

        // ── Categories: Whitehouse Lantai 1 — IT ─────────────────────────────
        foreach (['IT','EQUIPMENT','CONSUMABLE'] as $code) {
            ItemCategory::create([
                'warehouse_id' => $whl1->id,
                'code'         => $code,
                'name_en'      => $all[$code]['name_en'],
                'name_id'      => $all[$code]['name_id'],
                'name_zh'      => $all[$code]['name_zh'],
                'is_active'    => true,
            ]);
        }
    }
}
