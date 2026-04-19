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
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
    $table->string('student_number')->unique();
    $table->string('first_name');
    $table->string('middle_name')->nullable();
    $table->string('last_name');
    $table->string('grade_level');
    $table->string('section')->nullable();
    $table->string('gender')->nullable();
    $table->date('date_of_birth')->nullable();
    $table->string('parent_name')->nullable();
    $table->string('contact_number')->nullable();
    $table->text('address')->nullable();
    $table->string('profile_picture')->nullable();
    $table->enum('status',['active','inactive','graduated'])->default('active');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
