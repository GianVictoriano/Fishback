<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Featured extends Model
{
    use HasFactory;

    protected $table = 'featured';

    protected $fillable = [
        'article_id',
        'featured_at',
    ];

    protected $casts = [
        'featured_at' => 'datetime',
    ];

    /**
     * Get the article that was featured.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Scope to get featured articles ordered by most recent
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('featured_at', 'desc');
    }

    /**
     * Feature an article
     */
    public static function featureArticle($articleId)
    {
        return self::firstOrCreate([
            'article_id' => $articleId,
        ], [
            'featured_at' => now(),
        ]);
    }

    /**
     * Unfeature an article
     */
    public static function unfeatureArticle($articleId)
    {
        return self::where('article_id', $articleId)->delete();
    }

    /**
     * Check if an article is featured
     */
    public static function isFeatured($articleId)
    {
        return self::where('article_id', $articleId)->exists();
    }
}
