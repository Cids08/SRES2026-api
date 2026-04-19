<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Photo
 *
 * @property int         $id
 * @property int         $album_id
 * @property string      $filename
 * @property string|null $original_filename
 * @property string|null $title
 * @property string|null $caption
 * @property string|null $alt_text
 * @property int         $display_order
 * @property string|null $mime_type
 * @property int|null    $file_size
 * @property bool        $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Album $album
 */
class Photo extends Model
{
    protected $fillable = [
        'album_id',
        'filename',
        'original_filename',
        'title',
        'caption',
        'alt_text',
        'display_order',
        'mime_type',
        'file_size',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }
}