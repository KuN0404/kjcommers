<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission; // Meskipun tidak diminta, ini adalah praktik yang baik untuk di-import jika Anda akan menambahkan permission nanti
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        // app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Membuat Roles
        $adminRole = Role::create(['name' => 'admin']);
        $sellerRole = Role::create(['name' => 'seller']);
        $buyerRole = Role::create(['name' => 'buyer']);

        // Anda bisa menambahkan permission di sini jika diperlukan dan menetapkannya ke role
        // Contoh:
        // $manageUsersPermission = Permission::create(['name' => 'manage users']);
        // $adminRole->givePermissionTo($manageUsersPermission);
    }
}