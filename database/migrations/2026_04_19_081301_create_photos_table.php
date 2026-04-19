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
Schema::create('photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('album_id')->constrained()->cascadeOnDelete();
    $table->string('filename');
    $table->string('original_filename')->nullable();
    $table->string('title')->nullable();
    $table->text('caption')->nullable();
    $table->string('alt_text')->nullable();
    $table->integer('display_order')->default(0);
    $table->string('mime_type')->nullable();
    $table->integer('file_size')->nullable();
    $table->boolean('is_featured')->default(false);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
