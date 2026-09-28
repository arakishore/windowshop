<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    public const TYPE_STANDARD = 'standard';
    public const TYPE_CUSTOM = 'custom';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = ['page_type', 'page_key', 'title', 'slug', 'body', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
