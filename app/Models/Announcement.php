<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'category_id',
        'type',           // 'announcement' | 'news'
        'title',
        'content',
        'details',
        'importance',
        // News-specific fields
        'image',
        'is_featured',
        'event_date',
        'event_location',
        // Shared
        'posted_at',
        'expires_at',
        'is_published',
        'show_on_homepage',
        'view_count',
        'created_by',
    ];

    protected $casts = [
        'is_published'     => 'boolean',
        'is_featured'      => 'boolean',
        'show_on_homepage' => 'boolean',
        'posted_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'event_date'       => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function eventDetails(): HasMany
    {
        return $this->hasMany(AnnouncementEventDetail::class, 'announcement_id')
                    ->orderBy('display_order');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas(
            'category',
            fn ($q) => $q->where('slug', $slug)
        );
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOnHomepage($query)
    {
        return $query->where('show_on_homepage', true);
    }
}