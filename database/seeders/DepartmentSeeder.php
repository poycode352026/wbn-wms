<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        ['code' => 'ENV',   'name' => 'Environment'],
        ['code' => 'HSE',   'name' => 'HSE'],
        ['code' => 'GEO',   'name' => 'Geology'],
        ['code' => 'ENG',   'name' => 'Engineering'],
        ['code' => 'CON',   'name' => 'Construction'],
        ['code' => 'CRU',   'name' => 'Crusher'],
        ['code' => 'MNT',   'name' => 'Maintenance'],
        ['code' => 'OPR',   'name' => 'Operation'],
        ['code' => 'GTC',   'name' => 'Geotechnical'],
        ['code' => 'ADM',   'name' => 'Administration'],
        ['code' => 'HLG',   'name' => 'Hauling'],
        ['code' => 'SVY',   'name' => 'Survey'],
        ['code' => 'FMS',   'name' => 'FMS'],
        ['code' => 'BLS',   'name' => 'Blasting'],
        ['code' => 'DBS',   'name' => 'Database'],
        ['code' => 'CCC',   'name' => 'CCC'],
        ['code' => 'SFT',   'name' => 'Safety'],
        ['code' => 'GS',    'name' => 'General Service'],
        ['code' => 'SPL',   'name' => 'Supply'],
        ['code' => 'TS',    'name' => 'Technical Service'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name'], 'is_active' => true]
            );
        }
    }
}
