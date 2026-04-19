<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    protected $table = 'news_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color_class',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }
}