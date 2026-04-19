<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Album
 *
 * @property int         $id
 * @property string      $slug
 * @property string      $title
 * @property string|null $description
 * @property string|null $cover_image
 * @property int         $photo_count
 * @property bool        $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read int    $photos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Photo> $photos
 */
class Album extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'cover_image',
        'photo_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'album_id')
                    ->orderBy('display_order')
                    ->orderBy('created_at');
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}