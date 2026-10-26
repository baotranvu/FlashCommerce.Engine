<?php

namespace FlashCommerce\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use FlashCommerce\Identity\Infrastructure\Persistence\Eloquent\UserModel;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserModel::updateOrCreate(
            ['email' => 'test@flashcommerce.com'],
            [
                'name' => 'Verified Test User',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        UserModel::updateOrCreate(
            ['email' => 'unverified@flashcommerce.com'],
            [
                'name' => 'Unverified Test User',
                'password' => Hash::make('password123'),
                'email_verified_at' => null,
            ]
        );
    }
}
