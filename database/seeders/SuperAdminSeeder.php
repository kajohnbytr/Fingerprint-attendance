<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@northbridge.test'],
            [
                'name' => 'System Super Administrator',
                'password' => Hash::make('SuperAdmin2026!'),
                'role' => 'super_admin',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
    }
}
