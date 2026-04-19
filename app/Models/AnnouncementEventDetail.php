<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementEventDetail extends Model
{
    protected $table = 'announcement_event_details';

    protected $fillable = [
        'announcement_id',
        'detail_type',    // 'item' | 'highlight' | 'body'
        'detail_key',     // label shown on frontend (e.g. "Date", "Venue")
        'detail_value',
        'display_order',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}