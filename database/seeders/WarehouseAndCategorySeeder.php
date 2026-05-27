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
        Warehouse::create([
            'code'      => 'KM17',
            'name'      => 'Warehouse KM 17',
            'location'  => 'KM 17, Area Tambang',
            'is_active' => true,
        ]);

        Warehouse::create([
            'code'      => 'WH-L2',
            'name'      => 'Whitehouse Lantai 2',
            'location'  => 'Gedung Whitehouse, Lantai 2 — Barang Office & Titipan KM17',
            'is_active' => true,
        ]);

        Warehouse::create([
            'code'      => 'WH-L1',
            'name'      => 'Whitehouse Lantai 1',
            'location'  => 'Gedung Whitehouse, Lantai 1 — Barang IT',
            'is_active' => true,
        ]);

        // ── Global Categories (not tied to any warehouse) ─────────────────────
        $categories = [
            ['code' => 'IT',         'prefix' => 'IT',   'name_en' => 'Information Technology',    'name_id' => 'Teknologi Informasi',       'name_zh' => 'IT设备'],
            ['code' => 'ATK',        'prefix' => 'ATK',  'name_en' => 'Office Supplies',            'name_id' => 'Alat Tulis Kantor',         'name_zh' => '办公文具'],
            ['code' => 'PPE',        'prefix' => 'PPE',  'name_en' => 'Personal Protective Equip.', 'name_id' => 'Alat Pelindung Diri',       'name_zh' => '个人防护'],
            ['code' => 'TOOLS',      'prefix' => 'TLS',  'name_en' => 'Tools',                      'name_id' => 'Perkakas',                  'name_zh' => '工具'],
            ['code' => 'EQUIPMENT',  'prefix' => 'EQM',  'name_en' => 'Equipment',                  'name_id' => 'Peralatan',                 'name_zh' => '设备'],
            ['code' => 'CONSUMABLE', 'prefix' => 'CSMB', 'name_en' => 'Consumable',                 'name_id' => 'Barang Habis Pakai',        'name_zh' => '耗材'],
            ['code' => 'MECHANICAL', 'prefix' => 'MCNC', 'name_en' => 'Mechanical',                 'name_id' => 'Mekanikal',                 'name_zh' => '机械'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::create(array_merge($cat, ['is_active' => true]));
        }
    }
}
