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
        // ── Warehouses (idempotent: unique by code) ───────────────────────────
        $generalWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GNR'],
            ['name' => 'Warehouse General', 'location' => 'WBN Office Gedung Putih Lantai 2', 'is_active' => true, 'sort_order' => 1]
        );

        $itWarehouse = Warehouse::firstOrCreate(
            ['code' => 'CTR'],
            ['name' => 'Warehouse Counter Room', 'location' => 'WBN Office Gedung Putih Lantai 1 - Counter Room', 'is_active' => true, 'sort_order' => 2]
        );

        $km17 = Warehouse::firstOrCreate(
            ['code' => 'KM17'],
            ['name' => 'Warehouse KM 17', 'location' => 'KM 17, Area Tambang', 'is_active' => true, 'sort_order' => 3]
        );

        // ── Rack Locations KM17 (idempotent: unique by code) ─────────────────
        for ($rack = 1; $rack <= 12; $rack++) {
            foreach (range('A', 'I') as $shelf) {
                for ($level = 1; $level <= 4; $level++) {
                    $rackCode  = str_pad($rack, 2, '0', STR_PAD_LEFT);
                    $levelCode = str_pad($level, 2, '0', STR_PAD_LEFT);
                    $code = "RN-{$rackCode}-{$shelf}{$level}";
                    Location::firstOrCreate(
                        ['code' => $code],
                        ['warehouse_id' => $km17->id, 'name' => "Rack Number {$rack} · Shelf {$shelf} · Level {$levelCode}", 'is_active' => true]
                    );
                }
            }
        }

        // ── Holding Areas ─────────────────────────────────────────────────────
        $holdingAreaDescription = 'A designated temporary storage area used to hold items before they are processed, inspected, transferred, issued, or relocated to their final destination.';

        Location::firstOrCreate(
            ['code' => 'GNR-HA'],
            ['warehouse_id' => $generalWarehouse->id, 'name' => 'General Warehouse . Holding Area', 'description' => $holdingAreaDescription, 'is_active' => true]
        );
        Location::firstOrCreate(
            ['code' => 'CTR-HA'],
            ['warehouse_id' => $itWarehouse->id, 'name' => 'Counter Room Warehouse . Holding Area', 'description' => $holdingAreaDescription, 'is_active' => true]
        );
        Location::firstOrCreate(
            ['code' => 'KM17-HA'],
            ['warehouse_id' => $km17->id, 'name' => 'KM17 Warehouse . Holding Area', 'description' => $holdingAreaDescription, 'is_active' => true]
        );

        // ── General Warehouse Racks ───────────────────────────────────────────
        foreach ([15, 8, 19, 13, 14, 18] as $rack) {
            foreach (['A', 'B'] as $level) {
                Location::firstOrCreate(
                    ['code' => "RN-{$rack}-{$level}"],
                    ['warehouse_id' => $generalWarehouse->id, 'name' => "Rack Number {$rack} . Level {$level}", 'is_active' => true]
                );
            }
        }

        // ── Item Categories (idempotent: unique by code) ──────────────────────
        $categories = [
            ['code' => 'IT',         'prefix' => 'IT',   'name_en' => 'Information Technology',    'name_id' => 'Teknologi Informasi',  'name_zh' => 'IT设备'],
            ['code' => 'ATK',        'prefix' => 'ATK',  'name_en' => 'Office Supplies',            'name_id' => 'Alat Tulis Kantor',    'name_zh' => '办公文具'],
            ['code' => 'PPE',        'prefix' => 'PPE',  'name_en' => 'Personal Protective Equip.', 'name_id' => 'Alat Pelindung Diri',  'name_zh' => '个人防护'],
            ['code' => 'TOOLS',      'prefix' => 'TLS',  'name_en' => 'Tools',                      'name_id' => 'Perkakas',             'name_zh' => '工具'],
            ['code' => 'EQUIPMENT',  'prefix' => 'EQM',  'name_en' => 'Equipment',                  'name_id' => 'Peralatan',            'name_zh' => '设备'],
            ['code' => 'CONSUMABLE', 'prefix' => 'CSMB', 'name_en' => 'Consumable',                 'name_id' => 'Barang Habis Pakai',   'name_zh' => '耗材'],
            ['code' => 'MECHANICAL', 'prefix' => 'MCNC', 'name_en' => 'Mechanical',                 'name_id' => 'Mekanikal',            'name_zh' => '机械'],
            ['code' => 'CHEMICAL',   'prefix' => 'CMC',  'name_en' => 'Chemical',                   'name_id' => 'Bahan Kimia',          'name_zh' => '化学品'],
        ];

        foreach ($categories as $cat) {
            ItemCategory::firstOrCreate(['code' => $cat['code']], array_merge($cat, ['is_active' => true]));
        }
    }
}
