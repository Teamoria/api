<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $companies = Company::factory(10)->create();

        User::factory(50)->recycle($companies)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'company_id' => $companies->first()->id,
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'ahmedalyazuri@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('1234568'),
            'role' => UserRole::ADMIN,
        ]);



    }
}
