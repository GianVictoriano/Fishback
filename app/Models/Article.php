<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    public function metrics()
    {
        return $this->hasOne(ArticleMetric::class);
    }
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'status',
        'published_at',
        'genre',
        'keywords',
        'content_hash',
        'is_featured',
        'featured_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'featured_at' => 'datetime',
    ];

    /**
     * Boot method to auto-extract keywords when article is saved
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($article) {
            // Only update keywords if content has changed
            $newHash = md5($article->content);
            if ($article->content_hash !== $newHash) {
                $keywords = \App\Services\ContentAnalyzer::extractKeywords($article->content, 30);
                $article->keywords = json_encode($keywords);
                $article->content_hash = $newHash;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->hasMany(ArticleMedia::class);
    }

    public function reactions()
    {
        return $this->hasMany(ArticleReaction::class);
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function featured()
    {
        return $this->hasOne(Featured::class);
    }

    /**
     * Get the is_featured attribute from the featured relationship
     */
    public function getIsFeaturedAttribute()
    {
        return $this->featured !== null;
    }
}
