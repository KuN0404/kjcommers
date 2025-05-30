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
        // Ini penting jika Anda sering mengubah roles/permissions dan menjalankan seeder berulang kali
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Membuat Roles
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'seller', 'guard_name' => 'web']);
        Role::create(['name' => 'buyer', 'guard_name' => 'web']);

        // Contoh jika Anda ingin membuat permission dan menetapkannya ke role admin:
        // Permission::create(['name' => 'manage users', 'guard_name' => 'web']);
        // Permission::create(['name' => 'manage products', 'guard_name' => 'web']);
        // Permission::create(['name' => 'view orders', 'guard_name' => 'web']);

        // $adminRole = Role::findByName('admin');
        // $adminRole->givePermissionTo(['manage users', 'manage products', 'view orders']);

        // $sellerRole = Role::findByName('seller');
        // $sellerRole->givePermissionTo(['manage products', 'view orders']);

        // $buyerRole = Role::findByName('buyer');
        // $buyerRole->givePermissionTo('view orders');

        $this->command->info('Roles seeded successfully!');
    }
}
