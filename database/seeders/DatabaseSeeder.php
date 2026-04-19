<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'morales.carljohn1@gmail.com'],
            [
                'name' => 'Carl John Morales',
                'password' => Hash::make('Admin22026'),
            ]
        );

        $this->call([
            StaffSeeder::class,
        ]);
    }
}