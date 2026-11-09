<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseArticle extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['author_id', 'title', 'slug', 'category', 'body', 'is_published', 'published_at'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
