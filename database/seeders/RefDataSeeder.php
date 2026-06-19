<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RefDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
