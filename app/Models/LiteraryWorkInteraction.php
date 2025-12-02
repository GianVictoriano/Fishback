<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiteraryWorkInteraction extends Model
{
    use HasFactory;

    protected $table = 'literary_work_interactions';

    protected $fillable = [
        'literary_work_id',
        'user_id',
        'interaction_type',
        'time_spent',
        'scroll_percentage',
        'session_id',
        'ip_address',
    ];

    /**
     * Get the literary work this interaction belongs to
     */
    public function literaryWork()
    {
        return $this->belongsTo(LiteraryWork::class);
    }

    /**
     * Get the user who made this interaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get interactions by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope to get interactions for a specific literary work
     */
    public function scopeForWork($query, $literaryWorkId)
    {
        return $query->where('literary_work_id', $literaryWorkId);
    }

    /**
     * Scope to get interactions by a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get interactions from a specific session
     */
    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Get view interactions
     */
    public function scopeViews($query)
    {
        return $query->where('interaction_type', 'view');
    }

    /**
     * Get time spent interactions
     */
    public function scopeTimeSpent($query)
    {
        return $query->where('interaction_type', 'time_spent');
    }

    /**
     * Get scroll interactions
     */
    public function scopeScrolls($query)
    {
        return $query->where('interaction_type', 'scroll');
    }

    /**
     * Get average scroll percentage for a literary work
     */
    public static function getAverageScrollPercentage($literaryWorkId)
    {
        return self::forWork($literaryWorkId)
                   ->scrolls()
                   ->avg('scroll_percentage');
    }

    /**
     * Get average time spent for a literary work
     */
    public static function getAverageTimeSpent($literaryWorkId)
    {
        return self::forWork($literaryWorkId)
                   ->timeSpent()
                   ->avg('time_spent');
    }

    /**
     * Get total unique users who viewed a literary work
     */
    public static function getUniqueViewerCount($literaryWorkId)
    {
        return self::forWork($literaryWorkId)
                   ->views()
                   ->distinct('user_id')
                   ->count('user_id');
    }
}
