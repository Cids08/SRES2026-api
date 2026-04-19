<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'morales.carljohn1@gmail.com'],
            [
                'name' => 'Carl John Morales',
                'bio' => 'System Administrator',
                'profile_photo' => null,
                'password' => Hash::make('Admin22026'),
            ]
        );

        $this->call([
            StaffSeeder::class,
        ]);
    }
}