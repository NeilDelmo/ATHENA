<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameRole('research_secretary', 'research_office_secretary');
    }

    public function down(): void
    {
        $this->renameRole('research_office_secretary', 'research_secretary');
    }

    private function renameRole(string $oldName, string $newName): void
    {
        DB::transaction(function () use ($oldName, $newName): void {
            $roles = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', [$oldName, $newName])
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id', 'name');

            $oldRoleId = $roles->get($oldName);
            if ($oldRoleId === null) {
                return;
            }

            $newRoleId = $roles->get($newName);
            if ($newRoleId === null) {
                DB::table('roles')->where('id', $oldRoleId)->update(['name' => $newName]);

                return;
            }

            foreach (['model_has_roles' => ['model_type', 'model_id'], 'role_has_permissions' => ['permission_id']] as $table => $columns) {
                DB::table($table)->insertOrIgnoreUsing(
                    ['role_id', ...$columns],
                    DB::table($table)->selectRaw('? as role_id', [$newRoleId])->addSelect($columns)->where('role_id', $oldRoleId),
                );
            }

            DB::table('roles')->where('id', $oldRoleId)->delete();
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
