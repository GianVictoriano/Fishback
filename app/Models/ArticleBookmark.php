<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleBookmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'article_id',
        'highlighted_text',
        'start_offset',
        'end_offset',
        'context_before',
        'context_after',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the bookmark
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the article that this bookmark belongs to
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
