<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $table = 'enrollments';

    protected $fillable = [
        'student_type',
        'grade_level',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'student_email',
        'previous_school',
        'special_needs',
        'parent_name',
        'relationship',
        'mobile_number',
        'landline',
        'email',
        'address',
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',
        'has_id_pictures',
        'agreement',
        'status',
        'confirmed_details',
    ];

    protected $casts = [
        'date_of_birth'    => 'date',
        'has_id_pictures'  => 'boolean',
        'agreement'        => 'boolean',
        'confirmed_details'=> 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'enrollment_id');
    }

        public function student()
    {
        return $this->hasOne(Student::class);
    }
}