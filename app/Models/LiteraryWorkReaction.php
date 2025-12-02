<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiteraryWorkReaction extends Model
{
    use HasFactory;

    protected $table = 'literary_work_reactions';

    protected $fillable = [
        'literary_work_id',
        'user_id',
        'reaction_type',
        'ip_address',
    ];

    /**
     * Get the literary work this reaction belongs to
     */
    public function literaryWork()
    {
        return $this->belongsTo(LiteraryWork::class);
    }

    /**
     * Get the user who made this reaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get reactions by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('reaction_type', $type);
    }

    /**
     * Scope to get reactions for a specific literary work
     */
    public function scopeForWork($query, $literaryWorkId)
    {
        return $query->where('literary_work_id', $literaryWorkId);
    }

    /**
     * Scope to get reactions by a specific user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if user already reacted with this type
     */
    public static function hasUserReacted($literaryWorkId, $userId, $reactionType)
    {
        return self::where('literary_work_id', $literaryWorkId)
                   ->where('user_id', $userId)
                   ->where('reaction_type', $reactionType)
                   ->exists();
    }
}
