<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffSeeder extends Seeder
{
    /**
     * Run: php artisan db:seed --class=StaffSeeder
     *
     * NOTE: This seeder does NOT upload actual image files.
     * The image column is left null so the frontend falls back to initials avatars.
     * To attach real photos, upload them via the Admin panel after seeding.
     */
    public function run(): void
    {
        // Avoid duplicate seeding
        if (DB::table('staff')->count() > 0) {
            $this->command->info('Staff table already has data — skipping seeder.');
            return;
        }

        $now = now();

        $staff = [
            [
                'name'          => 'Randy T. Odi',
                'position'      => 'School Principal',
                'image'         => null,   // upload via admin panel
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 0,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Mercy O. De Leon',
                'position'      => 'Kinder Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 1,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Janice T. Odiaman',
                'position'      => 'Grade I Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 2,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Elizabeth T. Villary',
                'position'      => 'Grade II Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 3,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Analisa O. Cepriano',
                'position'      => 'Grade III Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 4,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Cecile C. Alano',
                'position'      => 'Grade IV Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 5,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Elena T. Odi',
                'position'      => 'Grade V Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 6,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Christina O. Tuplano',
                'position'      => 'Grade VI Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 7,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Ginalyn T. Manlangit',
                'position'      => 'Grade VI Adviser',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 8,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Ramil T. Dela Cruz',
                'position'      => 'Subject Teacher',
                'image'         => null,
                'facebook_url'  => null,
                'twitter_url'   => null,
                'linkedin_url'  => null,
                'display_order' => 9,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        DB::table('staff')->insert($staff);

        $this->command->info('Seeded ' . count($staff) . ' staff members successfully.');
        $this->command->info('Tip: Upload photos via the Admin panel → Staff Management.');
    }
}