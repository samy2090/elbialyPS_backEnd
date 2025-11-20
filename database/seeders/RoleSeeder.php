<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'permissions' => [
                    'users' => ['view', 'add', 'edit', 'delete'],
                    'devices' => ['view', 'add', 'edit', 'delete'],
                    'sessions' => ['view', 'start', 'pause', 'end', 'delete'],
                    'products' => ['view', 'add', 'edit', 'delete'],
                    'invoices' => ['view', 'add', 'edit', 'delete']
                ]
            ],
            [
                'name' => 'staff',
                'permissions' => [
                    'users' => ['view', 'add', 'edit_basic'],
                    'devices' => ['view'],
                    'sessions' => ['view', 'start', 'pause', 'end'],
                    'products' => ['view', 'add']
                ]
            ],
            [
                'name' => 'customer',
                'permissions' => [
                    'devices' => ['view'],
                    'sessions' => ['view'],
                    'products' => ['view']
                ]
            ],
            [
                'name' => 'guest',
                'permissions' => [
                    'devices' => ['view'],
                    'products' => ['view']
                ]
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['permissions' => $role['permissions']]
            );
        }
    }
}