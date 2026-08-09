<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $researchHeadRole = Role::firstOrCreate(['name' => 'research_head']);

        $head = User::updateOrCreate(['email' => '23-78498@g.batstate-u.edu.ph'], [
            'name' => 'Research Head',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $head->syncRoles([$researchHeadRole]);
    }
}
