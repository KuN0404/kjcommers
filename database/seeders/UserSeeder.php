<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Membuat User Admin
        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Ganti 'password' dengan password yang aman
            'is_active' => true,
        ]);
        // Menetapkan role 'admin' ke user admin
        // Pastikan role 'admin' sudah ada (dibuat oleh RoleSeeder)
        $adminRole = Role::findByName('admin');
        if ($adminRole) {
            $adminUser->assignRole($adminRole);
        }


        // Membuat User Seller
        $sellerUser = User::create([
            'name' => 'Seller',
            'email' => 'seller@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Ganti 'password' dengan password yang aman
            'is_active' => true,
        ]);
        // Menetapkan role 'seller' ke user seller
        $sellerRole = Role::findByName('seller');
        if ($sellerRole) {
            $sellerUser->assignRole($sellerRole);
        }

        // Membuat User Buyer
        $buyerUser = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Ganti 'password' dengan password yang aman
            'is_active' => true,
        ]);
        // Menetapkan role 'buyer' ke user buyer
        $buyerRole = Role::findByName('buyer');
        if ($buyerRole) {
            $buyerUser->assignRole($buyerRole);
        }
    }
}