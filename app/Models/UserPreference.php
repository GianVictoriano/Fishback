<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'article_id',
        'genre',
        'interaction_type', // 'view', 'like', 'heart', 'sad', 'wow', 'time_spent'
        'interaction_weight', // numerical weight for ML scoring
        'time_spent', // seconds spent reading
        'scroll_percentage', // how much of article was scrolled
        'session_id',
        'created_at',
    ];

    protected $casts = [
        'interaction_weight' => 'float',
        'time_spent' => 'integer',
        'scroll_percentage' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get user's genre preferences based on interaction history
     */
    public static function getUserGenrePreferences($userId)
    {
        return self::where('user_id', $userId)
            ->selectRaw('genre, SUM(interaction_weight) as total_weight, COUNT(*) as interaction_count')
            ->groupBy('genre')
            ->orderByDesc('total_weight')
            ->get();
    }

    /**
     * Get user's interaction patterns for ML analysis
     */
    public static function getUserInteractionPatterns($userId, $days = 30)
    {
        return self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->with('article')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
