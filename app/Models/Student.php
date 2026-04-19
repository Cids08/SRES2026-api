<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'profile_picture',
        'enrollment_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'grade_level',
        'section',
        'gender',
        'date_of_birth',
        'parent_name',     
        'contact_number',   
        'address',    
        'status',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}