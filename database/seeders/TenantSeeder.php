<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [[
            'name' => 'First Bank MFB',
            'code' => 'firstbank'
        ], [
            'name' => 'RMF23 Cooperative',
            'code' => 'rmf23'
        ]];


        foreach ($tenants as $tenantData) {
            $tenant = Tenant::firstOrCreate(
                ['code' => $tenantData['code']],
                ['name' => $tenantData['name']]
            );

            User::firstOrCreate(
                ['email' => 'admin@' . $tenant->code . '.com'],
                [
                    'name'      => 'Admin User',
                    'password'  => Hash::make('password'),
                    'slug' => \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'role'      => 'admin',
                ]
            );

            User::firstOrCreate(
                ['email' => 'approver@' . $tenant->code . '.com'],
                [
                    'name'      => 'Approver User',
                    'password'  => Hash::make('password'),
                    'slug' => \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'role'      => 'approver',
                ]
            );

            User::firstOrCreate(
                ['email' => 'operator@' . $tenant->code . '.com'],
                [
                    'name'      => 'Operator User',
                    'password'  => Hash::make('password'),
                    'tenant_id' => $tenant->id,
                    'slug' => \Illuminate\Support\Str::uuid(),
                    'role'      => 'operator',
                ]
            );
        }
    }
}
