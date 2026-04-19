<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'school_name',          'value' => 'San Roque Elementary School'],
            ['key' => 'school_tagline',        'value' => 'DepEd · Division of Catanduanes'],
            ['key' => 'school_email',          'value' => '113330@deped.gov.ph'],
            ['key' => 'school_phone',          'value' => '+63 9605519104'],
            ['key' => 'school_address',        'value' => 'San Roque, Viga, Catanduanes, Philippines'],
            ['key' => 'enrollment_open',       'value' => 'true'],
            ['key' => 'enrollment_year',       'value' => '2025–2026'],
            ['key' => 'announcement_ticker',   'value' => ''],
            ['key' => 'maintenance_mode',      'value' => 'false'],
            ['key' => 'maintenance_pages',     'value' => '[]'],
            ['key' => 'school_year',           'value' => '2025–2026'],
            ['key' => 'two_fa_enabled',        'value' => 'false'],
        ];

        foreach ($defaults as $row) {
            DB::table('site_settings')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};