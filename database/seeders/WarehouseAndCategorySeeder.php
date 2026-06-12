<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseAndCategorySeeder extends Seeder
{
    public function run(): void
    {

        // 1. General Warehouse (priority 1 = closest)
        $generalWarehouse = Warehouse::create([
            'code'       => 'GNR',
            'name'       => 'Warehouse General',
            'location'   => 'WBN Office Gedung Putih Lantai 2',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        // 2. IT Warehouse (priority 2)
        $itWarehouse = Warehouse::create([
            'code'       => 'IT',
            'name'       => 'Warehouse IT',
            'location'   => 'WBN Office Gedung Putih Lantai 1 - Counter Room',
            'is_active'  => true,
            'sort_order' => 2,
        ]);

        // ── Warehouses ────────────────────────────────────────────────────────
        $km17 = Warehouse::create([
            'code'       => 'KM17',
            'name'       => 'Warehouse KM 17',
            'location'   => 'KM 17, Area Tambang',
            'is_active'  => true,
            'sort_order' => 3,
        ]);

        
        // ── Rack Locations ────────────────────────────────────────────────────
        // KM17 racks
        $km17Racks = [];
        for ($rack = 1; $rack <= 12; $rack++) {
            foreach (range('A', 'I') as $shelf) {
                for ($level = 1; $level <= 4; $level++) {

                    $rackCode = str_pad($rack, 2, '0', STR_PAD_LEFT);
                    $levelCode = str_pad($level, 2, '0', STR_PAD_LEFT);

                    $km17Racks[] = [
                        'code' => "RN-{$rackCode}-{$shelf}{$level}",
                        'name' => "Rack Number {$rack} · Shelf {$shelf} · Level {$levelCode}",
                    ];
                }
            }
        }
        foreach ($km17Racks as $rack) {
            Location::create(array_merge($rack, ['warehouse_id' => $km17->id, 'is_active' => true]));
        }

        // ── Holding Area for Every Warehouse ───────────────────────────────

        $holdingAreaDescription = 'A designated temporary storage area used to hold items before they are processed, inspected, transferred, issued, or relocated to their final destination.';

        Location::create([
            'warehouse_id' => $generalWarehouse->id,
            'code'         => 'GNR-HA',
            'name'         => 'General Warehouse . Holding Area',
            'description'  => $holdingAreaDescription,
            'is_active'    => true,
        ]);

        Location::create([
            'warehouse_id' => $itWarehouse->id,
            'code'         => 'IT-HA',
            'name'         => 'IT Warehouse . Holding Area',
            'description'  => $holdingAreaDescription,
            'is_active'    => true,
        ]);

        Location::create([
            'warehouse_id' => $km17->id,
            'code'         => 'KM17-HA',
    'name'         => 'KM17 Warehouse . Holding Area',
    'description'  => $holdingAreaDescription,
    'is_active'    => true,
]);

        // Rack Numbers
        $generalRacks = [15, 8, 19, 13, 14,18];

        foreach ($generalRacks as $rack) {
            foreach (['A', 'B'] as $level) {
                Location::create([
                    'warehouse_id' => $generalWarehouse->id,
                    'code'         => "RN-{$rack}-{$level}",
                    'name'         => "Rack Number {$rack} . Level {$level}",
                    'is_active'    => true,
                ]);
            }
        }

        // ── Global Categories (not tied to any warehouse) ─────────────────────
        $categories = [
            ['code' => 'IT',         'prefix' => 'IT',   'name_en' => 'Information Technology',     'name_id' => 'Teknologi Informasi',    'name_zh' => 'IT设备'],
            ['code' => 'ATK',        'prefix' => 'ATK',  'name_en' => 'Office Supplies',             'name_id' => 'Alat Tulis Kantor',      'name_zh' => '办公文具'],
            ['code' => 'PPE',        'prefix' => 'PPE',  'name_en' => 'Personal Protective Equip.',  'name_id' => 'Alat Pelindung Diri',    'name_zh' => '个人防护'],
            ['code' => 'TOOLS',      'prefix' => 'TLS',  'name_en' => 'Tools',                       'name_id' => 'Perkakas',               'name_zh' => '工具'],
            ['code' => 'EQUIPMENT',  'prefix' => 'EQM',  'name_en' => 'Equipment',                   'name_id' => 'Peralatan',              'name_zh' => '设备'],
            ['code' => 'CONSUMABLE', 'prefix' => 'CSMB', 'name_en' => 'Consumable',                  'name_id' => 'Barang Habis Pakai',     'name_zh' => '耗材'],
            ['code' => 'MECHANICAL', 'prefix' => 'MCNC', 'name_en' => 'Mechanical',                  'name_id' => 'Mekanikal',              'name_zh' => '机械'],
            ['code' => 'CHEMICAL', 'prefix' => 'CMC', 'name_en' => 'Chemical',                    'name_id' => 'Bahan Kimia',            'name_zh' => '化学品'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::create(array_merge($cat, ['is_active' => true]));
        }
    }
}
