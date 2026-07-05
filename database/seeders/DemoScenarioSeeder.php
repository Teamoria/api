<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoScenarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                OrganizationScenarioSeeder::class,
                WorkManagementScenarioSeeder::class,
                UploadScenarioSeeder::class,
            ]);
        });
    }
}
