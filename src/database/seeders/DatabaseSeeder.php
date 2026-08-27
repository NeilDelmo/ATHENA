<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $workspaceRoles = [];

        foreach (array_keys(User::workspaceDefinitions()) as $workspace) {
            $workspaceRoles[$workspace] = Role::findOrCreate($workspace, 'web');
        }

        $head = User::updateOrCreate(['email' => '23-78498@g.batstate-u.edu.ph'], [
            'name' => 'Research Head',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $head->syncRoles([$workspaceRoles[User::WORKSPACE_RESEARCH_HEAD]]);
    }
}
