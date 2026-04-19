<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class News extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'content',
        'details',
        'image',
        'is_featured',
        'event_date',
        'event_location',
        'posted_at',
        'expires_at',
        'is_published',
        'view_count',
        'created_by',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'is_published' => 'boolean',
        'event_date'   => 'datetime',
        'posted_at'    => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function eventDetails(): HasMany
    {
        return $this->hasMany(NewsEventDetail::class, 'news_id')
                    ->orderBy('display_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    // Filter by category slug e.g. ?category=event
    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas('category', fn($q) => $q->where('slug', $slug));
    }
}