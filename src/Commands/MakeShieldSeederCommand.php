<?php

namespace BezhanSalleh\FilamentShield\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'shield:seeder')]
class MakeShieldSeederCommand extends Command
{
    use Concerns\CanManipulateFiles;

    /**
     * The console command signature.
     *
     * @var string
     */
    public $signature = 'shield:seeder
        {--generate : Generates permissions for all entities as configured }
        {--F|force : Override if the seeder already exists }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Create a seeder file from existing/configured roles and permission, that could be used within your deploy script.';

    public function handle(): int
    {
        $path = database_path('seeders/ShieldSeeder.php');

        if (! $this->option('force') && $this->checkForCollision(paths: [$path])) {
            return static::INVALID;
        }

        if ($this->option('generate')) {
            $this->call('shield:generate', [
                '--all' => true,
            ]);
        }

        if (Role::doesntExist() && Permission::doesntExist()) {
            $this->warn(' There are no roles or permissions to create the seeder. Please first run `shield:generate --all`');

            return static::INVALID;
        }

        $directPermissionNames = collect();
        $permissionsViaRoles = collect();
        $directPermissions = collect();

        if (Role::exists()) {
            $permissionsViaRoles = collect(Role::with('permissions')->get())
                ->map(function ($role) use ($directPermissionNames) {
                    $rolePermissions = $role->permissions
                        ->pluck('name')
                        ->toArray();

                    $directPermissionNames->push($rolePermissions);

                    return [
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'permissions' => $rolePermissions,
                    ];
                });
        }

        if (Permission::exists()) {
            $directPermissions = collect(Permission::get())
                ->filter(fn ($permission) => ! in_array($permission->name, $directPermissionNames->unique()->flatten()->all()))
                ->map(fn ($permission) => [
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ]);
        }

        $this->copyStubToApp(
            stub: 'ShieldSeeder',
            targetPath: $path,
            replacements: [
                'RolePermissions' => $permissionsViaRoles->all(),
                'DirectPermissions' => $directPermissions->all(),
            ]
        );

        $this->info('<fg=green;options=bold>ShieldSeeder</> generated successfully.');
        $this->line('Now you can use it in your deploy script. i.e:');
        $this->line('<bg=bright-green;options=bold> php artisan db:seed --class=ShieldSeeder </>');

        return self::SUCCESS;
    }
}
