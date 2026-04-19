<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'description', 'ip'];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}