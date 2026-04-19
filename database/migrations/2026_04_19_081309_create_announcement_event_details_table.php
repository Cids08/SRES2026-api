<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void
{
    if (!Schema::hasTable('announcement_event_details')) {
        Schema::create('announcement_event_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->string('detail_type');
            $table->string('detail_key')->nullable();
            $table->text('detail_value');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->foreign('announcement_id')
                ->references('id')
                ->on('announcements')
                ->cascadeOnDelete();
        });
    }
}

    public function down(): void
    {
        Schema::dropIfExists('announcement_event_details');
    }
};