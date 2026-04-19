<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained('announcement_categories')->cascadeOnDelete();
    $table->enum('type', ['announcement','news'])->default('announcement');
    $table->string('title');
    $table->text('content');
    $table->text('details')->nullable();
    $table->string('image')->nullable();
    $table->boolean('is_featured')->default(false);
    $table->dateTime('event_date')->nullable();
    $table->string('event_location')->nullable();
    $table->enum('importance',['high','medium','low'])->default('medium');
    $table->dateTime('posted_at');
    $table->dateTime('expires_at')->nullable();
    $table->boolean('is_published')->default(true);
    $table->boolean('show_on_homepage')->default(false);
    $table->integer('view_count')->default(0);
    $table->string('created_by')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
