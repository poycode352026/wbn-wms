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

        // ── Categories: KM17 ─────────────────────────────────────────────────
        $categoriesKm17 = [
            ['code' => 'GEN',  'name_en' => 'General',            'name_id' => 'Umum',              'name_zh' => '一般物资'],
            ['code' => 'MECH', 'name_en' => 'Mechanical',         'name_id' => 'Mekanikal',          'name_zh' => '机械'],
            ['code' => 'ELEC', 'name_en' => 'Electrical',         'name_id' => 'Elektrikal',         'name_zh' => '电气'],
            ['code' => 'SAFE', 'name_en' => 'Safety & PPE',       'name_id' => 'APD & Keselamatan',  'name_zh' => '安全防护'],
            ['code' => 'CHEM', 'name_en' => 'Chemical',           'name_id' => 'Kimia',              'name_zh' => '化学品'],
            ['code' => 'TOOL', 'name_en' => 'Tools & Equipment',  'name_id' => 'Perkakas',           'name_zh' => '工具设备'],
            ['code' => 'PIPE', 'name_en' => 'Pipes & Fittings',   'name_id' => 'Pipa & Fitting',     'name_zh' => '管道配件'],
            ['code' => 'WEAR', 'name_en' => 'Spare Part',         'name_id' => 'Suku Cadang',        'name_zh' => '备用零件'],
        ];

        foreach ($categoriesKm17 as $cat) {
            ItemCategory::create([
                'warehouse_id' => $km17->id,
                'code'         => $cat['code'],
                'name_en'      => $cat['name_en'],
                'name_id'      => $cat['name_id'],
                'name_zh'      => $cat['name_zh'],
                'is_active'    => true,
            ]);
        }

        // ── Categories: Whitehouse Lantai 2 (Office) ─────────────────────────
        $categoriesWhl2 = [
            ['code' => 'ATK',  'name_en' => 'General (ATK)',       'name_id' => 'Umum (ATK)',          'name_zh' => '文具办公'],
            ['code' => 'FURN', 'name_en' => 'Furniture & Fixture', 'name_id' => 'Furnitur',            'name_zh' => '家具'],
            ['code' => 'TITIP','name_en' => 'Consignment KM17',    'name_id' => 'Titipan dari KM17',   'name_zh' => 'KM17寄存'],
        ];

        foreach ($categoriesWhl2 as $cat) {
            ItemCategory::create([
                'warehouse_id' => $whl2->id,
                'code'         => $cat['code'],
                'name_en'      => $cat['name_en'],
                'name_id'      => $cat['name_id'],
                'name_zh'      => $cat['name_zh'],
                'is_active'    => true,
            ]);
        }

        // ── Categories: Whitehouse Lantai 1 (IT) ─────────────────────────────
        $categoriesWhl1 = [
            ['code' => 'ITDEV', 'name_en' => 'IT Device',        'name_id' => 'Perangkat IT',        'name_zh' => 'IT设备'],
            ['code' => 'ITPER', 'name_en' => 'IT Peripheral',    'name_id' => 'Peripheral IT',       'name_zh' => 'IT外设'],
            ['code' => 'ITCON', 'name_en' => 'IT Consumable',    'name_id' => 'Habis Pakai IT',      'name_zh' => 'IT耗材'],
            ['code' => 'NETW',  'name_en' => 'Networking',       'name_id' => 'Jaringan',            'name_zh' => '网络设备'],
        ];

        foreach ($categoriesWhl1 as $cat) {
            ItemCategory::create([
                'warehouse_id' => $whl1->id,
                'code'         => $cat['code'],
                'name_en'      => $cat['name_en'],
                'name_id'      => $cat['name_id'],
                'name_zh'      => $cat['name_zh'],
                'is_active'    => true,
            ]);
        }
    }
}
