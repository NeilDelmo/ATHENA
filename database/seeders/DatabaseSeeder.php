<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $researchHeadRole = Role::create(['name' => 'research_head']);
        $facultyRole = Role::create([ 'name' => 'faculty'
        ]);

        $head = User::where('email','23-78498@g.batstate-u.edu.ph')->first();
        if($head){
            $head -> assignRole($researchHeadRole);
        }

        User::where('email', '!=', '23-78498@g.batstate-u.edu.ph')
        ->get()
        ->each(function ($user) use ($facultyRole) {
            $user->assignRole($facultyRole);
        });
    }
}
