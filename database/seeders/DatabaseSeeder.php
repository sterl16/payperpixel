<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // local admin for the report page, password is "password"
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@pixelforge.test',
        ]);
        $admin->forceFill(['is_admin' => true])->save();
    }
}
